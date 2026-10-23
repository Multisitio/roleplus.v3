#!/usr/bin/python3
"""Migracion administrativa reversible; el nombre conserva el protocolo cron.php de ISPConfig."""
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import subprocess
import sys
import time

LIB = Path('/usr/local/lib/vigilante')
NATIVE = Path('/usr/local/ispconfig/server/lib/classes/cron.d/500-backup.inc.php')
TARGETS = {
    'vigilante-sql-schedule.py': LIB / 'vigilante-sql-schedule.py',
    'vigilante-sql-window.py': LIB / 'vigilante-sql-window.py',
    'vigilante-detect.py': LIB / 'vigilante-detect.py',
    'vigilante-sql-cron.php': NATIVE,
    'vigilante-sql-schedule.service': Path('/etc/systemd/system/vigilante-sql-schedule.service'),
    'vigilante-scheduled-jobs.json': Path('/etc/vigilante/scheduled-jobs.json'),
}
CONFIG = Path('/etc/vigilante/sql-schedule.json')
CASE_ROOT = Path('/root/codex-vigilante')
ISP_ROOT = Path('/usr/local/ispconfig')


def run(args):
    return subprocess.run(args, capture_output=True, text=True, check=True, timeout=25)


def put(path, content, mode):
    temporary = path.with_name(path.name + '.vigilante-new')
    with temporary.open('xb') as stream:
        os.fchmod(stream.fileno(), mode); stream.write(content); stream.flush(); os.fsync(stream.fileno())
    os.replace(temporary, path)


def main(case):
    if os.geteuid() != 0 or 'cron.php' not in Path(sys.argv[0]).name:
        raise PermissionError('Instalador administrativo con identidad cron.php requerido')
    os.umask(0o077)
    case = Path(case).resolve(strict=True)
    if not case.is_relative_to(CASE_ROOT) or case.stat().st_mode & 0o077:
        raise ValueError('Caso no privado')
    plan = json.loads((case / 'deployment.json').read_text())
    if CONFIG.exists(): raise ValueError('Ya existe un propietario SQL central; no repetir la migracion')
    for name, target in TARGETS.items():
        raw = (case / name).read_bytes()
        if hashlib.sha256(raw).hexdigest() != plan['source_sha256'][name]:
            raise ValueError('Fuente cambiada desde la revision')
        expected = plan['before_sha256'][name]
        if target.is_symlink() or (hashlib.sha256(target.read_bytes()).hexdigest() if target.exists() else None) != expected:
            raise ValueError('Destino cambiado desde la revision')
    spec = importlib.util.spec_from_file_location('preflight', case / 'vigilante-sql-schedule.py')
    candidate = importlib.util.module_from_spec(spec); spec.loader.exec_module(candidate)
    candidate.LIB = LIB
    candidate.catalogue(candidate.module('vigilante-sql-backup')); candidate.validate_aliases()
    if candidate.schedule(int(time.time()))[0]: raise ValueError('No migrar durante la ventana SQL')
    query = "define('SCRIPT_PATH','/usr/local/ispconfig/server'); require SCRIPT_PATH.'/lib/config.inc.php'; echo $conf['temppath'];"
    parent = Path(run(['/usr/bin/php','-r',query]).stdout.strip())
    if not parent.is_absolute() or not parent.is_relative_to(ISP_ROOT):
        raise ValueError('Ruta del bloqueo ISPConfig inesperada')
    lock = parent / '.ispconfig_cron_lock'
    fd = os.open(lock, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600)
    applied = False; previous = {}; timer_enabled = False
    report = {'applied': False, 'production_sql_dumps': 0, 'web_restarts': 0}
    try:
        os.write(fd, str(os.getpid()).encode()); os.fsync(fd)
        if run(['/usr/bin/systemctl','show','vigilante-independent-sql.service','--property=ActiveState','--value']).stdout.strip() not in ('inactive','failed'):
            raise ValueError('Copia independiente en curso; no migrar')
        for name, target in TARGETS.items():
            previous[name] = (target.read_bytes(), target.stat().st_mode & 0o777) if target.exists() else None
            if previous[name]:
                (case / ('before-' + name)).write_bytes(previous[name][0])
        timer_enabled = subprocess.run(['/usr/bin/systemctl','is-enabled','vigilante-independent-sql.timer'],capture_output=True,text=True).stdout.strip() == 'enabled'
        run(['/usr/bin/systemctl','disable','--now','vigilante-independent-sql.timer'])
        for name, target in TARGETS.items():
            mode = 0o600 if target.parent == Path('/etc/vigilante') else 0o644
            put(target, (case / name).read_bytes(), mode)
        spec = importlib.util.spec_from_file_location('sql_schedule', LIB / 'vigilante-sql-schedule.py')
        module = importlib.util.module_from_spec(spec); spec.loader.exec_module(module)
        module.catalogue(module.module('vigilante-sql-backup')); module.validate_aliases()
        eligible, _, first_at = module.schedule(int(time.time()))
        if eligible: raise ValueError('Migrar fuera de la ventana de copia; no iniciar bloqueos ahora')
        config = {'version': 1, 'cron_sha256': plan['source_sha256']['vigilante-sql-cron.php'],
            'first_at': first_at, 'source_commit': plan['source_commit'], 'owner': 'ISPConfig cron'}
        put(CONFIG, (json.dumps(config, indent=2) + '\n').encode(), 0o600)
        module.ownership()
        run(['/usr/bin/systemd-analyze','verify',str(TARGETS['vigilante-sql-schedule.service'])])
        run(['/usr/bin/systemctl','daemon-reload'])
        run(['/usr/bin/systemctl','start','vigilante-sql-schedule.service'])
        latest = json.loads((module.STATE / 'latest.json').read_text())
        if latest.get('reason') != 'outside_window' or not latest.get('deferred'):
            raise ValueError('No se acredita el aplazamiento sin bloqueos')
        report.update(applied=True, first_at=first_at, native_owner='ISPConfig cron',
            independent_timer_disabled=True, deferred_without_dump=latest)
        applied = True
    finally:
        if not applied and previous:
            for name, original in previous.items():
                target = TARGETS[name]
                if original: put(target, original[0], original[1])
                elif target.exists(): target.unlink()
            if CONFIG.exists(): CONFIG.unlink()
            run(['/usr/bin/systemctl','daemon-reload'])
            if timer_enabled: run(['/usr/bin/systemctl','enable','--now','vigilante-independent-sql.timer'])
        (case / 'deployment-result.json').write_text(json.dumps(report, indent=2) + '\n')
        os.close(fd)
        if lock.exists() and lock.read_text().strip() == str(os.getpid()): lock.unlink()
    print(json.dumps(report))


if __name__ == '__main__':
    if len(sys.argv) != 3 or sys.argv[1] != '--apply': raise SystemExit(2)
    main(sys.argv[2])
