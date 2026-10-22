#!/usr/bin/python3
"""Actualizacion administrativa PHP, versiones fijadas y activacion coordinada."""
import base64
import fcntl
import gzip
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import pwd
import re
import shutil
import subprocess
import sys
import tempfile
import time
import uuid

CASE=Path('/root/codex-vigilante/php-security-2026-10-22')
ARCHIVES=CASE/'packages'
POLICY=Path('/etc/vigilante/policy.json')
RC=Path('/usr/sbin/policy-rc.d')
RC_BODY=b'#!/bin/sh\n# Actualizacion PHP coordinada; no reinicios desde paquetes.\nexit 101\n'


def save(name,data):
    fd,tmp=tempfile.mkstemp(dir=CASE)
    with os.fdopen(fd,'w') as out:
        os.fchmod(out.fileno(),0o600);json.dump(data,out,indent=2);out.write('\n');out.flush();os.fsync(out.fileno())
    os.replace(tmp,CASE/name)


def phase(name,**fields):
    data={'phase':name,'time':int(time.time()),**fields};save('progress.json',data);print(json.dumps(data),flush=True)


def plan():
    proof=json.loads((CASE/'candidate-security-proof.json').read_text())
    items=json.loads((CASE/'package-plan-initial.json').read_text())
    if len(items)!=239 or len(proof)!=11 or not all(all(p['cves'].values()) for p in proof):
        raise ValueError('Falta prueba del proveedor para todas las ramas')
    if {p['source']+'-common' for p in items}!={p['package'] for p in proof}:
        raise ValueError('Cobertura de ramas incoherente')
    return items


def apt_args(items):
    return ['apt-get','--no-remove','--no-install-recommends','--only-upgrade',
        '-o','Dir::Cache::archives='+str(ARCHIVES),
        '-o','Dpkg::Options::=--force-confdef','-o','Dpkg::Options::=--force-confold',
        '-y','install']+[p['name']+'='+p['candidate'] for p in items]


def health():
    sites=json.loads(POLICY.read_text())['sites'];result={}
    for site in sites:
        p=subprocess.run(['curl','--silent','--show-error','--output','/dev/null',
            '--resolve',site+':443:127.0.0.1','--connect-timeout','3','--max-time','10',
            '--write-out','%{http_code}','https://'+site+'/'],capture_output=True,text=True,timeout=12)
        result[site]={'code':p.stdout,'exit':p.returncode}
    return result


def checked_archives(items):
    import apt
    cache=apt.Cache();found={}
    for path in ARCHIVES.glob('*.deb'):
        metadata=subprocess.check_output(['dpkg-deb','-f',str(path),'Package','Version'],text=True)
        fields=dict(l.split(': ',1) for l in metadata.splitlines())
        name=fields['Package'];version=fields['Version']
        expected=next((p for p in items if p['name']==name),None)
        if not expected or version!=expected['candidate']:
            raise ValueError('Archivo de paquete ajeno al plan')
        candidate=cache[name].candidate
        if candidate.version!=version or hashlib.sha256(path.read_bytes()).hexdigest()!=candidate.record['SHA256']:
            raise ValueError('Paquete distinto del indice firmado')
        found[name]=path
    if set(found)!={p['name'] for p in items}:raise ValueError('Descarga incompleta')
    return found


