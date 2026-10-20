#!/usr/bin/env python3
"""Ensayo manual de un volcado en MariaDB desechable, dentro de su unidad aislada."""
import gzip
import hashlib
import json
import os
from pathlib import Path
import subprocess
import time

STATE = Path('/var/lib/vigilante-restore-drill')
RUNTIME = Path('/run/vigilante-restore-drill')
INPUT = RUNTIME / 'input.sql.gz'
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


def main():
    os.umask(0o077)
    if os.geteuid() == 0:
        raise RuntimeError('El ensayo nunca se ejecuta como administrador')
    # Las rutas reales quedan ocultas por systemd; no basta con no utilizarlas.
    for protected in ['/run/mysqld/mysqld.sock', '/var/lib/mysql/ibdata1',
                      '/var/www/clients', '/var/lib/vigilante-auth/auth.json']:
        if os.access(protected, os.R_OK):
            raise RuntimeError('El aislamiento no oculta una ruta protegida')
    sql = STATE / 'restored.sql'
    data = STATE / 'database'
    data.mkdir(mode=0o700)  # Un ensayo previo exige revision, nunca se sobrescribe.
    report = {'version': 1, 'started_at': int(time.time()), 'uid': os.geteuid(),
              'completed': False, 'network_enabled': False, 'live_paths_readable': False}
    server = None
    with (STATE / 'private-errors.log').open('xb') as errors:
        def run(args, **kwargs):
            return subprocess.run(args, check=True, stderr=errors, timeout=180, **kwargs)
        client = ['/usr/bin/mariadb', '--no-defaults',
                  '--socket=' + str(RUNTIME / 'db.sock'), '--user=root', '--batch',
                  '--skip-column-names', '--binary-mode']
        try:
            report.update(unpack(INPUT, sql))
            run(['/usr/bin/mariadb-install-db', '--no-defaults', '--datadir=' + str(data),
                 '--auth-root-authentication-method=normal', '--skip-test-db'], stdout=errors)
            secure = STATE / 'file-access'
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
            run(client + ['-e', 'CREATE DATABASE vigilante_restore'], stdout=errors)
            with sql.open('rb') as src:
                run(client + ['vigilante_restore'], stdin=src, stdout=errors)
            counts = run(client + ['-e', "SELECT COUNT(*) FROM information_schema.tables "
                         "WHERE table_schema='vigilante_restore' AND table_type='BASE TABLE'"],
                         stdout=subprocess.PIPE).stdout
            report['tables'] = int(counts.strip())
            if report['tables'] < 1:
                raise RuntimeError('No se restauraron tablas')
            run(['/usr/bin/mariadb-check', '--no-defaults',
                 '--socket=' + str(RUNTIME / 'db.sock'), '--user=root',
                 '--check', 'vigilante_restore'], stdout=errors)
            report['completed'] = True
        finally:
            if server is not None and server.poll() is None:
                server.terminate()
                server.wait(timeout=30)
            report['finished_at'] = int(time.time())
            (STATE / 'report.json').write_text(json.dumps(report, indent=2) + '\n')
    print(json.dumps(report))


if __name__ == '__main__':
    main()
