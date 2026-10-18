#!/usr/bin/env python3
"""Un turno acotado, sin entorno local y con una herramienta externa fija."""
import importlib.util
import json
import os
from pathlib import Path
import signal
import socket
import socketserver
import stat
import subprocess
import threading
import time


def load(name, file):
    spec = importlib.util.spec_from_file_location(name, file)
    module = importlib.util.module_from_spec(spec); spec.loader.exec_module(module)
    return module


def tool_reply(params, thread_id, used):
    if used or params.get('threadId') != thread_id or params.get('tool') != 'comprobar_aislamiento' or params.get('arguments') != {}:
        raise PermissionError('Herramienta o argumentos no autorizados')
    with socket.socket(socket.AF_UNIX) as connection:
        connection.settimeout(25)
        connection.connect('/run/tool-probe.sock')
        connection.sendall(b'{"action":"probe"}\n')
        data = b''
        while b'\n' not in data and len(data) <= 8192:
            chunk = connection.recv(1024)
            if not chunk:
                break
            data += chunk
        if len(data) > 8192:
            raise ValueError('Salida de herramienta excesiva')
        report = json.loads(data)
    if report.get('passed') is not True or report.get('synthetic') is not True or report.get('credentials_accessible') is not False:
        raise RuntimeError('Herramienta no verificada')
    if type(report.get('uid')) is not int or report['uid'] <= 0 or report['uid'] == os.geteuid() or report.get('checks', 0) < 32:
        raise RuntimeError('Identidad o barreras no verificadas')
    # Solo se devuelve al modelo un resultado acotado, sin contenido de archivos.
    return {key: report[key] for key in ('passed', 'checks', 'uid', 'synthetic', 'credentials_accessible')}


def run_check(rpc, quota, task=None):
    rpc.request('initialize', 1, {'clientInfo': {'name': 'vigilante_prueba', 'version': '1.0.0'},
                                  'capabilities': {'experimentalApi': True}})
    rpc.send({'method': 'initialized', 'params': {}})
    snapshot = quota.normalize(rpc.request('account/rateLimits/read', 2), int(time.time()))
    gate = quota.admission(snapshot, int(time.time()))
    if not gate.get('routine_allowed'):
        return {'completed': False, 'model_called': False, 'admission': gate}
    catalog = rpc.request('model/list', 3, {'limit': 100})
    defaults = [m for m in catalog.get('data', []) if m.get('isDefault') is True]
    if len(defaults) != 1 or not isinstance(defaults[0].get('model'), str):
        raise RuntimeError('Modelo predeterminado no confirmado')
    model = defaults[0]['model']
    levels = [r.get('reasoningEffort') for r in defaults[0].get('supportedReasoningEfforts', [])]
    effort = 'low' if 'low' in levels else defaults[0].get('defaultReasoningEffort')
    if not isinstance(effort, str):
        raise RuntimeError('Esfuerzo del modelo no confirmado')
    thread = rpc.request('thread/start', 4, {
        'model': model, 'allowProviderModelFallback': False, 'ephemeral': True,
        'cwd': '/tmp', 'environments': [], 'permissions': ':read-only', 'approvalPolicy': 'never',
        'developerInstructions': task.INSTRUCTIONS if task else 'Prueba sintetica. Es obligatorio llamar una vez a comprobar_aislamiento y basar la respuesta en su resultado. Solo esta autorizada esa herramienta. No inventes valores ni solicites accesos.',
        'dynamicTools': [{'type': 'function', 'name': task.TOOL if task else 'comprobar_aislamiento', 'deferLoading': False,
                          'description': getattr(task, 'DESCRIPTION', 'Lee una instantanea TLS minimizada.') if task else 'Comprueba barreras de aislamiento con datos sinteticos. No recibe argumentos.',
                          'inputSchema': {'type': 'object', 'properties': {}, 'additionalProperties': False}}]})
    thread_id = thread['thread']['id']
    turn_input = {'threadId': thread_id, 'effort': effort,
        'input': [{'type': 'text', 'text': task.PROMPT if task else 'Comprueba el aislamiento con comprobar_aislamiento({}). Indica el UID real y el numero de comprobaciones que devuelve. No conoces esos datos sin llamar a la herramienta.'}]}
    if task:
        turn_input['outputSchema'] = task.SCHEMA
    rpc.request('turn/start', 5, turn_input)
    used = False
    report = None
    answer = ''
    while True:
        message = rpc.read()
        if 'id' in message and 'method' in message:
            if message['method'] != 'item/tool/call':
                raise PermissionError('Solicitud no autorizada')
            report = (task.tool_reply if task else tool_reply)(message.get('params', {}), thread_id, used)
            used = True
            rpc.send({'id': message['id'], 'result': {'success': True,
                'contentItems': [{'type': 'inputText', 'text': json.dumps(report)}]}})
        elif message.get('method') == 'item/completed':
            item = message.get('params', {}).get('item', {})
            if item.get('type') == 'agentMessage' and isinstance(item.get('text'), str):
                if len(item['text']) > (16384 if task else 1000) and task:
                    raise ValueError('Respuesta excesiva')
                answer = item['text'][:16384 if task else 1000]
        elif message.get('method') == 'turn/completed':
            params = message.get('params', {})
            if params.get('threadId') != thread_id or params.get('turn', {}).get('status') != 'completed' or not used:
                turn = params.get('turn', {})
                info = (turn.get('error') or {}).get('codexErrorInfo')
                return {'completed': False, 'model_called': True, 'model': model, 'tool_calls': int(used),
                        'turn_status': turn.get('status'),
                        'error_kind': info if isinstance(info, str) else list(info) if isinstance(info, dict) else None,
                        'synthetic_reply': answer,
                        'automatic_repairs_enabled': False}
            diagnosis = {'diagnosis': task.finish(answer, report)} if task else {}
            return dict(diagnosis, **{'completed': True, 'model_called': True, 'model': model, 'effort': effort,
                    'tool_calls': 1, 'tool': report, 'quota_before': snapshot,
                    'automatic_repairs_enabled': False, 'local_environment_enabled': False})


