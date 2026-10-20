#!/usr/bin/env python3
"""Un unico propietario de las copias de las bases sin dominio, sin reasignarlas."""
from datetime import datetime, timedelta
import fcntl
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import shutil
import time
import uuid
from zoneinfo import ZoneInfo

CONFIG = Path('/etc/vigilante/independent-sql.json')
STATE = Path('/var/lib/vigilante-independent-sql')
DEST = Path('/var/backup/vigilante-independent-sql')


def save(path, value):
    tmp = path.with_suffix('.tmp')
    with tmp.open('w') as stream:
        os.fchmod(stream.fileno(), 0o600)
        json.dump(value, stream, indent=2);stream.write('\n');stream.flush();os.fsync(stream.fileno())
    os.replace(tmp, path)


def next_run(now):
    current = datetime.fromtimestamp(now, ZoneInfo('Europe/Madrid'))
    target = current.replace(hour=4, minute=30, second=0, microsecond=0)
    target += timedelta(days=(6 - current.weekday()) % 7)
    if target.timestamp() <= now:target += timedelta(days=7)
    return int(target.timestamp())


def main():
    if os.geteuid() != 0:raise PermissionError('Solo administrador')
    os.umask(0o077)
    info = CONFIG.lstat()
    if CONFIG.is_symlink() or info.st_uid != 0 or info.st_mode & 0o077:
        raise ValueError('Configuracion privada requerida')
    STATE.mkdir(mode=0o700, exist_ok=True)
    with (STATE / 'operations.lock').open('a') as lock:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        ledger_path = STATE / 'ledger.json'
        ledger = json.loads(ledger_path.read_text()) if ledger_path.exists() else {'version': 1, 'archives': []}
        now = int(time.time())
        if ledger.get('next_at', 0) > now:
            print(json.dumps({'completed': True, 'due': False}));return
        if shutil.disk_usage('/var/backup').free < 20 * 1024 ** 3 + 32 * 1024 ** 2:
            raise ValueError('Margen de disco insuficiente')
        known = {x['filename'] for x in ledger['archives']}
        if {p.name for p in DEST.glob('db_*.sql.gz')} != known:
            raise ValueError('Hay una copia interrumpida; conciliar antes de repetir')
        case = STATE / ('run-' + time.strftime('%Y%m%d-%H%M%S') + '-' + uuid.uuid4().hex[:8])
        case.mkdir(mode=0o700)
        plan = json.loads(CONFIG.read_text());save(case / 'plan.json', plan)
        spec = importlib.util.spec_from_file_location('backup', '/usr/local/lib/vigilante/vigilante-sql-backup.py')
        backup = importlib.util.module_from_spec(spec);spec.loader.exec_module(backup)
        try:
            backup.main(case / 'plan.json')
        finally:
            result_path = case / 'sql-result.json'
            if result_path.exists():
                result = json.loads(result_path.read_text())
                ledger['archives'].extend(result.get('databases', []))
                ledger['last_result'] = str(result_path)
                if result.get('completed'):
                    ledger['last_success'] = now;ledger['next_at'] = next_run(now)
                save(ledger_path, ledger)
                save(STATE / 'latest.json', result)
        # Solo las siete copias propias mas recientes de cada base revisada.
        for database in plan['databases']:
            items = [x for x in ledger['archives'] if x['name'] == database['name']]
            while len(items) > 7:
                old = items.pop(0);filename = old['filename']
                pattern = r'db_' + re.escape(database['name']) + r'_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql\.gz'
                if not re.fullmatch(pattern, filename):raise ValueError('Referencia no valida')
                path = DEST / filename
                if path.is_symlink() or hashlib.sha256(path.read_bytes()).hexdigest() != old['gzip_sha256']:
                    raise ValueError('La copia anterior ha cambiado')
                path.unlink();ledger['archives'].remove(old);save(ledger_path, ledger)


if __name__ == '__main__':main()
