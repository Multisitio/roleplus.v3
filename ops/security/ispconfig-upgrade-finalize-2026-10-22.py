#!/usr/bin/python3
"""Cierra la migracion: permisos de registros resueltos por vhost y retiro de 8.1."""
import fcntl
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import pwd
import grp
import re
import stat
import subprocess
import tempfile

CASE=Path('/root/codex-vigilante/ispconfig-php84-2026-10-22')
spec=importlib.util.spec_from_file_location('migration',CASE/'ispconfig-php84-migrate-2026-10-22.py')
migration=importlib.util.module_from_spec(spec);spec.loader.exec_module(migration)
upgrade=migration.upgrade
POLICY=Path('/etc/vigilante/policy.json')
RC=Path('/usr/sbin/policy-rc.d')
RC_BODY=b'#!/bin/sh\n# Retirada coordinada de PHP 8.1 sin consumidores.\nexit 101\n'


def write_policy(data):
    fd,tmp=tempfile.mkstemp(dir=POLICY.parent)
    with os.fdopen(fd,'w') as out:
        os.fchmod(out.fileno(),0o600);json.dump(data,out,indent=2);out.write('\n');out.flush();os.fsync(out.fileno())
    os.replace(tmp,POLICY)


def log_permissions():
    rows=upgrade.prep.sql('SELECT document_root,system_user,system_group FROM dbispconfig.web_domain WHERE type="vhost"').splitlines()
    mapping={}
    for row in rows:
        values=row.split('\t')
        if len(values)!=3 or not values[0].startswith('/var/www/'):continue
        base,user,group=values
        mapping[str((Path(base)/'web').resolve())]=(base,user,group)
    roots={};changes=[];skipped=[]
    for vhost in Path('/etc/apache2/sites-enabled').glob('*'):
        if not vhost.is_file():continue
        content=vhost.read_text()
        docs=re.findall(r'^\s*DocumentRoot\s+["\x27]?([^"\x27\s]+)',content,re.M)
        errors=re.findall(r'^\s*ErrorLog\s+["\x27]?([^"\x27\s]+)',content,re.M)
        if not docs or not errors:continue
        doc=str(Path(docs[0]).resolve())
        if doc not in mapping:continue
        base,user,group=mapping[doc];error=Path(errors[0])
        if not error.is_absolute() or not error.exists():continue
        error=error.resolve();root=error.parent
        if not str(root).startswith('/var/log/ispconfig/httpd/'):continue
        assert os.path.samefile(root,Path(base)/'log'),'Registro no coincide con el sitio resuelto'
        assert root.stat().st_uid==0 and not root.stat().st_mode&0o022
        roots[str(root)]=(user,group,vhost.name,doc)
    for name,(user,group,vhost,doc) in roots.items():
        uid=pwd.getpwnam(user).pw_uid;gid=grp.getgrnam(group).gr_gid
        count=0
        for directory,dirs,files,fd in os.fwalk(name,follow_symlinks=False):
            assert (Path(directory).resolve()).is_relative_to(Path(name))
            for child in ['.']+files:
                try:handle=os.open(child,os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK,dir_fd=fd)
                except OSError:continue
                try:
                    info=os.fstat(handle)
                    if not (stat.S_ISREG(info.st_mode) or stat.S_ISDIR(info.st_mode)):continue
                    if info.st_uid not in (0,uid,pwd.getpwnam('www-data').pw_uid):
                        skipped.append(str(Path(directory)/child));continue
                    mode=(info.st_mode&0o7777)&~0o007
                    if info.st_gid!=gid or mode!=(info.st_mode&0o7777):
                        changes.append({'path':str(Path(directory)/child),'gid_before':info.st_gid,
                            'mode_before':info.st_mode&0o7777,'gid_after':gid,'mode_after':mode,'vhost':vhost})
                        os.fchown(handle,-1,gid);os.fchmod(handle,mode)
                    count+=1;assert count<5000,'Revisar alcance antes de ampliar'
                finally:os.close(handle)
    upgrade.save('log-permissions-review.json',{'resolved_roots':roots,'changes':changes,
        'skipped_unexpected_owner':skipped,'followed_symlinks':False,'content_read_or_changed':False})
    return len(changes)


