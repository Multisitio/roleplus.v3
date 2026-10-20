#!/usr/bin/env python3
"""Prueba real de bloqueos en MariaDB aislado; nunca usa el socket de produccion."""
import gzip
import importlib.util
import json
import os
from pathlib import Path
import subprocess
import signal
import time

BASE = Path('/run/vigilante-sql-check')
STATE = Path('/var/lib/vigilante-sql-check')


def main():
    if os.geteuid() == 0 or os.access('/run/mysqld/mysqld.sock', os.R_OK):
        raise RuntimeError('Falta aislamiento')
    os.umask(0o077)
    spec = importlib.util.spec_from_file_location('backup', '/usr/local/lib/vigilante/vigilante-sql-backup.py')
    backup = importlib.util.module_from_spec(spec);spec.loader.exec_module(backup)
    data = BASE / 'database';data.mkdir(mode=0o700)
    socket = BASE / 'db.sock'
    client = ['/usr/bin/mysql', '--no-defaults', '--socket=' + str(socket), '--user=root',
              '--batch', '--skip-column-names', '--unbuffered']
    result = {'completed': False, 'uid': os.geteuid(), 'network_enabled': False}
    server = None
    with (STATE / 'private-errors.log').open('w') as errors:
        def run(sql, database=None):
            p = subprocess.run(client + ([database] if database else []), input=sql,
                text=True, stdout=subprocess.PIPE, stderr=errors, timeout=10)
            if p.returncode:raise RuntimeError('Fallo de SQL en la instancia de ensayo')
            return p.stdout.strip()
        try:
            subprocess.run(['/usr/bin/mariadb-install-db', '--no-defaults', '--datadir=' + str(data),
                '--auth-root-authentication-method=normal', '--skip-test-db'], stdout=errors, stderr=errors,
                check=True, timeout=90)
            server = subprocess.Popen(['/usr/sbin/mariadbd', '--no-defaults', '--datadir=' + str(data),
                '--socket=' + str(socket), '--pid-file=' + str(BASE / 'db.pid'), '--skip-networking',
                '--skip-log-bin', '--local-infile=0', '--skip-symbolic-links',
                '--innodb-buffer-pool-size=32M', '--performance-schema=OFF'], stdout=errors, stderr=errors)
            for _ in range(100):
                if socket.exists():
                    probe = subprocess.run(client + ['-e', 'SELECT 1'], stdout=subprocess.DEVNULL, stderr=errors)
                    if probe.returncode == 0:break
                time.sleep(0.1)
            else:raise RuntimeError('No arranco MariaDB aislado')
            run("CREATE DATABASE fixture; CREATE TABLE fixture.t(id INT PRIMARY KEY, value TEXT) ENGINE=MyISAM;"
                "INSERT INTO fixture.t SELECT seq,REPEAT('x',1024) FROM fixture.seq_1_to_256;"
                "CREATE TABLE fixture.marker(id INT) ENGINE=InnoDB; INSERT INTO fixture.marker VALUES(1);")
            locker = subprocess.Popen(client + ['fixture'], stdin=subprocess.PIPE, stdout=subprocess.PIPE,
                                      stderr=errors, text=True)
            try:
                locker.stdin.write("LOCK TABLES t WRITE; SELECT 'locked';\n");locker.stdin.flush()
                import select
                if not select.select([locker.stdout], [], [], 5)[0] or locker.stdout.readline().strip() != 'locked':
                    raise RuntimeError('No se acredito el bloqueo de ensayo')
                start = time.monotonic()
                try:backup.dump('fixture', BASE / 'blocked.sql', socket, seconds=0.25)
                except backup.BackupFailure:pass
                else:raise RuntimeError('El volcado bloqueado debio fallar')
                result['lock_wait_aborted_seconds'] = round(time.monotonic() - start, 4)
                if result['lock_wait_aborted_seconds'] > 1.5:raise RuntimeError('Espera excesiva')
            finally:
                locker.stdin.close();locker.wait(timeout=5)
            # Forzar salida bloqueada: el cliente mantiene READ mientras no se consume el tubo.
            writer = None
            with backup.strict_read('fixture', socket, 1.5, errors) as (holder, _):
                dumper = backup.spawn(backup.command('fixture', socket), 1.5,
                                      stdout=subprocess.PIPE, stderr=errors)
                try:
                    writer = subprocess.Popen(client + ['fixture', '-e', "INSERT INTO t VALUES(9999,'after')"],
                        stdout=subprocess.DEVNULL, stderr=errors)
                    time.sleep(0.1)
                    if writer.poll() is not None:raise RuntimeError('La escritura debio estar esperando')
                    start = time.monotonic();backup.stop_dump(dumper)
                finally:
                    backup.stop_dump(dumper);dumper.stdout.close()
            writer.wait(timeout=2)
            if writer.returncode != 0:raise RuntimeError('La escritura no se recupero')
            result['write_resumed_seconds'] = round(time.monotonic() - start, 4)
            # El limite debe sobrevivir incluso a un controlador muerto o suspendido.
            import select
            for mode in ['killed', 'stopped']:
                supervisor = subprocess.Popen(['/usr/bin/python3', '-I', '-B', __file__, '--hold-fixture'],
                    stdout=subprocess.PIPE, stderr=errors, text=True)
                pending = None
                try:
                    if not select.select([supervisor.stdout], [], [], 5)[0] or supervisor.stdout.readline().strip() != 'ready':
                        raise RuntimeError('No arranco el controlador de ensayo')
                    pending = subprocess.Popen(client + ['fixture', '-e', 'UPDATE marker SET id=id'],
                        stdout=subprocess.DEVNULL, stderr=errors)
                    time.sleep(0.05)
                    if pending.poll() is not None:raise RuntimeError('Falta el bloqueo del controlador')
                    start = time.monotonic()
                    os.kill(supervisor.pid, signal.SIGKILL if mode == 'killed' else signal.SIGSTOP)
                    pending.wait(timeout=2)
                    if pending.returncode:raise RuntimeError('No se libero la escritura')
                    result['controller_' + mode + '_release_seconds'] = round(time.monotonic() - start, 4)
                finally:
                    if supervisor.poll() is None:
                        os.kill(supervisor.pid, signal.SIGCONT);supervisor.kill()
                    supervisor.wait(timeout=3);supervisor.stdout.close()
                    if pending is not None and pending.poll() is None:pending.kill();pending.wait()
            raw = BASE / 'fixture.sql';result['normal_dump_seconds'] = round(backup.dump('fixture', raw, socket), 4)
            run('CREATE DATABASE restored;')
            with raw.open('rb') as stream:
                subprocess.run(client + ['restored'], stdin=stream, stdout=errors, stderr=errors, check=True, timeout=15)
            if run('SELECT COUNT(*) FROM t;', 'restored') != '257':raise RuntimeError('Restauracion incompleta')
            result['mixed_engine_restore_verified'] = True
            result['production_restores'] = []
            inputs = json.loads((BASE / 'inputs.json').read_text()) if (BASE / 'inputs.json').exists() else []
            if len(inputs) > 2:raise RuntimeError('Demasiadas entradas de ensayo')
            import re
            for item in inputs:
                number, name = item['number'], item['name']
                if number not in (1, 2) or not re.fullmatch(r'c1_[a-z0-9_]+', name):
                    raise RuntimeError('Entrada no valida')
                path = BASE / ('input' + str(number) + '.sql.gz')
                if not path.exists():continue
                run('CREATE DATABASE ' + name + ';')
                with gzip.open(path, 'rb') as stream:
                    sql = stream.read()
                subprocess.run(client + [name], input=sql, stdout=errors, stderr=errors, check=True, timeout=15)
                count = int(run("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='" + name + "';"))
                if count != 1:raise RuntimeError('Numero de tablas restauradas inesperado')
                rows = {}
                for table in run('SHOW TABLES;', name).splitlines():
                    if not table.replace('_', '').isalnum():raise RuntimeError('Identificador no valido')
                    rows[table] = int(run('SELECT COUNT(*) FROM `' + table + '`;', name))
                if rows != item['expected_rows']:raise RuntimeError('Los recuentos recuperados no coinciden')
                result['production_restores'].append({'name': name, 'tables': count, 'row_counts': rows})
            result['completed'] = True
        finally:
            if server is not None and server.poll() is None:
                server.terminate();server.wait(timeout=30)
            (STATE / 'report.json').write_text(json.dumps(result, indent=2) + '\n')
    print(json.dumps(result))


if __name__ == '__main__':
    import sys
    if len(sys.argv) == 2 and sys.argv[1] == '--hold-fixture':
        if os.geteuid() == 0:raise SystemExit(2)
        spec = importlib.util.spec_from_file_location('backup', '/usr/local/lib/vigilante/vigilante-sql-backup.py')
        module = importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
        with open(os.devnull, 'w') as errors:
            with module.strict_read('fixture', BASE / 'db.sock', 0.6, errors):
                print('ready', flush=True);time.sleep(20)
    else:
        main()
