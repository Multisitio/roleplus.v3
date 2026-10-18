#!/usr/bin/env python3
"""Contrato de observaciones anonimizadas; lector sin rutas elegibles."""
import hashlib
import json
import os
from pathlib import Path
import re
import stat
import sys
import time
import uuid

KINDS = {'http_5xx','php_warning','php_fatal','invalid_image','memory_exhausted',
         'permission_denied','missing_controller','undefined_variable','site_down',
         'service_down','disk_space','inodes'}
ROUTES = {'img','usuarios','publicaciones','admin','mensajes','personajes','index','otras'}
FAMILIES = ROUTES | {'ninguna','apache2','php_fpm','mariadb','watchdog'}
SNAPSHOT = Path('/srv/vigilante-ai/input/events/current.json')


def fingerprint(data):
    return hashlib.sha256(json.dumps(data, sort_keys=True, separators=(',', ':')).encode()).hexdigest()


def trusted(path, directory=False):
    info = path.lstat()
    if not (stat.S_ISDIR if directory else stat.S_ISREG)(info.st_mode) or info.st_uid != 0 or info.st_mode & 0o022:
        raise PermissionError('Fuente no confiable')


def validate(data, now=None):
    if not isinstance(data, dict) or set(data) != {'version','id','collected_at','scope_site_count','events','sha256'}:
        raise ValueError('Instantanea no valida')
    bare = dict(data); digest = bare.pop('sha256')
    if digest != fingerprint(bare) or data['version'] != 1 or str(uuid.UUID(data['id'])) != data['id']:
        raise ValueError('Identidad o huella no valida')
    now = int(time.time()) if now is None else now
    if type(data['collected_at']) is not int or not 0 <= now-data['collected_at'] <= 600:
        raise ValueError('Datos caducados')
    if type(data['scope_site_count']) is not int or not 1 <= data['scope_site_count'] <= 256:
        raise ValueError('Catalogo no valido')
    if not isinstance(data['events'], list) or not 1 <= len(data['events']) <= 16:
        raise ValueError('Observaciones no validas')
    ids = set()
    for event in data['events']:
        if not isinstance(event, dict) or set(event) != {'id','site','kind','family','code','count','severity'}:
            raise ValueError('Campos no autorizados')
        if not isinstance(event['site'], str) or not re.fullmatch(r'[a-z0-9]+(?:[.-][a-z0-9]+)*', event['site']):
            raise ValueError('Web no valida')
        if event['kind'] not in KINDS or event['family'] not in FAMILIES or event['severity'] not in ('normal','high'):
            raise ValueError('Categoria no autorizada')
        if type(event['code']) is not int or (event['code'] != 0 and not 500<=event['code']<=599):
            raise ValueError('Codigo no autorizado')
        if type(event['count']) is not int or not 1 <= event['count'] <= 100000:
            raise ValueError('Recuento no valido')
        expected = fingerprint({k:event[k] for k in ('site','kind','family','code')})
        if event['id'] != expected or expected in ids:
            raise ValueError('Identificador no valido o duplicado')
        ids.add(expected)
    return data


def read():
    line = sys.stdin.buffer.readline(257)
    if os.geteuid() == 0 or len(line) > 256 or not line.endswith(b'\n') or json.loads(line) != {'action':'read_events'}:
        raise PermissionError('Lectura no autorizada')
    path = Path('/input/events/current.json'); trusted(path.parent, True); trusted(path)
    if path.stat().st_size > 16384:
        raise ValueError('Datos excesivos')
    print(json.dumps({'uid':os.geteuid(),'snapshot':validate(json.loads(path.read_text()))}), flush=True)


if __name__ == '__main__':
    try:
        if sys.argv[1:] != ['read']:
            raise ValueError('Operacion no autorizada')
        read()
    except Exception as error:
        print(json.dumps({'completed':False,'error_type':type(error).__name__}), flush=True)
        raise SystemExit(1)
