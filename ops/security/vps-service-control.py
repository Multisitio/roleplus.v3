#!/usr/bin/env python3
"""Control unico de reinicios y recargas TLS; nunca ejecuta comandos del modelo."""
import argparse
import fcntl
import json
import os
from pathlib import Path
import re
import stat
import subprocess
import sys
import time

POLICY = Path('/etc/vigilante/policy.json')
STATE = Path('/var/lib/vigilante')
AUDIT = Path('/var/log/vigilante/acciones.jsonl')
LOCK = Path('/run/lock/vigilante-restarts.lock')


def trusted(path, directory=False, uid=0):
    info = path.lstat()
    expected = stat.S_ISDIR if directory else stat.S_ISREG
    if not expected(info.st_mode) or info.st_uid != uid or info.st_mode & 0o022:
        raise ValueError('Propietario, permisos o tipo no permitido: ' + str(path))


def load_policy(path=POLICY, uid=0):
    trusted(path.parent, directory=True, uid=uid)
    trusted(path, uid=uid)
    policy = json.loads(path.read_text())
    if policy.get('version') != 1 or policy.get('ai_actions_enabled') is not False:
        raise ValueError('Version o modo de autonomia no permitido en este tramo')
    if policy.get('restart_owner') != 'webwatchdog':
        raise ValueError('Debe existir un unico responsable de reinicio')
    units = policy.get('restart_units')
    if not isinstance(units, list) or not units or any(
        not isinstance(x, str) or not re.fullmatch(r'[a-zA-Z0-9_][a-zA-Z0-9_.@-]*\.service', x)
        for x in units
    ):
        raise ValueError('Lista de unidades invalida')
    delay = policy.get('restart_cooldown_seconds')
    if type(delay) is not int or not 60 <= delay <= 86400:
        raise ValueError('Espera entre reinicios invalida')
    sites = policy.get('sites')
    if not isinstance(sites, list) or not sites or any(
        not isinstance(x, str) or not re.fullmatch(r'[a-z0-9]+(?:[.-][a-z0-9]+)*', x)
        for x in sites
    ) or len(sites) != len(set(sites)):
        raise ValueError('Catalogo de webs invalido')
    excluded = policy.get('restart_excluded_sites', [])
    if not isinstance(excluded, list) or any(x not in sites for x in excluded):
        raise ValueError('Excepciones de reinicio invalidas')
    reload_sites = policy.get('tls_reload_sites', [])
    if not isinstance(reload_sites, list) or any(x not in sites for x in reload_sites):
        raise ValueError('Catalogo de recarga TLS invalido')
    return policy


def append_event(path, event):
    fd = os.open(path, os.O_WRONLY | os.O_APPEND | os.O_CREAT | os.O_NOFOLLOW, 0o600)
    with os.fdopen(fd, 'a') as stream:
        stream.write(json.dumps(event, ensure_ascii=False) + '\n')
        stream.flush()
        os.fsync(stream.fileno())


