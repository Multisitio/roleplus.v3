#!/usr/bin/python3
"""Unico ejecutor semanal SQL: ISPConfig solicita, las barreras deciden."""
from datetime import datetime, timedelta
from zoneinfo import ZoneInfo
import fcntl
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import shutil
import stat
import time
import uuid

LIB = Path(__file__).resolve().parent
STATE = Path('/var/lib/vigilante-sql-schedule')
CONFIG = Path('/etc/vigilante/sql-schedule.json')
NATIVE = Path('/usr/local/ispconfig/server/lib/classes/cron.d/500-backup.inc.php')
BACKUPS = Path('/var/backup')
SYSTEMD = Path('/etc/systemd/system')
MAPPED = (
    (1, 'c1_controlate', 26, 'controlate.kumbia.app', ()),
    (3, 'c1_mtgsearch', 4, 'mtgsearch.kumbia.app', ('*.mtgsearch.kumbia.app',)),
    (4, 'c1_roleplus', 6, 'roleplus.app', ('*.roleplus.app', '.roleplus.app')),
    (6, 'c1_delixio', 24, 'delixiomarket.com', ('*.delixiomarket.com',)),
    (8, 'c1_mansion', 15, 'mansion.kumbia.app', ('*.mansion.kumbia.app',)),
    (9, 'c1_multisitio', 17, 'sitios.kumbia.app', ()),
    (10, 'c1_rola', 16, 'rola.kumbia.app', ()),
)


def module(name):
    spec = importlib.util.spec_from_file_location(name.replace('-', '_'), LIB / (name + '.py'))
    loaded = importlib.util.module_from_spec(spec); spec.loader.exec_module(loaded)
    return loaded


def private(path, directory=False):
    info = path.lstat()
    if (path.is_symlink() or info.st_uid != 0 or info.st_mode & 0o077
            or (directory and not stat.S_ISDIR(info.st_mode))):
        raise ValueError('Ruta SQL no privada')


def save(path, value):
    temporary = path.with_name(path.name + '.' + uuid.uuid4().hex + '.tmp')
    with temporary.open('x') as stream:
        os.fchmod(stream.fileno(), 0o600)
        json.dump(value, stream, indent=2); stream.write('\n'); stream.flush(); os.fsync(stream.fileno())
    os.replace(temporary, path)


def schedule(now):
    current = datetime.fromtimestamp(now, ZoneInfo('Europe/Madrid'))
    start = current.replace(hour=4, minute=45, second=0, microsecond=0)
    end = start.replace(hour=5, minute=15)
    eligible = current.weekday() == 6 and start <= current < end
    target = start + timedelta(days=(6 - current.weekday()) % 7)
    if target.timestamp() <= now and not eligible:
        target += timedelta(days=7)
    return eligible, current.strftime('%G-W%V'), int(target.timestamp())


def catalogue(backup):
    rows = backup.query("SELECT b.database_id,b.database_name,b.parent_domain_id,b.backup_interval,"
        "b.backup_copies,COALESCE(d.domain,''),COALESCE(d.active,'') FROM web_database b "
        "LEFT JOIN web_domain d ON d.domain_id=b.parent_domain_id WHERE b.active='y' "
        "AND b.server_id=1 ORDER BY b.database_id;")
    expected = {str(i): (name, str(domain), host) for i, name, domain, host, _ in MAPPED}
    expected.update({'5': ('c1_carmenarnaumuro', '0', ''), '7': ('c1_deportes', '0', '')})
    seen = set()
    for row in rows.splitlines():
        parts = row.split('\t')
        if len(parts) != 7: raise ValueError('Catalogo SQL no reconocido')
        ident, name, domain, interval, copies, host, active = parts
        if (ident not in expected or (name, domain, host) != expected[ident]
                or interval != 'weekly' or copies != ('1' if domain == '0' else '7')
                or (domain != '0' and active != 'y')):
            raise ValueError('La politica o correspondencia SQL ha cambiado')
        seen.add(ident)
    if seen != set(expected): raise ValueError('Cobertura SQL incompleta')
    if backup.query("SELECT COUNT(*) FROM web_domain WHERE server_id=1 AND active='y' "
        "AND backup_interval NOT IN ('','none');") != '0':
        raise ValueError('Hay copias web nativas fuera del coordinador de texto')


