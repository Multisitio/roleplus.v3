#!/usr/bin/python3
"""Preparacion y ensayo de la actualizacion ISPConfig 3.3.2p1."""
import hashlib
import importlib.util
import io
import json
import os
from pathlib import Path
import re
import secrets
import subprocess
import tarfile
import time

CASE=Path('/root/codex-vigilante/ispconfig-php84-2026-10-22')
SOURCE=CASE/'source/ispconfig3_install'
RELEASE_SHA='b3a2111e997df8e1472933788c2809f038498e97bc00fbd285e18a84a47d4a93'
LAB='codex_ispconfig_upgrade_20261022'
LABUSER='codex_isp_lab_20261022'


def save(name,data):
    p=CASE/name;p.write_text(json.dumps(data,indent=2)+'\n');p.chmod(0o600)


def sql(query):
    return subprocess.check_output(['mysql','--batch','--skip-column-names','--raw','-e',query],text=True)


def backup(name):
    spec=importlib.util.spec_from_file_location('text_backup','/usr/local/lib/vigilante/vigilante-text-backups.py')
    mod=importlib.util.module_from_spec(spec);spec.loader.exec_module(mod)
    roots=list(map(Path,['/usr/local/ispconfig','/etc/apache2','/etc/php/8.1','/etc/php/8.4',
        '/etc/default/rkhunter','/etc/logrotate.d','/usr/local/bin/run-getmail.sh',
        '/var/www/php-fcgi-scripts/ispconfig','/var/www/php-fcgi-scripts/apps']))
    files=[];skipped=[];links={};count=0
    for root in roots:
        if not root.exists():continue
        candidates=[root] if root.is_file() else root.rglob('*')
        for p in candidates:
            if p.is_symlink():links[str(p)]=os.readlink(p);continue
            if not p.is_file() or p.stat().st_size>16*1024**2:continue
            data=p.read_bytes()
            if not mod.text_content(data):skipped.append(str(p));continue
            files.append((p,data))
    with tarfile.open(CASE/name,'w:gz',compresslevel=3) as out:
        for p,data in files:
            info=out.gettarinfo(str(p),arcname=str(p).lstrip('/'))
            assert info.isfile();out.addfile(info,io.BytesIO(data));count+=1
    save(name+'.manifest.json',{'text_files':count,'excluded_binary_files':skipped,'symlinks':links,
        'sha256':hashlib.sha256((CASE/name).read_bytes()).hexdigest()})
    return count


def dump(target):
    with target.open('wb') as out:
        subprocess.run(['mysqldump','--single-transaction','--quick','--skip-lock-tables',
            '--routines','--events','--triggers','--hex-blob','dbispconfig'],stdout=out,check=True,timeout=90)
    target.chmod(0o600)


def prepare_source():
    assert hashlib.sha256((CASE/'release.tar.gz').read_bytes()).hexdigest()==RELEASE_SHA
    original=(SOURCE/'install/update.php').read_text()
    replacements={
        "$inst->make_acme_vhost('nginx');":"/* ACME existente conservado por el coordinador. */",
        "$inst->make_acme_vhost('apache');":"/* ACME existente conservado por el coordinador. */",
        '$inst->update_acme();':'/* Renovacion existente conservada; no actualizar ACME aqui. */'}
    for before,after in replacements.items():
        assert original.count(before)==1;original=original.replace(before,after)
    (SOURCE/'install/update-coordinated.php').write_text(original)
    patch=SOURCE/'install/patches/upd_0103.php'
    if patch.exists():patch.rename(patch.with_suffix('.php.reviewed-not-run'))
    config='[update]\ndo_backup=no\nreconfigure_services=no\nreconfigure_crontab=no\nreconfigure_permissions_in_master_database=no\nispconfig_port=8080\ncreate_new_ispconfig_ssl_cert=no\ncreate_ssl_server_certs=n\nignore_hostname_dns=n\n'
    for service in ['mail','web','dns','xmpp','firewall','vserver','db']:
        config+='svc_detect_change_'+service+'_server=no\n'
    (CASE/'update.ini').write_text(config)
    subprocess.run(['/usr/bin/php8.4','-n','-l',str(SOURCE/'install/update-coordinated.php')],check=True,capture_output=True)
    save('installer-adaptations.json',{'release_sha256':RELEASE_SHA,'acme_preserved':True,
        'recursive_log_patch_replaced_by_review':True,'service_reconfiguration':False,
        'native_binary_backups_disabled':True,'native_crontab_reconfiguration':False})


def trial():
    assert sql("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='"+LAB+"'").strip()=='0'
    assert sql("SELECT COUNT(*) FROM mysql.user WHERE User='"+LABUSER+"'").strip()=='0'
    password=secrets.token_urlsafe(30)
    client=CASE/'lab-client.cnf'
    client.write_text('[client]\nuser='+LABUSER+'\npassword='+password+'\nhost=localhost\n');client.chmod(0o600)
    created=False
    try:
        sql('CREATE DATABASE '+LAB+"; CREATE USER '"+LABUSER+"'@'localhost' IDENTIFIED BY '"+password+"'; GRANT ALL ON "+LAB+".* TO '"+LABUSER+"'@'localhost';")
        created=True
        with (CASE/'dbispconfig-before.sql').open('rb') as inp:
            subprocess.run(['mysql','--defaults-extra-file='+str(client),LAB],stdin=inp,check=True,capture_output=True,timeout=90)
        count=int(sql("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='"+LAB+"'").strip())
        assert count==77,count
        base=int(sql('SELECT dbversion FROM dbispconfig.server WHERE server_id=1').strip())
        patches=[]
        with (CASE/'trial.log').open('wb') as log:
            for p in sorted((SOURCE/'install/sql/incremental').glob('upd_*.sql')):
                if not p.stem[4:].isdigit() or int(p.stem[4:])<=base:continue
                content=p.read_text();quoted=chr(96)+'dbispconfig'+chr(96)
                if quoted in content:
                    assert p.name=='upd_0103.sql' and content.count(quoted)==1
                    content=content.replace(quoted,chr(96)+LAB+chr(96))
                subprocess.run(['mysql','--defaults-extra-file='+str(client),'--default-character-set=utf8mb4',LAB],
                    input=content.encode(),stdout=log,stderr=log,check=True,timeout=180)
                patches.append(p.name)
        critical=['web_domain','web_database','web_database_user','server_php','remote_user','mail_domain','mail_user','dns_soa','sys_user']
        for table in critical:
            assert re.fullmatch('[a-z_]+',table)
            counts=sql('SELECT (SELECT COUNT(*) FROM dbispconfig.'+table+'),(SELECT COUNT(*) FROM '+LAB+'.'+table+')').strip().split('\t')
            assert len(set(counts))==1,table
        save('trial-verification.json',{'completed':True,'restored_tables':count,'sql_patches':patches,
            'critical_table_counts_preserved':critical,'limited_database_user':True,'production_schema_untouched':True})
    finally:
        if created:sql('DROP DATABASE '+LAB+"; DROP USER '"+LABUSER+"'@'localhost';")
        client.unlink(missing_ok=True)


def main():
    assert os.geteuid()==0;os.umask(0o077);CASE.mkdir(mode=0o700,exist_ok=True)
    assert sql("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='dbispconfig' AND ENGINE<>'InnoDB'").strip()=='0'
    prepare_source()
    count=backup('before-text.tar.gz');dump(CASE/'dbispconfig-before.sql')
    trial()
    save('prepared.json',{'completed':True,'text_files':count,'time':int(time.time())})
    print('Preparacion, copia textual y ensayo SQL completados.',flush=True)


if __name__=='__main__':main()
