#!/usr/bin/env python3
"""Instala un artefacto fijado y crea solo una comprobacion manual sin conexion."""
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import stat
import subprocess
import tarfile
import urllib.request

SOURCE = Path(__file__).resolve().parent
BASE = Path('/root/codex-vigilante/tramo-6')
INSTALLED = Path('/opt/vigilante-codex')
ISOLATION = Path('/etc/systemd/system/vigilante-isolation-check.service')
UNIT = Path('/etc/systemd/system/vigilante-codex-check.service')
CHECK = Path('/usr/local/lib/vigilante/vigilante-codex-check.py')
ARCHIVE = 'codex-x86_64-unknown-linux-musl.tar.gz'


def private_root(path):
    for parent in [path, *path.parents]:
        meta = parent.lstat()
        if not stat.S_ISDIR(meta.st_mode) or meta.st_uid != 0 or meta.st_mode & 0o022:
            raise ValueError('Ruta no fiable')


def new_file(path, data, mode):
    fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, mode)
    with os.fdopen(fd, 'wb') as stream:
        stream.write(data)
        os.fchmod(stream.fileno(), mode)
        stream.flush()
        os.fsync(stream.fileno())


def command(args, timeout=45):
    return subprocess.run(args, capture_output=True, text=True, check=True, timeout=timeout)


def make_unit(original, binary):
    old = 'ExecStart=/usr/bin/python3 -I -B /probe.py'
    if original.count(old) != 1 or 'PrivateNetwork=yes' not in original:
        raise ValueError('Unidad base distinta de la revisada')
    unit = original.replace('Description=Comprobacion manual del aislamiento del vigilante',
                            'Description=Comprobacion manual de Codex sin conexion')
    unit = unit.replace(old, 'ExecStart=/usr/bin/python3 -I -B /input/tools/codex-check.py')
    unit += ('\nBindReadOnlyPaths=' + str(binary) + ':/input/tools/codex\n'
             'BindReadOnlyPaths=' + str(CHECK) + ':/input/tools/codex-check.py\n'
             'Environment=CODEX_HOME=/workspace/codex-offline\n'
             'UnsetEnvironment=OPENAI_API_KEY CODEX_API_KEY\n')
    return unit


