#!/usr/bin/env python3
"""Consulta fija de cuota. Sin instrucciones, turnos, herramientas ni API keys."""
import importlib.util
import json
import math
import os
from pathlib import Path
import re
import selectors
import socket
import socketserver
import stat
import subprocess
import threading
import time

MAX_AGE = 300
RESERVE_PERCENT = 90


def number(value):
    return type(value) in (int, float) and math.isfinite(value)


def normalize(result, now):
    """Solo datos numericos y nombres acotados; no identidad ni credenciales."""
    if not isinstance(result, dict):
        raise ValueError('Respuesta no valida')
    buckets = result.get('rateLimitsByLimitId')
    if buckets is None:
        legacy = result.get('rateLimits')
        buckets = {legacy.get('limitId') or 'codex': legacy} if isinstance(legacy, dict) else {}
    if not isinstance(buckets, dict) or len(buckets) > 50:
        raise ValueError('Limites no validos')
    clean = {}
    for key, bucket in buckets.items():
        if not isinstance(key, str) or not re.fullmatch(r'[A-Za-z0-9_.-]{1,80}', key) or not isinstance(bucket, dict):
            raise ValueError('Identificador no valido')
        windows = {}
        for name in ('primary', 'secondary'):
            window = bucket.get(name)
            if window is None:
                windows[name] = None
                continue
            if not isinstance(window, dict):
                raise ValueError('Ventana no valida')
            used, duration, reset = (window.get(x) for x in ('usedPercent', 'windowDurationMins', 'resetsAt'))
            if not number(used) or not 0 <= used <= 100000:
                raise ValueError('Porcentaje no valido')
            if type(duration) is not int or duration <= 0 or type(reset) is not int or reset <= 0:
                raise ValueError('Renovacion no valida')
            windows[name] = {'used_percent': used, 'remaining_percent': max(0, 100-used),
                             'window_minutes': duration, 'resets_at': reset}
        windows['provider_limit_reached'] = bucket.get('rateLimitReachedType') is not None
        clean[key] = windows
    return {'checked_at': now, 'buckets': clean, 'model_called': False}


def admission(snapshot, now):
    """No deducir una renovacion ni habilitar trabajo con datos antiguos."""
    checked = snapshot.get('checked_at')
    if not number(checked) or not 0 <= now-checked <= MAX_AGE:
        return {'state': 'datos_caducados', 'routine_allowed': False}
    bucket = snapshot.get('buckets', {}).get('codex')
    windows = [bucket.get(k) for k in ('primary', 'secondary') if bucket.get(k)] if bucket else []
    if not windows:
        return {'state': 'cuota_desconocida', 'routine_allowed': False}
    if any(w['resets_at'] <= now for w in windows):
        return {'state': 'requiere_nueva_consulta', 'routine_allowed': False}
    state = 'disponible'
    if bucket.get('provider_limit_reached') or any(w['used_percent'] >= 100 for w in windows):
        state = 'agotada'
    elif any(w['used_percent'] >= RESERVE_PERCENT for w in windows):
        state = 'reserva_del_usuario'
    return {'state': state, 'routine_allowed': state == 'disponible',
            'next_reset_check_at': min(w['resets_at'] for w in windows)+5,
            'reserve_threshold_percent': RESERVE_PERCENT}


def query(process):
    """Protocolo cerrado: inicializar, consultar cuota y terminar."""
    selector = selectors.DefaultSelector()
    selector.register(process.stdout, selectors.EVENT_READ)
    buffer = b''
    total = 0
    deadline = time.monotonic()+40

    def send(message):
        process.stdin.write((json.dumps(message)+'\n').encode())
        process.stdin.flush()

    def receive(expected):
        nonlocal buffer, total
        while time.monotonic() < deadline:
            while b'\n' in buffer:
                line, buffer = buffer.split(b'\n', 1)
                if not line:
                    continue
                message = json.loads(line)
                if not isinstance(message, dict):
                    raise ValueError('Mensaje no valido')
                if 'method' in message and 'id' in message:
                    raise PermissionError('Operacion del servidor no autorizada')
                if type(message.get('id')) is int and message['id'] == expected:
                    if 'error' in message or 'result' not in message:
                        raise RuntimeError('Consulta rechazada')
                    return message['result']
            ready = selector.select(max(0, min(1, deadline-time.monotonic())))
            if not ready:
                continue
            chunk = os.read(process.stdout.fileno(), 65536)
            if not chunk:
                raise RuntimeError('Cliente cerrado antes de responder')
            total += len(chunk)
            buffer += chunk
            if total > 2*1024*1024 or len(buffer) > 1024*1024:
                raise ValueError('Respuesta excesiva')
        raise TimeoutError('Tiempo de consulta agotado')

    try:
        send({'id': 1, 'method': 'initialize', 'params': {
            'clientInfo': {'name': 'vigilante_cuota', 'version': '1.0.0'}}})
        receive(1)
        send({'method': 'initialized', 'params': {}})
        send({'id': 2, 'method': 'account/rateLimits/read'})
        return receive(2)
    finally:
        selector.close()


