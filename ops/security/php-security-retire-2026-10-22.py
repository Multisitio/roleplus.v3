#!/usr/bin/python3
"""Retira solo las nueve ramas PHP acreditadas sin consumidores."""
import fcntl
import importlib.util
import json
import os
from pathlib import Path
import re
import subprocess
import tempfile

CASE=Path('/root/codex-vigilante/php-security-2026-10-22')
POLICY=Path('/etc/vigilante/policy.json')
BRANCHES=['5.6','7.0','7.1','7.2','7.3','7.4','8.0','8.2','8.3']
IDS=[1,2,3,4,5,6,7,9,10]
RC=Path('/usr/sbin/policy-rc.d')
RC_BODY=b'#!/bin/sh\n# Retirada PHP coordinada; no reinicios desde paquetes.\nexit 101\n'


def store(path,data):
    fd,tmp=tempfile.mkstemp(dir=path.parent)
    with os.fdopen(fd,'w') as out:
        os.fchmod(out.fileno(),0o600);json.dump(data,out,indent=2);out.write('\n');out.flush();os.fsync(out.fileno())
    os.replace(tmp,path)


def sql(query):
    return subprocess.check_output(['mysql','--batch','--skip-column-names','dbispconfig','-e',query],text=True)


def main():
    assert os.geteuid()==0
    os.umask(0o077)
    verification=json.loads((CASE/'verification.json').read_text());assert verification['completed']
    oldrefs=json.loads((CASE/'unused-branches-references.json').read_text());assert oldrefs==[]
    selected=','.join(map(str,IDS))
    assert sql('SELECT COUNT(*) FROM web_domain WHERE server_php_id IN ('+selected+')').strip()=='0'
    plan=json.loads((CASE/'retirement-plan.json').read_text());names=plan['packages']
    assert len(names)==200 and not plan['removed'] and set(plan['purged'])==set(names) and plan['exit']==0
    p=subprocess.run(['apt-get','--simulate','purge']+names,capture_output=True,text=True,check=True)
    assert not re.search(r'^Inst ',p.stdout,re.M)
    assert set(re.findall(r'^(?:Purg|Remv) (\S+)',p.stdout,re.M))==set(names)
    assert all(re.fullmatch(r'php(?:5\.6|7\.[0-4]|8\.[023])(?:-[a-z0-9-]+)?(?::amd64)?',name) for name in names)
    # Repetir la deteccion de referencias al momento de actuar.
    refs=[];pattern=re.compile(r'php(?:5\.6|7\.[0-4]|8\.[023])(?:-fpm|-cgi|/|\b)')
    for root in map(Path,['/etc/apache2/sites-enabled','/etc/apache2/conf-enabled','/etc/cron.d','/etc/systemd/system','/var/spool/cron/crontabs']):
        for file in root.rglob('*'):
            if not file.is_file() or file.stat().st_size>1000000:continue
            for line in file.read_text(errors='replace').splitlines():
                if not line.lstrip().startswith('#') and pattern.search(line):refs.append(str(file))
    assert refs==[],refs
    spec=importlib.util.spec_from_file_location('update',CASE/'php-security-update-2026-10-22.py');update=importlib.util.module_from_spec(spec);spec.loader.exec_module(update)
    before=update.health();assert all(p['exit']==0 and p['code']=='200' for p in before.values())
    with (CASE/'ispconfig-php-choices-before.sql').open('w') as out:
        subprocess.run(['mysqldump','--skip-lock-tables','--no-create-info','dbispconfig','server_php'],stdout=out,check=True)
    if RC.exists() or RC.is_symlink():raise RuntimeError('Existe politica de paquetes')
    units=['php'+v+'-fpm.service' for v in BRANCHES]
    policy=json.loads(POLICY.read_text());assert not policy.get('php_security_retirement_units')
    policy['php_security_retirement_units']=units;store(POLICY,policy)
    try:
        actions=[]
        for unit in units:
            p=subprocess.run(['/usr/local/sbin/vps-service-control','retire-php-security',unit,'--origin','security-php-update'],capture_output=True,text=True,check=True,timeout=30)
            actions.append(json.loads(p.stdout));store(CASE/'retirement-actions.json',actions)
        with open('/run/lock/vigilante-restarts.lock','a') as lock:
            fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
            with RC.open('xb') as out:out.write(RC_BODY);os.fchmod(out.fileno(),0o755)
            try:
                env=dict(os.environ,DEBIAN_FRONTEND='noninteractive',NEEDRESTART_MODE='l')
                with (CASE/'apt-retire.log').open('w') as out:
                    subprocess.run(['apt-get','-y','purge']+names,env=env,stdout=out,stderr=out,check=True)
            finally:
                if RC.exists() and not RC.is_symlink() and RC.read_bytes()==RC_BODY:RC.unlink()
        assert sql('SELECT COUNT(*) FROM web_domain WHERE server_php_id IN ('+selected+')').strip()=='0'
        sql('UPDATE server_php SET active="n" WHERE server_php_id IN ('+selected+')')
        after=update.health();assert all(p['exit']==0 and p['code']=='200' for p in after.values())
        pools=update.probe_pools();audit=subprocess.check_output(['dpkg','--audit'],text=True);assert not audit.strip()
        for v in BRANCHES:
            p=subprocess.run(['systemctl','is-active','--quiet','php'+v+'-fpm.service']);assert p.returncode!=0
        store(CASE/'retirement-verification.json',{'completed':True,'branches_removed':BRANCHES,'packages_removed':len(names),'websites_http_200':len(after),'pools_verified':len(pools),'ispconfig_old_choices_disabled':IDS,'autoremove_used':False})
        print('Retirada y verificacion completadas',flush=True)
    finally:
        policy=json.loads(POLICY.read_text());policy.pop('php_security_retirement_units',None);store(POLICY,policy)


if __name__=='__main__':main()
