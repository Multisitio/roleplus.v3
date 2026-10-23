#!/usr/bin/env python3
"""Recoge copias existentes por SSH restringido; reanuda sin tocar webs ni SQL."""
import argparse
import codecs
from contextlib import contextmanager
import gzip
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import re
import shutil
import subprocess
import sys
import tarfile
import time

CHUNK = 4 * 1024**2
MAX_STORE = 4 * 1024**3
MAX_OBJECT = 512 * 1024**2


def digest(path):
    value=hashlib.sha256()
    with path.open('rb') as stream:
        while block:=stream.read(1024**2):value.update(block)
    return value.hexdigest()


def save(path,data):
    temporary=path.with_suffix('.new')
    with temporary.open('w',encoding='utf-8') as out:
        json.dump(data,out,ensure_ascii=True,indent=2);out.write('\n');out.flush();os.fsync(out.fileno())
    os.replace(temporary,path)


def directory(path):
    path.mkdir(parents=True,exist_ok=True)
    if path.is_symlink() or (hasattr(path,'is_junction') and path.is_junction()) or not path.is_dir():
        raise ValueError('Directorio de copia no fiable')


@contextmanager
def lock(path):
    with path.open('a+b') as stream:
        if stream.tell()==0:stream.write(b'0');stream.flush()
        stream.seek(0)
        if os.name=='nt':
            import msvcrt
            msvcrt.locking(stream.fileno(),msvcrt.LK_NBLCK,1)
        else:
            import fcntl
            fcntl.flock(stream,fcntl.LOCK_EX|fcntl.LOCK_NB)
        try:yield
        finally:
            if os.name=='nt':stream.seek(0);msvcrt.locking(stream.fileno(),msvcrt.LK_UNLCK,1)


def validate(manifest):
    entries=manifest['entries']
    if manifest.get('version')!=1 or not 1<=len(entries)<=100 or len({e['id'] for e in entries})!=len(entries):
        raise ValueError('Catalogo no valido')
    for e in entries:
        if (not re.fullmatch(r'[a-f0-9]{64}',e['id']) or e['sha256']!=e['id']
                or e['kind'] not in ('text','sql') or type(e['bytes']) is not int
                or not 0<e['bytes']<=MAX_OBJECT or e.get('text_verified') is not True
                or not re.fullmatch(r'(text:(?:web[0-9]+|shared-(?:core|vendor))|sql:c1_[a-z0-9_]+)',e['dataset'])):
            raise ValueError('Objeto fuera del alcance')
    if sum(e['bytes'] for e in entries)>MAX_STORE:raise ValueError('Catalogo fuera de presupuesto')


def verify(path,entry):
    if path.stat().st_size!=entry['bytes'] or digest(path)!=entry['sha256']:
        raise ValueError('La copia no coincide con el origen')
    # Leer hasta el final verifica tambien el CRC y el cierre gzip.
    size=0;tail=b'';decoder=codecs.getincrementaldecoder('utf-8-sig')('strict')
    with gzip.open(path,'rb') as stream:
        while block:=stream.read(1024**2):
            size+=len(block)
            if size>1024**3:raise ValueError('Contenido descomprimido excesivo')
            if entry['kind']=='sql':
                text=decoder.decode(block)
                if re.search(r'[\x00-\x08\x0b\x0e-\x1f\x7f-\x9f]',text):raise ValueError('SQL no textual')
                tail=(tail+block)[-4096:]
    if entry['kind']=='sql':
        decoder.decode(b'',final=True)
        if b'-- Dump completed on ' not in tail:raise ValueError('SQL sin cierre')
    else:
        tree=[];seen=set()
        with tarfile.open(path,'r:gz') as tar:
            for member in tar:
                name=member.name.removeprefix('./');part=PurePosixPath(name)
                if (not member.isfile() or part.is_absolute() or '..' in part.parts or name in seen
                        or not 0<=member.size<=16*1024**2):raise ValueError('Contenido tar no permitido')
                seen.add(name);data=tar.extractfile(member).read()
                tree.append({'path':name,'sha256':hashlib.sha256(data).hexdigest(),'bytes':len(data),
                             'uid':member.uid,'gid':member.gid,'mode':member.mode})
        sha=hashlib.sha256(json.dumps(tree,sort_keys=True,separators=(',',':')).encode()).hexdigest()
        if sha!=entry['tree_sha256'] or len(tree)!=entry['files']:raise ValueError('Textos o metadatos no coinciden')


