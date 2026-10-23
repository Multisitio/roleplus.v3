#!/usr/bin/python3
"""Proteccion textual de ISPConfig; propietario unico: su cron nativo."""
import fcntl
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import shutil
import subprocess
import time
import uuid

LIB = Path('/usr/local/lib/vigilante')
STATE = Path('/var/lib/vigilante-panel-backups')
DEST = Path('/var/backup/vigilante-panel-text')
CONFIG = Path('/etc/vigilante/panel-backups.json')
CRON = Path('/usr/local/ispconfig/server/lib/classes/cron.d/500-sysbackup.inc.php')
SOCKET = '/run/mysqld/mysqld.sock'
ROOTS = {'etc': Path('/etc'), 'ispconfig': Path('/usr/local/ispconfig')}
MAX_SQL = 64 * 1024**2


def module(name):
    spec = importlib.util.spec_from_file_location(name, LIB / (name + '.py'))
    obj = importlib.util.module_from_spec(spec); spec.loader.exec_module(obj); return obj


def sql(query):
    p = subprocess.run(['/usr/bin/mysql', '--no-defaults', '--protocol=socket',
        '--socket=' + SOCKET, '--user=root', '-NB'], input=query, text=True,
        capture_output=True, timeout=5)
    if p.returncode: raise ValueError('No se pudo contrastar el inventario del panel')
    return p.stdout.strip()


def inventory():
    rows = sql("SELECT TABLE_NAME,TABLE_TYPE,COALESCE(ENGINE,'NULL') FROM information_schema.TABLES "
        "WHERE TABLE_SCHEMA='dbispconfig' ORDER BY TABLE_NAME").splitlines()
    if not rows or len(rows) > 256: raise ValueError('Inventario del panel inesperado')
    for row in rows:
        name, kind, engine = row.split('\t')
        if kind != 'BASE TABLE' or engine != 'InnoDB':
            raise ValueError('Cambio de motor o tipo; no iniciar copia sin revisar su consistencia')
    return rows


def schema(rows):
    import re
    names = [row.split('\t')[0] for row in rows]
    if any(not re.fullmatch(r'[a-zA-Z0-9_]{1,64}', name) for name in names):
        raise ValueError('Nombre de tabla inesperado')
    return hashlib.sha256(sql(';'.join('SHOW CREATE TABLE dbispconfig.`' + name + '`' for name in names)).encode()).hexdigest()


def capture_sql(path):
    before = inventory(); before_schema = schema(before)
    backup = module('vigilante-sql-backup')
    # InnoDB exclusivamente: instantanea transaccional sin LOCK TABLES ni bloqueo global.
    with path.with_suffix('.errors').open('xb') as errors:
        proc = backup.spawn(backup.command('dbispconfig', SOCKET, path, 8), 12,
            stdout=subprocess.DEVNULL, stderr=errors)
        try: proc.wait(timeout=13)
        finally:
            if proc.poll() is None: backup.stop_dump(proc)
        if proc.returncode: raise ValueError('Volcado transaccional incompleto o fuera de plazo')
    if inventory() != before or schema(before) != before_schema:
        raise ValueError('Cambio de esquema durante la captura')
    if not 0 < path.stat().st_size <= MAX_SQL: raise ValueError('SQL fuera de presupuesto')
    with path.open('rb') as stream:
        stream.seek(max(0, path.stat().st_size - 4096))
        if b'-- Dump completed on ' not in stream.read(): raise ValueError('SQL sin cierre')
    return len(before)


def observation(now):
    status = json.loads((STATE / 'status.json').read_text())
    config = json.loads(CONFIG.read_text())
    drift = hashlib.sha256(CRON.read_bytes()).hexdigest() != config['cron_sha256']
    return {'failed': not status.get('ok') or now - status.get('last_success', 0) > 27 * 3600 or drift,
        'last_success': status.get('last_success'), 'owner_changed': drift,
        'datasets': 3, 'text_only': True}


