#!/usr/bin/env python3
"""Aplicacion unica y acotada de una recuperacion previamente contrastada."""
import fcntl
import gzip
import hashlib
import json
import os
from pathlib import Path
import pwd
import stat
import subprocess
import time

CASE = Path('/root/codex-vigilante/tramo-30-2026-10-20')
LIVE = Path('/var/lib/mysql/c1_deportes')
STAGE = 'usuarios_vigilante_20261020'
COLUMNS = ['id', 'aid', 'correo', 'nombre', 'slug', 'imagenes', 'creado', 'actualizado', 'leido']
VALUES = 'SELECT ' + ','.join('IF(`'+c+'` IS NULL,\'NULL\',CONCAT(\'HEX:\',HEX(`'+c+'`)))' for c in COLUMNS) + ' FROM {table} ORDER BY id;'


def sha(data):
    return hashlib.sha256(data).hexdigest()


def main():
    if os.geteuid() != 0:
        raise PermissionError('Solo administrador')
    os.umask(0o077)
    report = json.loads((CASE / 'isolated-report.json').read_text())
    if not all(report.get(k) is True for k in ['completed', 'original_page_checksums_verified',
               'second_restore_identical', 'orphan_1932_reproduced', 'preserving_replacement_verified']):
        raise RuntimeError('Falta acreditacion aislada')
    exported = gzip.decompress((CASE / 'recovered.sql.gz').read_bytes())
    if sha(exported) != report['sql_sha256']:
        raise RuntimeError('La recuperacion SQL ha cambiado')
    status = {'completed': False, 'originals_preserved': False, 'services_restarted': False}
    moved = []
    held = CASE / 'originals'
    with open('/run/lock/vigilante-restarts.lock', 'a') as central, \
         open('/var/lib/vigilante-independent-sql/operations.lock', 'a') as backups, \
         (CASE / 'apply-private-errors.log').open('xb') as errors:
        fcntl.flock(central, fcntl.LOCK_EX | fcntl.LOCK_NB)
        fcntl.flock(backups, fcntl.LOCK_EX | fcntl.LOCK_NB)

        def sql(query, check=True):
            p = subprocess.run(['/usr/bin/mariadb', '--no-defaults', '--protocol=socket',
                '--socket=/run/mysqld/mysqld.sock', '--user=root', '--batch', '--raw',
                '--skip-column-names', 'c1_deportes'],
                input=('SET SESSION lock_wait_timeout=2; SET SESSION max_statement_time=3;\n'+query).encode(),
                stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=8)
            if p.returncode and check:
                errors.write(p.stderr);errors.flush()
                raise RuntimeError('Fallo SQL; consultar registro privado')
            return p

        try:
            status['pid_before'] = subprocess.check_output(['systemctl', 'show', 'mariadb', '-p', 'MainPID', '--value'], text=True).strip()
            if sql("SELECT NAME FROM information_schema.INNODB_SYS_TABLES WHERE NAME LIKE 'c1_deportes/%';").stdout.strip():
                raise RuntimeError('Existe registro InnoDB; detener recuperacion')
            if sql('SHOW TABLES;').stdout.strip() != b'usuarios':
                raise RuntimeError('El inventario de tablas ha cambiado')
            probe = sql('SHOW CREATE TABLE usuarios;', False)
            if probe.returncode == 0 or b'1932' not in probe.stderr:
                raise RuntimeError('La incidencia original ha cambiado')
            for name, expected in report['input_sha256'].items():
                if sha((LIVE / name).read_bytes()) != expected:
                    raise RuntimeError('Los originales han cambiado')
            status['originals_preserved'] = True
            if held.exists():
                raise RuntimeError('Ya existe una conservacion de originales')
            directory = LIVE.stat()
            if directory.st_uid != pwd.getpwnam('mysql').pw_uid or stat.S_IMODE(directory.st_mode) != 0o555:
                raise RuntimeError('Los permisos del directorio han cambiado')
            status['directory_mode_before'] = '0555'
            LIVE.chmod(0o750)
            status['directory_mode_after'] = '0750'
            sql(exported.replace(b'`usuarios`', ('`'+STAGE+'`').encode()).decode('utf-8'))
            if sha(sql(VALUES.format(table=STAGE)).stdout) != report['all_values_sha256']:
                raise RuntimeError('Los valores preparados difieren')
            structure = sql('SHOW CREATE TABLE '+STAGE+';').stdout.replace(STAGE.encode(), b'usuarios')
            if sha(structure) != report['schema_sha256']:
                raise RuntimeError('La estructura preparada difiere')
            if not sql('CHECK TABLE '+STAGE+' EXTENDED;').stdout.strip().endswith(b'\tstatus\tOK'):
                raise RuntimeError('La tabla preparada no supera la comprobacion')
            sql('FLUSH TABLES usuarios;')
            held.mkdir(mode=0o700)
            started = time.monotonic()
            for name, expected in report['input_sha256'].items():
                if sha((LIVE / name).read_bytes()) != expected:
                    raise RuntimeError('Cambio concurrente en originales')
                (LIVE / name).rename(held / name);moved.append(name)
            sql('RENAME TABLE '+STAGE+' TO usuarios;')
            status['replacement_seconds'] = round(time.monotonic() - started, 4)
            if sha(sql(VALUES.format(table='usuarios')).stdout) != report['all_values_sha256']:
                raise RuntimeError('Los valores finales difieren')
            if sha(sql('SHOW CREATE TABLE usuarios;').stdout) != report['schema_sha256']:
                raise RuntimeError('La estructura final difiere')
            if not sql('CHECK TABLE usuarios EXTENDED;').stdout.strip().endswith(b'\tstatus\tOK'):
                raise RuntimeError('La tabla final no supera la comprobacion')
            status['originals_preserved'] = all(sha((held / n).read_bytes()) == h for n, h in report['input_sha256'].items())
            status['rows'] = int(sql('SELECT COUNT(*) FROM usuarios;').stdout)
            status['pid_after'] = subprocess.check_output(['systemctl', 'show', 'mariadb', '-p', 'MainPID', '--value'], text=True).strip()
            if status['pid_before'] != status['pid_after'] or not status['originals_preserved']:
                raise RuntimeError('Fallo de continuidad o conservacion')
            status['completed'] = True
        finally:
            # Si el cambio de nombre no se completo, devolver solamente los
            # originales que este proceso movio. Nunca sobrescribir un archivo.
            if not status['completed']:
                for name in moved:
                    if not (LIVE / name).exists():
                        (held / name).rename(LIVE / name)
            (CASE / 'apply-report.json').write_text(json.dumps(status, indent=2)+'\n')
    print(json.dumps(status))


if __name__ == '__main__':
    main()
