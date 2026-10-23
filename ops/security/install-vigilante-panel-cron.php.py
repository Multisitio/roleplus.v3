#!/usr/bin/python3
"""Migracion reversible con el bloqueo cron nativo de ISPConfig."""
import hashlib
import json
import os
from pathlib import Path
import subprocess
import sys

LIB = Path('/usr/local/lib/vigilante')
CASE_ROOT = Path('/root/codex-vigilante')
ISP_ROOT = Path('/usr/local/ispconfig')
CONFIG = Path('/etc/vigilante/panel-backups.json')
TARGETS = {name:LIB/name for name in ['vigilante-text-backups.py', 'vigilante-panel-backups.py',
    'vigilante-replica-export.py', 'vigilante-detect.py']}
TARGETS.update({'vigilante-panel-cron.php':Path('/usr/local/ispconfig/server/lib/classes/cron.d/500-sysbackup.inc.php'),
    'vigilante-panel-backups.service':Path('/etc/systemd/system/vigilante-panel-backups.service'),
    'vigilante-scheduled-jobs.json':Path('/etc/vigilante/scheduled-jobs.json')})


def execute(args, timeout=20):
    return subprocess.run(args, capture_output=True, text=True, check=True, timeout=timeout)


def put(path, raw, mode):
    temp=path.with_name(path.name+'.vigilante-new')
    with temp.open('xb') as out:
        os.fchmod(out.fileno(),mode);out.write(raw);out.flush();os.fsync(out.fileno())
    os.replace(temp,path)


def main(case):
    if os.geteuid()!=0 or 'cron.php' not in Path(sys.argv[0]).name:
        raise PermissionError('Identidad administrativa de migracion requerida')
    os.umask(0o077);case=Path(case).resolve(strict=True)
    if not case.is_relative_to(CASE_ROOT) or case.stat().st_mode&0o077:
        raise ValueError('Caso no privado')
    plan=json.loads((case/'deployment.json').read_text())
    if CONFIG.exists():raise ValueError('Ya migrado; no repetir')
    for name,path in TARGETS.items():
        if hashlib.sha256((case/name).read_bytes()).hexdigest()!=plan['source_sha256'][name]:
            raise ValueError('Fuente divergente')
        current=hashlib.sha256(path.read_bytes()).hexdigest() if path.exists() else None
        if path.is_symlink() or current!=plan['before_sha256'][name]:raise ValueError('Destino divergente')
    code="define('SCRIPT_PATH','/usr/local/ispconfig/server'); require SCRIPT_PATH.'/lib/config.inc.php'; echo $conf['temppath'];"
    parent=Path(execute(['/usr/bin/php','-r',code]).stdout.strip())
    if not parent.is_relative_to(ISP_ROOT):raise ValueError('Bloqueo inesperado')
    lock=parent/'.ispconfig_cron_lock'
    fd=os.open(lock,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    previous={};applied=False
    try:
        os.write(fd,str(os.getpid()).encode());os.fsync(fd)
        for unit in ['vigilante-text-backups.service','vigilante-sql-schedule.service','vigilante-panel-backups.service']:
            state=subprocess.run(['/usr/bin/systemctl','show',unit,'--property=ActiveState','--value'],capture_output=True,text=True).stdout.strip()
            if state in ('active','activating','deactivating'):raise ValueError('Hay otra captura en curso')
        for name,path in TARGETS.items():
            previous[name]=(path.read_bytes(),path.stat().st_mode&0o777) if path.exists() else None
            if previous[name]:(case/('before-'+name)).write_bytes(previous[name][0])
        for name,path in TARGETS.items():put(path,(case/name).read_bytes(),0o600 if path.parent==Path('/etc/vigilante') else 0o644)
        put(CONFIG,(json.dumps({'version':1,'copies':3,'cron_sha256':plan['source_sha256']['vigilante-panel-cron.php'],
            'source_commit':plan['source_commit'],'owner':'ISPConfig sysbackup','schedule':'0 3 * * *'})+'\n').encode(),0o600)
        execute(['/usr/bin/php','-l',str(TARGETS['vigilante-panel-cron.php'])])
        execute(['/usr/bin/systemd-analyze','verify',str(TARGETS['vigilante-panel-backups.service'])])
        execute(['/usr/bin/systemctl','daemon-reload'])
        execute(['/usr/bin/systemctl','start','vigilante-panel-backups.service'],timeout=190)
        status=json.loads(Path('/var/lib/vigilante-panel-backups/status.json').read_text())
        if not status.get('ok') or status.get('archives')!=3:raise ValueError('Primera captura no acreditada')
        applied=True
        (case/'deployment-result.json').write_text(json.dumps({'applied':True,'status':status,
            'web_restarts':0,'write_locks':0,'old_archives_deleted':0},indent=2)+'\n')
    finally:
        if not applied and previous:
            for name,old in previous.items():
                if old:put(TARGETS[name],old[0],old[1])
                elif TARGETS[name].exists():TARGETS[name].unlink()
            if CONFIG.exists():CONFIG.unlink()
            execute(['/usr/bin/systemctl','daemon-reload'])
            (case/'deployment-result.json').write_text(json.dumps({'applied':False,'rolled_back':True})+'\n')
        os.close(fd)
        if lock.exists() and lock.read_text().strip()==str(os.getpid()):lock.unlink()
    print(json.dumps({'applied':applied,'archives':3,'write_locks':0,'web_restarts':0}))


if __name__=='__main__':
    if len(sys.argv)!=3 or sys.argv[1]!='--apply':raise SystemExit(2)
    main(sys.argv[2])
