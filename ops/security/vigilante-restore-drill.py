#!/usr/bin/env python3
"""Ensayo manual de un volcado en MariaDB desechable, dentro de su unidad aislada."""
import gzip
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import time

STATE = Path('/var/lib/vigilante-restore-drill')
RUNTIME = Path('/run/vigilante-restore-drill')
INPUT = RUNTIME / 'input.sql.gz'
WORK = STATE / 'work'
LIMIT = 1024 * 1024 * 1024


def unpack(source, target, limit=LIMIT):
    digest = hashlib.sha256()
    size = 0
    with gzip.open(source, 'rb') as src, target.open('xb') as dst:
        while chunk := src.read(1024 * 1024):
            size += len(chunk)
            if size > limit:
                raise ValueError('Volcado demasiado grande para este ensayo')
            digest.update(chunk)
            dst.write(chunk)
    return {'sql_bytes': size, 'sql_sha256': digest.hexdigest()}


def clean_work():
    """Solo retira el espacio desechable creado por este ejecutor."""
    if os.geteuid() == 0:
        raise RuntimeError('La limpieza tampoco se ejecuta como administrador')
    if not WORK.exists():
        return
    marker = WORK / '.vigilante-restore-work'
    if (WORK.is_symlink() or WORK.resolve() != STATE.resolve() / 'work'
            or marker.is_symlink() or not marker.is_file()
            or marker.read_text() != 'temporary isolated restore\n'
            or WORK.stat().st_uid != os.geteuid()):
        raise RuntimeError('Espacio de ensayo no reconocido; requiere revision')
    shutil.rmtree(WORK)  # rmtree no sigue enlaces encontrados dentro del directorio.


