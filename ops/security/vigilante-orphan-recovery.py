#!/usr/bin/env python3
"""Recupera una copia de c1_deportes.usuarios, exclusivamente en aislamiento."""
import gzip
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import time

BASE = Path('/run/vigilante-orphan-recovery')
STATE = Path('/var/lib/vigilante-orphan-recovery')
INPUT = Path(os.environ['CREDENTIALS_DIRECTORY'])
EXPECTED = {
    'usuarios.frm': 'f6aea7371a6175278a39ed71dc3f673070cc863824887ab9d70d1a1da6017f8f',
    'usuarios.ibd': '629faa0407207f48c772b4e101f2a726a7c2f95b4c772d11ed9ab28d1413c3f7',
}
# Extraido del .frm original, no de datos locales. Dbsake revision
# 48e6d429bc8de2cfea5e56651f87d2e94927c775. Se conserva la estructura 10.1.
DDL = """CREATE TABLE usuarios (
 id int(11) NOT NULL AUTO_INCREMENT,
 aid varchar(255) DEFAULT NULL,
 correo varchar(255) DEFAULT NULL,
 nombre varchar(255) DEFAULT NULL,
 slug varchar(255) DEFAULT NULL,
 imagenes text DEFAULT NULL,
 creado datetime DEFAULT NULL,
 actualizado datetime DEFAULT NULL,
 leido datetime DEFAULT NULL,
 PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
ROW_FORMAT=COMPACT;"""


def digest(value):
    return hashlib.sha256(value).hexdigest()


