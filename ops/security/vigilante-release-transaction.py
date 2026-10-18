#!/usr/bin/env python3
"""Motor de ensayo reversible. La entrada de consola solo admite casos privados."""
import argparse
import ast
from contextlib import contextmanager
import fcntl
import importlib.util
import json
import os
from pathlib import Path, PurePosixPath
import re
import stat
import uuid

spec = importlib.util.spec_from_file_location('plan', Path(__file__).with_name('vigilante-release-plan.py'))
plan = importlib.util.module_from_spec(spec)
spec.loader.exec_module(plan)
CASES = Path('/root/codex-vigilante/ensayos')
LIMIT = 10 * 1024 * 1024


@contextmanager
def directory(path):
    """Abre cada componente sin seguir enlaces, incluido el directorio raiz."""
    path = Path(path).absolute()
    fd = os.open('/', os.O_RDONLY | os.O_DIRECTORY)
    try:
        for part in path.parts[1:]:
            if part in ('.', '..'):
                raise ValueError('Componente no permitido')
            child = os.open(part, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW, dir_fd=fd)
            os.close(fd)
            fd = child
        yield fd
    finally:
        os.close(fd)


def read_file(path):
    path = Path(path)
    with directory(path.parent) as parent:
        fd = os.open(path.name, os.O_RDONLY | os.O_NOFOLLOW | os.O_NONBLOCK, dir_fd=parent)
        with os.fdopen(fd, 'rb') as stream:
            before = os.fstat(stream.fileno())
            if (not stat.S_ISREG(before.st_mode) or before.st_nlink != 1
                    or before.st_size > LIMIT or before.st_mode & 0o7000):
                raise ValueError('Tipo, enlaces, tamano o permisos especiales no admitidos')
            if os.listxattr(stream.fileno()):
                raise ValueError('Los atributos extendidos requieren otro procedimiento')
            data = stream.read(LIMIT + 1)
            after = os.fstat(stream.fileno())
            if (len(data) > LIMIT or (before.st_size, before.st_mtime_ns, before.st_ctime_ns)
                    != (after.st_size, after.st_mtime_ns, after.st_ctime_ns)):
                raise ValueError('El archivo ha cambiado durante la lectura')
    return data, {'sha256': plan.digest(data), 'uid': before.st_uid,
                  'gid': before.st_gid, 'mode': stat.S_IMODE(before.st_mode)}


def private_directory(path):
    with directory(path) as fd:
        info = os.fstat(fd)
        if info.st_uid != os.geteuid() or info.st_mode & 0o077:
            raise ValueError('Directorio privado no fiable')


def private_read(path):
    private_directory(Path(path).parent)
    data, meta = read_file(path)
    if meta['uid'] != os.geteuid() or meta['mode'] & 0o077:
        raise ValueError('Archivo privado no fiable')
    return data


def atomic(path, data, metadata=None, expected=None):
    """Patron existente: temporal vecino, metadatos, fsync y reemplazo."""
    path = Path(path)
    name = '.vigilante-' + uuid.uuid4().hex
    with directory(path.parent) as parent:
        fd = os.open(name, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600, dir_fd=parent)
        try:
            with os.fdopen(fd, 'wb') as stream:
                stream.write(data)
                if metadata:
                    os.fchown(stream.fileno(), metadata['uid'], metadata['gid'])
                    os.fchmod(stream.fileno(), metadata['mode'])
                stream.flush()
                os.fsync(stream.fileno())
            if expected is not None and read_file(path)[1] != expected:
                raise ValueError('El destino ha cambiado desde la revision')
            os.replace(name, path.name, src_dir_fd=parent, dst_dir_fd=parent)
            os.fsync(parent)
        finally:
            try:
                os.unlink(name, dir_fd=parent)
            except FileNotFoundError:
                pass


def save(path, record):
    atomic(path, (json.dumps(record, ensure_ascii=False, indent=2) + '\n').encode())


@contextmanager
def action_lock(path):
    private_directory(Path(path).parent)
    fd = os.open(path, os.O_RDWR | os.O_CREAT | os.O_NOFOLLOW, 0o600)
    with os.fdopen(fd, 'a+') as handle:
        info = os.fstat(handle.fileno())
        if (not stat.S_ISREG(info.st_mode) or info.st_nlink != 1
                or info.st_uid != os.geteuid() or info.st_mode & 0o077):
            raise ValueError('Bloqueo no fiable')
        fcntl.flock(handle, fcntl.LOCK_EX | fcntl.LOCK_NB)
        yield


def rollback(base, transaction):
    """El llamante mantiene el bloqueo comun tambien durante la recuperacion."""
    record = json.loads(private_read(transaction / 'journal.json'))
    if record.get('version') != 1 or record.get('base') != str(Path(base).absolute()):
        raise ValueError('Diario de otro destino o version')
    conflicts = []
    for entry in reversed(record['files']):
        name = plan.source_path(entry['path'])
        if not re.fullmatch(r'[0-9]+\.before', entry['backup']):
            raise ValueError('Nombre de copia invalido')
        target = base / name
        try:
            _, current = read_file(target)
            before = entry['before']
            if current == before:
                continue
            expected = dict(before, sha256=entry['after_sha256'])
            if current != expected:
                raise ValueError('Cambio posterior a la publicacion')
            backup = private_read(transaction / entry['backup'])
            if plan.digest(backup) != before['sha256']:
                raise ValueError('Copia de recuperacion alterada')
            atomic(target, backup, before, expected)
        except (OSError, ValueError):
            conflicts.append(name)
    record['status'] = 'requiere_intervencion' if conflicts else 'revertido'
    record['conflicts'] = conflicts
    save(transaction / 'journal.json', record)
    return record


