#!/usr/bin/python3
"""Conecta el aviso existente al volcado acotado; no programa ni activa copias."""
from contextlib import contextmanager
from datetime import datetime
from zoneinfo import ZoneInfo
import fcntl
import importlib.util
import json
from pathlib import Path
import re
import subprocess
import time
import uuid

LIB = Path(__file__).resolve().parent
LOCK = Path('/run/lock/vigilante-restarts.lock')
LEAD = 8
LIMITS = {'c1_roleplus': ('roleplus.app', 15), 'c1_mtgsearch': ('mtgsearch.kumbia.app', 45)}


def module(name):
    spec = importlib.util.spec_from_file_location(name.replace('-', '_'), LIB / (name + '.py'))
    loaded = importlib.util.module_from_spec(spec);spec.loader.exec_module(loaded)
    return loaded


maintenance = module('vigilante-maintenance')
backup = module('vigilante-sql-backup')


def request(host, path, method='GET'):
    # El destino siempre es Apache local; TLS comprueba el nombre publico.
    args = ['/usr/bin/curl', '--silent', '--show-error', '--noproxy', '*',
            '--max-time', '2', '--resolve', host + ':443:127.0.0.1',
            '--request', method, '--dump-header', '-', 'https://' + host + path]
    response = subprocess.run(args, capture_output=True, check=True, timeout=3)
    header, body = response.stdout.split(b'\r\n\r\n', 1)
    lines = header.decode('iso-8859-1').splitlines()
    headers = dict(line.lower().split(':', 1) for line in lines[1:] if ':' in line)
    return int(lines[0].split()[1]), {k: v.strip() for k,v in headers.items()}, body


def check_status(host, window):
    code, headers, body = request(host, '/__vigilante_maintenance?status=1')
    data = json.loads(body)
    if (code != 200 or data.get('active') is not True or data.get('window') != window
            or 'set-cookie' in headers):
        raise backup.BackupFailure('El sitio no acredita el aviso estatico de mantenimiento')


@contextmanager
def window(hosts, seconds):
    if (not hosts or len(hosts) > 8 or len(set(hosts)) != len(hosts)
            or any(not re.fullmatch(r'[a-z0-9]+(?:[.-][a-z0-9]+)*', h) for h in hosts)):
        raise ValueError('Nombres de sitio no validos')
    with LOCK.open('a') as lock:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        ident = maintenance.activate(hosts, estimate=seconds, limit=seconds, lead_seconds=LEAD)
        try:
            for host in hosts:check_status(host, ident)
            until = time.monotonic() + LEAD
            while time.monotonic() < until:time.sleep(max(0, min(0.1, until - time.monotonic())))

            def guard(required):
                for host in hosts:
                    data = maintenance.current(host)
                    if (not data or data.get('window') != ident or not data.get('guard_active')
                            or float(data['monotonic_until']) - time.monotonic() < required + 2):
                        raise backup.BackupFailure('El aviso ha caducado o no cubre el plazo de copia')

            guard(seconds)
            for host in hosts:
                check_status(host, ident)
                code, headers, _ = request(host, '/__vigilante_sql_probe_' + uuid.uuid4().hex, 'POST')
                if code != 503 or headers.get('x-vigilante-maintenance') != ident:
                    raise backup.BackupFailure('Apache no acredita la barrera antes de PHP')
            guard(seconds)
            yield guard
        finally:
            # clear comprueba la identidad: no elimina una ventana ajena.
            maintenance.clear(ident)


def night_window():
    return 3 <= datetime.now(ZoneInfo('Europe/Madrid')).hour < 6


def dump(database, output, hosts, seconds, counts=None, socket=backup.SOCKET):
    if database in LIMITS:
        canonical, limit = LIMITS[database]
        if not night_window():
            raise ValueError('Las bases grandes solo se copian en la ventana de madrugada')
        if not hosts or hosts[0] != canonical or seconds != limit:
            raise ValueError('Base grande fuera del alcance revisado')
    elif seconds != backup.MAX_SECONDS:
        raise ValueError('Una base no revisada no puede ampliar el bloqueo')
    if output.exists():raise ValueError('No sobrescribir un volcado anterior')
    with window(hosts, seconds) as guard:
        return backup.dump(database, output, socket=socket, seconds=seconds, counts=counts,
                           statement_seconds=seconds, guard=guard)
