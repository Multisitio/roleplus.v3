#!/usr/bin/python3
"""Aviso temporal anterior a PHP; estado publico sin claves, escrito solo por root."""
import html
import json
import math
import os
from pathlib import Path
import sys
import time
import uuid

STATE = Path(os.environ.get('VIGILANTE_MAINTENANCE_STATE', '/run/vigilante-maintenance/state.json'))


def publish(data):
    if os.geteuid() != 0:
        raise PermissionError('Solo el coordinador de administrador puede publicar ventanas')
    STATE.parent.mkdir(mode=0o755, exist_ok=True)
    info = STATE.parent.lstat()
    if STATE.parent.is_symlink() or info.st_uid != 0 or info.st_mode & 0o022:
        raise ValueError('Directorio publico de ventanas no fiable')
    temporary = STATE.with_name('state-' + uuid.uuid4().hex + '.tmp')
    with temporary.open('x') as stream:
        os.fchmod(stream.fileno(), 0o644)
        json.dump(data, stream)
        stream.write('\n')
        stream.flush()
        os.fsync(stream.fileno())
    os.replace(temporary, STATE)


def activate(hosts, estimate, limit):
    if not hosts or not 0 < limit <= 45 or not 0 < estimate <= 45:
        raise ValueError('Ventana fuera de los limites autorizados')
    if STATE.exists():
        previous = json.loads(STATE.read_text())
        if previous.get('hosts') and current(previous['hosts'][0]):
            raise ValueError('Ya hay otra ventana activa')
    window = uuid.uuid4().hex
    publish({'window': window, 'hosts': hosts,
             'boot_id': Path('/proc/sys/kernel/random/boot_id').read_text().strip(),
             'estimated_until': time.time() + estimate + 3,
             'monotonic_until': time.monotonic() + limit + 8})
    return window


def clear(window):
    previous = json.loads(STATE.read_text())
    if previous.get('window') != window:
        raise ValueError('No se retira una ventana ajena')
    publish({})


def current(host):
    host = host.lower().split(':', 1)[0].rstrip('.')
    try:
        info = STATE.lstat()
        if info.st_size > 16384 or STATE.is_symlink() or info.st_mode & 0o022:
            return None
        data = json.loads(STATE.read_text())
        boot = Path('/proc/sys/kernel/random/boot_id').read_text().strip()
        remaining = float(data['monotonic_until']) - time.monotonic()
        if (data['boot_id'] != boot or not math.isfinite(remaining) or not 0 < remaining <= 120
                or not any(host == pattern or (pattern.startswith('*.')
                    and host.endswith(pattern[1:]) and len(host) > len(pattern) - 1)
                    for pattern in data['hosts'])):
            return None
        data['max_seconds'] = math.ceil(remaining)
        data['estimated_seconds'] = max(0, math.ceil(float(data['estimated_until']) - time.time()))
        return data
    except (OSError, ValueError, KeyError, TypeError):
        return None


def payload(data):
    return {'active': bool(data), 'window': data['window'] if data else None,
            'estimated_seconds': data['estimated_seconds'] if data else 0,
            'max_seconds': data['max_seconds'] if data else 0,
            'message': 'Estamos en fase de mantenimiento, en modo de solo lectura. '
                       'Los cambios no se pueden guardar durante este intervalo.'}


def cgi():
    data = current(os.environ.get('HTTP_HOST', ''))
    reply = payload(data)
    # La consulta de estado solo pertenece al punto reservado. Una peticion
    # de la aplicacion con esa misma consulta debe seguir recibiendo 503.
    status_query = (os.environ.get('QUERY_STRING') == 'status=1'
        and os.environ.get('REQUEST_URI', '').split('?', 1)[0] == '/__vigilante_maintenance'
        and os.environ.get('REQUEST_METHOD', 'GET') in ('GET', 'HEAD'))
    ajax = (os.environ.get('REQUEST_METHOD', 'GET') not in ('GET', 'HEAD')
            or 'application/json' in os.environ.get('HTTP_ACCEPT', '')
            or os.environ.get('HTTP_X_REQUESTED_WITH', '').lower() == 'xmlhttprequest')
    code = '200 OK' if status_query or not data else '503 Service Unavailable'
    headers = ['Status: ' + code, 'Cache-Control: no-store, max-age=0',
               'X-Content-Type-Options: nosniff']
    if data:
        headers += ['Retry-After: ' + str(data['max_seconds']),
                    'X-Vigilante-Maintenance: ' + data['window']]
    if status_query or ajax:
        if data:
            reply['error'] = reply['message'] + ' Tiempo estimado: ' + str(data['estimated_seconds']) + ' segundos.'
        headers += ['Content-Type: application/json; charset=utf-8']
        body = json.dumps(reply, ensure_ascii=True)
    else:
        headers += ['Content-Type: text/html; charset=utf-8']
        estimate = str(data['estimated_seconds']) if data else '0'
        body = '''<!doctype html><html lang="es" data-theme="dark" data-color="red">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex"><title>Mantenimiento temporal</title>
<link rel="stylesheet" href="/__vigilante_assets/base.css">
<link rel="stylesheet" href="/__vigilante_assets/maintenance.css">
<script src="/__vigilante_assets/maintenance.js" defer></script></head>
<body class="vigilante-maintenance"><main><article>
<header><p>Servicio temporalmente protegido</p><h1>Estamos en mantenimiento</h1></header>
<section class="contenido"><div class="vertical"><h2>Modo de solo lectura</h2>
<p>Los cambios no se pueden guardar durante este intervalo. Puedes seguir leyendo lo que ya tienes abierto.</p>
<p>La navegación y los guardados se reanudarán automáticamente al terminar.</p>
<p class="maintenance-time" aria-live="polite">Tiempo estimado: <strong data-estimate>''' + html.escape(estimate) + ''' segundos</strong>.</p>
<p data-maintenance-detail>Estamos comprobando el estado del servicio.</p></div></section>
<footer><button type="button" data-maintenance-retry>Volver a comprobar</button><a href="/">Ir al inicio</a></footer>
</article></main></body></html>'''
    sys.stdout.write('\r\n'.join(headers) + '\r\n\r\n' + body)


if __name__ == '__main__':
    if sys.argv[1:2] == ['--map'] and len(sys.argv) in (2, 3):
        # Apache limpia el entorno de sus programas RewriteMap. La ruta se
        # fija en su configuracion, nunca procede de la peticion HTTP.
        if len(sys.argv) == 3:
            STATE = Path(sys.argv[2])
            if str(STATE) not in ('/run/vigilante-maintenance/state.json',
                                  '/run/vigilante-maintenance-check/state.json'):
                raise SystemExit(2)
        for key in sys.stdin:
            print('on' if current(key.strip()) else 'off', flush=True)
    elif sys.argv[1:] == ['--cgi']:
        cgi()
    else:
        raise SystemExit(2)