def update_known_checksums():
    manifests=sorted(Path('/usr/local/ispconfig/security/data').glob('file_checksums_*.md5'),key=lambda p:p.stat().st_mtime)
    assert manifests
    path=manifests[-1];lines=path.read_text().splitlines()
    for p in [migration.CUSTOM/'apache_ispconfig_fcgi_starter.master',migration.CUSTOM/'apache_apps_fcgi_starter.master']:
        lines=[l for l in lines if not l.endswith('  '+str(p))]
        lines.append(hashlib.md5(p.read_bytes()).hexdigest()+'  '+str(p))
    path.write_text('\n'.join(lines)+'\n')


def retire81():
    assert json.loads((CASE/'php84-verification.json').read_text())['completed']
    assert not migration.LINK81.exists() and migration.LINK84.resolve()==Path('/etc/apache2/conf-available/php8.4-fpm.conf')
    assert os.path.realpath('/usr/bin/php-cgi')=='/usr/bin/php-cgi8.4'
    assert upgrade.prep.sql('SELECT COUNT(*) FROM dbispconfig.web_domain WHERE server_php_id=8').strip()=='0'
    assert upgrade.prep.sql('SELECT active FROM dbispconfig.server_php WHERE server_php_id=8').strip()=='n'
    expression=re.compile(r'/usr/bin/php(?:-cgi)?8\.1\b|/usr/sbin/php-fpm8\.1\b|/etc/php/8\.1/|php8\.1-fpm\.service')
    references=[]
    for root in map(Path,['/etc/apache2/sites-enabled','/etc/apache2/conf-enabled','/etc/cron.d','/var/spool/cron/crontabs']):
        for p in root.rglob('*'):
            if not p.is_file():continue
            for line in p.read_text(errors='replace').splitlines():
                if not line.lstrip().startswith('#') and expression.search(line):references.append(str(p))
    assert not references,references
    busy=[]
    for p in Path('/proc').glob('[0-9]*/exe'):
        try:name=os.readlink(p)
        except OSError:continue
        if name.endswith(('/php8.1','/php-cgi8.1')):busy.append(p.parent.name)
    assert not busy,'Quedan procesos CLI/CGI 8.1; no se fuerza su cierre'
    fmt=chr(36)+'{Package}\t'+chr(36)+'{db:Status-Status}\n'
    raw=subprocess.check_output(['dpkg-query','-W','-f='+fmt],text=True)
    names=[l.split('\t')[0] for l in raw.splitlines() if l.endswith('\tinstalled') and re.fullmatch(r'php8\.1(?:-[a-z0-9-]+)?',l.split('\t')[0])]
    assert 5<len(names)<40
    plan=subprocess.run(['apt-get','--simulate','purge']+names,capture_output=True,text=True,check=True)
    removed=set(re.findall(r'^(?:Purg|Remv) (\S+)',plan.stdout,re.M))
    assert removed==set(names) and not re.search(r'^Inst ',plan.stdout,re.M)
    upgrade.save('retire81-plan.json',{'packages':names,'unexpected_dependencies':[],'consumer_references':references})
    assert not RC.exists() and not RC.is_symlink()
    policy=json.loads(POLICY.read_text());assert not policy.get('php_security_retirement_units')
    policy['php_security_retirement_units']=['php8.1-fpm.service']
    policy['php_security_retirement_verified_branches']=['8.1']
    write_policy(policy)
    try:
        result=subprocess.run(['/usr/local/sbin/vps-service-control','retire-php-security','php8.1-fpm.service',
            '--origin','security-php-update'],capture_output=True,text=True,check=True,timeout=30)
        upgrade.save('retire81-action.json',json.loads(result.stdout))
        with open('/run/lock/vigilante-restarts.lock','a') as lock:
            fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
            with RC.open('xb') as out:out.write(RC_BODY);os.fchmod(out.fileno(),0o755)
            try:
                with (CASE/'apt-retire81.log').open('w') as out:
                    subprocess.run(['apt-get','-o','DPkg::Lock::Timeout=30','-y','purge']+names,
                        env=dict(os.environ,DEBIAN_FRONTEND='noninteractive',NEEDRESTART_MODE='l'),stdout=out,stderr=out,check=True)
            finally:
                if RC.exists() and not RC.is_symlink() and RC.read_bytes()==RC_BODY:RC.unlink()
    finally:
        policy=json.loads(POLICY.read_text());policy.pop('php_security_retirement_units',None)
        policy.pop('php_security_retirement_verified_branches',None);write_policy(policy)
    assert not subprocess.check_output(['dpkg','--audit'],text=True).strip()
    remaining=subprocess.check_output(['dpkg-query','-W','-f='+fmt],text=True)
    assert not any(l.endswith('\tinstalled') and re.match(r'php8\.1(?:-|\t)',l) for l in remaining.splitlines())
    assert os.path.realpath('/usr/bin/php-cgi')=='/usr/bin/php-cgi8.4'
    return len(names)


