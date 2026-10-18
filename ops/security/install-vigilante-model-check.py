#!/usr/bin/env python3
"""Instala un ensayo manual: cliente sin entorno y herramienta con otra identidad."""
import importlib.util
import hashlib
import json
import os
from pathlib import Path
import subprocess

HERE = Path(__file__).resolve().parent
LIB = Path('/usr/local/lib/vigilante')
UNITS = Path('/etc/systemd/system')
CONTROL = Path('/var/lib/vigilante-auth/control')


def generate():
    code_host = Path('/opt/vigilante-codex/rust-v0.163.0/codex-code-mode-host')
    if not code_host.is_file() or code_host.is_symlink():
        raise ValueError('Instalar primero el auxiliar fijado del CLI')
    if (code_host.stat().st_uid != 0 or code_host.stat().st_mode & 0o022 or
            hashlib.sha256(code_host.read_bytes()).hexdigest() != '8724d73ec190536233ab4628d24a071bfbcf27e5124f08fe35129f26fe48b916'):
        raise ValueError('Auxiliar distinto del artefacto fijado')
    proxy = (UNITS/'vigilante-quota-proxy.service').read_text()
    proxy = proxy.replace('vigilante-quota-net', 'vigilante-model-net').replace('vigilante-quota-proxy', 'vigilante-model-proxy')
    proxy = proxy.replace('RuntimeMaxSec=75', 'RuntimeMaxSec=120')
    proxy = proxy.replace('Description=Pasarela temporal exclusiva de consulta de cuota',
                          'Description=Pasarela temporal de la prueba de IA')
    client = (UNITS/'vigilante-quota.service').read_text()
    client = client.replace('vigilante-quota-proxy', 'vigilante-model-proxy').replace('TimeoutStartSec=60s', 'TimeoutStartSec=110s')
    client = client.replace('ExecStart=/usr/bin/python3 -I -B /tools/quota.py', 'ExecStart=/usr/bin/python3 -I -B /tools/model-check.py')
    client = client.replace('quota-output.jsonl', 'model-check.jsonl')
    client = client.replace('[Unit]', '[Unit]\nRequires=vigilante-tool-probe.socket vigilante-code-host.socket\nAfter=vigilante-tool-probe.socket vigilante-code-host.socket', 1)
    client = client.replace('Description=Consulta manual del porcentaje y renovacion de cuota',
                            'Description=Prueba manual de IA con herramientas externas')
    client = client.replace('CPUQuota=25%', 'CPUQuota=10%')
    client += ('BindReadOnlyPaths='+str(LIB/'vigilante-model-check.py')+':/tools/model-check.py\n'
               'BindReadOnlyPaths='+str(LIB/'vigilante-rpc.py')+':/tools/rpc.py\n'
               'BindReadOnlyPaths='+str(LIB/'vigilante-code-host-bridge.py')+':/tools/codex-code-mode-host\n'
               'BindReadOnlyPaths=/run/vigilante-code-host.sock:/run/code-host.sock\n'
               'BindReadOnlyPaths=/run/vigilante-tool-probe.sock:/run/tool-probe.sock\n')
    worker = (UNITS/'vigilante-isolation-check.service').read_text()
    worker = worker.replace('ExecStart=/usr/bin/python3 -I -B /probe.py',
                            'ExecStart=/usr/bin/python3 -I -B /input/tools/tool-probe.py')
    worker = worker.replace('StandardOutput=journal', 'StandardInput=socket\nStandardOutput=inherit').replace('StandardError=journal', 'StandardError=null')
    worker = worker.replace('Type=oneshot', 'Type=exec')
    worker += ('BindReadOnlyPaths='+str(LIB/'vigilante-tool-probe.py')+':/input/tools/tool-probe.py\n'
               'Environment=CODEX_HOME=/workspace/codex-offline\n'
               'UnsetEnvironment=OPENAI_API_KEY CODEX_API_KEY CODEX_ACCESS_TOKEN HTTPS_PROXY HTTP_PROXY ALL_PROXY NO_PROXY\n'
               'RuntimeMaxSec=25\nRestart=no\n')
    worker = worker.replace('Description=Comprobacion manual del aislamiento del vigilante',
                            'Description=Herramienta sintetica aislada del vigilante')
    worker = worker.replace('CPUQuota=25%', 'CPUQuota=5%')
    socket_unit = ('[Unit]\nDescription=Herramienta sintetica privada del vigilante\nStopWhenUnneeded=yes\n'
                   '[Socket]\nListenStream=/run/vigilante-tool-probe.sock\nAccept=yes\nSocketUser=root\n'
                   'SocketGroup=vigilante-auth\nSocketMode=0660\nRemoveOnStop=yes\nMaxConnections=1\n')
    code_socket = socket_unit.replace('vigilante-tool-probe.sock', 'vigilante-code-host.sock')
    code_socket = code_socket.replace('Herramienta sintetica privada', 'Motor de codigo privado')
    code_worker = (UNITS/'vigilante-isolation-check.service').read_text()
    code_worker = code_worker.replace('ExecStart=/usr/bin/python3 -I -B /probe.py',
                                      'ExecStart=/usr/bin/python3 -I -B /input/tools/tool-probe.py --code-host')
    code_worker = code_worker.replace('Description=Comprobacion manual del aislamiento del vigilante',
                                      'Description=Motor de codigo sin credenciales ni red')
    code_worker = code_worker.replace('StandardOutput=journal', 'StandardInput=socket\nStandardOutput=inherit')
    code_worker = code_worker.replace('StandardError=journal', 'StandardError=null')
    # El motor JavaScript necesita memoria ejecutable. La excepcion solo afecta
    # a esta identidad/raiz sin credenciales, no al cliente autenticado.
    code_worker = code_worker.replace('MemoryDenyWriteExecute=yes', 'MemoryDenyWriteExecute=no')
    code_worker = code_worker.replace('Type=oneshot', 'Type=exec').replace('CPUQuota=25%', 'CPUQuota=10%')
    code_worker += ('BindReadOnlyPaths='+str(code_host)+':/input/tools/code-host\n'
                    'BindReadOnlyPaths='+str(LIB/'vigilante-tool-probe.py')+':/input/tools/tool-probe.py\n'
                    'Environment=CODEX_HOME=/workspace/codex-offline TOKIO_WORKER_THREADS=2 RAYON_NUM_THREADS=2\n'
                    'UnsetEnvironment=OPENAI_API_KEY CODEX_API_KEY CODEX_ACCESS_TOKEN HTTPS_PROXY HTTP_PROXY ALL_PROXY NO_PROXY\n'
                    'RuntimeMaxSec=100\nRestart=no\n')
    return {'vigilante-model-check.service': client, 'vigilante-model-proxy.service': proxy,
            'vigilante-tool-probe.socket': socket_unit, 'vigilante-tool-probe@.service': worker,
            'vigilante-code-host.socket': code_socket, 'vigilante-code-host@.service': code_worker}


