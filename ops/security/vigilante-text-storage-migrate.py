#!/usr/bin/python3
"""Aisla los textos de la limpieza nativa; conserva evidencia de las copias ausentes."""
import fcntl
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import shutil
import time

CASE=Path('/root/codex-vigilante/replica-r2d2-2026-10-23/text-storage')
STATE=Path('/var/lib/vigilante-text-backups')
DEST=Path('/var/backup/vigilante-text')


def digest(path):
    value=hashlib.sha256()
    with path.open('rb') as stream:
        while block:=stream.read(1024**2):value.update(block)
    return value.hexdigest()


def main():
    if os.geteuid()!=0:raise PermissionError('Solo mantenimiento administrativo')
    os.umask(0o077);CASE.mkdir(mode=0o700)
    spec=importlib.util.spec_from_file_location('text','/usr/local/lib/vigilante/vigilante-text-backups.py')
    text=importlib.util.module_from_spec(spec);spec.loader.exec_module(text)
    assert text.DEST==DEST
    with (STATE/'operations.lock').open('a') as lock:
        fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
        original=(STATE/'ledger.json').read_bytes();(CASE/'ledger-before.json').write_bytes(original)
        ledger=json.loads(original);changes=[];missing=[]
        assert len(ledger['items'])==13
        DEST.mkdir(mode=0o700,exist_ok=True)
        assert not DEST.is_symlink() and DEST.stat().st_uid==0 and not DEST.stat().st_mode&0o077
        for ident,item in ledger['items'].items():
            assert re.fullmatch(r'web[0-9]+|shared-(core|vendor)',ident)
            folder=DEST/ident;folder.mkdir(mode=0o700,exist_ok=True)
            assert not folder.is_symlink() and folder.stat().st_uid==0
            retained=[]
            for archive in item['archives']:
                name=archive['name']
                assert re.fullmatch('text-'+re.escape(ident)+r'_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.tar\.gz',name)
                source=Path('/var/backup')/ident/name;target=folder/name
                if not source.exists():
                    assert not target.exists()
                    missing.append({'id':ident,**archive,'noticed_at':int(time.time())});continue
                assert not source.is_symlink() and digest(source)==archive['sha256']
                text.verify(source,json.loads((STATE/(name+'.json')).read_text()))
                with source.open('rb') as src,target.open('xb') as out:
                    os.fchmod(out.fileno(),0o600);shutil.copyfileobj(src,out,1024**2);out.flush();os.fsync(out.fileno())
                assert digest(target)==archive['sha256'];retained.append(archive);changes.append(ident)
            item['archives']=retained
            if not retained:item['next_at']=0
        ledger.setdefault('unavailable_archives',[]).extend(missing)
        ledger['text_storage']=str(DEST)
        text.save(STATE/'ledger.json',ledger)
        text.save(CASE/'result.json',{'completed':True,'preserved_existing':changes,
            'missing_before_change':missing,'original_files_deleted':False,'rebuild_uses_existing_service':True})
    print(json.dumps({'preserved':len(changes),'missing_to_regenerate':len(missing)}))


if __name__=='__main__':main()
