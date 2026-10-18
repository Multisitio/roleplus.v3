#!/usr/bin/env python3
"""Instalacion independiente: no toca paquetes del sistema ni servicios web."""
import hashlib
import json
import os
from pathlib import Path
import pwd
import shutil
import subprocess
import tarfile
import urllib.request

HERE=Path(__file__).resolve().parent
BASE=Path('/opt/vigilante-whatsapp')
STATE=Path('/var/lib/vigilante-wa')
VERSION='v24.22.0'
ARCHIVE='node-'+VERSION+'-linux-x64.tar.xz'


def run(args,timeout=30):
    result=subprocess.run(args,capture_output=True,text=True,timeout=timeout)
    if result.returncode:
        (HERE/'installation-error.txt').write_text(result.stdout+result.stderr)
        raise RuntimeError('Operacion fallida; consultar evidencia privada')
    return result.stdout


def main():
    if os.geteuid()!=0 or BASE.exists():raise PermissionError('Instalacion nueva reservada al administrador')
    if shutil.disk_usage('/opt').free<1073741824:raise RuntimeError('Espacio de reserva insuficiente')
    if os.uname().machine!='x86_64':raise RuntimeError('Arquitectura no prevista')
    try:pwd.getpwnam('vigilante-wa');raise RuntimeError('Identidad ya existente; revisar')
    except KeyError:pass
    url='https://nodejs.org/dist/'+VERSION+'/'
    with urllib.request.urlopen(url+'SHASUMS256.txt',timeout=30) as response:
        checksums=response.read(65536).decode()
    expected=next(line.split()[0] for line in checksums.splitlines() if line.split()[-1]==ARCHIVE)
    archive=HERE/ARCHIVE
    if not archive.exists():
        with urllib.request.urlopen(url+ARCHIVE,timeout=30) as response,archive.open('xb') as output:
            shutil.copyfileobj(response,output)
    if hashlib.sha256(archive.read_bytes()).hexdigest()!=expected:raise RuntimeError('Huella de Node discordante')
    BASE.mkdir(mode=0o755)
    with tarfile.open(archive,'r:xz') as source:
        links=[member for member in source.getmembers() if member.issym() or member.islnk()]
        prefix='node-'+VERSION+'-linux-x64'
        expected_links={prefix+'/bin/npm':'../lib/node_modules/npm/bin/npm-cli.js',
                        prefix+'/bin/npx':'../lib/node_modules/npm/bin/npx-cli.js',
                        prefix+'/bin/corepack':'../lib/node_modules/corepack/dist/corepack.js'}
        if {member.name:member.linkname for member in links}!=expected_links or any(not member.issym() for member in links):
            raise RuntimeError('Enlaces de Node no previstos')
        source.extractall(BASE,members=[member for member in source.getmembers() if not member.issym()],filter='data')
        for member in links:
            destination=BASE/member.name
            target=(destination.parent/member.linkname).resolve()
            if not target.is_relative_to(BASE.resolve()) or not target.is_file():raise RuntimeError('Enlace fuera de Node')
            destination.symlink_to(member.linkname)
    (BASE/('node-'+VERSION+'-linux-x64')).rename(BASE/'node')
    run(['useradd','--system','--home-dir',str(STATE),'--no-create-home','--shell','/usr/sbin/nologin','vigilante-wa'])
    account=pwd.getpwnam('vigilante-wa')
    STATE.mkdir(mode=0o700);os.chown(STATE,account.pw_uid,account.pw_gid)
    app=BASE/'app';app.mkdir(mode=0o755);os.chown(app,account.pw_uid,account.pw_gid)
    for name in ('package.json','transport.mjs'):
        shutil.copyfile(HERE/name,app/name);os.chmod(app/name,0o644);os.chown(app/name,account.pw_uid,account.pw_gid)
    if (HERE/'package-lock.json').exists():
        shutil.copyfile(HERE/'package-lock.json',app/'package-lock.json')
        os.chmod(app/'package-lock.json',0o644);os.chown(app/'package-lock.json',account.pw_uid,account.pw_gid)
    command=['systemd-run','--quiet','--wait','--pipe','--collect','--unit=vigilante-whatsapp-install',
             '-p','User=vigilante-wa','-p','Group=vigilante-wa','-p','WorkingDirectory='+str(app),
             '-p','Environment=HOME='+str(STATE),'-p','Environment=PATH='+str(BASE/'node/bin')+':/usr/bin:/bin',
             '-p','Environment=UV_THREADPOOL_SIZE=2','-p','CPUQuota=20%','-p','MemoryMax=512M',
             '-p','TasksMax=32','-p','NoNewPrivileges=yes','-p','CapabilityBoundingSet=',
             '-p','ProtectSystem=strict','-p','ProtectHome=yes','-p','PrivateTmp=yes','-p','PrivateDevices=yes',
             '-p','ReadWritePaths='+str(app)+' '+str(STATE),'-p','InaccessiblePaths=/var/www /var/lib/vigilante-auth /etc/vigilante',
             '-p','RuntimeMaxSec=300s',str(BASE/'node/bin/node'),'--v8-pool-size=2',
             str(BASE/'node/lib/node_modules/npm/bin/npm-cli.js'),'ci' if (app/'package-lock.json').exists() else 'install',
             '--ignore-scripts','--legacy-peer-deps',
             '--omit=dev','--no-fund','--no-audit']
    output=run(command,330);(HERE/'npm-install.txt').write_text(output)
    # Tras instalar, ni la identidad del transporte puede editar dependencias.
    for current,dirs,files in os.walk(app):
        for path in [Path(current)]+[Path(current)/name for name in files]:
            os.chown(path,0,0,follow_symlinks=False)
            if not path.is_symlink():os.chmod(path,0o755 if path.is_dir() or path.stat().st_mode&0o111 else 0o644)
    run([str(BASE/'node/bin/node'),'--check',str(app/'transport.mjs')])
    probe="import('@whiskeysockets/baileys').then(m=>{if(typeof m.default!=='function'||typeof m.useMultiFileAuthState!=='function')process.exit(1)})"
    result=subprocess.run([str(BASE/'node/bin/node'),'--v8-pool-size=2','--input-type=module','-e',probe],cwd=app,capture_output=True,text=True,timeout=30,
                          user=account.pw_uid,group=account.pw_gid,extra_groups=[])
    if result.returncode:
        (HERE/'import-error.txt').write_text(result.stdout+result.stderr);raise RuntimeError('Importacion no valida')
    unit=Path('/etc/systemd/system/vigilante-whatsapp.service')
    if unit.exists():raise RuntimeError('Unidad ya existente')
    shutil.copyfile(HERE/unit.name,unit);os.chmod(unit,0o644)
    control=Path('/usr/local/lib/vigilante/whatsapp-control.py')
    if control.exists():raise RuntimeError('Control administrativo ya existente')
    shutil.copyfile(HERE/'control.py',control);os.chmod(control,0o644)
    run(['systemd-analyze','verify',str(unit)])
    run(['systemctl','daemon-reload'])
    shutil.copyfile(app/'package-lock.json',HERE/'package-lock.json')
    report={'installed':True,'node_version':VERSION,'node_sha256':expected,'uid':account.pw_uid,
            'linked':False,'started':False,'recipient_configuration_required':True,'additional_cost_eur':0,
            'sha256':{name:hashlib.sha256((app/name).read_bytes()).hexdigest() for name in ('transport.mjs','package.json','package-lock.json')}}
    (HERE/'installation.json').write_text(json.dumps(report));print(json.dumps(report),flush=True)


if __name__=='__main__':main()
