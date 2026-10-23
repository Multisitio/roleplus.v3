#!/usr/bin/env python3
"""Copias privadas de codigo/configuracion textual; nunca copia archivos binarios."""
from contextlib import contextmanager
from datetime import datetime
import fcntl
import gzip
import hashlib
import io
import json
import os
from pathlib import Path, PurePosixPath
import re
import shutil
import stat
import subprocess
import tarfile
import time

CONFIG = Path('/etc/vigilante/text-backups.json')
STATE = Path('/var/lib/vigilante-text-backups')
DEST = Path('/var/backup/vigilante-text')
MAX_FILE = 16 * 1024 ** 2
MAX_ARCHIVE = 512 * 1024 ** 2
SYSTEM_ROOTS = (Path('/etc'), Path('/usr/local/ispconfig'))
EXCLUDED = {'.git', '.svn', 'node_modules', '__pycache__', '.venv', 'backup', 'backups',
            'cache', 'temp', 'tmp', 'log', 'logs', 'stats', 'img', 'images', 'audio',
            'mp3', 'fonts', 'uploads', 'upload', 'scratch'}
SUFFIXES = {'.php', '.phtml', '.html', '.htm', '.css', '.js', '.mjs', '.cjs', '.json',
            '.md', '.txt', '.yml', '.yaml', '.xml', '.ini', '.conf', '.cnf', '.cfg',
            '.sh', '.py', '.sql', '.toml', '.csv', '.ts', '.tsx', '.jsx', '.map'}


def text_content(data):
    if b'\0' in data or data.startswith((b'MZ', b'PK\x03\x04', b'\x7fELF', b'%PDF-',
                                         b'\x89PNG', b'GIF8', b'\xff\xd8', b'\x1f\x8b')):
        return False
    try:
        value = data.decode('utf-8-sig')
    except UnicodeDecodeError:
        try:
            value = data.decode('cp1252')
        except UnicodeDecodeError:
            return False
    return re.search(r'[\x00-\x08\x0b\x0e-\x1f\x7f-\x9f]', value) is None


def candidate(relative):
    p = PurePosixPath(relative)
    if p.is_absolute() or '..' in p.parts or any(x.lower() in EXCLUDED for x in p.parts):
        return False
    if p.suffix.lower() == '.sql' and 'config' in p.parts:
        return False  # Volcados operativos: tienen su politica de bases, no son fuentes.
    return p.suffix.lower() in SUFFIXES or p.name in {
        '.htaccess', '.gitignore', '.gitattributes', '.env', 'LICENSE', 'Makefile', 'Dockerfile'} or p.name.startswith('.env.')


@contextmanager
def directory(path):
    fd = os.open('/', os.O_RDONLY | os.O_DIRECTORY)
    try:
        for part in Path(path).absolute().parts[1:]:
            child = os.open(part, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW, dir_fd=fd)
            os.close(fd)
            fd = child
        yield fd
    finally:
        os.close(fd)


def read_regular(path):
    with directory(path.parent) as parent:
        fd = os.open(path.name, os.O_RDONLY | os.O_NONBLOCK | os.O_NOFOLLOW, dir_fd=parent)
        with os.fdopen(fd, 'rb') as stream:
            info = os.fstat(stream.fileno())
            if not stat.S_ISREG(info.st_mode) or info.st_nlink != 1 or info.st_size > MAX_FILE:
                raise ValueError('Tipo, enlaces o tamano requieren otro procedimiento')
            data = stream.read(MAX_FILE + 1)
            after = os.fstat(stream.fileno())
            if (len(data) > MAX_FILE or (info.st_size, info.st_mtime_ns, info.st_ctime_ns)
                    != (after.st_size, after.st_mtime_ns, after.st_ctime_ns)):
                raise ValueError('El archivo cambio durante la copia')
    return data, info


