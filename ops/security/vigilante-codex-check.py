#!/usr/bin/env python3
"""Verifica el CLI sin red, credenciales ni llamadas a modelos."""
import json
import os
from pathlib import Path
import subprocess
import tempfile


def main():
    profile = Path(os.environ['CODEX_HOME'])
    if profile != Path('/workspace/codex-offline'):
        raise RuntimeError('Perfil de ensayo distinto del permitido')
    profile.mkdir(mode=0o700, exist_ok=True)
    if profile.is_symlink() or profile.stat().st_uid != os.geteuid() or profile.stat().st_mode & 0o077:
        raise RuntimeError('Perfil de ensayo no fiable')
    if (profile / 'auth.json').exists():
        raise RuntimeError('Este ensayo requiere un perfil sin credenciales')
    isolation = subprocess.run(['/usr/bin/python3', '-I', '-B', '/probe.py'],
                               capture_output=True, text=True, timeout=15)
    if isolation.returncode:
        raise RuntimeError('Las barreras de aislamiento no estan verificadas')
    commands = [(['--version'], 0), (['--help'], 0), (['login', '--help'], 0),
                (['login', 'status'], 1), (['exec', '--help'], 0), (['sandbox', '--help'], 0)]
    checks = []
    for args, expected in commands:
        result = subprocess.run(['/input/tools/codex', '-c', 'forced_login_method="chatgpt"', *args],
                                capture_output=True, text=True, timeout=10)
        if result.returncode != expected:
            raise RuntimeError('Comando de comprobacion fallido: ' + ' '.join(args))
        checks.append({'args': args, 'exit_code': result.returncode,
                       'stdout': result.stdout[:16000], 'stderr': result.stderr[:4000]})
    status = checks[3]
    if 'not logged in' not in (status['stdout'] + status['stderr']).lower():
        Path('/workspace/codex-check-debug.json').write_text(json.dumps(checks, indent=2))
        raise RuntimeError('No se ha confirmado el estado sin autenticar; diagnostico privado conservado')
    if (Path(os.environ['CODEX_HOME']) / 'auth.json').exists():
        raise RuntimeError('Este ensayo requiere un perfil sin credenciales')
    report = {'version': 1, 'passed': True, 'uid': os.geteuid(),
              'binary_version': checks[0]['stdout'].strip(), 'commands': checks,
              'authenticated': False, 'ai_called': False, 'network_enabled': False}
    with tempfile.NamedTemporaryFile(dir='/workspace', mode='w', delete=False) as stream:
        json.dump(report, stream, ensure_ascii=False, indent=2)
        stream.write('\n')
        stream.flush()
        os.fsync(stream.fileno())
        temporary = Path(stream.name)
    temporary.replace('/workspace/codex-check-result.json')
    print(json.dumps({'passed': True, 'commands': len(checks),
                      'version': report['binary_version'], 'ai_called': False}))


if __name__ == '__main__':
    main()
