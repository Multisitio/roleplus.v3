#!/usr/bin/env python3
"""Una unica herramienta sintetica; no admite rutas, instrucciones ni comandos."""
import errno
import json
import os
from pathlib import Path
import subprocess
import sys


def validate(request):
    if request != {'action': 'probe'}:
        raise ValueError('Operacion no autorizada')


def main():
    request = sys.stdin.buffer.readline(257)
    if len(request) > 256 or not request.endswith(b'\n'):
        raise ValueError('Entrada no valida')
    validate(json.loads(request))
    if os.geteuid() == 0:
        raise PermissionError('Identidad privilegiada')
    result = subprocess.run(['/usr/bin/python3', '-I', '-B', '/probe.py'],
                            capture_output=True, text=True, timeout=15)
    if result.returncode:
        raise RuntimeError('Aislamiento no confirmado')
    report = json.loads(Path('/workspace/isolation-result.json').read_text())
    checks = dict(report['checks'])
    for path in ('/auth/auth.json', '/var/lib/vigilante-auth/profile/auth.json',
                 '/run/proxy/login.sock', '/run/vigilante-quota-proxy/login.sock'):
        try:
            fd = os.open(path, os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK)
        except OSError as error:
            checks[path] = error.errno in (errno.EACCES, errno.EPERM, errno.ENOENT, errno.ENOTDIR)
        else:
            os.close(fd); checks[path] = False
    checks['sin_variables_de_credenciales'] = not any(os.environ.get(k) for k in (
        'OPENAI_API_KEY', 'CODEX_API_KEY', 'CODEX_ACCESS_TOKEN'))
    if not report.get('passed') or not all(checks.values()):
        raise RuntimeError('Barrera no confirmada')
    print(json.dumps({'passed': True, 'checks': len(checks), 'uid': os.geteuid(),
                      'synthetic': True, 'credentials_accessible': False}), flush=True)


if __name__ == '__main__':
    try:
        if sys.argv[1:] == ['--code-host']:
            if os.geteuid() == 0 or os.environ.get('CODEX_HOME') != '/workspace/codex-offline':
                raise PermissionError('Entorno del motor no autorizado')
            if Path('/auth/auth.json').exists() or Path('/workspace/codex-offline/auth.json').exists():
                raise PermissionError('El motor requiere un entorno sin sesion')
            os.execv('/input/tools/code-host', ['/input/tools/code-host', '--listen', 'stdio'])
        elif sys.argv[1:]:
            raise ValueError('Modo no autorizado')
        else:
            main()
    except Exception as error:
        print(json.dumps({'passed': False, 'error_type': type(error).__name__}), flush=True)
        raise SystemExit(1)