def execute(base, package, approval_path, transaction, lock, validate, health):
    """API interna de ensayo; no se expone como permiso de escritura al modelo."""
    base, package, transaction = Path(base), Path(package), Path(transaction)
    with action_lock(lock):
        private_directory(base)
        private_directory(package)
        private_directory(transaction.parent)
        raw = private_read(package / 'manifest.json')
        manifest = json.loads(raw)
        approval = json.loads(private_read(approval_path))
        if (manifest.get('version') != 1 or manifest.get('scope') != 'selected-files-only'
                or manifest.get('deployment_enabled') is not False
                or not re.fullmatch(r'[0-9a-f]{40}', manifest.get('commit', ''))
                or approval.get('version') != 1 or approval.get('reviewed') is not True
                or approval.get('manifest_sha256') != plan.digest(raw)
                or approval.get('base') != str(base.absolute())):
            raise ValueError('Paquete o revision no validos para este ensayo')
        entries = manifest.get('files', [])
        names = [plan.source_path(x['path']) for x in entries]
        if not 1 <= len(names) <= 20 or len(set(names)) != len(names) or set(approval['before']) != set(names):
            raise ValueError('Seleccion no valida')
        prepared = []
        # Leer y validar todos los archivos antes de modificar el primero.
        for entry in entries:
            name = entry['path']
            data = private_read(package / 'files' / name)
            original, metadata = read_file(base / name)
            if (plan.digest(data) != entry['sha256'] or len(data) != entry['bytes']
                    or metadata != approval['before'][name]
                    or entry.get('local_matches_commit') is not True):
                raise ValueError('Huellas, metadatos o copia local no coinciden')
            validate(name, data)
            prepared.append((name, data, original, metadata))
        transaction.mkdir(mode=0o700)  # Nunca reutilizar un identificador.
        with directory(transaction.parent) as parent:
            os.fsync(parent)
        record = {'version': 1, 'base': str(base.absolute()), 'commit': manifest['commit'],
                  'manifest_sha256': plan.digest(raw), 'status': 'preparado', 'files': []}
        for index, (name, data, original, metadata) in enumerate(prepared):
            backup = str(index) + '.before'
            atomic(transaction / backup, original)
            record['files'].append({'path': name, 'before': metadata,
                                    'after_sha256': plan.digest(data), 'backup': backup})
        # Intencion duradera antes de escribir: permite recuperar un corte abrupto.
        save(transaction / 'journal.json', record)
        try:
            for name, data, _, metadata in prepared:
                atomic(base / name, data, metadata, metadata)
                if read_file(base / name)[1] != dict(metadata, sha256=plan.digest(data)):
                    raise ValueError('Verificacion posterior fallida')
            if health() is not True:
                raise ValueError('Comprobacion de salud fallida')
            record['status'] = 'aplicado'
            save(transaction / 'journal.json', record)
        except Exception as error:
            # No sobrescribir cambios nuevos durante la vuelta atras.
            record['failure_type'] = type(error).__name__
            save(transaction / 'journal.json', record)
            return rollback(base, transaction)
        return record


def syntax(name, data):
    if not name.endswith('.py'):
        raise ValueError('La consola de ensayo solo admite fuentes Python')
    ast.parse(data, filename=name)


def main():
    if os.geteuid() != 0:
        raise PermissionError('Solo administrador; no se concede acceso al agente')
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('action', choices=['apply', 'rollback'])
    parser.add_argument('--case', required=True)
    parser.add_argument('--transaction', required=True)
    parser.add_argument('--simulate-health-failure', action='store_true')
    args = parser.parse_args()
    if any(not re.fullmatch(r'[a-z0-9][a-z0-9-]{0,63}', x) for x in (args.case, args.transaction)):
        parser.error('Identificador de ensayo invalido')
    private_directory(CASES)
    case = CASES / args.case
    private_directory(case)
    base, transaction = case / 'site', case / 'transactions' / args.transaction
    lock = case / 'operations.lock'
    if args.action == 'apply':
        result = execute(base, case / 'package', case / 'approval.json', transaction, lock,
                         syntax, lambda: not args.simulate_health_failure)
    else:
        private_directory(transaction)
        with action_lock(lock):
            result = rollback(base, transaction)
    print(json.dumps({'status': result['status'], 'conflicts': result.get('conflicts', []),
                      'production_deployment_enabled': False}))
    expected = 'aplicado' if args.action == 'apply' else 'revertido'
    return 0 if result['status'] == expected else 1


if __name__ == '__main__':
    try:
        raise SystemExit(main())
    except (OSError, ValueError, KeyError, TypeError) as error:
        print(json.dumps({'status': 'rechazado', 'error_type': type(error).__name__}))
        raise SystemExit(1)