def prepare():
    import apt
    items=plan();cache=apt.Cache()
    for p in items:
        if cache[p['name']].installed.version!=p['before'] or cache[p['name']].candidate.version!=p['candidate']:
            raise ValueError('El inventario ha cambiado')
    phase('descargando',packages=len(items))
    ARCHIVES.mkdir(mode=0o700,exist_ok=True)
    with (CASE/'apt-download.log').open('w') as log:
        subprocess.run(apt_args(items)+['--download-only'],stdout=log,stderr=log,check=True,timeout=600)
    found=checked_archives(items)
    stage=CASE/'preflight';stage.mkdir(mode=0o700,exist_ok=True)
    branches=sorted({p['source'][3:] for p in items})
    with (CASE/'preflight.log').open('w') as log:
        for name,path in found.items():
            if name.endswith(('-cli','-fpm')):
                subprocess.run(['dpkg-deb','--extract',str(path),str(stage)],stdout=log,stderr=log,check=True,timeout=20)
        for version in branches:
            subprocess.run([str(stage/'usr/bin'/('php'+version)),'-n','-r','echo PHP_VERSION;'],stdout=log,stderr=log,check=True,timeout=10)
            subprocess.run([str(stage/'usr/sbin'/('php-fpm'+version)),'-n','--test',
                '--fpm-config=/etc/php/'+version+'/fpm/php-fpm.conf'],stdout=log,stderr=log,check=True,timeout=15)
    before=health();save('http-before.json',before)
    if any(x['exit'] or x['code']!='200' for x in before.values()):raise ValueError('Revisar estado web previo antes de actualizar')
    config={}
    for path in Path('/etc/php').rglob('*'):
        if path.is_symlink() or not path.is_file() or path.suffix not in ('.ini','.conf'):continue
        content=path.read_bytes()
        if b'\x00' in content:raise ValueError('Configuracion no textual')
        try:content.decode('utf-8')
        except UnicodeDecodeError:content.decode('cp1252')
        config[str(path)]={'sha256':hashlib.sha256(content).hexdigest(),'text_base64':base64.b64encode(content).decode()}
    save('php-config-text-before.json',config)
    save('apache-before.json',{str(p.resolve()):hashlib.sha256(p.read_bytes()).hexdigest() for p in Path('/etc/apache2/sites-enabled').glob('*') if p.is_file()})
    save('alternatives-before.json',{n:os.path.realpath('/usr/bin/'+n) for n in ['php','php-cgi']})
    units=['php'+v+'-fpm.service' for v in branches]+['apache2.service','adapterman-sse.service','adapterman-ws.service']
    save('activation-units.json',units)
    save('prepared.json',{'completed':True,'packages':len(items),'branches':branches,
        'archives_verified':len(found),'new_binaries_and_fpm_configs_checked':True,'http_200':len(before)})
    phase('preparado',packages=len(items),branches=len(branches))


def restore_rc():
    if RC.exists() and not RC.is_symlink() and RC.read_bytes()==RC_BODY:RC.unlink()


def probe_pools():
    pools=json.loads((CASE/'php-pools-before.json').read_text());result=[]
    for pool in pools:
        version=pool['version'];user=pool['user'][0].partition('=')[2].strip()
        socket=pool['listen'][0].partition('=')[2].strip()
        if not socket.startswith('/'):raise ValueError('Socket inesperado')
        if re.fullmatch('web[0-9]+',user):
            directory=Path('/var/www/clients/client1')/user/'private'
        elif user=='www-data':
            # /root es privado; para los pools genericos usar un directorio
            # efimero accesible, cuyo contenido no contiene datos del servidor.
            directory=Path('/run/vigilante-php-security');directory.mkdir(mode=0o755,exist_ok=True)
            directory.chmod(0o755)
        else:raise ValueError('Usuario de pool no revisado')
        target=directory/('codex-runtime-'+uuid.uuid4().hex+'.php')
        try:
            with target.open('x') as out:
                out.write('<?php echo json_encode(array("version"=>PHP_VERSION,"sapi"=>PHP_SAPI));')
                os.fchmod(out.fileno(),0o644)
            owner=pwd.getpwnam(user);os.chown(target,owner.pw_uid,owner.pw_gid)
            env={'PATH':'/usr/bin:/bin','REQUEST_METHOD':'GET','SCRIPT_NAME':'/private-runtime-probe.php',
                'SCRIPT_FILENAME':str(target),'REQUEST_URI':'/private-runtime-probe.php','SERVER_PROTOCOL':'HTTP/1.1'}
            p=subprocess.run(['/usr/bin/cgi-fcgi','-bind','-connect',socket],env=env,capture_output=True,timeout=10,check=True)
            raw=p.stdout.decode('utf-8');data=json.loads(raw[raw.index('{'):])
            expected=next(x['candidate'].split(':')[-1].split('-')[0] for x in plan() if x['name']=='php'+version+'-common')
            if data!={'version':expected,'sapi':'fpm-fcgi'}:raise ValueError('Un pool sigue ejecutando otra version')
            result.append({'pool':pool['pool'],'branch':version,'version':data['version']})
        finally:
            if target.exists():target.unlink()
    directory=Path('/run/vigilante-php-security')
    if directory.exists():directory.rmdir()
    return result


