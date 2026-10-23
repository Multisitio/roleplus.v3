#!/usr/bin/python3
"""Exportacion restringida de copias existentes, sin crear volcados ni restaurar."""
import codecs
import gzip
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import shlex
import stat
import subprocess
import sys
import time

LIB = Path('/usr/local/lib/vigilante')
STATE = Path('/var/lib/vigilante-replica-export')
BACKUPS = Path('/var/backup')
TEXT_STATE = Path('/var/lib/vigilante-text-backups')
INDEPENDENT_STATE = Path('/var/lib/vigilante-independent-sql')
MAX_COMPRESSED = 512 * 1024**2
MAX_SQL = 1024**3
MAX_RANGE = 4 * 1024**2
MAPPED = {'c1_controlate':26,'c1_mtgsearch':4,'c1_roleplus':6,'c1_delixio':24,
          'c1_mansion':15,'c1_multisitio':17,'c1_rola':16}
INDEPENDENT = {'c1_carmenarnaumuro','c1_deportes'}


def module(name):
    spec=importlib.util.spec_from_file_location(name,LIB/(name+'.py'))
    mod=importlib.util.module_from_spec(spec);spec.loader.exec_module(mod);return mod


def private(path):
    st=path.lstat()
    if path.is_symlink() or st.st_uid!=0 or st.st_mode&0o077:
        raise ValueError('Estado de exportacion no privado')


def fingerprint(st):return [st.st_dev,st.st_ino,st.st_size,st.st_mtime_ns,st.st_ctime_ns]


def open_archive(path):
    # directory() recorre cada componente sin seguir enlaces.
    with module('vigilante-text-backups').directory(path.parent) as directory:
        fd=os.open(path.name,os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK,dir_fd=directory)
    stream=os.fdopen(fd,'rb');st=os.fstat(stream.fileno())
    if (not stat.S_ISREG(st.st_mode) or st.st_uid!=0 or st.st_nlink!=1
            or not 0<st.st_size<=MAX_COMPRESSED):
        stream.close();raise ValueError('Archivo no exportable')
    return stream


def sql_text(stream):
    decoder=codecs.getincrementaldecoder('utf-8-sig')('strict');size=0;tail=b''
    with gzip.GzipFile(fileobj=stream,mode='rb') as zipped:
        while chunk:=zipped.read(1024**2):
            size+=len(chunk)
            if size>MAX_SQL:raise ValueError('SQL fuera de presupuesto')
            if re.search(r'[\x00-\x08\x0b\x0e-\x1f\x7f-\x9f]',decoder.decode(chunk)):
                raise ValueError('SQL no textual')
            tail=(tail+chunk)[-4096:]
        decoder.decode(b'',final=True)
    if b'-- Dump completed on ' not in tail:raise ValueError('SQL sin cierre')


def digest(stream):
    h=hashlib.sha256()
    while chunk:=stream.read(1024**2):h.update(chunk)
    return h.hexdigest()


def mapped_sources():
    q="SELECT d.database_name,d.parent_domain_id,b.filename,b.tstamp FROM web_database d JOIN web_backup b ON b.parent_domain_id=d.parent_domain_id AND b.backup_type='mysql' WHERE d.active='y' AND d.parent_domain_id>0 AND b.filename LIKE CONCAT('db_',d.database_name,'_%') ORDER BY b.tstamp DESC"
    result=subprocess.run(['/usr/bin/mysql','--no-defaults','--protocol=socket',
        '--socket=/run/mysqld/mysqld.sock','--user=root','-NB','dbispconfig'],input=q,
        capture_output=True,text=True,check=True,timeout=10)
    seen=set()
    for line in result.stdout.splitlines():
        name,ident,filename,created=line.split('\t')
        if name in seen or MAPPED.get(name)!=int(ident):continue
        if not re.fullmatch('db_'+re.escape(name)+r'_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}(?:-\d{2})?\.sql\.gz',filename):continue
        seen.add(name)
        yield {'dataset':'sql:'+name,'kind':'sql','path':str(BACKUPS/('web'+ident)/filename),'created_at':int(created)}


def sources():
    ledger=json.loads((TEXT_STATE/'ledger.json').read_text())
    for ident,item in ledger['items'].items():
        if not re.fullmatch(r'web[0-9]+|shared-(core|vendor)',ident):raise ValueError('Raiz no prevista')
        # Replica todas las versiones que conserva el productor (actualmente dos).
        for archive in item['archives']:
            name=archive['name']
            if not re.fullmatch('text-'+re.escape(ident)+r'_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.tar\.gz',name):raise ValueError('Nombre no valido')
            yield {'dataset':'text:'+ident,'kind':'text','path':str(BACKUPS/ident/name),
                   'created_at':int((BACKUPS/ident/name).stat().st_mtime),'expected_sha256':archive['sha256'],
                   'text_manifest':str(TEXT_STATE/(name+'.json'))}
    yield from mapped_sources()
    ledger=json.loads((INDEPENDENT_STATE/'ledger.json').read_text())
    for item in ledger['archives']:
        name=item['name'];filename=item['filename']
        if name not in INDEPENDENT or not re.fullmatch('db_'+re.escape(name)+r'_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql\.gz',filename):raise ValueError('Base no prevista')
        yield {'dataset':'sql:'+name,'kind':'sql','path':str(BACKUPS/'vigilante-independent-sql'/filename),
               'created_at':int((BACKUPS/'vigilante-independent-sql'/filename).stat().st_mtime),
               'expected_sha256':item['gzip_sha256']}


