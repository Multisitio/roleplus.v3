#!/usr/bin/env python3
"""Instala login separado, sin iniciar sesiones, modelos ni temporizadores."""
import importlib.util
import json
import os
from pathlib import Path
import pwd
import grp
import subprocess

HERE = Path(__file__).resolve().parent
STATE = Path('/var/lib/vigilante-auth')
ROOT = Path('/srv/vigilante-auth')
NAME = 'vigilante-auth'
CODEX = Path('/opt/vigilante-codex/rust-v0.163.0/codex')
UNIT_DIR = Path('/etc/systemd/system')
LIB = Path('/usr/local/lib/vigilante')


def unit(original, description, root, user, working, command, bindings):
    lines = []
    replacements = {'Description': description, 'User': user, 'Group': user,
                    'RootDirectory': str(root), 'WorkingDirectory': working,
                    'ExecStart': command}
    for line in original.splitlines():
        key = line.split('=', 1)[0]
        if key in {'BindReadOnlyPaths', 'BindPaths', 'ReadWritePaths'}:
            continue
        lines.append(key + '=' + replacements[key] if key in replacements else line)
    return '\n'.join(lines) + '\n' + bindings


def main():
    spec = importlib.util.spec_from_file_location('install', HERE / 'install-vigilante-isolation.py')
    support = importlib.util.module_from_spec(spec); spec.loader.exec_module(support)
    if os.geteuid() != 0:
        raise PermissionError('Solo administrador')
    support.trusted_directory(HERE)
    for path in (STATE, ROOT):
        if os.path.lexists(path):
            raise ValueError('Destino ya existente; requiere revision')
        support.trusted_directory(path.parent)
    for lookup in (pwd.getpwnam, grp.getgrnam):
        try:
            lookup(NAME)
        except KeyError:
            continue
        raise ValueError('La identidad ya existe')
    for name in ['vigilante-auth-gateway.py', 'vigilante-auth-client.py']:
        if os.path.lexists(LIB / name):
            raise ValueError('Programa ya existente')
    units = ['vigilante-auth-proxy.service', 'vigilante-auth-login.service',
             'vigilante-auth-status.service', 'vigilante-auth-check.service']
    if any(os.path.lexists(UNIT_DIR / name) for name in units):
        raise ValueError('Unidad ya existente')
    original = (HERE / 'vigilante-isolation-check.service').read_text()
    if Path('/etc/systemd/system/vigilante-isolation-check.service').read_text() != original:
        raise ValueError('La unidad base difiere de la fuente local')
    if not CODEX.is_file() or CODEX.is_symlink():
        raise ValueError('CLI fijado no disponible')
    support.run(['/usr/sbin/useradd', '--system', '--user-group', '--home-dir', str(STATE / 'profile'),
                 '--shell', '/usr/sbin/nologin', '--no-create-home', NAME])
    account = pwd.getpwnam(NAME)
    STATE.mkdir(mode=0o711)
    (STATE / 'profile').mkdir(mode=0o700); os.chown(STATE / 'profile', account.pw_uid, account.pw_gid)
    (STATE / 'control').mkdir(mode=0o700)
    for name in ('login-output.txt', 'status-output.txt', 'check-output.txt'):
        support.write_new(STATE / 'control' / name, b'')
    ROOT.mkdir(mode=0o755)
    for kind in ('login-root', 'proxy-root'):
        (ROOT / kind).mkdir(mode=0o755)
        for name in ('usr', 'lib', 'lib64', 'dev', 'proc', 'sys', 'tmp', 'etc', 'run', 'root', 'home', 'tools', 'auth'):
            (ROOT / kind / name).mkdir(mode=0o755)
    for name in ('vigilante-auth-gateway.py', 'vigilante-auth-client.py'):
        support.write_new(LIB / name, (HERE / name).read_bytes(), 0o644)
    runtime = 'BindReadOnlyPaths=/usr/bin /usr/lib /usr/share /lib /lib64\n'
    gateway = 'BindReadOnlyPaths=' + str(LIB / 'vigilante-auth-gateway.py') + ':/tools/gateway.py\n'
    client = ('BindReadOnlyPaths=' + str(LIB / 'vigilante-auth-client.py') + ':/tools/client.py\n'
              'BindReadOnlyPaths=' + str(CODEX) + ':/tools/codex\n'
              'BindReadOnlyPaths=/etc/ssl/certs:/etc/ssl/certs\n'
              'BindPaths=' + str(STATE / 'profile') + ':/auth\nReadWritePaths=/auth\n'
              'Environment=CODEX_HOME=/auth SSL_CERT_FILE=/etc/ssl/certs/ca-certificates.crt\n'
              'UnsetEnvironment=OPENAI_API_KEY CODEX_API_KEY HTTPS_PROXY HTTP_PROXY ALL_PROXY NO_PROXY https_proxy http_proxy all_proxy no_proxy\n')
    shared_socket = 'BindReadOnlyPaths=/run/vigilante-auth-proxy:/run/proxy\n'
    proxy = unit(original, 'Pasarela temporal exclusiva de autenticacion', ROOT / 'proxy-root',
                 'vigilante-auth-net', '/run/proxy',
                 '/usr/bin/python3 -I -B /tools/gateway.py --uid ' + str(account.pw_uid) + ' --gid ' + str(account.pw_gid),
                 runtime + gateway + 'BindReadOnlyPaths=/etc/resolv.conf /etc/hosts /etc/nsswitch.conf\n'
                 'BindPaths=/run/vigilante-auth-proxy:/run/proxy\nReadWritePaths=/run/proxy\n')
    proxy = proxy.replace('Group=vigilante-auth-net\n', '').replace('Type=oneshot', 'Type=exec')
    proxy = proxy.replace('PrivateNetwork=yes', 'PrivateNetwork=no').replace('RestrictAddressFamilies=AF_UNIX', 'RestrictAddressFamilies=AF_UNIX AF_INET AF_INET6')
    proxy = proxy.replace('IPAddressDeny=any\n', '').replace('MemoryMax=256M', 'MemoryMax=64M')
    proxy = proxy.replace('CPUQuota=25%', 'CPUQuota=10%')
    proxy = proxy.replace('[Unit]', '[Unit]\nStopWhenUnneeded=yes', 1)
    proxy += ('DynamicUser=yes\nSupplementaryGroups=vigilante-auth\nRuntimeDirectory=vigilante-auth-proxy\n'
              'RuntimeDirectoryMode=0755\nRuntimeMaxSec=900\nRestart=no\n')
    login = unit(original, 'Inicio de sesion manual de ChatGPT para el vigilante', ROOT / 'login-root', NAME,
                 '/auth', '/usr/bin/python3 -I -B /tools/client.py login', runtime + gateway + client + shared_socket)
    login = login.replace('[Unit]', '[Unit]\nRequires=vigilante-auth-proxy.service\nAfter=vigilante-auth-proxy.service', 1)
    login = login.replace('Type=oneshot', 'Type=exec').replace('RestrictAddressFamilies=AF_UNIX', 'RestrictAddressFamilies=AF_UNIX AF_INET')
    login += 'RuntimeMaxSec=900\nRestart=no\nIPAddressAllow=localhost\n'
    login = login.replace('StandardOutput=journal', 'StandardOutput=append:' + str(STATE / 'control/login-output.txt'))
    login = login.replace('StandardError=journal', 'StandardError=append:' + str(STATE / 'control/login-output.txt'))
    login = login.replace('[Unit]', '[Unit]\nConditionPathExists=!' + str(STATE / 'profile/auth.json'), 1)
    status = unit(original, 'Estado privado de la sesion del vigilante', ROOT / 'login-root', NAME,
                  '/auth', '/usr/bin/python3 -I -B /tools/client.py status', runtime + gateway + client)
    status = status.replace('StandardOutput=journal', 'StandardOutput=append:' + str(STATE / 'control/status-output.txt'))
    status = status.replace('StandardError=journal', 'StandardError=append:' + str(STATE / 'control/status-output.txt'))
    check = login.replace('/tools/client.py login', '/tools/client.py check').replace('Type=exec', 'Type=oneshot')
    check = check.replace('RuntimeMaxSec=900\n', '').replace('/control/login-output.txt', '/control/check-output.txt')
    generated = dict(zip(units, [proxy, login, status, check]))
    created = []
    try:
        for name, text in generated.items():
            support.write_new(UNIT_DIR / name, text.encode(), 0o644); created.append(name)
            support.write_new(HERE / name, text.encode())
        for name in units:
            validation = support.run(['systemd-analyze', 'verify', str(UNIT_DIR / name)])
            if any(word in validation.stderr for word in ('Unknown', 'Failed to parse')):
                raise ValueError('Directiva no admitida')
        support.run(['systemctl', 'daemon-reload'])
        print(json.dumps({'status': 'instalado_sin_iniciar', 'uid': account.pw_uid, 'gid': account.pw_gid,
                          'units': units, 'login_started': False, 'ai_called': False}))
    except Exception:
        for name in created:
            path = UNIT_DIR / name
            if not path.is_symlink() and path.read_text() == generated[name]:
                path.unlink()
        subprocess.run(['systemctl', 'daemon-reload'], capture_output=True, timeout=15)
        raise


if __name__ == '__main__':
    main()