def host_snapshot():
    """Consulta desde el coordinador; solo la unidad autenticada toca el perfil."""
    if os.geteuid()!=0:raise PermissionError('Solo administrador')
    path=Path('/var/lib/vigilante-auth/control/quota-output.jsonl')
    for target,directory in ((path.parent,True),(path,False)):
        meta=target.lstat()
        if meta.st_uid!=0 or meta.st_mode&0o022 or not (stat.S_ISDIR if directory else stat.S_ISREG)(meta.st_mode):
            raise PermissionError('Salida de cuota no confiable')
    before=path.stat().st_size;started=int(time.time())
    subprocess.run(['/usr/bin/systemctl','start','vigilante-quota.service'],capture_output=True,timeout=70)
    if not 0<=path.stat().st_size-before<=131072:return None
    with path.open('rb') as stream:stream.seek(before);lines=stream.read(131073).splitlines()
    for line in reversed(lines):
        try:value=json.loads(line)
        except (ValueError,UnicodeDecodeError):continue
        if isinstance(value,dict) and type(value.get('checked_at')) is int and started<=value['checked_at']<=int(time.time()) and value.get('model_called') is False:
            return value
    return None


def main():
    profile = Path('/auth')
    meta = (profile/'auth.json').lstat()
    if os.geteuid() == 0 or not stat.S_ISREG(meta.st_mode) or meta.st_uid != os.geteuid() or meta.st_mode & 0o077:
        raise PermissionError('Sesion no privada')
    spec = importlib.util.spec_from_file_location('gateway', '/tools/gateway.py')
    gateway = importlib.util.module_from_spec(spec); spec.loader.exec_module(gateway)

    class Bridge(socketserver.ThreadingMixIn, socketserver.TCPServer):
        daemon_threads = True

    class Handler(socketserver.BaseRequestHandler):
        def handle(self):
            with socket.socket(socket.AF_UNIX) as upstream:
                upstream.connect('/run/proxy/login.sock')
                gateway.relay(self.request, upstream, seconds=60)

    with Bridge(('127.0.0.1', 0), Handler) as bridge:
        thread = threading.Thread(target=bridge.serve_forever, daemon=True); thread.start()
        address = 'http://127.0.0.1:'+str(bridge.server_address[1])
        environment = dict(os.environ, HTTPS_PROXY=address, HTTP_PROXY=address, ALL_PROXY=address, NO_PROXY='')
        command = ['/tools/codex', '-c', 'forced_login_method="chatgpt"',
                   '-c', 'cli_auth_credentials_store="file"', '-c', 'analytics.enabled=false',
                   'app-server', '--listen', 'stdio://']
        process = None
        try:
            process = subprocess.Popen(command, stdin=subprocess.PIPE, stdout=subprocess.PIPE,
                                       stderr=subprocess.DEVNULL, env=environment, cwd='/tmp', start_new_session=True)
            snapshot = normalize(query(process), int(time.time()))
            snapshot['admission'] = admission(snapshot, int(time.time()))
            snapshot['automatic_repairs_enabled'] = False
            print(json.dumps(snapshot), flush=True)
        finally:
            if process is not None:
                import signal
                try:
                    os.killpg(process.pid, signal.SIGKILL)
                except ProcessLookupError:
                    pass
                process.wait(timeout=5)
            bridge.shutdown()


if __name__ == '__main__':
    try:
        main()
    except Exception as error:
        print(json.dumps({'available': False, 'error_type': type(error).__name__,
                          'routine_allowed': False, 'model_called': False}), flush=True)
        raise SystemExit(1)
