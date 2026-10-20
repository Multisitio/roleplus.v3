#!/usr/bin/env python3
"""Copias SQL manuales y acotadas de bases independientes revisadas."""
import gzip
from contextlib import contextmanager
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess
import select
import time

SOCKET = '/run/mysqld/mysqld.sock'
MAX_SECONDS = 1.5
MAX_RAW_BYTES = 16 * 1024 ** 2
CHILD = '/usr/local/lib/vigilante/vigilante-sql-child.py'


class BackupFailure(RuntimeError):
    pass


def command(database, socket=SOCKET, output=None, statement_seconds=0.5):
    if not re.fullmatch(r'[a-z][a-z0-9_]{0,63}', database):
        raise ValueError('Nombre de base no permitido')
    args = ['/usr/bin/mariadb-dump', '--no-defaults', '--protocol=socket',
            '--socket=' + str(socket), '--user=root', '--default-character-set=utf8mb4',
            '--skip-lock-tables', '--single-transaction', '--quick', '--routines', '--events', '--triggers',
            '--hex-blob', '--max-statement-time=' + str(statement_seconds)]
    if output is not None:
        args.append('--result-file=' + str(output))
    return args + [database]


def stop_dump(process):
    if process.poll() is None:
        process.kill()  # Solo el cliente creado por este ejecutor, nunca otras consultas.
    process.wait(timeout=3)


def spawn(args, seconds, **kwargs):
    # Dos niveles de senal de muerte: controlador -> timeout -> cliente.
    # El plazo sigue vivo aunque el controlador quede suspendido.
    timed = ['/usr/bin/timeout', '--signal=TERM', '--kill-after=0.1s', str(seconds) + 's',
             '/usr/bin/python3', '-I', '-B', CHILD] + args
    return subprocess.Popen(['/usr/bin/python3', '-I', '-B', CHILD] + timed, **kwargs)


def client(socket):
    return ['/usr/bin/mysql', '--no-defaults', '--protocol=socket', '--socket=' + str(socket),
            '--user=root', '--batch', '--skip-column-names', '--unbuffered']


@contextmanager
def strict_read(database, socket, seconds, errors):
    if not re.fullmatch(r'[a-z][a-z0-9_]{0,63}', database):
        raise ValueError('Nombre no permitido')
    p = spawn(client(socket) + ['-e', "SELECT TABLE_NAME FROM information_schema.TABLES "
        "WHERE TABLE_SCHEMA='" + database + "' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME"],
        2, stdout=subprocess.PIPE, stderr=errors, text=True)
    tables = p.communicate(timeout=3)[0].splitlines()
    if p.returncode or not 1 <= len(tables) <= 256 or any(not re.fullmatch(r'[a-zA-Z0-9_]{1,64}', t) for t in tables):
        raise BackupFailure('No se pudieron acreditar las tablas de la base')
    start = time.monotonic()
    holder = spawn(client(socket), seconds, stdin=subprocess.PIPE, stdout=subprocess.PIPE,
                   stderr=errors, text=True)
    try:
        locks = ','.join('`' + database + '`.`' + table + '` READ' for table in tables)
        holder.stdin.write("SET autocommit=0; SET SESSION max_statement_time=0.5; LOCK TABLES "
                           + locks + "; SELECT 'vigilante_ready';\n")
        holder.stdin.flush()
        if not select.select([holder.stdout], [], [], seconds)[0] or holder.stdout.readline().strip() != 'vigilante_ready':
            raise BackupFailure('No se obtuvo el bloqueo acotado')
        holder.vigilante_tables = tables
        yield holder, start
    finally:
        try:
            holder.stdin.close()
        except (BrokenPipeError, OSError):
            pass
        try:
            holder.wait(timeout=0.3)
        except subprocess.TimeoutExpired:
            stop_dump(holder)
        holder.stdout.close()


def dump(database, output, socket=SOCKET, seconds=MAX_SECONDS, counts=None, statement_seconds=0.5):
    if output.exists():
        raise ValueError('No sobrescribir una copia previa')
    start = time.monotonic()
    with output.with_suffix('.errors').open('xb') as errors:
        os.chmod(errors.name, 0o600)
        with strict_read(database, socket, seconds, errors) as (holder, locked_at):
            remaining = seconds - (time.monotonic() - locked_at)
            if remaining <= 0:
                raise BackupFailure('Se agoto el plazo de bloqueo')
            process = spawn(command(database, socket, output, statement_seconds), remaining,
                            stdout=subprocess.DEVNULL, stderr=errors)
            try:
                process.wait(timeout=remaining + 0.3)
            except subprocess.TimeoutExpired:
                stop_dump(process)
                raise BackupFailure('Se agoto el plazo; la copia no es valida') from None
            if process.returncode != 0 or holder.poll() is not None:
                raise BackupFailure('El volcado o su bloqueo no terminaron correctamente')
            if counts is not None:
                for table in holder.vigilante_tables:
                    holder.stdin.write('SELECT COUNT(*) FROM `' + database + '`.`' + table + '`;\n')
                    holder.stdin.flush()
                    remaining = max(0, seconds - (time.monotonic() - locked_at))
                    if not select.select([holder.stdout], [], [], remaining)[0]:
                        raise BackupFailure('No se pudo acreditar el recuento bajo bloqueo')
                    value = holder.stdout.readline().strip()
                    if not value.isdecimal():raise BackupFailure('Recuento no valido')
                    counts[table] = int(value)
                if holder.poll() is not None or time.monotonic() - locked_at >= seconds:
                    raise BackupFailure('El plazo caduco durante la comprobacion')
    elapsed = time.monotonic() - start
    os.chmod(output, 0o600)
    with output.open('rb') as stream:
        stream.seek(max(0, output.stat().st_size - 4096))
        if b'-- Dump completed on ' not in stream.read():
            raise BackupFailure('Falta el cierre del volcado')
    return elapsed