def restart(policy, unit, origin, state=STATE, audit=AUDIT, lock=LOCK,
            runner=subprocess.run, clock=time.time, uid=0):
    unit = unit if unit.endswith('.service') else unit + '.service'
    if origin != policy['restart_owner'] or unit not in policy['restart_units']:
        raise ValueError('Origen o unidad no autorizado')
    trusted(state, directory=True, uid=uid)
    trusted(audit.parent, directory=True, uid=uid)
    if audit.exists():
        trusted(audit, uid=uid)
    if lock.exists():
        trusted(lock, uid=uid)
    fd = os.open(lock, os.O_RDWR | os.O_CREAT | os.O_NOFOLLOW, 0o600)
    with os.fdopen(fd, 'a+') as handle:
        try:
            fcntl.flock(handle, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            return {'decision': 'ocupado', 'unit': unit}
        now = clock()
        saved = state / (unit + '.json')
        event = {'time': now, 'origin': origin, 'unit': unit}
        if saved.exists():
            trusted(saved, uid=uid)
            last = json.loads(saved.read_text())['last_attempt']
            if now - last < policy['restart_cooldown_seconds']:
                event['decision'] = 'espera'
                append_event(audit, event)
                return event
        enabled = runner(['/usr/bin/systemctl', 'is-enabled', unit],
                         capture_output=True, timeout=10)
        if enabled.returncode != 0:
            event['decision'] = 'unidad_no_habilitada'
            append_event(audit, event)
            return event
        event['decision'] = 'intento'
        append_event(audit, event)
        temporary = state / (unit + '.tmp')
        out = os.open(temporary, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600)
        with os.fdopen(out, 'w') as stream:
            json.dump({'last_attempt': now}, stream)
            stream.flush()
            os.fsync(stream.fileno())
        os.replace(temporary, saved)
        try:
            result = runner(['/usr/bin/systemctl', 'restart', unit],
                            capture_output=True, timeout=120)
            event['decision'] = 'ejecutado' if result.returncode == 0 else 'error'
            event['exit_code'] = result.returncode
        except subprocess.TimeoutExpired:
            event['decision'] = 'plazo_agotado'
        append_event(audit, event)
        return event


def reload_tls(policy, site, origin, audit=AUDIT, lock=LOCK,
               runner=subprocess.run, clock=time.time, uid=0):
    if origin != 'ispconfig-acme' or site not in policy.get('tls_reload_sites', []):
        raise ValueError('Recarga TLS no autorizada')
    trusted(audit.parent, directory=True, uid=uid)
    if audit.exists():
        trusted(audit, uid=uid)
    if lock.exists():
        trusted(lock, uid=uid)
    fd = os.open(lock, os.O_RDWR|os.O_CREAT|os.O_NOFOLLOW, 0o600)
    with os.fdopen(fd, 'a+') as handle:
        event = {'time': clock(), 'origin': origin, 'site': site,
                 'unit': 'apache2.service', 'action': 'reload_tls'}
        try:
            fcntl.flock(handle, fcntl.LOCK_EX|fcntl.LOCK_NB)
        except BlockingIOError:
            event['decision'] = 'ocupado'; append_event(audit, event); return event
        try:
            check = runner(['/usr/sbin/apache2ctl', 'configtest'], capture_output=True, timeout=15)
            if check.returncode:
                event['decision'] = 'configuracion_invalida'
            elif runner(['/usr/bin/systemctl', 'is-active', '--quiet', 'apache2.service'],
                        capture_output=True, timeout=10).returncode:
                event['decision'] = 'servicio_inactivo'
            else:
                append_event(audit, dict(event, decision='intento'))
                result = runner(['/usr/bin/systemctl', 'reload', 'apache2.service'],
                                capture_output=True, timeout=30)
                event['decision'] = 'ejecutado' if result.returncode == 0 else 'error'
                event['exit_code'] = result.returncode
        except subprocess.TimeoutExpired:
            event['decision'] = 'plazo_agotado'
        append_event(audit, event)
        return event


def main():
    if os.geteuid() != 0:
        raise PermissionError('Este control requiere administrador; no se concede sudo al agente')
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('action', choices=['check', 'list-sites', 'site-restart-allowed', 'restart', 'reload-tls'])
    parser.add_argument('unit', nargs='?')
    parser.add_argument('--origin')
    args = parser.parse_args()
    policy = load_policy()
    if args.action == 'list-sites':
        print('\n'.join(policy['sites']))
    elif args.action == 'check':
        print(json.dumps({'valid': True, 'sites': len(policy['sites']),
                          'ai_actions_enabled': False}))
    elif args.action == 'site-restart-allowed':
        site = (args.unit or '').removeprefix('https://').rstrip('/')
        if site not in policy['sites']:
            raise ValueError('Sitio no registrado')
        allowed = site not in policy.get('restart_excluded_sites', [])
        print(json.dumps({'site': site, 'restart_allowed': allowed}))
        return 0 if allowed else 1
    elif args.action == 'reload-tls':
        event = reload_tls(policy, args.unit, args.origin)
        print(json.dumps(event))
        return 0 if event['decision'] == 'ejecutado' else 1
    else:
        if not args.unit:
            parser.error('Falta la unidad')
        event = restart(policy, args.unit, args.origin)
        print(json.dumps(event))
        return 0 if event['decision'] in ('ejecutado', 'unidad_no_habilitada') else 1
    return 0


if __name__ == '__main__':
    try:
        sys.exit(main())
    except (OSError, ValueError, KeyError, subprocess.SubprocessError) as error:
        print(json.dumps({'decision': 'rechazado', 'error': str(error)}), file=sys.stderr)
        sys.exit(1)
