#!/usr/bin/env python3
"""Copia manual acotada de bases pequenas; conserva a ISPConfig como programador."""
import gzip
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import shutil
import stat
import time

STATE = Path('/var/lib/vigilante-mapped-sql')


def private(path):
    meta = path.lstat()
    if path.is_symlink() or meta.st_uid != 0 or meta.st_mode & 0o077:
        raise ValueError('Plan o evidencia no privados')


def main():
    if os.geteuid() != 0:
        raise PermissionError('Solo mantenimiento manual de administrador')
    os.umask(0o077)
    private(STATE)
    private(STATE / 'plan.json')
    plan = json.loads((STATE / 'plan.json').read_text())
    items = plan['databases']
    if (plan.get('version') != 1 or not 1 <= len(items) <= 5
            or len({i['id'] for i in items}) != len(items)):
        raise ValueError('Seleccion no valida')
    spec = importlib.util.spec_from_file_location('backup',
        '/usr/local/lib/vigilante/vigilante-sql-backup.py')
    backup = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(backup)
    # Contrastar toda la seleccion antes de reservar el resultado o tomar READ.
    for item in items:
        if (type(item['id']) is not int or item['id'] not in (1, 6, 8, 9, 10)
                or type(item['domain_id']) is not int or item['domain_id'] < 1
                or not re.fullmatch(r'c1_[a-z0-9_]+', item['name'])):
            raise ValueError('Base fuera de alcance')
        evidence = STATE / 'preflight' / (item['name'] + '.json')
        private(evidence.parent)
        private(evidence)
        raw = evidence.read_bytes()
        previous = json.loads(raw)
        if (hashlib.sha256(raw).hexdigest() != item['preflight_sha256']
                or previous.get('database') != item['name']
                or previous.get('bounded_redump', {}).get('completed') is not True):
            raise ValueError('Falta ensayo acotado satisfactorio')
        query = ("SELECT COUNT(*) FROM web_database b JOIN web_domain d "
                 "ON d.domain_id=b.parent_domain_id WHERE b.database_id=" + str(item['id'])
                 + " AND b.database_name='" + item['name'] + "' AND b.parent_domain_id="
                 + str(item['domain_id']) + " AND b.active='y' AND b.server_id=1 "
                 "AND b.backup_interval='weekly' AND b.backup_copies=7 "
                 "AND d.active='y' AND d.type='vhost' AND d.server_id=1;")
        if backup.query(query) != '1':
            raise ValueError('La correspondencia o politica del panel ha cambiado')
    result = {'completed': False, 'databases': [], 'native_schedule_changed': False}
    result_path = STATE / 'result.json'
    with result_path.open('x') as stream:
        stream.write(json.dumps(result) + '\n')
    try:
        for item in items:
            if shutil.disk_usage('/var/backup').free < 20 * 1024 ** 3 + 32 * 1024 ** 2:
                raise ValueError('Reserva de disco insuficiente')
            dest = Path('/var/backup') / ('web' + str(item['domain_id']))
            dest.mkdir(mode=0o700, exist_ok=True)
            meta = dest.lstat()
            if (not stat.S_ISDIR(meta.st_mode) or dest.is_symlink()
                    or meta.st_uid != 0 or meta.st_mode & 0o022):
                raise ValueError('Destino de copia no fiable')
            raw = STATE / (item['name'] + '.sql')
            counts = {}
            elapsed = backup.dump(item['name'], raw, counts=counts)
            if raw.stat().st_size > backup.MAX_RAW_BYTES:
                raise ValueError('Base fuera del presupuesto revisado')
            data = raw.read_bytes()
            if re.search(r'[\x00-\x08\x0b\x0e-\x1f\x7f-\x9f]', data.decode('utf-8-sig')):
                raise ValueError('El volcado requiere revisar su codificacion')
            now = int(time.time())
            filename = 'db_' + item['name'] + '_' + time.strftime('%Y-%m-%d_%H-%M-%S') + '.sql.gz'
            target = dest / filename
            with target.open('xb') as stream:
                stream.write(gzip.compress(data, compresslevel=1))
                stream.flush()
                os.fsync(stream.fileno())
            with gzip.open(target, 'rb') as stream:
                if stream.read() != data:
                    raise RuntimeError('El comprimido no coincide con el SQL')
            entry = {'database_id': item['id'], 'name': item['name'], 'source': str(target),
                     'domain_id': item['domain_id'], 'sql_bytes': len(data),
                     'sql_sha256': hashlib.sha256(data).hexdigest(),
                     'gzip_sha256': hashlib.sha256(target.read_bytes()).hexdigest(),
                     'row_counts_under_lock': counts, 'dump_seconds': round(elapsed, 4),
                     'strict_read_guard': True, 'global_read_lock_used': False,
                     'registered_in_panel': False}
            result['databases'].append(entry)
            backup.query('INSERT INTO web_backup(server_id,parent_domain_id,backup_type,backup_mode,'
                'backup_format,tstamp,filename,filesize,backup_password) VALUES(1,'
                + str(item['domain_id']) + ",'mysql','sqlgz','gzip'," + str(now) + ",'"
                + filename + "','" + str(target.stat().st_size) + "','');")
            entry['registered_in_panel'] = True
            raw.unlink()  # Temporal propio, despues de verificar su copia y el registro.
        result['completed'] = True
    finally:
        with result_path.open('w') as stream:
            json.dump(result, stream, indent=2)
            stream.write('\n')
    print(json.dumps({'completed': result['completed'], 'databases': len(result['databases'])}))


if __name__ == '__main__':
    main()