def save(data):
    temporary=STATE/('catalog-'+str(os.getpid())+'.tmp')
    with temporary.open('x') as out:
        os.fchmod(out.fileno(),0o600);json.dump(data,out);out.flush();os.fsync(out.fileno())
    os.replace(temporary,STATE/'catalog.json')


def catalogue():
    old=json.loads((STATE/'catalog.json').read_text()) if (STATE/'catalog.json').exists() else {'entries':[]}
    cache={x['path']:x for x in old['entries']}
    entries=[];omitted=[]
    for source in sources():
        try:
            path=Path(source['path'])
            with open_archive(path) as stream:
                before=fingerprint(os.fstat(stream.fileno()));previous=cache.get(str(path))
                if previous and previous['fingerprint']==before:
                    entry=dict(previous)
                else:
                    sha=digest(stream);stream.seek(0)
                    if source.get('expected_sha256',sha)!=sha:raise ValueError('Huella inesperada')
                    if source['kind']=='sql':sql_text(stream)
                    else:
                        manifest=json.loads(Path(source['text_manifest']).read_text())
                        module('vigilante-text-backups').verify(path,manifest)
                    if fingerprint(os.fstat(stream.fileno()))!=before:raise ValueError('Cambio durante lectura')
                    entry={k:source[k] for k in ['dataset','kind','created_at','path']}
                    entry.update(id=sha,sha256=sha,bytes=before[2],fingerprint=before,name=path.name,text_verified=True)
                    if source['kind']=='text':
                        entry['tree_sha256']=hashlib.sha256(json.dumps(manifest['files'],sort_keys=True,separators=(',',':')).encode()).hexdigest()
                        entry['files']=len(manifest['files'])
                entries.append(entry)
        except (OSError,ValueError,UnicodeError,gzip.BadGzipFile):
            omitted.append({'dataset':source['dataset'],'reason':'copia_ausente_cambiada_o_no_textual'})
    if len(entries)>100 or len({e['id'] for e in entries})!=len(entries):raise ValueError('Catalogo fuera de alcance')
    expected={'sql:'+n for n in set(MAPPED)|INDEPENDENT}
    expected|={'text:'+site['id'] for site in json.loads(Path('/etc/vigilante/text-backups.json').read_text())['sites']}
    present={e['dataset'] for e in entries}
    for missing in sorted(expected-present):
        if not any(o['dataset']==missing for o in omitted):omitted.append({'dataset':missing,'reason':'sin_copia_exportable'})
    data={'version':1,'generated_at':int(time.time()),'entries':entries,'omitted':omitted}
    save(data);return public(data)


def public(data):
    return {**{k:v for k,v in data.items() if k!='entries'},'entries':[
        {k:v for k,v in entry.items() if k not in ('path','fingerprint')} for entry in data['entries']]}


def get(ident,offset,length):
    if not re.fullmatch(r'[a-f0-9]{64}',ident) or not 0<=offset<MAX_COMPRESSED or not 0<length<=MAX_RANGE:
        raise ValueError('Rango no valido')
    data=json.loads((STATE/'catalog.json').read_text());matches=[e for e in data['entries'] if e['id']==ident]
    if len(matches)!=1:raise ValueError('Objeto fuera del catalogo')
    entry=matches[0]
    with open_archive(Path(entry['path'])) as stream:
        if fingerprint(os.fstat(stream.fileno()))!=entry['fingerprint'] or offset+length>entry['bytes']:
            raise ValueError('La captura ha cambiado')
        # No basta el tamano/mtime: algunos sistemas agrupan marcas de tiempo.
        # Contrastar tambien el contenido antes de entregar cualquier rango.
        if digest(stream)!=entry['id']:raise ValueError('La captura ha cambiado de contenido')
        stream.seek(offset);chunk=stream.read(length)
        if len(chunk)!=length or fingerprint(os.fstat(stream.fileno()))!=entry['fingerprint']:
            raise ValueError('Lectura interrumpida')
    return chunk


def main():
    if os.geteuid()!=0:raise PermissionError('Solo exportador instalado por administrador')
    os.umask(0o077);os.nice(15);private(STATE)
    # El cliente no elige archivos ni ordenes de sistema. SSH fuerza este programa.
    args=shlex.split(os.environ.get('SSH_ORIGINAL_COMMAND',''))
    if args==['manifest']:
        sys.stdout.write(json.dumps(catalogue())+'\n')
    elif len(args)==4 and args[0]=='get' and args[2].isdigit() and args[3].isdigit():
        sys.stdout.buffer.write(get(args[1],int(args[2]),int(args[3])))
    else:raise ValueError('Operacion no permitida')


if __name__=='__main__':
    try:main()
    except Exception:
        sys.stderr.write('Exportacion no disponible; conservar la copia anterior y reintentar.\n');sys.exit(1)
