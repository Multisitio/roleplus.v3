#!/usr/bin/python3
"""Prueba conjunta con Apache y MariaDB privados; sin acceso a produccion."""
import importlib.util
import json
import os
from pathlib import Path
import subprocess
import time
from unittest.mock import patch

LIB = Path(__file__).resolve().parent


def load(name):
    spec = importlib.util.spec_from_file_location(name.replace('-', '_'), LIB / (name + '.py'))
    mod = importlib.util.module_from_spec(spec);spec.loader.exec_module(mod)
    return mod


runner = load('vigilante-maintenance-check')
connector = load('vigilante-sql-window')
runner.STATE = Path('/var/lib/vigilante-sql-window-check')


def integration(request, data_path, errors, report):
    assert os.geteuid() == 33 and not os.access('/run/mysqld/mysqld.sock', os.R_OK)
    run = runner.RUN
    data = run / 'database';data.mkdir(mode=0o700)
    socket = run / 'db.sock'
    client = ['/usr/bin/mysql', '--no-defaults', '--socket=' + str(socket), '--user=root',
              '--batch', '--skip-column-names']
    server = None

    def sql(statement, database=None):
        p = subprocess.run(client + ([database] if database else []), input=statement,
                           text=True, capture_output=True, timeout=5)
        if p.returncode:raise RuntimeError('Fallo SQL en el laboratorio')
        return p.stdout.strip()

    def laboratory_publish(value):
        # El publicador real exige root. En la unidad sin privilegios se
        # escribe solo el estado del laboratorio, conservando su formato
        # y sustitucion atomica. No se modifica el publicador de produccion.
        temporary=data_path.with_suffix('.new')
        temporary.write_text(json.dumps(value));temporary.chmod(0o644)
        os.replace(temporary,data_path)

    def http(host, path, method='GET'):
        code, headers, body = request(path, method, host=host)
        return code, {k.lower():v for k,v in headers.items()}, body

    connector.backup.CHILD = str(LIB / 'vigilante-sql-child.py')
    connector.LOCK = run / 'coordinator.lock'
    connector.maintenance.STATE = data_path
    try:
        subprocess.run(['/usr/bin/mariadb-install-db', '--no-defaults', '--datadir=' + str(data),
                        '--auth-root-authentication-method=normal', '--skip-test-db'],
                       stdout=errors, stderr=errors, check=True, timeout=60)
        server = subprocess.Popen(['/usr/sbin/mariadbd', '--no-defaults', '--datadir=' + str(data),
            '--socket=' + str(socket), '--pid-file=' + str(run / 'db.pid'), '--skip-networking',
            '--skip-log-bin', '--local-infile=0', '--skip-symbolic-links',
            '--innodb-buffer-pool-size=32M', '--performance-schema=OFF'], stdout=errors, stderr=errors)
        for _ in range(100):
            if socket.exists():
                try:
                    if sql('SELECT 1') == '1':break
                except RuntimeError:pass
            time.sleep(.1)
        else:raise RuntimeError('No arranco MariaDB privado')
        sql("CREATE DATABASE c1_roleplus; CREATE TABLE c1_roleplus.t(id INT PRIMARY KEY,value TEXT) ENGINE=MyISAM;"
            "INSERT INTO c1_roleplus.t VALUES(1,'texto'); CREATE TABLE c1_roleplus.i(id INT) ENGINE=InnoDB;"
            "INSERT INTO c1_roleplus.i VALUES(2);")
        with patch.object(connector.maintenance, 'publish', laboratory_publish), patch.object(connector, 'request', http), patch.object(connector, 'night_window', return_value=True):
            raw = run / 'normal.sql';counts={}
            connector.dump('c1_roleplus', raw, ['roleplus.app'], 15, counts, socket)
            assert counts == {'i':1,'t':1} and json.loads(data_path.read_text()) == {}
            sql('CREATE DATABASE restored')
            p = subprocess.run(client + ['restored'], input=raw.read_bytes(), stdout=errors,
                               stderr=errors, timeout=10, check=True)
            assert sql('SELECT value FROM t', 'restored') == 'texto'
            report['checks'].append('notice_http_barrier_read_lock_dump_restore_and_clear')
            raw.unlink()
            sql('CREATE DATABASE c1_mtgsearch; CREATE TABLE c1_mtgsearch.t(id INT) ENGINE=MyISAM; INSERT INTO c1_mtgsearch.t VALUES(1);')
            counts={};raw=run/'mtg.sql'
            connector.dump('c1_mtgsearch',raw,['mtgsearch.kumbia.app'],45,counts,socket)
            assert counts == {'t':1} and json.loads(data_path.read_text()) == {}
            raw.unlink()
            report['checks'].append('second_large_database_45_second_limit_path')

            def broken_notice(host, path, method='GET'):
                code, headers, body = http(host,path,method)
                if '?status=1' in path:body=b'{"active":false}'
                return code,headers,body

            for label, replacement in [('missing_notice',broken_notice),
                    ('missing_barrier',lambda h,p,m='GET': (200,{},b'') if m=='POST' else http(h,p,m))]:
                with patch.object(connector,'request',replacement), patch.object(connector.backup,'dump') as dump:
                    try:connector.dump('c1_roleplus',run/(label+'.sql'),['roleplus.app'],15,socket=socket)
                    except connector.backup.BackupFailure:pass
                    else:raise AssertionError('Debio rechazar '+label)
                    dump.assert_not_called()
                assert json.loads(data_path.read_text()) == {}
                report['checks'].append(label+'_aborts_before_sql_and_clears')

            original = connector.backup.strict_read
            def expire_before_read(*args,**kwargs):
                value=json.loads(data_path.read_text());value['monotonic_until']=time.monotonic()-1
                data_path.write_text(json.dumps(value));data_path.chmod(0o644)
                return original(*args,**kwargs)
            with patch.object(connector.backup,'strict_read',expire_before_read):
                try:connector.dump('c1_roleplus',run/'expired.sql',['roleplus.app'],15,socket=socket)
                except connector.backup.BackupFailure:pass
                else:raise AssertionError('Una ventana caducada no permite READ')
            assert sql("INSERT INTO t VALUES(3,'despues'); SELECT COUNT(*) FROM t",'c1_roleplus')=='2'
            assert json.loads(data_path.read_text()) == {}
            report['checks'].append('expiry_before_read_aborts_and_writes_remain_available')
            try:
                with connector.window(['roleplus.app'],15):raise RuntimeError('fallo simulado')
            except RuntimeError:pass
            assert json.loads(data_path.read_text()) == {}
            report['checks'].append('exception_clears_own_window')
    finally:
        if server is not None and server.poll() is None:server.terminate();server.wait(timeout=25)
        # Solo la instancia de laboratorio, dentro del RuntimeDirectory privado.
        import shutil
        assert data.resolve().parent == run.resolve() and not data.is_symlink()
        shutil.rmtree(data)
        for raw in run.glob('*.sql'):raw.unlink()


if __name__ == '__main__':
    runner.EXTRA_CHECKS = integration
    runner.main()