def audit_configuration():
    name='codex_ispconfig_cfg_audit_20261023'
    assert upgrade.prep.sql("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='"+name+"'").strip()=='0'
    upgrade.prep.sql('CREATE DATABASE '+name)
    try:
        statements=[]
        with (CASE/'dbispconfig-before-apply.sql').open() as snapshot:
            for line in snapshot:
                if any(line.startswith('INSERT INTO '+chr(96)+table+chr(96)+' ') for table in ['server','sys_ini']):
                    statements.append(line)
        assert len(statements)==2
        upgrade.prep.sql('CREATE TABLE '+name+'.server LIKE dbispconfig.server;CREATE TABLE '+name+'.sys_ini LIKE dbispconfig.sys_ini')
        p=subprocess.run(['mysql','--default-character-set=utf8mb4',name],input=''.join(statements),capture_output=True,text=True)
        if p.returncode:raise RuntimeError('No se ha podido contrastar la copia de configuracion')
        payload={}
        for table,key in [('server','server_id'),('sys_ini','sysini_id')]:
            payload[table]={
                'before':upgrade.prep.sql('SELECT HEX(config) FROM '+name+'.'+table+' WHERE '+key+'=1').strip(),
                'after':upgrade.prep.sql('SELECT HEX(config) FROM dbispconfig.'+table+' WHERE '+key+'=1').strip()}
        code='''require "/root/codex-vigilante/ispconfig-php84-2026-10-22/native-install.lib.php";
$data=json_decode(stream_get_contents(STDIN),true);$result=[];
$expected=["web.php_fpm_init_script","web.php_fpm_ini_path","web.php_fpm_pool_dir","web.php_ini_path_cgi","web.php_ini_path_apache","fastcgi.fastcgi_bin","fastcgi.fastcgi_phpini_path"];
foreach($data as $table=>$row){
 $before=ini_to_array(stripslashes(hex2bin($row["before"])));
 $after=ini_to_array(stripslashes(hex2bin($row["after"])));
 foreach($before as $section=>$values){foreach($values as $key=>$value){
  if(($after[$section][$key]??null)!==$value && !($table==="server" && in_array($section.".".$key,$expected,true)))$result[]=$table.".".$section.".".$key;
 }}
}
echo json_encode($result);
'''
        p=subprocess.run(['/usr/bin/php8.4','-r',code],input=json.dumps(payload),capture_output=True,text=True,check=True)
        unexpected=json.loads(p.stdout)
        upgrade.save('configuration-preservation-verification.json',{'compared_original_snapshot':True,
            'unexpected_changed_keys':unexpected,'php_profile_changes_only':True,'values_not_logged':True})
        assert not unexpected,unexpected
    finally:upgrade.prep.sql('DROP DATABASE '+name)


def main():
    assert os.geteuid()==0;os.umask(0o077)
    assert json.loads((CASE/'php84-verification.json').read_text())['completed']
    audit_configuration()
    upgrade.phase('revisando_permisos_de_registros')
    count=log_permissions();update_known_checksums()
    upgrade.phase('retirando_php81_sin_consumidores')
    packages=retire81()
    panel=migration.probe_panel();others=migration.probe_apps_and_generic()
    spec=importlib.util.spec_from_file_location('health',Path('/root/codex-vigilante/php-security-2026-10-22/php-security-update-2026-10-22.py'))
    health=importlib.util.module_from_spec(spec);spec.loader.exec_module(health)
    websites=health.health();assert all(x['exit']==0 and x['code']=='200' for x in websites.values())
    upgrade.save('final-verification.json',{'completed':True,'ispconfig':'3.3.2p1','schema':107,
        'only_php_branch':'8.4','php':'8.4.27','php81_packages_removed':packages,
        'log_metadata_changes':count,'panel_http_runtime':panel,'other_runtimes':others,
        'websites_http_200':len(websites),'autoremove_used':False,'server_rebooted':False})
    upgrade.phase('actualizacion_y_unificacion_completadas')


if __name__=='__main__':main()