class SSH:
    def __init__(self,config,root,identity=None):
        if not re.fullmatch(r'[a-zA-Z0-9][a-zA-Z0-9.:-]*',config['host']):raise ValueError('Servidor no valido')
        if not re.fullmatch(r'[a-z_][a-z0-9_-]*',config['user']):raise ValueError('Cuenta no valida')
        ssh=shutil.which('ssh')
        if not ssh:raise RuntimeError('Falta el cliente OpenSSH')
        self.args=[ssh,'-F',str(root/'access/ssh_config'),'-T','-o','BatchMode=yes','-o','IdentitiesOnly=yes','-o','StrictHostKeyChecking=yes',
            '-o','ConnectTimeout=8','-o','ServerAliveInterval=15','-o','ServerAliveCountMax=2',
            '-o','ClearAllForwardings=yes','-o','LogLevel=ERROR','-o','UserKnownHostsFile='+str(root/'access/known_hosts'),
            '-i',str(identity or root/'access/id_ed25519'),'-p',str(config.get('port',22)),
            '-l',config['user'],config['host']]
    def __call__(self,command):
        p=subprocess.run(self.args+[command],capture_output=True,timeout=180)
        if p.returncode:raise ConnectionError('Conexion de copias no disponible; transferencia pendiente')
        return p.stdout


def collect(root,remote):
    for name in ['objects','pending']:directory(root/name)
    with lock(root/'operations.lock'):
        previous=json.loads((root/'status.json').read_text()) if (root/'status.json').exists() else {}
        status={'checked_at':int(time.time()),'completed':False,'restoration_tested':False,'verified':[]}
        if previous.get('last_success'):status['last_success']=previous['last_success']
        try:
            manifest=json.loads(remote('manifest'));validate(manifest)
            save(root/'source-manifest.json',manifest)
            used=sum(p.stat().st_size for folder in ['objects','pending'] for p in (root/folder).iterdir() if p.is_file())
            for entry in manifest['entries']:
                extension='.tar.gz' if entry['kind']=='text' else '.sql.gz'
                target=root/'objects'/(entry['id']+extension)
                partial=root/'pending'/(entry['id']+'.partial')
                for path in (target,partial):
                    if path.is_symlink() or (path.exists() and path.stat().st_nlink!=1):raise ValueError('Enlace no permitido en el almacen')
                if not target.exists():
                    offset=partial.stat().st_size if partial.exists() else 0
                    if not 0<=offset<=entry['bytes']:raise ValueError('Parcial fuera de rango; requiere revision')
                    missing=entry['bytes']-offset
                    if used+missing>MAX_STORE or shutil.disk_usage(root).free<missing+2*1024**3:
                        raise ValueError('Margen de disco insuficiente; conservar copias anteriores')
                    with partial.open('ab') as out:
                        while offset<entry['bytes']:
                            length=min(CHUNK,entry['bytes']-offset)
                            block=remote('get '+entry['id']+' '+str(offset)+' '+str(length))
                            if len(block)!=length:raise ConnectionError('Bloque incompleto')
                            out.write(block);out.flush();os.fsync(out.fileno());offset+=length
                    verify(partial,entry);os.replace(partial,target);used+=missing
                else:verify(target,entry)
                status['verified'].append({'id':entry['id'],'dataset':entry['dataset'],'source_created_at':entry['created_at']})
                save(root/'status.json',status)
            status['completed']=not manifest.get('omitted');status['omitted']=manifest.get('omitted',[])
            if status['completed']:status['last_success']=int(time.time())
        except Exception as error:
            status['pending']=True;status['failure_type']=type(error).__name__
            raise
        finally:save(root/'status.json',status)
    return status


def main():
    args=argparse.ArgumentParser();args.add_argument('--root',type=Path,required=True)
    args.add_argument('--identity',type=Path);args=args.parse_args()
    directory(args.root)
    config=json.loads((args.root/'access/config.json').read_text())
    result=collect(args.root,SSH(config,args.root,args.identity))
    print(json.dumps({'completed':result['completed'],'verified':len(result['verified'])}))
    if not result['completed']:sys.exit(1)


if __name__=='__main__':
    try:main()
    except Exception as error:print('Replica pendiente: '+type(error).__name__,file=sys.stderr);sys.exit(1)
