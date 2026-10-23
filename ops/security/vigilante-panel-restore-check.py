#!/usr/bin/python3
"""Restaura una copia del panel en MariaDB aislado, sin red ni SQL de produccion."""
import gzip
import hashlib
import json
import os
from pathlib import Path
import subprocess
import time

STATE=Path('/var/lib/vigilante-panel-check')
RUN=Path('/run/vigilante-panel-check')


def main():
    if os.geteuid()!=33:raise PermissionError('Prueba sin privilegios requerida')
    source=STATE/'input.sql.gz';data=RUN/'database';data.mkdir(mode=0o700)
    socket=RUN/'db.sock';server=None
    client=['/usr/bin/mysql','--no-defaults','--socket='+str(socket),'--user=root','-NB']
    with (RUN/'errors.txt').open('wb') as errors:
        try:
            subprocess.run(['/usr/bin/mariadb-install-db','--no-defaults','--datadir='+str(data),
                '--auth-root-authentication-method=normal','--skip-test-db'],stdout=errors,stderr=errors,check=True,timeout=50)
            server=subprocess.Popen(['/usr/sbin/mariadbd','--no-defaults','--datadir='+str(data),
                '--socket='+str(socket),'--pid-file='+str(RUN/'db.pid'),'--skip-networking','--skip-log-bin',
                '--local-infile=0','--skip-symbolic-links','--innodb-buffer-pool-size=32M',
                '--performance-schema=OFF'],stdout=errors,stderr=errors)
            for _ in range(100):
                p=subprocess.run(client+['-e','SELECT 1'],capture_output=True,timeout=2)
                if p.returncode==0:break
                time.sleep(.1)
            else:raise RuntimeError('No arranco SQL privado')
            subprocess.run(client+['-e','CREATE DATABASE recovered'],stdout=errors,stderr=errors,check=True,timeout=3)
            raw=gzip.decompress(source.read_bytes())
            p=subprocess.run(client+['recovered'],input=raw,stdout=errors,stderr=errors,timeout=25)
            if p.returncode:raise RuntimeError('No se restaura la copia')
            query="SELECT TABLE_NAME,COALESCE(ENGINE,'NULL') FROM information_schema.TABLES WHERE TABLE_SCHEMA='recovered' ORDER BY TABLE_NAME"
            rows=subprocess.check_output(client+['-e',query],text=True,timeout=5).splitlines()
            if len(rows)!=79 or any(not row.endswith('\tInnoDB') for row in rows):
                raise ValueError('Esquema recuperado inesperado')
            # Lee cada tabla restaurada, sin exponer su contenido ni compararlo con filas vivas cambiantes.
            for row in rows:
                name=row.split('\t')[0]
                subprocess.run(client+['-e','SELECT COUNT(*) FROM recovered.`'+name+'`'],stdout=subprocess.DEVNULL,
                    stderr=errors,check=True,timeout=3)
            report={'completed':True,'uid':os.geteuid(),'production_access':False,'public_network':False,
                'restored_tables':len(rows),'all_tables_readable':True,'source_sha256':hashlib.sha256(source.read_bytes()).hexdigest()}
            (STATE/'report.json').write_text(json.dumps(report,indent=2)+'\n')
            print(json.dumps(report))
        finally:
            if server is not None and server.poll() is None:server.terminate();server.wait(timeout=25)
            # El directorio binario temporal desaparece al terminar RuntimeDirectory.


if __name__=='__main__':main()
