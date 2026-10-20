#!/usr/bin/env python3
"""Instalacion reservada al administrador, con copias privadas y sin reinicios web."""
import hashlib
import json
import os
from pathlib import Path
import pwd
import subprocess

HERE=Path(__file__).resolve().parent
LIB=Path('/usr/local/lib/vigilante')
UNIT=Path('/etc/systemd/system/vigilante-notify.service')


def digest(path):return hashlib.sha256(path.read_bytes()).hexdigest()


def replace(path,content,mode=0o644,uid=0,gid=0):
    temporary=path.with_name(path.name+'.push-new')
    fd=os.open(temporary,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,mode)
    with os.fdopen(fd,'wb') as stream:
        os.fchown(stream.fileno(),uid,gid);os.fchmod(stream.fileno(),mode)
        stream.write(content);stream.flush();os.fsync(stream.fileno())
    os.replace(temporary,path)


def main():
    if os.geteuid()!=0:raise PermissionError('Solo administrador')
    settings=json.loads((HERE/'installation.json').read_text())
    for path,expected in settings['expected'].items():
        if digest(Path(path))!=expected:raise ValueError('Fuente de produccion modificada')
    for path in [UNIT,LIB/'roleplus-push-sender.py',LIB/'roleplus-push-worker.php',Path('/etc/vigilante/roleplus-push.json')]:
        if os.path.lexists(path):raise ValueError('Instalacion ya existente')
    account=pwd.getpwnam('web6')
    details=Path('/var/www/clients/client1/web6/private/temp/vigilante')
    if details.exists():raise ValueError('Carpeta de fichas ya existente; revisar')
    details.mkdir(mode=0o750);os.chown(details,0,account.pw_gid);details.chmod(0o2750)
    # El usuario PHP debe poder leer solo los preparadores, nunca esta carpeta privada.
    # Se ejecuta una copia temporal publica, sin secretos, fuera del arbol web.
    prepared=LIB/'roleplus-push-prepare.php'
    replace(prepared,(HERE/'prepare-config.php').read_bytes())
    # HERE permanece 0700; usar la copia publica root:root 0644 para el subprocess.
    command=['/usr/bin/php','-d','display_errors=0','-d','log_errors=0',str(prepared),
             '/var/www/clients/client1/web6/private/','/var/www/clients/client1/core/kumbia/config.php']
    def prepare():
        result=subprocess.run(command,capture_output=True,timeout=15,user=account.pw_uid,group=account.pw_gid,extra_groups=[])
        if result.returncode:raise ValueError('Configuracion no preparada')
        return json.loads(result.stdout)
    values=prepare()
    targets={LIB/'roleplus-push-sender.py':(HERE/'sender.py').read_bytes(),
             LIB/'roleplus-push-worker.php':(HERE/'worker.php').read_bytes(),
             LIB/'vigilante-notifications.py':(HERE/'vigilante-notifications.py').read_bytes(),
             LIB/'vigilante-detect.py':(HERE/'vigilante-detect.py').read_bytes(),
             UNIT:(HERE/'vigilante-notify.service').read_bytes()}
    keys=Path('/var/www/clients/client1/web6/private/config/keys.php')
    push=keys.with_name('webpush.php')
    targets[keys]=values['source'].encode()
    targets[push]=(HERE/'webpush-config.php').read_bytes()
    old={};stats={};created=[]
    for index,path in enumerate(targets):
        if path.exists():
            if path.is_symlink():raise ValueError('Destino enlazado')
            old[path]=path.read_bytes();stats[path]=path.stat()
            backup=HERE/('previous-'+str(index));backup.write_bytes(old[path]);backup.chmod(0o600)
    try:
        for path,content in targets.items():
            st=stats.get(path)
            replace(path,content,st.st_mode&0o777 if st else 0o644,st.st_uid if st else 0,st.st_gid if st else 0)
            created.append(path)
        if prepare()['fingerprint']!=values['fingerprint']:raise ValueError('Valores de claves alterados')
        check=subprocess.run(['systemd-analyze','verify',str(UNIT)],capture_output=True,text=True,timeout=20)
        if check.returncode:raise ValueError('Unidad no valida')
        configpath=Path('/etc/vigilante/roleplus-push.json')
        replace(configpath,json.dumps({'version':1,'enabled':False,'recipient':settings['recipient']}).encode(),0o600)
        created.append(configpath)
        subprocess.run(['systemctl','daemon-reload'],check=True,timeout=20)
    except Exception:
        for path in reversed(created):
            if path in old:
                st=stats[path];replace(path,old[path],st.st_mode&0o777,st.st_uid,st.st_gid)
            else:path.unlink()
        subprocess.run(['systemctl','daemon-reload'],capture_output=True,timeout=20)
        raise
    finally:prepared.unlink(missing_ok=True)
    print(json.dumps({'installed':True,'enabled':False,'vapid_preserved':True,'web_services_restarted':False,
                      'source_hashes':{str(p):digest(p) for p in targets if p not in (keys,push)}}))


if __name__=='__main__':main()