def main():
    if os.geteuid() != 0 or os.uname().machine != 'x86_64':
        raise PermissionError('Instalador para administrador en x86_64')
    os.nice(15)
    private_root(SOURCE)
    private_root(BASE)
    for path in (INSTALLED, UNIT, CHECK):
        private_root(path.parent)
        if os.path.lexists(path):
            raise ValueError('Destino existente; no actualizar ni sobrescribir automaticamente')
    metadata = json.loads((BASE / 'release-metadata.json').read_text())
    tag = metadata['tag_name']
    if metadata['draft'] or metadata['prerelease'] or not re.fullmatch(r'rust-v[0-9]+\.[0-9]+\.[0-9]+', tag):
        raise ValueError('Version estable no valida')
    asset = next(a for a in metadata['assets'] if a['name'] == ARCHIVE)
    expected_url = 'https://github.com/openai/codex/releases/download/' + tag + '/' + ARCHIVE
    if (asset['browser_download_url'] != expected_url or not 1 <= asset['size'] <= 160 * 1024 * 1024
            or not re.fullmatch(r'sha256:[0-9a-f]{64}', asset['digest'])):
        raise ValueError('Metadatos del artefacto no validos')
    archive = BASE / ARCHIVE
    calculated = hashlib.sha256()
    total = 0
    with archive.open('xb') as output:
        archive.chmod(0o600)
        request = urllib.request.Request(expected_url, headers={'User-Agent': 'vigilante-install-audit'})
        with urllib.request.urlopen(request, timeout=30) as incoming:
            while True:
                chunk = incoming.read(1024 * 1024)
                if not chunk:
                    break
                total += len(chunk)
                if total > asset['size']:
                    raise ValueError('Descarga mayor de lo anunciado')
                calculated.update(chunk)
                output.write(chunk)
        output.flush()
        os.fsync(output.fileno())
    if total != asset['size'] or 'sha256:' + calculated.hexdigest() != asset['digest']:
        raise ValueError('La huella de la descarga no coincide')
    INSTALLED.mkdir(mode=0o755)
    release = INSTALLED / tag
    release.mkdir(mode=0o755)
    binary = release / 'codex'
    with tarfile.open(archive, 'r:gz') as bundle:
        members = [m for m in bundle.getmembers() if m.name == 'codex-x86_64-unknown-linux-musl']
        if len(members) != 1 or not members[0].isfile() or not 1 <= members[0].size <= 400 * 1024 * 1024:
            raise ValueError('Archivo ejecutable del paquete no reconocido')
        # No extraer el arbol ni enlaces o rutas elegidos por el archivo tar.
        with bundle.extractfile(members[0]) as incoming, binary.open('xb') as output:
            shutil.copyfileobj(incoming, output, 1024 * 1024)
            output.flush()
            os.fsync(output.fileno())
    binary.chmod(0o755)
    with binary.open('rb') as stream:
        binary_hash = hashlib.file_digest(stream, 'sha256').hexdigest() if hasattr(hashlib, 'file_digest') else None
    if binary_hash is None:
        h = hashlib.sha256()
        with binary.open('rb') as stream:
            for chunk in iter(lambda: stream.read(1024 * 1024), b''):
                h.update(chunk)
        binary_hash = h.hexdigest()
    source_unit = (SOURCE / ISOLATION.name).read_bytes()
    if ISOLATION.read_bytes() != source_unit:
        raise ValueError('El aislamiento instalado difiere de su fuente local')
    unit = make_unit(source_unit.decode(), binary).encode()
    tools = Path('/srv/vigilante-ai/input/tools')
    tools.mkdir(mode=0o750)
    os.chown(tools, 0, Path('/srv/vigilante-ai/input').stat().st_gid)
    for name in ('codex', 'codex-check.py'):
        new_file(tools / name, b'', 0o644)
    (BASE / UNIT.name).write_bytes(unit)
    (BASE / UNIT.name).chmod(0o600)
    command(['systemd-analyze', 'verify', str(BASE / UNIT.name)])
    check = (SOURCE / CHECK.name).read_bytes()
    new_file(CHECK, check, 0o644)
    try:
        new_file(UNIT, unit, 0o644)
        command(['systemctl', 'daemon-reload'])
        command(['systemctl', 'start', UNIT.name])
        result = json.loads(Path('/var/lib/vigilante-ai/workspace/codex-check-result.json').read_text())
        if not result.get('passed') or result.get('ai_called') is not False:
            raise ValueError('La comprobacion del CLI ha fallado')
        if result['binary_version'] != 'codex-cli ' + tag.removeprefix('rust-v'):
            raise ValueError('La version ejecutada no coincide con el paquete')
        report = {'release': tag, 'archive_sha256': calculated.hexdigest(),
                  'binary_sha256': binary_hash, 'binary_path': str(binary),
                  'unit_sha256': hashlib.sha256(unit).hexdigest(), 'check': result,
                  'model_call_tested': False, 'native_tool_sandbox_tested': False,
                  'web_services_restarted': False}
        new_file(BASE / 'installation-result.json', (json.dumps(report, indent=2) + '\n').encode(), 0o600)
        print(json.dumps({'status': 'instalado_sin_conexion', 'version': tag,
                          'authenticated': False, 'ai_called': False}))
    except Exception:
        subprocess.run(['systemctl', 'stop', UNIT.name], capture_output=True, timeout=10)
        for path, data in ((UNIT, unit), (CHECK, check)):
            if path.is_file() and not path.is_symlink() and path.read_bytes() == data:
                path.unlink()
        subprocess.run(['systemctl', 'daemon-reload'], capture_output=True, timeout=15)
        raise


if __name__ == '__main__':
    main()
