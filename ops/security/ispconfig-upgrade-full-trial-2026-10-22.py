#!/usr/bin/python3
"""Ensayo completo del actualizador: archivos separados y usuario SQL limitado."""
import base64
import importlib.util
import json
import os
from pathlib import Path
import secrets
import shutil
import subprocess
import tarfile

CASE=Path('/root/codex-vigilante/ispconfig-php84-2026-10-22')
LAB=CASE/'full-trial'
DB='codex_ispconfig_full_20261022'
USER='codex_isp_full_20261022'
spec=importlib.util.spec_from_file_location('prepare',CASE/'ispconfig-upgrade-prepare-2026-10-22.py')
prep=importlib.util.module_from_spec(spec);spec.loader.exec_module(prep)


def run():
    assert os.geteuid()==0;os.umask(0o077)
    assert not LAB.exists()
    assert prep.sql("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='"+DB+"'").strip()=='0'
    LAB.mkdir(mode=0o700)
    software=LAB/'ispconfig';software.mkdir()
    # Instancia de trabajo de la distribucion anterior; no copia binarios
    # personales ni los incorpora a los respaldos.
    with tarfile.open(CASE/'previous-release.tar.gz') as archive:
        for m in archive:
            parts=Path(m.name).parts
            if not m.isfile() or len(parts)<3 or parts[1] not in ['server','interface','security']:continue
            p=software.joinpath(*parts[1:]);p.parent.mkdir(parents=True,exist_ok=True)
            p.write_bytes(archive.extractfile(m).read())
    with tarfile.open(CASE/'before-apply-text.tar.gz') as archive:
        for m in archive:
            if not m.isfile() or not m.name.startswith('usr/local/ispconfig/'):continue
            relative=m.name[len('usr/local/ispconfig/'):]
            assert '..' not in Path(relative).parts
            p=software/relative;p.parent.mkdir(parents=True,exist_ok=True)
            p.write_bytes(archive.extractfile(m).read());p.chmod(m.mode);os.chown(p,m.uid,m.gid)
    # Conservar los enlaces internos necesarios del programa.
    links=json.loads((CASE/'before-apply-text.tar.gz.manifest.json').read_text())['symlinks']
    for name,target in links.items():
        if not name.startswith('/usr/local/ispconfig/'):continue
        p=software/name[len('/usr/local/ispconfig/'):]
        if p.exists() or p.is_symlink():continue
        p.parent.mkdir(parents=True,exist_ok=True);p.symlink_to(target)
    source=LAB/'source';shutil.copytree(prep.SOURCE,source,symlinks=True)
    updater=source/'install/update-coordinated.php'
    updater.write_text(updater.read_text().replace('$inst->check_prerequisites();', '$inst->check_prerequisites();\n$conf[\"backup_path\"] = \"'+str(LAB/'logs')+'\";'))
    password=secrets.token_urlsafe(30)
    client=LAB/'client.cnf';client.write_text('[client]\nuser='+USER+'\npassword='+password+'\nhost=localhost\nprotocol=SOCKET\n[mysql]\ninit-command=SET SESSION max_statement_time=60\n');client.chmod(0o600)
    override="\n$conf['db_host']='localhost';$conf['db_database']='"+DB+"';$conf['db_user']='"+USER+"';$conf['db_password']='"+password+"';\n"
    for section in ['server','interface']:
        p=software/section/'lib/config.inc.php'
        text=p.read_text().rstrip()
        assert text.endswith('?>')
        p.write_text(text[:-2]+override+'\n?>\n')
        result=subprocess.check_output(['/usr/bin/php8.4','-r','$conf=[];include $argv[1];echo json_encode([$conf["db_host"],$conf["db_database"],$conf["db_user"]]);',str(p)],text=True)
        assert json.loads(result)==['localhost',DB,USER]
    (software/'server/lib/mysql_clientdb.conf').write_text("<?php $clientdb_host='localhost';$clientdb_user='"+USER+"';$clientdb_password='"+password+"';\n")
    sqlfile=source/'install/sql/incremental/upd_0103.sql'
    sqlfile.write_text(sqlfile.read_text().replace(chr(96)+'dbispconfig'+chr(96),chr(96)+DB+chr(96)))
    template=source/'install/tpl/config.inc.php.master'
    template.write_text(template.read_text().replace('127.0.0.1','localhost'))
    binds=[]
    for host in ['/etc/apache2','/etc/logrotate.d','/etc/systemd/system','/var/www/php-fcgi-scripts']:
        copy=LAB/host.lstrip('/');copy.parent.mkdir(parents=True,exist_ok=True)
        shutil.copytree(host,copy,symlinks=True);binds.append(str(copy)+':'+host)
    default=LAB/'rkhunter-default';default.write_bytes(Path('/etc/default/rkhunter').read_bytes())
    binds.append(str(default)+':/etc/default/rkhunter')
    logs=LAB/'logs';(logs/'ispconfig').mkdir(parents=True);binds.append(str(logs)+':/var/log')
    commands=LAB/'commands';commands.mkdir()
    for command in ['mysql','mysqlcheck']:
        p=commands/command
        p.write_text('#!/bin/sh\nexec /usr/bin/'+command+' --defaults-extra-file='+str(client)+' "$@" --protocol=SOCKET --user='+USER+'\n');p.chmod(0o700)
    # mysqldump del fabricante recibe las mismas credenciales temporales.
    localbin=LAB/'local-bin';localbin.mkdir()
    src=Path('/usr/local/bin/run-getmail.sh')
    if src.exists():shutil.copy2(src,localbin/src.name)
    binds.append(str(localbin)+':/usr/local/bin')
    binds.append(str(software)+':/usr/local/ispconfig')
    prep.sql('CREATE DATABASE '+DB+"; CREATE USER '"+USER+"'@'localhost' IDENTIFIED BY '"+password+"'; GRANT ALL ON "+DB+".* TO '"+USER+"'@'localhost';")
    try:
        with (CASE/'dbispconfig-before.sql').open('rb') as inp:
            subprocess.run(['/usr/bin/mysql','--defaults-extra-file='+str(client),DB],stdin=inp,check=True,capture_output=True)
        args=['systemd-run','--unit=codex-ispconfig-full-trial-20261022','--wait','--pipe','--collect',
              '--property=Type=exec','--property=UMask=0077','--property=Nice=10','--property=CPUQuota=100%',
              '--property=ProtectSystem=strict','--property=ProtectHome=read-only',
              '--property=PrivateTmp=yes','--property=PrivateDevices=yes','--property=PrivateNetwork=yes',
              '--property=ReadWritePaths='+str(LAB),
              '--property=TemporaryFileSystem=/run',
              '--property=BindReadOnlyPaths=/run/mysqld:/run/mysqld',
              '--property=CapabilityBoundingSet=CAP_CHOWN CAP_DAC_OVERRIDE CAP_FOWNER CAP_LINUX_IMMUTABLE CAP_SETUID CAP_SETGID',
              '--property=WorkingDirectory='+str(source/'install'),
              '--setenv=PATH='+str(commands)+':/usr/sbin:/usr/bin:/sbin:/bin']
        for bind in binds:args+=['--property=BindPaths='+bind]
        args+=['/usr/bin/php8.4','-d','disable_functions=','-d','disable_classes=','-d','open_basedir=',
               '-q','update-coordinated.php','--autoinstall='+str(CASE/'update-options.php')]
        with (CASE/'full-trial.log').open('w') as out:
            p=subprocess.run(args,stdout=out,stderr=out)
        log=(CASE/'full-trial.log').read_text(errors='replace')
        assert p.returncode==0 and 'Update finished.' in log and 'Fatal error' not in log
        assert prep.sql('SELECT dbversion FROM '+DB+'.server WHERE server_id=1').strip()=='107'
        assert prep.sql('SELECT dbversion FROM dbispconfig.server WHERE server_id=1').strip()=='100'
        prep.save('full-trial-verification.json',{'completed':True,'namespace_filesystem_separated':True,
            'private_network':True,'database_limited_to_trial_schema':True,'schema_after':107,
            'production_schema_untouched':100,'full_updater_completed':True})
        print('Ensayo completo del actualizador terminado con aislamiento y base temporal.',flush=True)
    finally:
        prep.sql('DROP DATABASE '+DB+"; DROP USER '"+USER+"'@'localhost';")
        client.unlink(missing_ok=True)


if __name__=='__main__':run()