def ownership():
    private(CONFIG)
    config = json.loads(CONFIG.read_text())
    if config.get('version') != 1 or hashlib.sha256(NATIVE.read_bytes()).hexdigest() != config.get('cron_sha256'):
        raise ValueError('El programador SQL no coincide con el propietario unico')
    native_info = NATIVE.lstat()
    if NATIVE.is_symlink() or native_info.st_uid != 0 or native_info.st_mode & 0o022:
        raise ValueError('Programador SQL modificable por terceros')
    # Una unidad habilitada es otro programador, aunque no este ejecutandose ahora.
    for enabled in SYSTEMD.glob('*.wants/vigilante-independent-sql.timer'):
        if enabled.is_symlink(): raise ValueError('El temporizador SQL heredado sigue habilitado')


def validate_aliases():
    import re
    for _, _, _, host, aliases in MAPPED:
        paths = []
        for path in Path('/etc/apache2/sites-enabled').glob('*.vhost'):
            data = path.read_text()
            if re.search(r'(?m)^\s*ServerName\s+' + re.escape(host) + r'\s*$', data):
                paths.append(data)
        if len(paths) != 1: raise ValueError('Servidor virtual no univoco')
        actual = set()
        for match in re.finditer(r'(?m)^\s*ServerAlias\s+([^\r\n]+)', paths[0]):
            actual.update(match.group(1).split())
        if actual != set(aliases): raise ValueError('Han cambiado los alias de la web')


def archive(item, ledger, backup, connector, compressor):
    ident, name, domain, host, aliases = item
    if shutil.disk_usage(BACKUPS).free < 20 * 1024 ** 3 + 2 * 1024 ** 3:
        raise ValueError('Reserva de disco insuficiente para copia SQL')
    dest = BACKUPS / ('web' + str(domain)); dest.mkdir(mode=0o700, exist_ok=True)
    meta = dest.lstat()
    if dest.is_symlink() or meta.st_uid != 0 or meta.st_mode & 0o022:
        raise ValueError('Destino SQL no fiable')
    case = STATE / ('run-' + uuid.uuid4().hex); case.mkdir(mode=0o700)
    raw = case / 'dump.sql'
    filename = 'db_' + name + '_' + time.strftime('%Y-%m-%d_%H-%M-%S') + '.sql.gz'
    target = dest / filename
    partial = dest / (filename + '.partial')
    if target.exists() or partial.exists(): raise ValueError('No sobrescribir una copia SQL')
    ledger['pending'] = {'database': name, 'case': str(case), 'target': str(target)}
    save(STATE / 'ledger.json', ledger)
    counts = {}
    seconds = connector.LIMITS[name][1] if name in connector.LIMITS else backup.MAX_SECONDS
    elapsed = connector.dump(name, raw, [host] + list(aliases), seconds, counts)
    metadata = compressor.compress(raw, partial)
    # La compresion verifica CRC, huellas y texto despues de liberar READ.
    os.rename(partial, target)
    entry = dict(metadata, database_id=ident, name=name, domain_id=domain, filename=filename,
        source=str(target), captured_at=int(time.time()), dump_seconds=round(elapsed, 4),
        row_counts_under_lock=counts, strict_read_guard=True, restored_in_isolation=False)
    backup.query('INSERT INTO web_backup(server_id,parent_domain_id,backup_type,backup_mode,'
        'backup_format,tstamp,filename,filesize,backup_password) VALUES(1,' + str(domain)
        + ",'mysql','sqlgz','gzip'," + str(entry['captured_at']) + ",'" + filename
        + "','" + str(metadata['gzip_bytes']) + "','');")
    ledger['archives'].append(entry)
    ledger['pending'] = None
    save(STATE / 'ledger.json', ledger)
    raw.unlink()  # Solo el temporal propio, despues de verificar su sustituto textual.
    return entry