def archive(root, destination, *, system_config=False):
    """Seleccion positiva mas comprobacion del contenido completo; sin seguir enlaces."""
    if system_config and root not in SYSTEM_ROOTS:
        raise ValueError('Raiz de configuracion no revisada')
    metadata = {'directories': [], 'links': []}
    total = 0
    files = []
    skipped = {'binary': 0, 'unsupported': 0}
    with destination.open('xb') as out:
        os.fchmod(out.fileno(), 0o600)
        with tarfile.open(fileobj=out, mode='w:gz', compresslevel=1) as tar:
            for folder, dirs, names in os.walk(root, followlinks=False):
                if system_config:
                    meta = Path(folder).lstat()
                    metadata['directories'].append({'path': Path(folder).relative_to(root).as_posix(),
                        'uid': meta.st_uid, 'gid': meta.st_gid, 'mode': stat.S_IMODE(meta.st_mode)})
                    for name in sorted(dirs + names):
                        path = Path(folder) / name
                        if path.is_symlink():
                            meta = path.lstat()
                            metadata['links'].append({'path': path.relative_to(root).as_posix(),
                                'target': os.readlink(path), 'uid': meta.st_uid, 'gid': meta.st_gid})
                dirs[:] = sorted(d for d in dirs if d.lower() not in EXCLUDED
                                 and not (Path(folder) / d).is_symlink())
                for name in sorted(names):
                    path = Path(folder) / name
                    relative = path.relative_to(root).as_posix()
                    if (not system_config and not candidate(relative)) or path.is_symlink():
                        skipped['unsupported'] += 1
                        continue
                    data, meta = read_regular(path)
                    if not text_content(data):
                        skipped['binary'] += 1
                        continue
                    total += len(data)
                    if total > MAX_ARCHIVE:
                        raise ValueError('Se excedio el presupuesto de la copia')
                    item = tarfile.TarInfo('./' + relative)
                    item.size, item.uid, item.gid = len(data), meta.st_uid, meta.st_gid
                    item.mode, item.mtime = stat.S_IMODE(meta.st_mode), int(meta.st_mtime)
                    tar.addfile(item, io.BytesIO(data))
                    files.append({'path': relative, 'sha256': hashlib.sha256(data).hexdigest(),
                                  'bytes': len(data), 'uid': item.uid, 'gid': item.gid, 'mode': item.mode})
            if system_config:
                # Los enlaces se describen como texto; nunca se siguen ni se crean al verificar.
                data = (json.dumps(metadata, ensure_ascii=True, sort_keys=True) + '\n').encode()
                total += len(data)
                if len(data) > MAX_FILE or total > MAX_ARCHIVE:
                    raise ValueError('Metadatos fuera de presupuesto')
                name = '.vigilante-metadata.json'
                if any(x['path'] == name for x in files):
                    raise ValueError('Nombre reservado ocupado')
                item = tarfile.TarInfo('./' + name)
                item.size, item.uid, item.gid, item.mode = len(data), 0, 0, 0o600
                tar.addfile(item, io.BytesIO(data))
                files.append({'path': name, 'sha256': hashlib.sha256(data).hexdigest(),
                              'bytes': len(data), 'uid': 0, 'gid': 0, 'mode': 0o600})
        out.flush()
        os.fsync(out.fileno())
    if not files:
        raise ValueError('No se encontro texto para esta copia')
    return {'files': files, 'text_bytes': total, 'excluded': skipped}


def verify(path, manifest):
    with gzip.open(path, 'rb') as stream:
        while stream.read(1024 * 1024):
            pass  # Comprueba tambien el final y CRC, no solo las entradas tar.
    expected = {x['path']: x for x in manifest['files']}
    seen = set()
    with tarfile.open(path, 'r:gz') as tar:
        for member in tar:
            name = member.name.removeprefix('./')
            if not member.isfile() or name not in expected or name in seen or member.size > MAX_FILE:
                raise ValueError('Entrada inesperada en la copia')
            data = tar.extractfile(member).read()
            item = expected[name]
            if (not text_content(data) or hashlib.sha256(data).hexdigest() != item['sha256']
                    or (member.uid, member.gid, member.mode) != (item['uid'], item['gid'], item['mode'])):
                raise ValueError('Contenido o metadatos no validos')
            seen.add(name)
    if seen != set(expected):
        raise ValueError('Copia incompleta')


def save(path, value):
    temp = path.with_suffix(path.suffix + '.tmp')
    with temp.open('w') as out:
        os.fchmod(out.fileno(), 0o600)
        json.dump(value, out, indent=2)
        out.write('\n')
        out.flush()
        os.fsync(out.fileno())
    os.replace(temp, path)


def sql(query):
    result = subprocess.run(['/usr/bin/mysql', '--no-defaults', '--protocol=socket',
        '--socket=/run/mysqld/mysqld.sock', '--user=root', '--batch', '--skip-column-names',
        'dbispconfig'], input=query, text=True, capture_output=True, timeout=15)
    if result.returncode:
        raise RuntimeError('El panel no confirmo el registro de la copia')
    return result.stdout