def apply():
    items=plan();assert json.loads((CASE/'prepared.json').read_text())['completed']
    checked_archives(items)
    if RC.exists() or RC.is_symlink():raise ValueError('Existe politica de servicios; conciliar antes de cambiarla')
    units=json.loads((CASE/'activation-units.json').read_text())
    original_policy=POLICY.read_bytes();save('policy-before.json',json.loads(original_policy))
    if json.loads(original_policy).get('php_security_activation_units'):
        raise ValueError('Ya existe una activacion administrativa')
    phase('instalando',packages=len(items))
    with open('/run/lock/vigilante-restarts.lock','a') as lock:
        fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
        with RC.open('xb') as out:out.write(RC_BODY);os.fchmod(out.fileno(),0o755)
        try:
            env=dict(os.environ,DEBIAN_FRONTEND='noninteractive',NEEDRESTART_MODE='l')
            with (CASE/'apt-install.log').open('w') as log:
                # No matar dpkg por reloj mientras escribe su base de paquetes.
                subprocess.run(apt_args(items)+['--no-download'],env=env,stdout=log,stderr=log,check=True)
        finally:restore_rc()
    current=json.loads(POLICY.read_text());current['php_security_activation_units']=units
    fd,tmp=tempfile.mkstemp(dir=POLICY.parent)
    with os.fdopen(fd,'w') as out:os.fchmod(out.fileno(),0o600);json.dump(current,out)
    os.replace(tmp,POLICY)
    actions=[]
    try:
        for unit in sorted(units,key=lambda u:(u not in ('php8.4-fpm.service','php8.1-fpm.service'),u=='apache2.service',u)):
            phase('activando',unit=unit)
            p=subprocess.run(['/usr/local/sbin/vps-service-control','activate-php-security',unit,
                              '--origin','security-php-update'],capture_output=True,text=True,timeout=100)
            if p.returncode:raise RuntimeError('No se activo '+unit)
            actions.append(json.loads(p.stdout));save('activation-results.json',actions)
        phase('verificando_pools')
        pools=probe_pools();save('runtime-pools-after.json',pools)
        after=health();save('http-after.json',after)
        if any(v['exit'] or v['code']!='200' for v in after.values()):raise RuntimeError('Revisar respuesta HTTP tras actualizar')
        old=json.loads((CASE/'apache-before.json').read_text())
        if any(hashlib.sha256(Path(p).read_bytes()).hexdigest()!=s for p,s in old.items()):
            raise RuntimeError('La configuracion de sitios ha cambiado')
        for path,info in json.loads((CASE/'php-config-text-before.json').read_text()).items():
            if re.search(r'/pool.d/web\d+\.conf$',path) and hashlib.sha256(Path(path).read_bytes()).hexdigest()!=info['sha256']:
                raise RuntimeError('Se ha modificado un pool de una web')
        old=json.loads((CASE/'alternatives-before.json').read_text())
        if any(os.path.realpath('/usr/bin/'+n)!=p for n,p in old.items()):raise RuntimeError('Han cambiado los interpretes seleccionados')
        audit=subprocess.run(['dpkg','--audit'],capture_output=True,text=True,check=True)
        if audit.stdout.strip():raise RuntimeError('Estado dpkg pendiente')
        result={'completed':True,'packages':len(items),'branches':11,'fpm_pools_verified':len(pools),
                'websites_http_200':len(after),'configurations_preserved':True,'php_selections_preserved':True,
                'server_rebooted':False,'apache_restarted':False,'activation_actions':actions}
        save('verification.json',result);phase('completado',pools=len(pools),websites=len(after))
    finally:
        current=json.loads(POLICY.read_text());current.pop('php_security_activation_units',None)
        fd,tmp=tempfile.mkstemp(dir=POLICY.parent)
        with os.fdopen(fd,'w') as out:os.fchmod(out.fileno(),0o600);json.dump(current,out,indent=2);out.write('\n')
        os.replace(tmp,POLICY)


if __name__=='__main__':
    if os.geteuid()!=0:raise PermissionError('Solo administrador')
    os.umask(0o077)
    if sys.argv[1:]==['prepare']:prepare()
    elif sys.argv[1:]==['apply']:apply()
    elif sys.argv[1:]==['probe']:save('runtime-pools-after.json',probe_pools())
    else:raise SystemExit(2)
