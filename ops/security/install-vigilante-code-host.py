#!/usr/bin/env python3
"""Instala el auxiliar oficial de la misma version fijada del CLI."""
import hashlib
import importlib.util
import json
import os
from pathlib import Path, PurePosixPath
import tarfile
import urllib.request

HERE = Path(__file__).resolve().parent
NAME = 'codex-code-mode-host-x86_64-unknown-linux-musl.tar.gz'
DIGEST = '314263128ba6592e7e807c265deeaf928a692b72dcbfeb5e0b20c9423b703529'
SIZE = 27583103
TARGET = Path('/opt/vigilante-codex/rust-v0.163.0/codex-code-mode-host')


def main():
    spec = importlib.util.spec_from_file_location('support', HERE/'install-vigilante-isolation.py')
    support = importlib.util.module_from_spec(spec); spec.loader.exec_module(support)
    if os.geteuid() != 0 or os.uname().machine != 'x86_64':
        raise PermissionError('Solo administrador x86_64')
    support.trusted_directory(HERE); support.trusted_directory(TARGET.parent)
    if os.path.lexists(TARGET):
        raise ValueError('Auxiliar existente; revisar antes de reemplazar')
    if hashlib.sha256((TARGET.parent/'codex').read_bytes()).hexdigest() != '399ff69c431c066e40de318fba45c2b07f45a76b19939d247ee4828fc70c4215':
        raise ValueError('CLI diferente del fijado')
    archive = HERE/NAME
    request = urllib.request.Request('https://github.com/openai/codex/releases/download/rust-v0.163.0/'+NAME,
                                     headers={'User-Agent': 'vigilante-install-audit'})
    total = 0; digest = hashlib.sha256()
    with archive.open('xb') as output:
        os.fchmod(output.fileno(), 0o600)
        with urllib.request.urlopen(request, timeout=30) as response:
            while True:
                data = response.read(1024*1024)
                if not data:
                    break
                total += len(data)
                if total > SIZE:
                    raise ValueError('Descarga excesiva')
                output.write(data); digest.update(data)
    if total != SIZE or digest.hexdigest() != DIGEST:
        raise ValueError('Huella del auxiliar distinta de la oficial')
    with tarfile.open(archive, 'r:gz') as bundle:
        files = [m for m in bundle.getmembers() if m.isfile()]
        if len(files) != 1:
            raise ValueError('Contenido inesperado')
        member = files[0]; path = PurePosixPath(member.name)
        if path.is_absolute() or '..' in path.parts or path.name not in (
                'codex-code-mode-host', 'codex-code-mode-host-x86_64-unknown-linux-musl') or member.size > 150*1024*1024:
            raise ValueError('Archivo del auxiliar no permitido')
        data = bundle.extractfile(member).read()
    support.write_new(TARGET, data, 0o755)
    report = {'version': '0.163.0', 'archive_sha256': DIGEST,
              'binary_sha256': hashlib.sha256(data).hexdigest(), 'global_path_changed': False}
    support.write_new(HERE/'code-host-installation.json', (json.dumps(report, indent=2)+'\n').encode())
    print(json.dumps(report))


if __name__ == '__main__':
    main()
