#!/usr/bin/env python3
"""Instala consulta manual de cuota sin temporizadores ni modelos."""
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import subprocess

HERE = Path(__file__).resolve().parent
LIB = Path('/usr/local/lib/vigilante')
UNITS = Path('/etc/systemd/system')
CONTROL = Path('/var/lib/vigilante-auth/control')


def main():
    spec = importlib.util.spec_from_file_location('support', HERE/'install-vigilante-isolation.py')
    support = importlib.util.module_from_spec(spec); spec.loader.exec_module(support)
    if os.geteuid() != 0:
        raise PermissionError('Solo administrador')
    support.trusted_directory(HERE)
    support.trusted_directory(CONTROL)
    for name in ('vigilante-auth-login.service', 'vigilante-auth-proxy.service'):
        if subprocess.run(['systemctl', 'is-active', '--quiet', name]).returncode == 0:
            raise ValueError('Autenticacion en curso')
    units = ['vigilante-quota-proxy.service', 'vigilante-quota.service']
    if any(os.path.lexists(UNITS/name) for name in units) or os.path.lexists(LIB/'vigilante-quota.py') or os.path.lexists(CONTROL/'quota-output.jsonl'):
        raise ValueError('Destino existente; requiere revision')
    gateway = LIB/'vigilante-auth-gateway.py'
    if gateway.is_symlink() or not gateway.is_file():
        raise ValueError('Pasarela no valida')
    previous = gateway.read_bytes()
    # La version instalada debe ser exactamente la del tramo anterior.
    if hashlib.sha256(previous).hexdigest() != '1f5954c461db5500797b68e0107f5f028844aa6c6f3f2051377938f561d4fb08':
        raise ValueError('Pasarela modificada desde la ultima verificacion')
    proxy = (UNITS/'vigilante-auth-proxy.service').read_text()
    proxy = proxy.replace('vigilante-auth-net', 'vigilante-quota-net').replace('vigilante-auth-proxy', 'vigilante-quota-proxy')
    proxy = proxy.replace('autenticacion', 'consulta de cuota').replace('RuntimeMaxSec=900', 'RuntimeMaxSec=75')
    proxy = proxy.replace(' --gid 998', ' --gid 998 --host chatgpt.com')
    if '--host chatgpt.com' not in proxy:
        raise ValueError('Identidad de autenticacion no esperada')
    client = (UNITS/'vigilante-auth-login.service').read_text()
    client = '\n'.join(line for line in client.splitlines() if not line.startswith('ConditionPathExists=') and ':/tools/client.py' not in line)+'\n'
    client = client.replace('vigilante-auth-proxy', 'vigilante-quota-proxy').replace('Type=exec', 'Type=oneshot')
    client = client.replace('/tools/client.py login', '/tools/quota.py').replace('RuntimeMaxSec=900\n', '')
    client = client.replace('TimeoutStartSec=30s', 'TimeoutStartSec=60s')
    client = client.replace('control/login-output.txt', 'control/quota-output.jsonl')
    client = client.replace('Description=Inicio de sesion manual de ChatGPT para el vigilante', 'Description=Consulta manual del porcentaje y renovacion de cuota')
    client += 'BindReadOnlyPaths='+str(LIB/'vigilante-quota.py')+':/tools/quota.py\n'
    generated = dict(zip(units, (proxy, client)))
    support.write_new(HERE/'gateway-before.py', previous)
    support.write_new(LIB/'vigilante-quota.py', (HERE/'vigilante-quota.py').read_bytes(), 0o644)
    support.write_new(CONTROL/'quota-output.jsonl', b'')
    created = []
    try:
        support.write_new(LIB/'gateway-next.py', (HERE/'vigilante-auth-gateway.py').read_bytes(), 0o644)
        os.replace(LIB/'gateway-next.py', gateway)
        for name, text in generated.items():
            support.write_new(UNITS/name, text.encode(), 0o644); created.append(name)
            support.write_new(HERE/name, text.encode())
            validation = support.run(['systemd-analyze', 'verify', str(UNITS/name)])
            if any(word in validation.stderr for word in ('Unknown', 'Failed to parse')):
                raise ValueError('Directiva no admitida')
        support.run(['systemctl', 'daemon-reload'])
    except Exception:
        gateway.write_bytes(previous)
        for name in created:
            if (UNITS/name).read_text() == generated[name]:
                (UNITS/name).unlink()
        subprocess.run(['systemctl', 'daemon-reload'], capture_output=True, timeout=15)
        raise
    print(json.dumps({'installed': True, 'enabled': False, 'model_called': False,
                      'units': units, 'gateway_sha256': hashlib.sha256(gateway.read_bytes()).hexdigest()}))


if __name__ == '__main__':
    main()