def main(task=None):
    import fcntl
    descriptor = os.open('/auth/investigation.lock', os.O_RDWR|os.O_CREAT|os.O_NOFOLLOW, 0o600)
    with os.fdopen(descriptor, 'a+') as lock:
        metadata = os.fstat(lock.fileno())
        if metadata.st_uid != os.geteuid() or metadata.st_mode & 0o077 or not stat.S_ISREG(metadata.st_mode):
            raise PermissionError('Bloqueo no privado')
        fcntl.flock(lock, fcntl.LOCK_EX|fcntl.LOCK_NB)
        return authenticated_main(task)


def authenticated_main(task=None):
    metadata = Path('/auth/auth.json').lstat()
    if os.geteuid() == 0 or not stat.S_ISREG(metadata.st_mode) or metadata.st_uid != os.geteuid() or metadata.st_mode & 0o077:
        raise PermissionError('Perfil no privado')
    gateway = load('gateway', '/tools/gateway.py')
    quota = load('quota', '/tools/quota.py')
    rpc_module = load('rpc', '/tools/rpc.py')

    class Bridge(socketserver.ThreadingMixIn, socketserver.TCPServer):
        daemon_threads = True

    class Handler(socketserver.BaseRequestHandler):
        def handle(self):
            with socket.socket(socket.AF_UNIX) as upstream:
                upstream.connect('/run/proxy/login.sock')
                gateway.relay(self.request, upstream, seconds=100)

    with Bridge(('127.0.0.1', 0), Handler) as bridge:
        threading.Thread(target=bridge.serve_forever, daemon=True).start()
        address = 'http://127.0.0.1:'+str(bridge.server_address[1])
        environment = dict(os.environ, HTTPS_PROXY=address, HTTP_PROXY=address, ALL_PROXY=address, NO_PROXY='',
                           TOKIO_WORKER_THREADS='2', RAYON_NUM_THREADS='2')
        command = ['/tools/codex', '-c', 'forced_login_method="chatgpt"', '-c', 'cli_auth_credentials_store="file"',
                   '-c', 'analytics.enabled=false', '-c', 'web_search="disabled"']
        for feature in ('shell_tool', 'stable_environment_tools', 'code_mode', 'code_mode_host_grpc', 'apps', 'plugins',
                        'hooks', 'multi_agent', 'multi_agent_v2', 'browser_use', 'computer_use', 'memories', 'shell_snapshot'):
            command += ['--disable', feature]
        command += ['app-server', '--listen', 'stdio://']
        process = subprocess.Popen(command, stdin=subprocess.PIPE, stdout=subprocess.PIPE,
                                   stderr=subprocess.DEVNULL, cwd='/tmp', env=environment, start_new_session=True)
        rpc = rpc_module.Rpc(process, seconds=100)
        try:
            result = run_check(rpc, quota, task)
            print(json.dumps(result), flush=True)
            return 0 if result.get('completed') else 1
        except Exception as error:
            known = {'Modelo predeterminado no confirmado', 'Esfuerzo del modelo no confirmado',
                     'Turno no confirmado', 'Herramienta no verificada', 'Consulta rechazada',
                     'Identidad o barreras no verificadas', 'Cliente cerrado antes de responder'}
            print(json.dumps({'completed': False, 'error_type': type(error).__name__,
                              'failed_method': rpc.current_method, 'error_code': getattr(error, 'code', None),
                              'reason': str(error) if str(error) in known else None,
                              'automatic_repairs_enabled': False}), flush=True)
            return 1
        finally:
            rpc.close()
            try:
                os.killpg(process.pid, signal.SIGKILL)
            except ProcessLookupError:
                pass
            process.wait(timeout=5)
            bridge.shutdown()


if __name__ == '__main__':
    try:
        raise SystemExit(main())
    except Exception as error:
        print(json.dumps({'completed': False, 'error_type': type(error).__name__,
                          'automatic_repairs_enabled': False}), flush=True)
        raise SystemExit(1)