def query(sql):
    p = subprocess.run(['/usr/bin/mysql', '--no-defaults', '--protocol=socket',
        '--socket=' + SOCKET, '--user=root', '--batch', '--skip-column-names', 'dbispconfig'],
        input=sql, text=True, capture_output=True, timeout=5)
    if p.returncode:
        raise BackupFailure('No se pudo contrastar el catalogo del panel')
    return p.stdout.strip()


def main(plan_path):
    os.umask(0o077)
    plan_path = Path(plan_path)
    if os.geteuid() != 0 or plan_path.is_symlink():
        raise PermissionError('Plan de administrador requerido')
    for p in [plan_path, plan_path.parent]:
        if p.stat().st_uid != 0 or p.stat().st_mode & 0o077:
            raise PermissionError('Plan no privado')
    plan = json.loads(plan_path.read_text())
    if plan.get('version') != 1 or not 1 <= len(plan['databases']) <= 2:
        raise ValueError('Plan no valido')
    result_path = plan_path.parent / 'sql-result.json'
    with result_path.open('x') as out:
        json.dump({'completed': False}, out)
    result = {'version': 1, 'completed': False, 'databases': []}
    dest = Path('/var/backup/vigilante-independent-sql')
    dest.mkdir(mode=0o700, exist_ok=True)
    if dest.is_symlink() or dest.stat().st_uid != 0 or dest.stat().st_mode & 0o077:
        raise ValueError('Destino no privado')
    try:
        for item in plan['databases']:
            name, ident = item['name'], item['id']
            if not re.fullmatch(r'c1_[a-z0-9_]+', name) or type(ident) is not int or ident < 1:
                raise ValueError('Base no permitida')
            q = f"SELECT COUNT(*) FROM web_database WHERE database_id={ident} AND database_name='{name}' AND active='y' AND parent_domain_id=0 AND server_id=1;"
            if query(q) != '1':
                raise ValueError('La base independiente ha cambiado')
            raw = plan_path.parent / (name + '.sql')
            counts = {}
            elapsed = dump(name, raw, counts=counts)
            # Compresion despues de cerrar la conexion y sus bloqueos.
            if raw.stat().st_size > MAX_RAW_BYTES:
                raise BackupFailure('La base independiente excede su presupuesto revisado')
            data = raw.read_bytes()
            decoded = data.decode('utf-8-sig')
            if re.search(r'[\x00-\x08\x0b\x0e-\x1f\x7f-\x9f]', decoded):
                raise BackupFailure('El volcado requiere revisar su codificacion')
            filename = 'db_' + name + '_' + time.strftime('%Y-%m-%d_%H-%M-%S') + '.sql.gz'
            target = dest / filename
            with target.open('xb') as stream:
                os.fchmod(stream.fileno(), 0o600)
                with gzip.GzipFile(fileobj=stream, mode='wb', compresslevel=1) as compressed:
                    compressed.write(data)
                stream.flush()
                os.fsync(stream.fileno())
            with gzip.open(target, 'rb') as stream:
                if stream.read() != data:
                    raise BackupFailure('La copia no coincide con el volcado')
            result['databases'].append({'database_id': ident, 'name': name, 'filename': filename,
                'dump_seconds': round(elapsed, 4), 'sql_bytes': len(data),
                'gzip_bytes': target.stat().st_size, 'sql_sha256': hashlib.sha256(data).hexdigest(),
                'gzip_sha256': hashlib.sha256(target.read_bytes()).hexdigest(),
                'row_counts_under_lock': counts,
                'strict_read_guard': True, 'global_read_lock_used': False, 'verified': True})
            raw.unlink()  # Solo el temporal propio, una vez verificado su sustituto.
        result['completed'] = True
    finally:
        with result_path.open('w') as out:
            json.dump(result, out, indent=2)
            out.write('\n')
    print(json.dumps(result))


if __name__ == '__main__':
    import sys
    if len(sys.argv) != 3 or sys.argv[1] != '--apply':
        raise SystemExit(2)
    main(sys.argv[2])
