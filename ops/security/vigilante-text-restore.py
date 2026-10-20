#!/usr/bin/env python3
"""Restaura los textos seleccionados en un espacio temporal sin acceso a las webs."""
import hashlib
import importlib.util
import json
import os
from pathlib import Path, PurePosixPath
import re
import stat
import tarfile
import time

BASE = Path('/run/vigilante-text-restore')
STATE = Path('/var/lib/vigilante-text-restore')


def digest(path):
    h = hashlib.sha256()
    with path.open('rb') as stream:
        while chunk := stream.read(1024 * 1024):
            h.update(chunk)
    return h.hexdigest()


def main():
    if os.geteuid() == 0:
        raise RuntimeError('Ensayo reservado a usuario sin privilegios')
    for path in ['/var/www/clients', '/var/lib/mysql/ibdata1', '/run/mysqld/mysqld.sock',
                 '/var/lib/vigilante-auth/auth.json']:
        if os.access(path, os.R_OK):
            raise RuntimeError('Aislamiento insuficiente')
    spec = importlib.util.spec_from_file_location('textbackup',
        '/usr/local/lib/vigilante/vigilante-text-backups.py')
    backup = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(backup)
    inputs = BASE / 'inputs'
    plan = json.loads((inputs / 'plan.json').read_text())
    if not 1 <= len(plan) <= 13 or len({i['id'] for i in plan}) != len(plan):
        raise ValueError('Seleccion invalida')
    report = {'completed': False, 'started_at': int(time.time()), 'uid': os.geteuid(),
              'network_enabled': False, 'live_paths_readable': False, 'roots': [],
              'ownership_applied': False, 'ownership_metadata_verified': True}
    destination = BASE / 'restored'
    destination.mkdir(mode=0o700)
    try:
        for item in plan:
            ident = item['id']
            if not re.fullmatch(r'web[0-9]+|shared-(core|vendor)', ident):
                raise ValueError('Raiz no permitida')
            archive, manifest_path = inputs / (ident + '.tar.gz'), inputs / (ident + '.json')
            if digest(archive) != item['archive_sha256'] or digest(manifest_path) != item['manifest_sha256']:
                raise ValueError('Entrada alterada')
            manifest = json.loads(manifest_path.read_text())
            backup.verify(archive, manifest)
            expected = {f['path']: f for f in manifest['files']}
            root = destination / ident
            root.mkdir(mode=0o700)
            restored_bytes = 0
            with tarfile.open(archive, 'r:gz') as tar:
                for member in tar:
                    name = member.name.removeprefix('./')
                    relative = PurePosixPath(name)
                    if not member.isfile() or not backup.candidate(name) or relative.is_absolute() or '..' in relative.parts:
                        raise ValueError('Ruta o tipo no permitidos')
                    meta = expected[name]
                    target = root.joinpath(*relative.parts)
                    target.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
                    content = tar.extractfile(member).read(backup.MAX_FILE + 1)
                    if len(content) != member.size or len(content) > backup.MAX_FILE:
                        raise ValueError('Longitud invalida')
                    # No usar extractall, no ejecutar fuentes ni aplicar duenos
                    # de produccion desde este usuario sin privilegios.
                    with target.open('x+b') as stream:
                        stream.write(content)
                        stream.seek(0)
                        if hashlib.sha256(stream.read()).hexdigest() != meta['sha256']:
                            raise ValueError('Archivo recuperado diferente')
                    target.chmod(meta['mode'])
                    if stat.S_IMODE(target.stat().st_mode) != meta['mode']:
                        raise ValueError('Permisos no recuperados')
                    restored_bytes += len(content)
            report['roots'].append({'id': ident, 'files': len(expected),
                                    'bytes': restored_bytes, 'contents_and_modes_verified': True})
        report['completed'] = True
    finally:
        report['finished_at'] = int(time.time())
        (STATE / 'report.json').write_text(json.dumps(report, indent=2) + '\n')
    print(json.dumps({'completed': True, 'roots': len(report['roots']),
                      'files': sum(i['files'] for i in report['roots'])}))


if __name__ == '__main__':
    main()