def prune(ledger, backup, compressor):
    for ident, name, domain, _, _ in MAPPED:
        own = [e for e in ledger['archives'] if e['database_id'] == ident]
        for old in own[:-7]:
            path = BACKUPS / ('web' + str(domain)) / old['filename']
            if str(path) != old['source'] or path.is_symlink() or compressor.digest(path) != old['gzip_sha256']:
                raise ValueError('La copia anterior ha cambiado; no se retira')
            backup.query("DELETE FROM web_backup WHERE server_id=1 AND parent_domain_id=" + str(domain)
                + " AND backup_type='mysql' AND filename='" + old['filename'] + "';")
            path.unlink()
            ledger['archives'].remove(old); save(STATE / 'ledger.json', ledger)


def main(plan_only=False):
    if os.geteuid() != 0: raise PermissionError('Solo controlador de administrador')
    os.umask(0o077); STATE.mkdir(mode=0o700, exist_ok=True); private(STATE, True)
    with (STATE / 'operations.lock').open('a') as lock:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        backup = module('vigilante-sql-backup')
        ownership(); catalogue(backup); validate_aliases()
        now = int(time.time()); eligible, week, next_at = schedule(now)
        ledger_path = STATE / 'ledger.json'
        ledger = json.loads(ledger_path.read_text()) if ledger_path.exists() else {'version': 1, 'archives': []}
        if ledger.get('pending'): raise ValueError('Copia interrumpida pendiente de conciliacion')
        status = {'checked_at': now, 'eligible': eligible, 'next_at': next_at,
            'completed': False, 'databases': [], 'large_copies_restored': False}
        if plan_only or not eligible or ledger.get('attempted_week') == week:
            status['deferred'] = True
            status['reason'] = 'plan_only' if plan_only else ('outside_window' if not eligible else 'week_already_attempted')
            save(STATE / 'latest.json', status); print(json.dumps(status)); return
        # Un intento por semana; un fallo necesita revision, no nuevas ventanas cada quince minutos.
        ledger['attempted_week'] = week; save(ledger_path, ledger)
        try:
            connector = module('vigilante-sql-window'); compressor = module('vigilante-sql-stream')
            for item in MAPPED:
                catalogue(backup); validate_aliases()
                status['databases'].append(archive(item, ledger, backup, connector, compressor))
                save(STATE / 'latest.json', status)
            # El ejecutor anterior conserva su bloqueo, catalogo, retencion y pruebas.
            independent = module('vigilante-independent-sql'); independent.main()
            independent_result = json.loads((independent.STATE / 'latest.json').read_text())
            if independent_result.get('coverage_complete') is not True:
                raise ValueError('Las bases independientes no acreditan cobertura')
            status['independent'] = independent_result
            status['completed'] = True; ledger['last_success'] = now
            save(ledger_path, ledger); prune(ledger, backup, compressor)
        except Exception as error:
            status['failure_type'] = type(error).__name__; status['completed'] = False
            raise
        finally:
            status['finished_at'] = int(time.time())
            save(STATE / 'last-run.json', status); save(STATE / 'latest.json', status)
        print(json.dumps({'completed': status['completed'], 'mapped': len(status['databases'])}))


def observation(now):
    """El detector lee el ultimo intento real; un ciclo diferido no borra fallos."""
    private(CONFIG); config = json.loads(CONFIG.read_text())
    actual = STATE / 'last-run.json'
    previous = {}
    if actual.exists():
        private(actual)
        if actual.stat().st_size > 262144: raise ValueError('Resultado SQL excesivo')
        previous = json.loads(actual.read_text())
    current = datetime.fromtimestamp(now, ZoneInfo('Europe/Madrid'))
    due = (current - timedelta(days=(current.weekday() + 1) % 7)).replace(hour=4, minute=45, second=0, microsecond=0)
    if due.timestamp() > now: due -= timedelta(days=7)
    # Admitir el plazo maximo del ejecutor si el cron lo inicia al final de la ventana.
    missed = (now > due.timestamp() + 2400 and config['first_at'] <= due.timestamp()
        and previous.get('checked_at', 0) < due.timestamp())
    return {'failed': bool(previous and not previous.get('completed')) or missed,
        'missed_window': missed, 'last_run_at': previous.get('checked_at'),
        'last_run_completed': previous.get('completed'), 'large_copies_restored': previous.get('large_copies_restored', False)}


if __name__ == '__main__':
    import sys
    if sys.argv[1:] not in ([], ['--plan']): raise SystemExit(2)
    main(plan_only=sys.argv[1:] == ['--plan'])