def run():
    if os.geteuid() != 0: raise PermissionError('Solo el ejecutor administrativo')
    os.umask(0o077)
    text = module('vigilante-text-backups'); stream = module('vigilante-sql-stream')
    meta = CONFIG.lstat()
    if CONFIG.is_symlink() or meta.st_uid != 0 or meta.st_mode & 0o077:
        raise ValueError('Configuracion no privada')
    conf = json.loads(CONFIG.read_text())
    if conf['copies'] != 3 or hashlib.sha256(CRON.read_bytes()).hexdigest() != conf['cron_sha256']:
        raise ValueError('Propietario o retencion cambiados')
    STATE.mkdir(mode=0o700, exist_ok=True); DEST.mkdir(mode=0o700, exist_ok=True)
    with text.directory(STATE), text.directory(DEST): pass
    with (STATE / 'lock').open('a') as lock:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        ledger_path = STATE / 'ledger.json'
        ledger = json.loads(ledger_path.read_text()) if ledger_path.exists() else {'generations': []}
        pending = STATE / 'pending.json'
        if pending.exists(): raise ValueError('Captura interrumpida pendiente de revision')
        today = time.strftime('%Y-%m-%d')
        if ledger['generations'] and ledger['generations'][-1]['day'] == today:
            # Repetir el disparo no duplica una captura del mismo dia.
            return
        if shutil.disk_usage(DEST).free < 20 * 1024**3 + 2 * text.MAX_ARCHIVE + MAX_SQL:
            raise ValueError('No queda la reserva de disco requerida')
        ident = time.strftime('%Y-%m-%d_%H-%M-%S') + '-' + uuid.uuid4().hex[:8]
        stage = DEST / ('.pending-' + ident); final = DEST / ident
        if any(DEST.iterdir()):
            known = {x['name'] for x in ledger['generations']}
            if any(p.name not in known for p in DEST.iterdir()):
                raise ValueError('Archivo ajeno o captura incompleta en el almacen')
        text.save(pending, {'name': ident, 'started_at': int(time.time())})
        stage.mkdir(mode=0o700)
        artifacts = []
        try:
            raw = stage / 'dbispconfig.sql'
            tables = capture_sql(raw)
            zipped = stage / 'dbispconfig.sql.gz'
            data = stream.compress(raw, zipped)
            raw.unlink(); raw.with_suffix('.errors').unlink()
            artifacts.append({'dataset': 'sql:dbispconfig', 'kind': 'sql', 'name': zipped.name,
                'sha256': data['gzip_sha256'], 'bytes': zipped.stat().st_size, 'tables': tables})
            for name, root in ROOTS.items():
                path = stage / (name + '.tar.gz')
                manifest = text.archive(root, path, system_config=True)
                text.verify(path, manifest)
                manifest_name = name + '.manifest.json'
                text.save(stage / manifest_name, manifest)
                artifacts.append({'dataset': 'text:system-' + name, 'kind': 'text', 'name': path.name,
                    'sha256': stream.digest(path), 'bytes': path.stat().st_size,
                    'manifest': manifest_name, 'files': len(manifest['files']), 'excluded': manifest['excluded']})
            generation = {'name': ident, 'day': today, 'created_at': int(time.time()), 'artifacts': artifacts}
            text.save(stage / 'generation.json', generation)
            os.rename(stage, final)
            ledger['generations'].append(generation)
            text.save(ledger_path, ledger)
            pending.unlink()
            # Solo versiones propias y verificadas; nunca archivos heredados de ISPConfig.
            while len(ledger['generations']) > conf['copies']:
                old = ledger['generations'][0]; folder = DEST / old['name']
                if folder.is_symlink() or folder.parent != DEST or not folder.is_dir():
                    raise ValueError('Version anterior no fiable')
                expected = {'generation.json'}
                for item in old['artifacts']:
                    if Path(item['name']).name != item['name']: raise ValueError('Nombre inesperado')
                    expected.add(item['name'])
                    if item.get('manifest'): expected.add(item['manifest'])
                    if stream.digest(folder / item['name']) != item['sha256']:
                        raise ValueError('Version anterior cambiada; no eliminar')
                if {p.name for p in folder.iterdir()} != expected:
                    raise ValueError('Archivos ajenos en version anterior')
                for filename in expected: (folder / filename).unlink()
                folder.rmdir(); ledger['generations'].pop(0); text.save(ledger_path, ledger)
            stats = [{'file_name': a['name'], 'backup_type': 'mysql' if a['kind'] == 'sql' else 'file',
                'backup_date': g['name'][:19], 'size_mb': round(a['bytes'] / 1024**2, 2)}
                for g in ledger['generations'] for a in g['artifacts']]
            text.save(STATE / 'status.json', {'ok': True, 'last_success': int(time.time()),
                'generation': ident, 'stats': stats, 'archives': len(stats), 'binary_backups': False})
        except Exception:
            # Mantener copias buenas y evidencia pendiente, incluso si muere el servicio.
            previous = json.loads((STATE / 'status.json').read_text()) if (STATE / 'status.json').exists() else {}
            text.save(STATE / 'status.json', {**previous, 'ok': False, 'failed_at': int(time.time())})
            raise


if __name__ == '__main__':
    try: run()
    except Exception:
        print('Copia textual del panel fallida; conservar versiones anteriores y revisar estado privado.')
        raise SystemExit(1)
