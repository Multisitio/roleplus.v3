#!/usr/bin/env python3
"""Comprobar la revision comun antes de preparar un despliegue; no mezcla ni descarta."""
import argparse
import json
import os
from pathlib import Path
import re
import subprocess


def git(root, *args):
    p = subprocess.run(['git', '-C', str(root), *args], capture_output=True, timeout=60)
    if p.returncode:
        raise ValueError('No se pudo contrastar Git; se bloquea la operacion')
    return p.stdout.decode('utf-8').strip()


def verify(root, revision):
    root = Path(root).resolve()
    if not re.fullmatch(r'[0-9a-f]{40}', revision):
        raise ValueError('Revision completa requerida')
    actual = Path(git(root, 'rev-parse', '--show-toplevel')).resolve()
    if os.path.normcase(str(actual)) != os.path.normcase(str(root)):
        raise ValueError('Se requiere el repositorio propio')
    if git(root, 'status', '--porcelain', '--untracked-files=no'):
        raise ValueError('Cambios locales pendientes; no se prepara ni despliega')
    if git(root, 'rev-parse', 'HEAD') != revision:
        raise ValueError('La copia local ya no coincide con la revision revisada')
    remote = git(root, 'ls-remote', '--exit-code', 'origin', 'refs/heads/main').split()
    if len(remote) != 2 or remote != [revision, 'refs/heads/main']:
        raise ValueError('GitHub y la copia local difieren; resolver antes de desplegar')
    # Volver a comprobar tras la consulta de red: nunca dar un visto bueno con datos viejos.
    if git(root, 'status', '--porcelain', '--untracked-files=no') or git(root, 'rev-parse', 'HEAD') != revision:
        raise ValueError('La copia local cambio durante la comprobacion')
    return {'version': 1, 'local_head': revision, 'remote_head': revision, 'synchronized': True,
            'deployment_enabled': False, 'must_recheck_before_write': True}


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--repository', required=True)
    parser.add_argument('--commit', required=True)
    args = parser.parse_args()
    try:
        print(json.dumps(verify(args.repository, args.commit)))
    except (ValueError, OSError, subprocess.TimeoutExpired) as error:
        parser.exit(1, str(error) + '\n')
