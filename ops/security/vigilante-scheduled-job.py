#!/usr/bin/python3
"""Catalogo cerrado de tareas heredadas; nunca acepta comandos del modelo."""
import fcntl
import importlib.util
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import tempfile
import time

CONFIG = Path('/etc/vigilante/scheduled-jobs.json')
STATE = Path('/var/lib/vigilante/jobs')


def trusted(path):
    s = path.lstat()
    if path.is_symlink() or s.st_uid != 0 or s.st_mode & 0o022:
        raise PermissionError('Fuente no fiable')


def save(path, value):
    fd, tmp = tempfile.mkstemp(dir=path.parent)
    try:
        with os.fdopen(fd, 'w') as out:
            os.fchmod(out.fileno(), 0o600)
            json.dump(value, out);out.flush();os.fsync(out.fileno())
        os.replace(tmp, path)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)


def load():
    trusted(CONFIG.parent);trusted(CONFIG)
    data = json.loads(CONFIG.read_text())
    if data['version'] != 1 or len(data['jobs']) > 32:
        raise ValueError('Catalogo no valido')
    for name, job in data['jobs'].items():
        if not re.fullmatch('[a-z][a-z0-9-]{1,48}', name) or not 1 <= job['seconds'] <= 7200:
            raise ValueError('Tarea no valida')
        if job['argv'][0] != '/usr/bin/php' or len(job['argv']) != 3:
            raise ValueError('Ejecutable no permitido')
        if not re.fullmatch(r'/var/www/(?:clients/client1/(?:web\d+|roleplus.app)|roleplus.app|mtgsearch.kumbia.app)/private/bin/kcli.php', job['argv'][1]):
            raise ValueError('Ruta no permitida')
        if not re.fullmatch(r'[a-z_]+/[a-z_/]+', job['argv'][2]):
            raise ValueError('Accion no permitida')
    return data


def run(name):
    config = load()
    if name not in config['jobs']:
        raise ValueError('Tarea no registrada')
    job = config['jobs'][name]
    trusted(STATE)
    with (STATE / (name+'.lock')).open('a') as lock:
        try:fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            print(json.dumps({'job':name, 'decision':'ocupado'}));return 0
        status = {'job':name, 'started_at':int(time.time()), 'completed':False,
                  'owner':'root_cron', 'seconds_limit':job['seconds']}
        save(STATE / (name+'.json'), status)
        args = command(name, job)
        # La unidad unica impide duplicados aunque muera el intermediario.
        # El plazo y la terminacion del grupo sobreviven a su desconexion.
        result = subprocess.run(args, timeout=job['seconds']+30)
        status.update(finished_at=int(time.time()), completed=result.returncode==0,
                      exit_code=result.returncode)
        save(STATE / (name+'.json'), status)
        print(json.dumps(status));return result.returncode


def command(name, job):
    return ['/usr/bin/systemd-run', '--quiet', '--wait', '--pipe', '--collect',
                '--unit=vigilante-job-'+name, '--service-type=oneshot',
                '--property=TimeoutStartSec='+str(job['seconds'])+'s',
                '--property=TimeoutStopSec=5s', '--property=KillMode=control-group',
                '--property=CPUQuota=100%', '--property=MemoryMax=1G',
                '--property=TasksMax=128', '--property=Nice=10',
                '--property=WorkingDirectory=/', '--setenv=PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin'] + job['argv']


def maintenance(host):
    path = Path('/usr/local/lib/vigilante/vigilante-maintenance.py')
    trusted(path.parent);trusted(path)
    spec = importlib.util.spec_from_file_location('maintenance',path)
    module = importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
    return bool(module.current(host))


def observe():
    config = load();now = int(time.time())
    faults = []
    for name, job in config['jobs'].items():
        path = STATE / (name+'.json')
        if not path.exists():continue  # Nunca ejecutada no equivale a fallo.
        trusted(path);value = json.loads(path.read_text())
        if (value.get('finished_at') and not value.get('completed')) or (
                not value.get('finished_at') and now-value['started_at'] > job['seconds']+30):
            faults.append(name)
    for path in STATE.glob('awstats-*.json'):
        trusted(path);value=json.loads(path.read_text())
        if (value.get('finished_at') and not value.get('completed')) or (
                not value.get('finished_at') and now-value['started_at']>210):
            faults.append(value['job'])
    # Consulta agregada: sin INFO, parametros, nombres, IP ni credenciales.
    query = ("SET SESSION max_statement_time=1; SELECT COUNT(*),"
             "COALESCE(MAX(TIME),0),SUM(COMMAND='Query'),"
             "COALESCE(SUM(STATE IN ('Creating sort index','Copying to tmp table')),0) "
             "FROM information_schema.PROCESSLIST WHERE ID<>CONNECTION_ID();")
    p = subprocess.run(['/usr/bin/mariadb','--no-defaults','--protocol=socket',
        '--socket=/run/mysqld/mysqld.sock','--user=root','--batch','--skip-column-names',
        '-e',query],capture_output=True,text=True,timeout=3)
    sql = [int(x or '0') for x in p.stdout.strip().split('\t')] if p.returncode==0 else []
    panel = subprocess.run(['/usr/bin/mariadb','--no-defaults','--user=root','--batch',
        '--skip-column-names','dbispconfig','-e',
        "SET SESSION max_statement_time=1; SELECT name FROM sys_cron WHERE running=1;"],capture_output=True,text=True,timeout=3)
    old_path=STATE.parent/'legacy-automation.json'
    old={}
    if old_path.exists():
        trusted(old_path);old=json.loads(old_path.read_text())
    previous=old.get('native_running_since',{}) if 0<=now-old.get('checked_at',0)<=180 else {}
    running={n:previous.get(n,now) for n in panel.stdout.splitlines() if re.fullmatch(r'[a-z_]+',n)}
    if panel.returncode: faults.append('ispconfig-observacion')
    else: faults.extend('ispconfig-'+n for n,since in running.items() if now-since>900)
    result = {'checked_at':now, 'sql_observation_only':True, 'query_termination_enabled':False,
              'sql_aggregate':sql, 'failed_jobs':faults, 'sql_probe_ok':p.returncode==0,
              'native_owners':config['native_owners'], 'native_running_since':running}
    save(STATE.parent / 'legacy-automation.json',result)
    print(json.dumps(result));return 0


if __name__ == '__main__':
    if os.geteuid() != 0:raise SystemExit('Solo administrador, sin sudo para la IA')
    if sys.argv[1:] == ['observe']:raise SystemExit(observe())
    if len(sys.argv)==3 and sys.argv[1]=='maintenance':raise SystemExit(0 if maintenance(sys.argv[2]) else 1)
    if len(sys.argv)==3 and sys.argv[1]=='run':raise SystemExit(run(sys.argv[2]))
    raise SystemExit(2)