def main():
    if os.geteuid() == 0 or os.access('/run/mysqld/mysqld.sock', os.R_OK):
        raise RuntimeError('Falta aislamiento')
    os.umask(0o077)
    result = {'completed': False, 'uid': os.geteuid(), 'production_modified': False}
    data = BASE / 'database'
    data.mkdir(mode=0o700)
    socket = BASE / 'db.sock'
    client = ['/usr/bin/mariadb', '--no-defaults', '--socket=' + str(socket),
              '--user=root', '--batch', '--raw', '--skip-column-names']
    server = None
    with (STATE / 'private-errors.log').open('w') as errors:
        def sql(query, database='recovered'):
            p = subprocess.run(client + [database], input=query.encode(),
                               stdout=subprocess.PIPE, stderr=errors, timeout=15)
            if p.returncode:
                raise RuntimeError('Fallo SQL aislado; consultar registro privado')
            return p.stdout

        def dump(database):
            p = subprocess.run(['/usr/bin/mariadb-dump', '--no-defaults',
                '--socket=' + str(socket), '--user=root', '--skip-comments',
                '--skip-dump-date', '--skip-add-locks', '--skip-lock-tables',
                '--order-by-primary', '--hex-blob', '--default-character-set=utf8mb4',
                database, 'usuarios'], stdout=subprocess.PIPE, stderr=errors,
                timeout=15, check=True)
            p.stdout.decode('utf-8', errors='strict')
            return p.stdout

        try:
            result['input_sha256'] = {name: digest((INPUT / name).read_bytes()) for name in EXPECTED}
            if result['input_sha256'] != EXPECTED:
                raise RuntimeError('Los archivos no corresponden a los originales acreditados')
            subprocess.run(['/usr/bin/innochecksum', str(INPUT / 'usuarios.ibd')],
                           stdout=errors, stderr=errors, timeout=10, check=True)
            result['original_page_checksums_verified'] = True
            subprocess.run(['/usr/bin/mariadb-install-db', '--no-defaults',
                '--datadir=' + str(data), '--auth-root-authentication-method=normal',
                '--skip-test-db'], stdout=errors, stderr=errors, check=True, timeout=60)
            server = subprocess.Popen(['/usr/sbin/mariadbd', '--no-defaults',
                '--datadir=' + str(data), '--socket=' + str(socket),
                '--pid-file=' + str(BASE / 'db.pid'), '--skip-networking',
                '--skip-log-bin', '--local-infile=0', '--skip-symbolic-links',
                '--innodb-buffer-pool-size=32M', '--performance-schema=OFF'],
                stdout=errors, stderr=errors)
            for _ in range(100):
                probe = subprocess.run(client + ['-e', 'SELECT 1'],
                                       stdout=subprocess.DEVNULL, stderr=errors)
                if probe.returncode == 0:
                    break
                time.sleep(0.1)
            else:
                raise RuntimeError('No arranco MariaDB aislado')
            sql('CREATE DATABASE recovered; CREATE DATABASE restored;', 'mysql')
            sql(DDL)
            sql('ALTER TABLE usuarios DISCARD TABLESPACE;')
            shutil.copyfile(INPUT / 'usuarios.ibd', data / 'recovered/usuarios.ibd')
            sql('ALTER TABLE usuarios IMPORT TABLESPACE;')
            check = sql('CHECK TABLE usuarios EXTENDED;').decode().strip()
            if not check.endswith('\tstatus\tOK'):
                raise RuntimeError('La tabla importada no supera CHECK TABLE')
            result['check_table'] = check
            result['rows'] = int(sql('SELECT COUNT(*) FROM usuarios;'))
            # Comparar todos los valores y NULL sin exponer datos personales.
            columns = ['id', 'aid', 'correo', 'nombre', 'slug', 'imagenes',
                       'creado', 'actualizado', 'leido']
            query = 'SELECT ' + ','.join('IF(`'+c+'` IS NULL,\'NULL\',CONCAT(\'HEX:\',HEX(`'+c+'`)))' for c in columns) + ' FROM usuarios ORDER BY id;'
            values = sql(query)
            structure = sql('SHOW CREATE TABLE usuarios;')
            exported = dump('recovered')
            sql(exported.decode('utf-8'), 'restored')
            if sql(query, 'restored') != values or sql('SHOW CREATE TABLE usuarios;', 'restored') != structure:
                raise RuntimeError('La segunda restauracion difiere en estructura o valores')
            if dump('restored') != exported:
                raise RuntimeError('Los volcados completos no coinciden')
            # Reproducir la sustitucion sin diccionario antes de permitirla
            # en produccion. Conservar los archivos simulados, sin DROP.
            sql('CREATE DATABASE orphan_sim;', 'mysql')
            for name in EXPECTED:
                shutil.copyfile(INPUT / name, data / 'orphan_sim' / name)
            probe = subprocess.run(client + ['orphan_sim', '-e', 'SHOW CREATE TABLE usuarios'],
                                   stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=5)
            if probe.returncode == 0 or b'1932' not in probe.stderr:
                raise RuntimeError('No se reprodujo el error 1932 en aislamiento')
            staged = exported.replace(b'`usuarios`', b'`usuarios_vigilante_20261020`')
            sql(staged.decode('utf-8'), 'orphan_sim')
            if sql(query.replace('FROM usuarios ', 'FROM usuarios_vigilante_20261020 '), 'orphan_sim') != values:
                raise RuntimeError('La tabla preparada difiere')
            sql('FLUSH TABLES usuarios;', 'orphan_sim')
            held = BASE / 'originals-simulated';held.mkdir(mode=0o700)
            for name in EXPECTED:
                (data / 'orphan_sim' / name).rename(held / name)
            sql('RENAME TABLE usuarios_vigilante_20261020 TO usuarios;', 'orphan_sim')
            if sql(query, 'orphan_sim') != values or sql('SHOW CREATE TABLE usuarios;', 'orphan_sim') != structure:
                raise RuntimeError('La sustitucion aislada difiere')
            result['orphan_1932_reproduced'] = True
            result['preserving_replacement_verified'] = True
            result['all_values_sha256'] = digest(values)
            result['sql_sha256'] = digest(exported)
            result['schema_sha256'] = digest(structure)
            result['second_restore_identical'] = True
            with gzip.open(STATE / 'recovered.sql.gz', 'wb') as stream:
                stream.write(exported)
            (STATE / 'schema.sql').write_bytes(structure)
            result['completed'] = True
        finally:
            if server is not None and server.poll() is None:
                server.terminate()
                server.wait(timeout=30)
            (STATE / 'report.json').write_text(json.dumps(result, indent=2) + '\n')
    print(json.dumps(result))


if __name__ == '__main__':
    main()