def main():
    os.umask(0o077)
    if os.geteuid() == 0:
        raise RuntimeError('El ensayo nunca se ejecuta como administrador')
    # Las rutas reales quedan ocultas por systemd; no basta con no utilizarlas.
    for protected in ['/run/mysqld/mysqld.sock', '/var/lib/mysql/ibdata1',
                      '/var/www/clients', '/var/lib/vigilante-auth/auth.json']:
        if os.access(protected, os.R_OK):
            raise RuntimeError('El aislamiento no oculta una ruta protegida')
    metadata_path = RUNTIME / 'input.json'
    metadata = json.loads(metadata_path.read_text()) if metadata_path.exists() else {}
    database = metadata.get('database', 'vigilante_restore')
    if not re.fullmatch(r'[a-z][a-z0-9_]{0,63}', database):
        raise ValueError('Nombre de base no permitido')
    if (STATE / 'report.json').exists() or (STATE / 'private-errors.log').exists():
        raise RuntimeError('El informe anterior debe conservarse antes de repetir')
    WORK.mkdir(mode=0o700)  # Un espacio previo nunca se sobrescribe.
    (WORK / '.vigilante-restore-work').write_text('temporary isolated restore\n')
    sql = WORK / 'restored.sql'
    data = WORK / 'database'
    data.mkdir(mode=0o700)
    report = {'version': 1, 'started_at': int(time.time()), 'uid': os.geteuid(),
              'completed': False, 'network_enabled': False, 'live_paths_readable': False,
              'database': database, 'temporary_work_cleanup': 'ExecStopPost'}
    server = None
    with (STATE / 'private-errors.log').open('xb') as errors:
        def run(args, timeout=180, **kwargs):
            return subprocess.run(args, check=True, stderr=errors, timeout=timeout, **kwargs)
        client = ['/usr/bin/mariadb', '--no-defaults',
                  '--socket=' + str(RUNTIME / 'db.sock'), '--user=root', '--batch',
                  '--skip-column-names', '--binary-mode']
        try:
            report.update(unpack(INPUT, sql))
            if metadata and any(report[k] != metadata[k] for k in ['sql_sha256', 'sql_bytes']):
                raise RuntimeError('El SQL no coincide con la evidencia seleccionada')
            run(['/usr/bin/mariadb-install-db', '--no-defaults', '--datadir=' + str(data),
                 '--auth-root-authentication-method=normal', '--skip-test-db'], stdout=errors)
            secure = WORK / 'file-access'
            secure.mkdir(mode=0o700)
            server = subprocess.Popen(['/usr/sbin/mariadbd', '--no-defaults',
                '--datadir=' + str(data), '--socket=' + str(RUNTIME / 'db.sock'),
                '--pid-file=' + str(RUNTIME / 'db.pid'), '--skip-networking',
                '--skip-log-bin', '--local-infile=0', '--skip-symbolic-links',
                '--secure-file-priv=' + str(secure), '--innodb-buffer-pool-size=64M',
                '--max-connections=4', '--performance-schema=OFF'], stdout=errors, stderr=errors)
            for _ in range(100):
                if server.poll() is not None:
                    raise RuntimeError('La instancia aislada no ha arrancado')
                probe = subprocess.run(client + ['-e', 'SELECT 1'], stdout=subprocess.DEVNULL,
                                       stderr=subprocess.DEVNULL, timeout=3)
                if probe.returncode == 0:
                    break
                time.sleep(0.2)
            else:
                raise RuntimeError('Se agoto la espera de la instancia aislada')
            run(client + ['-e', 'CREATE DATABASE `' + database + '`'], stdout=errors)
            with sql.open('rb') as src:
                run(client + [database], stdin=src, stdout=errors, timeout=480)
            counts = run(client + ['-e', "SELECT COUNT(*) FROM information_schema.tables "
                         "WHERE table_schema='" + database + "' AND table_type='BASE TABLE'"],
                         stdout=subprocess.PIPE).stdout
            report['tables'] = int(counts.strip())
            if report['tables'] < 1:
                raise RuntimeError('No se restauraron tablas')
            run(['/usr/bin/mariadb-check', '--no-defaults',
                 '--socket=' + str(RUNTIME / 'db.sock'), '--user=root',
                 '--check', database], stdout=errors)
            definers = run(client + ['-e', "SELECT DISTINCT DEFINER FROM information_schema.VIEWS "
                           "WHERE TABLE_SCHEMA='" + database + "'"],
                           stdout=subprocess.PIPE).stdout.decode().splitlines()
            if len(definers) > 32:
                raise RuntimeError('Demasiadas cuentas definidoras de vistas')
            for definer in definers:
                user, host = definer.rsplit('@', 1)
                if not re.fullmatch(r'[a-zA-Z0-9_]{1,80}', user) or not re.fullmatch(r'[a-zA-Z0-9.%:_-]{1,255}', host):
                    raise ValueError('Cuenta definidora requiere revision')
                account = "'" + user + "'@'" + host + "'"
                # Identidades nuevas solo en el servidor desechable: sin claves
                # de produccion, bloqueadas y con SELECT exclusivamente aqui.
                run(client + ['-e', 'CREATE USER IF NOT EXISTS ' + account + ' ACCOUNT LOCK; '
                              'GRANT SELECT ON `' + database + '`.* TO ' + account], stdout=errors)
            report['isolated_view_definers'] = len(definers)
            objects = run(client + ['-e', "SELECT TABLE_NAME,TABLE_TYPE FROM information_schema.tables "
                          "WHERE TABLE_SCHEMA='" + database + "' ORDER BY TABLE_NAME"],
                          stdout=subprocess.PIPE).stdout.decode().splitlines()
            report['row_counts'] = {}
            report['views'] = []
            for item in objects:
                table, kind = item.split('\t')
                quoted = '`' + table.replace('`', '``') + '`'
                if kind == 'VIEW':
                    run(client + [database, '-e', 'SELECT * FROM ' + quoted + ' LIMIT 0'], stdout=errors)
                    report['views'].append(table)
                else:
                    count = run(client + [database, '-e', 'SELECT COUNT(*) FROM ' + quoted],
                                stdout=subprocess.PIPE).stdout
                    report['row_counts'][table] = int(count.strip())
            if 'expected_rows' in metadata and report['row_counts'] != metadata['expected_rows']:
                raise RuntimeError('La restauracion no coincide con los recuentos bajo bloqueo')
            if metadata.get('bounded_redump') is True:
                spec = importlib.util.spec_from_file_location('bounded_backup',
                    '/usr/local/lib/vigilante/vigilante-sql-backup.py')
                backup = importlib.util.module_from_spec(spec)
                spec.loader.exec_module(backup)
                raw = WORK / 'bounded.sql'
                counts = {}
                report['bounded_redump'] = {'completed': False, 'limit_seconds': backup.MAX_SECONDS}
                try:
                    elapsed = backup.dump(database, raw, RUNTIME / 'db.sock', counts=counts)
                except backup.BackupFailure:
                    report['bounded_redump']['reason'] = 'bounded_dump_rejected'
                else:
                    if counts != report['row_counts'] or raw.stat().st_size > backup.MAX_RAW_BYTES:
                        raise RuntimeError('Copia acotada con recuento o tamano inesperado')
                    run(client + ['-e', 'CREATE DATABASE vigilante_roundtrip'], stdout=errors)
                    with raw.open('rb') as src:
                        run(client + ['vigilante_roundtrip'], stdin=src, stdout=errors)
                    for table, expected in counts.items():
                        count = run(client + ['vigilante_roundtrip', '-e',
                            'SELECT COUNT(*) FROM `' + table + '`'], stdout=subprocess.PIPE).stdout
                        if int(count.strip()) != expected:
                            raise RuntimeError('La segunda restauracion no coincide')
                    report['bounded_redump'].update(completed=True,
                        seconds=round(elapsed, 4), row_counts_under_lock=counts)
            if metadata.get('verify_sql_controls') is True:
                if report['views']:
                    raise RuntimeError('Comparacion de valores con vistas requiere revision')
                reference = RUNTIME / 'original.sql'
                original = reference.read_bytes()
                if hashlib.sha256(original).hexdigest() != metadata['source_sql_sha256']:
                    raise RuntimeError('Referencia original alterada')
                spec = importlib.util.spec_from_file_location('sqltext',
                    '/usr/local/lib/vigilante/vigilante-sql-text.py')
                sqltext = importlib.util.module_from_spec(spec)
                spec.loader.exec_module(sqltext)
                encoded, controls = sqltext.encode_controls(original)
                if encoded != sql.read_bytes() or controls != metadata['escaped_control_characters']:
                    raise RuntimeError('La representacion SQL no coincide con la referencia')
                run(client + ['-e', 'CREATE DATABASE vigilante_original'], stdout=errors)
                with reference.open('rb') as src:
                    run(client + ['vigilante_original'], stdin=src, stdout=errors)
                digests = []
                for name in ['vigilante_original', database]:
                    path = WORK / (name + '-values.sql')
                    with path.open('xb') as out:
                        run(['/usr/bin/mariadb-dump', '--no-defaults',
                            '--socket=' + str(RUNTIME / 'db.sock'), '--user=root',
                            '--default-character-set=utf8mb4', '--no-create-info',
                            '--skip-comments', '--skip-add-locks', '--skip-disable-keys',
                            '--skip-triggers', '--hex-blob', '--skip-extended-insert',
                            '--order-by-primary', name], stdout=out)
                    digests.append(hashlib.sha256(path.read_bytes()).hexdigest())
                if digests[0] != digests[1]:
                    raise RuntimeError('Los datos restaurados difieren de la referencia')
                report['sql_controls_verification'] = {
                    'original_and_encoded_data_dump_sha256': digests[0],
                    'all_data_equal': True, 'escaped_control_characters': controls}
            report['completed'] = True
        finally:
            if server is not None and server.poll() is None:
                server.terminate()
                server.wait(timeout=30)
            report['finished_at'] = int(time.time())
            (STATE / 'report.json').write_text(json.dumps(report, indent=2) + '\n')
    print(json.dumps(report))


if __name__ == '__main__':
    if sys.argv[1:] == ['--clean-work']:
        clean_work()
    elif not sys.argv[1:]:
        main()
    else:
        raise SystemExit(2)
