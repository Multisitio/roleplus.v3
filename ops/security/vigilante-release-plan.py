#!/usr/bin/env python3
"""Prepara archivos de un commit explicito. No escribe en las webs ni reinicia."""
import argparse
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import re
import subprocess


def digest(data):
    return hashlib.sha256(data).hexdigest()


def git(root, *args):
    result = subprocess.run(['git', '-C', str(root), *args], capture_output=True)
    if result.returncode:
        # Los errores de Git pueden contener direcciones con credenciales.
        raise ValueError('Git no ha podido completar la lectura solicitada')
    return result.stdout


def source_path(value):
    path = PurePosixPath(value)
    if (not value or '\\' in value or ':' in value or path.is_absolute()
            or str(path) != value or any(x in ('.', '..') for x in path.parts)
            or any(ord(x) < 32 for x in value)):
        raise ValueError('Ruta relativa no permitida')
    lower = [x.lower() for x in path.parts]
    blocked = {'config', '.git', '.env', 'vendor', 'node_modules', 'temp',
               'cache', 'logs', 'upload', 'uploads', 'usuarios', 'rss',
               'vistas_previas', 'plantillas', 'backups', 'scratch'}
    if (any(x in blocked or x.startswith('.env.') for x in lower)
            or lower[-1] in {'index.php', '.htaccess', 'keys.php'}
            or path.suffix.lower() in {'.sql', '.ini', '.pem', '.key', '.crt', '.p12'}):
        raise ValueError('Configuracion, datos o artefacto protegido')
    if path.parts[0] not in {'private', 'web', 'ops'}:
        raise ValueError('Fuera de las carpetas de codigo revisables')
    return value


def prepare(repository, revision, paths, output):
    root = Path(repository).resolve()
    actual = Path(git(root, 'rev-parse', '--show-toplevel').decode().strip()).resolve()
    if os.path.normcase(str(actual)) != os.path.normcase(str(root)):
        raise ValueError('La carpeta no tiene repositorio propio')
    if not re.fullmatch(r'[0-9a-f]{40}', revision):
        raise ValueError('Indicar el identificador completo e inmutable del commit')
    commit = git(root, 'rev-parse', '--verify', revision + '^{commit}').decode().strip()
    selected = [source_path(p) for p in paths]
    if not selected or len(set(selected)) != len(selected):
        raise ValueError('Seleccion vacia o duplicada')
    files = []
    contents = {}
    for name in selected:
        entry = git(root, 'ls-tree', '-z', commit, '--', name).split(b'\0')
        if len(entry) != 2 or not entry[0]:
            raise ValueError('Debe seleccionarse un archivo versionado individual')
        metadata, returned = entry[0].split(b'\t', 1)
        mode, kind, blob = metadata.decode().split()
        if returned.decode('utf-8') != name or mode not in ('100644', '100755') or kind != 'blob':
            raise ValueError('No se admiten directorios, enlaces ni submodulos')
        data = git(root, 'cat-file', 'blob', blob)
        if len(data) > 10 * 1024 * 1024:
            raise ValueError('Archivo demasiado grande para este procedimiento')
        working = root.joinpath(*PurePosixPath(name).parts)
        matches = (working.is_file() and not working.is_symlink()
                   and digest(working.read_bytes()) == digest(data))
        files.append({'path': name, 'git_blob': blob, 'sha256': digest(data),
                      'bytes': len(data), 'local_matches_commit': matches})
        contents[name] = data
    output = Path(output)
    if output.exists():
        raise ValueError('El destino debe ser nuevo; no se reemplazan paquetes')
    output.mkdir(mode=0o700, parents=True)
    for name, data in contents.items():
        target = output / 'files' / name
        target.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
        with target.open('xb') as stream:
            stream.write(data)
        target.chmod(0o600)
    manifest = {'version': 1, 'commit': commit, 'scope': 'selected-files-only',
                'production_commit_certified': False, 'deployment_enabled': False,
                'requires_content_review': True, 'files': files}
    destination = output / 'manifest.json'
    destination.write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    destination.chmod(0o600)
    return manifest


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--repository', required=True)
    parser.add_argument('--commit', required=True)
    parser.add_argument('--output', required=True)
    parser.add_argument('files', nargs='+')
    args = parser.parse_args()
    try:
        result = prepare(args.repository, args.commit, args.files, args.output)
    except (ValueError, OSError) as error:
        parser.exit(1, str(error) + '\n')
    print(json.dumps({'commit': result['commit'], 'file_count': len(result['files']),
                      'deployment_enabled': False}))


if __name__ == '__main__':
    main()