def main():
    spec = importlib.util.spec_from_file_location('support', HERE/'install-vigilante-isolation.py')
    support = importlib.util.module_from_spec(spec); spec.loader.exec_module(support)
    if os.geteuid() != 0:
        raise PermissionError('Solo administrador')
    support.trusted_directory(HERE); support.trusted_directory(CONTROL)
    programs = ['vigilante-rpc.py', 'vigilante-model-check.py', 'vigilante-tool-probe.py', 'vigilante-code-host-bridge.py']
    generated = generate(); names = list(generated)
    if any(os.path.lexists(LIB/p) for p in programs) or any(os.path.lexists(UNITS/n) for n in names):
        raise ValueError('Destino existente; revisar antes de reemplazar')
    if os.path.lexists(CONTROL/'model-check.jsonl'):
        raise ValueError('Salida existente')
    for name in programs:
        support.write_new(LIB/name, (HERE/name).read_bytes(), 0o755 if name.endswith('bridge.py') else 0o644)
    support.write_new(CONTROL/'model-check.jsonl', b'')
    created = []
    try:
        for name, text in generated.items():
            support.write_new(UNITS/name, text.encode(), 0o644); created.append(name)
            support.write_new(HERE/name, text.encode())
        for name in names:
            check = support.run(['systemd-analyze', 'verify', str(UNITS/name)])
            if any(word in check.stderr for word in ('Unknown', 'Failed to parse')):
                raise ValueError('Directiva no admitida')
        support.run(['systemctl', 'daemon-reload'])
    except Exception:
        for name in created:
            if (UNITS/name).read_text() == generated[name]:
                (UNITS/name).unlink()
        subprocess.run(['systemctl', 'daemon-reload'], capture_output=True, timeout=15)
        raise
    print(json.dumps({'installed': True, 'started': False, 'automatic_repairs_enabled': False, 'units': names}))


if __name__ == '__main__':
    main()
