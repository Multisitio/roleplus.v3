#!/usr/bin/python3
"""Migracion revisada de automatismos, sin reiniciar servicios de las webs."""
import fcntl
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import tempfile
import time

CASE=Path('/root/codex-vigilante/tramo-31-2026-10-20')
LIB=Path('/usr/local/lib/vigilante')


def sha(data):return hashlib.sha256(data).hexdigest()


def atomic(path,content,mode):
    fd,tmp=tempfile.mkstemp(dir=path.parent)
    try:
        with os.fdopen(fd,'wb') as out:
            os.fchmod(out.fileno(),mode);out.write(content);out.flush();os.fsync(out.fileno())
        os.replace(tmp,path)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)


def sql(query):
    p=subprocess.run(['mariadb','--no-defaults','--batch','--skip-column-names','dbispconfig'],
        input=query,text=True,capture_output=True,timeout=5)
    if p.returncode:raise RuntimeError('No se pudo contrastar ISPConfig')
    return p.stdout.strip()


def main():
    if os.geteuid()!=0:raise PermissionError('Solo administrador')
    os.umask(0o077)
    expected=json.loads((CASE/'vigilante-legacy-baseline.json').read_text())
    checks={'watchdog':Path('/usr/local/sbin/webwatchdog.sh'),
        'policy':Path('/etc/vigilante/policy.json'),
        'vps-service-control.py':LIB/'vps-service-control.py',
        'vigilante-detect.py':LIB/'vigilante-detect.py'}
    for key,p in checks.items():
        if sha(p.read_bytes())!=expected[key]:raise ValueError('Fuente instalada divergente: '+key)
    before_cron=subprocess.check_output(['crontab','-l'])
    if sha(before_cron)!=expected['root_cron']:raise ValueError('Cron modificado desde el inventario')
    catalog=json.loads((CASE/'vigilante-scheduled-jobs.json').read_text())
    lines=before_cron.decode().splitlines();count=0
    for name,job in catalog['jobs'].items():
        matches=[i for i,l in enumerate(lines) if job['argv'][1] in l and job['argv'][2] in l]
        if len(matches)!=1:raise ValueError('Tarea sin correspondencia unica')
        i=matches[0]
        if not lines[i].startswith(job['schedule']+' '):raise ValueError('Horario divergente')
        suffix=''
        if '>>' in lines[i]:suffix=' >>'+lines[i].split('>>',1)[1]
        lines[i]=job['schedule']+' /usr/bin/python3 -I -B /usr/local/lib/vigilante/vigilante-scheduled-job.py run '+name+suffix
        count+=1
    if count!=9:raise ValueError('Numero de tareas inesperado')
    config=bytes.fromhex(sql('SELECT HEX(config) FROM server WHERE server_id=1;'))
    original=b'awstats_buildstaticpages_pl=/usr/share/awstats/tools/awstats_buildstaticpages.pl'
    replacement=b'awstats_buildstaticpages_pl=/usr/local/lib/vigilante/vigilante-awstats-run.py'
    if config.count(original)!=1:raise ValueError('Responsable de estadisticas divergente')
    before=CASE/'before';before.mkdir(mode=0o700)
    for key,p in checks.items():shutil.copyfile(p,before/key)
    (before/'root.cron').write_bytes(before_cron)
    (before/'ispconfig-server-config.private').write_bytes(config)
    for name in ['apache2','mariadb','php8.4-fpm']:
        (before/(name+'.pid')).write_bytes(subprocess.check_output(['systemctl','show',name,'-p','MainPID','--value']))
    # Solo se pausa el disparador de comprobaciones durante la migracion del
    # estado. No se termina una comprobacion activa ni un servicio de usuarios.
    subprocess.run(['systemctl','stop','webwatchdog.timer'],check=True)
    try:
        for _ in range(150):
            if subprocess.run(['systemctl','is-active','--quiet','webwatchdog.service']).returncode:break
            time.sleep(0.1)
        else:raise RuntimeError('Comprobacion en curso; no se interrumpe')
        with open('/run/lock/vigilante-restarts.lock','a') as lock:
            fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
            state=Path('/var/lib/vigilante/antipanico');state.mkdir(mode=0o700);state.chmod(0o700)
            panel=Path('/var/www/clients/client1/web17/web/webwatchdog')
            copied=0
            for site in json.loads(checks['policy'].read_text())['sites']:
                prefix='https___'+site
                for suffix in ['fail','status','restart_done','last_ok','last_ok_madrid','last_fail','last_fail_madrid','code']:
                    p=panel/(prefix+'.'+suffix)
                    if not p.exists():continue
                    if p.is_symlink() or p.stat().st_uid!=0 or p.stat().st_size>128:raise ValueError('Estado legado no fiable')
                    content=p.read_bytes();value=content.decode().strip()
                    if suffix=='status' and value not in ['up','down']:raise ValueError('Estado no valido')
                    if suffix in ['fail','restart_done','code'] and not re.fullmatch(r'\d{1,3}',value):raise ValueError('Contador no valido')
                    if suffix.startswith('last_') and not re.fullmatch(r'[0-9T:Z+ -]*',value):raise ValueError('Fecha no valida')
                    atomic(state/p.name,content,0o600);copied+=1
            snapshot=json.loads((panel/'status.json').read_text())
            if {r['url'][8:] for r in snapshot['sites']}!=set(json.loads(checks['policy'].read_text())['sites']):
                raise ValueError('Catalogo del panel divergente')
            atomic(state/'status.json',json.dumps(snapshot).encode(),0o600)
            (Path('/var/lib/vigilante')/'jobs').mkdir(mode=0o700,exist_ok=True)
            for name in ['vps-service-control.py','vigilante-detect.py','vigilante-scheduled-job.py','vigilante-awstats-run.py']:
                atomic(LIB/name,(CASE/name).read_bytes(),0o755 if name=='vigilante-awstats-run.py' else 0o644)
            atomic(Path('/usr/local/sbin/vps-service-control'),(CASE/'vps-service-control.py').read_bytes(),0o755)
            atomic(checks['watchdog'],(CASE/'webwatchdog.sh').read_bytes(),0o755)
            atomic(Path('/etc/vigilante/scheduled-jobs.json'),(CASE/'vigilante-scheduled-jobs.json').read_bytes(),0o600)
            policy=json.loads(checks['policy'].read_text())
            policy['owners']['sql_query_termination']='disabled_observation_only'
            policy['owners']['scheduled_jobs']='closed_catalog_preserving_native_owners'
            policy['owners']['antipanic_state']='private_root_only_with_read_only_web_replica'
            atomic(checks['policy'],(json.dumps(policy,indent=2)+'\n').encode(),0o600)
            atomic(Path('/etc/systemd/system/vigilante-legacy-observe.service'),(CASE/'vigilante-legacy-observe.service').read_bytes(),0o644)
            hook=Path('/etc/systemd/system/webwatchdog.service.d/40-vigilante-legacy.conf')
            atomic(hook,b'[Service]\nExecStartPost=-/usr/bin/systemctl --no-block start vigilante-legacy-observe.service\n',0o644)
            updated=config.replace(original,replacement)
            changed=sql("UPDATE server SET config=UNHEX('"+updated.hex()+"') WHERE server_id=1 AND config=UNHEX('"+config.hex()+"'); SELECT ROW_COUNT();")
            if changed!='1':raise RuntimeError('ISPConfig cambio durante la migracion')
            subprocess.run(['crontab','-'],input=('\n'.join(lines)+'\n').encode(),check=True)
            # El antiguo script pierde la credencial y la capacidad de KILL.
            oldunit=Path('/etc/systemd/system/mysql-query-killer.service')
            if oldunit.is_file() and not oldunit.is_symlink():oldunit.rename(before/'mysql-query-killer.service')
            atomic(Path('/usr/local/bin/mysql-query-killer.sh'),b'#!/bin/sh\nprintf "%s\\n" "Terminador SQL retirado; observacion central del vigilante."\nexit 0\n',0o755)
            subprocess.run(['systemctl','mask','mysql-query-killer.service'],check=True,capture_output=True)
            subprocess.run(['systemctl','daemon-reload'],check=True)
            report={'completed':True,'root_cron_jobs':count,'schedules_unchanged':True,
                'private_state_files':copied,'sql_killer_masked':True,'ispconfig_awstats_reflected':True,
                'services_restarted':False,'ai_actions_enabled':False}
            atomic(CASE/'installation.json',(json.dumps(report,indent=2)+'\n').encode(),0o600)
            print(json.dumps(report))
    finally:
        subprocess.run(['systemctl','start','webwatchdog.timer'],check=True)
    subprocess.run(['systemctl','start','vigilante-legacy-observe.service'],check=True)


if __name__=='__main__':main()