def run():
    if os.geteuid() != 0:
        raise PermissionError('Solo administrador; sin acceso para la IA')
    os.umask(0o077)
    meta = CONFIG.lstat()
    if not stat.S_ISREG(meta.st_mode) or meta.st_uid != 0 or meta.st_mode & 0o077:
        raise ValueError('Catalogo privado no fiable')
    config = json.loads(CONFIG.read_text())
    STATE.mkdir(mode=0o700, exist_ok=True)
    DEST.mkdir(mode=0o700, exist_ok=True)
    meta=DEST.lstat()
    if DEST.is_symlink() or meta.st_uid!=0 or meta.st_mode&0o077:
        raise ValueError('Almacen textual privado no fiable')
    with (STATE / 'operations.lock').open('a') as lock:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        now = int(time.time())
        ledger_path = STATE / 'ledger.json'
        ledger = json.loads(ledger_path.read_text()) if ledger_path.exists() else {'version': 1, 'items': {}}
        summary = []
        for site in config['sites']:
            ident = site['id']
            if not re.fullmatch(r'(web[0-9]+|shared-(core|vendor))', ident):
                raise ValueError('Destino no autorizado')
            root = Path(site['root'])
            expected_name = ident if ident.startswith('web') else ident.removeprefix('shared-')
            if root.is_symlink() or root.parent != Path('/var/www/clients/client1') or root.name != expected_name:
                raise ValueError('Raiz no autorizada')
            if ledger['items'].get(ident, {}).get('next_at', 0) > now:
                continue
            if shutil.disk_usage('/var/backup').free < 20 * 1024 ** 3 + MAX_ARCHIVE:
                raise ValueError('Espacio libre insuficiente')
            dest = DEST / ident
            dest.mkdir(mode=0o700, exist_ok=True)
            meta = dest.lstat()
            if not stat.S_ISDIR(meta.st_mode) or meta.st_uid != 0 or meta.st_mode & 0o022:
                raise ValueError('Destino de copias no fiable')
            known = {x['name'] for x in ledger['items'].get(ident, {}).get('archives', [])}
            existing = {p.name for p in dest.glob('text-' + ident + '_*.tar.gz')}
            if existing != known or any(dest.glob('text-' + ident + '_*.pending')):
                raise ValueError('Hay una copia interrumpida; requiere conciliacion')
            name = 'text-' + ident + '_' + datetime.now().strftime('%Y-%m-%d_%H-%M-%S') + '.tar.gz'
            path = dest / name
            pending = dest / (name + '.pending')
            manifest = archive(root, pending)
            verify(pending, manifest)
            os.rename(pending, path)
            save(STATE / (name + '.json'), manifest)
            if ident.startswith('web'):
                domain = int(ident[3:])
                if sql(f"SELECT COUNT(*) FROM web_domain WHERE domain_id={domain} AND document_root='{root}' AND active='y';").strip() != '1':
                    raise ValueError('El dominio del panel ha cambiado')
            previous = ledger['items'].get(ident, {}).get('archives', [])
            previous.append({'name': name, 'sha256': hashlib.sha256(path.read_bytes()).hexdigest()})
            # Conservar dos verificadas. Nunca barrer archivos ajenos o originales.
            while len(previous) > 2:
                old = previous[0]
                if not re.fullmatch(r'text-' + re.escape(ident) + r'_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.tar\.gz', old['name']):
                    raise ValueError('Referencia de retencion no valida')
                old_path = dest / old['name']
                if old_path.is_symlink() or hashlib.sha256(old_path.read_bytes()).hexdigest() != old['sha256']:
                    raise ValueError('Una copia anterior cambio')
                old_path.unlink()
                previous.pop(0)
            ledger['items'][ident] = {'last_at': now, 'next_at': next_sunday(now), 'archives': previous}
            save(ledger_path, ledger)
            summary.append({'id': ident, 'files': len(manifest['files']), 'text_bytes': manifest['text_bytes'],
                            'archive_bytes': path.stat().st_size})
        save(STATE / 'latest.json', {'checked_at': int(time.time()), 'completed': True, 'sites': summary})
        print(json.dumps({'completed': True, 'sites': summary}))


def next_sunday(now):
    from datetime import timedelta
    from zoneinfo import ZoneInfo
    current = datetime.fromtimestamp(now, ZoneInfo('Europe/Madrid'))
    target = current.replace(hour=4, minute=15, second=0, microsecond=0)
    target += timedelta(days=(6 - target.weekday()) % 7)
    if target.timestamp() <= now:
        target += timedelta(days=7)
    return int(target.timestamp())


if __name__ == '__main__':
    try:
        run()
    except Exception as error:
        if os.geteuid() == 0 and STATE.is_dir():
            save(STATE / 'latest.json', {'checked_at': int(time.time()), 'completed': False,
                                       'failure_type': type(error).__name__})
        raise
