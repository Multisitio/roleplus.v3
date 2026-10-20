#!/usr/bin/python3
"""Instala el aviso inactivo en las webs con SQL, sin regenerar sus vhosts."""
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import subprocess
import time

LIB = Path('/usr/local/lib/vigilante')
CASE = Path('/var/lib/vigilante-maintenance-install')
SITES = {26: 'controlate.kumbia.app', 4: 'mtgsearch.kumbia.app', 6: 'roleplus.app',
         24: 'delixiomarket.com', 15: 'mansion.kumbia.app', 17: 'sitios.kumbia.app', 16: 'rola.kumbia.app'}
CONF = Path('/etc/vigilante/maintenance-apache')
POLICY = Path('/etc/vigilante/policy.json')


def module(name):
    spec = importlib.util.spec_from_file_location(name.replace('-', '_'), LIB / (name + '.py'))
    mod = importlib.util.module_from_spec(spec); spec.loader.exec_module(mod)
    return mod


def save(path, data, mode=0o600):
    with path.open('xb') as stream:
        os.fchmod(stream.fileno(), mode); stream.write(data); stream.flush(); os.fsync(stream.fileno())


def main():
    if os.geteuid() != 0: raise PermissionError('Solo administrador')
    os.umask(0o077)
    for p in (LIB, POLICY.parent, POLICY, CASE):
        if p == CASE and not p.exists(): continue
        meta = p.lstat()
        if p.is_symlink() or meta.st_uid != 0 or meta.st_mode & 0o022:
            raise ValueError('Ruta no fiable')
    CASE.mkdir(mode=0o700)  # Aplicacion unica; nunca repetir una instalacion parcial.
    check = json.loads(Path('/var/lib/vigilante-maintenance-check/report.json').read_text())
    if not check.get('completed') or check.get('uid') != 33:
        raise ValueError('Falta ensayo privado satisfactorio')
    maintenance = module('vigilante-maintenance')
    if maintenance.STATE.exists() and json.loads(maintenance.STATE.read_text()).get('hosts'):
        raise ValueError('No instalar durante una ventana')
    backup = module('vigilante-sql-backup')
    rows = backup.query("SELECT domain_id,domain,document_root,system_user,system_group,CONCAT('x',HEX(apache_directives)) "
        'FROM web_domain WHERE domain_id IN (' + ','.join(map(str, SITES)) + ") AND active='y' AND type='vhost' AND server_id=1 ORDER BY domain_id;")
    entries = []
    for line in rows.splitlines():
        ident, host, root, user, group, previous = line.split('\t')
        previous = previous[1:]
        ident = int(ident)
        if SITES.get(ident) != host or not re.fullmatch(r'web[0-9]+', user) or group != 'client1':
            raise ValueError('Cambio en las correspondencias del panel')
        root = Path(root)
        if str(root) != '/var/www/clients/client1/' + user:
            raise ValueError('DocumentRoot del panel no esperado')
        vhost = Path('/etc/apache2/sites-available') / (host + '.vhost')
        text = vhost.read_text()
        actual = set(re.findall(r'(?mi)^\s*DocumentRoot\s+(\S+)', text))
        if actual != {str(root / 'web')} or not re.search(r'(?mi)^\s*ServerName\s+' + re.escape(host) + r'\s*$', text):
            raise ValueError('El servidor virtual no coincide con el panel')
        if not re.search(r'(?mi)^\s*ErrorLog\s+/var/log/ispconfig/httpd/' + re.escape(host) + '/error.log', text):
            raise ValueError('Registro del sitio no contrastado')
        include = 'Include ' + str(CONF / (host + '.conf'))
        old = bytes.fromhex(previous).decode()
        if include in old or '__vigilante_' in text:
            raise ValueError('Instalacion ya presente o conflictiva')
        wrapper = root / 'cgi-bin/vigilante-maintenance.cgi'
        if wrapper.exists() or wrapper.is_symlink(): raise ValueError('CGI ya existente')
        directory = wrapper.parent.stat()
        if directory.st_mode & 0o022 or wrapper.parent.is_symlink():
            raise ValueError('Directorio CGI no fiable')
        new_text, count = re.subn(r'(?mi)^(\s*)RewriteEngine\s+on\s*$',
                                  lambda m: m[1] + include + '\n' + m[0], text)
        if count != 2: raise ValueError('Se esperaban los dos VirtualHost HTTP y HTTPS')
        entries.append(dict(id=ident, host=host, root=str(root), vhost=str(vhost), old=previous,
            new=(old.rstrip() + '\n' + include + '\n').encode().hex(), before=text, after=new_text,
            wrapper=str(wrapper), uid=directory.st_uid, gid=directory.st_gid))
    if len(entries) != len(SITES): raise ValueError('Catalogo incompleto')
    map_target = Path('/etc/apache2/conf-available/vigilante-maintenance-map.conf')
    map_link = Path('/etc/apache2/conf-enabled/vigilante-maintenance-map.conf')
    if map_target.exists() or map_link.exists() or CONF.exists(): raise ValueError('Destino existente')
    control_target = Path('/usr/local/sbin/vps-service-control')
    save(CASE / 'control-before.py', control_target.read_bytes())
    save(CASE / 'policy-before.json', POLICY.read_bytes())
    save(CASE / 'plan.json', json.dumps(entries).encode())
    result = dict(completed=False, windows_activated=False, databases_locked=False, sites=[])
    created, applied, reloaded = [], [], False
    try:
        CONF.mkdir(mode=0o755)
        save(map_target, (LIB / 'vigilante-maintenance-map.conf').read_bytes(), 0o644); created.append(map_target)
        map_link.symlink_to(map_target); created.append(map_link)
        for entry in entries:
            wrapper = Path(entry['wrapper'])
            save(wrapper, Path('/usr/lib/vigilante-maintenance/maintenance.cgi').read_bytes(), 0o755)
            os.chown(wrapper, entry['uid'], entry['gid']); created.append(wrapper)
            content = (LIB / 'vigilante-maintenance-apache.conf').read_text().replace(
                '/usr/lib/vigilante-maintenance/maintenance.cgi', str(wrapper)).replace(
                '<Directory /usr/lib/vigilante-maintenance>', '<Directory ' + str(wrapper.parent) + '>')
            target = CONF / (entry['host'] + '.conf')
            save(target, content.encode(), 0o644); created.append(target)
            path = Path(entry['vhost'])
            if path.read_text() != entry['before']: raise ValueError('El vhost cambio durante la preparacion')
            path.write_text(entry['after']); applied.append(entry)
            sql = ("UPDATE web_domain SET apache_directives=UNHEX('" + entry['new'] + "') WHERE domain_id="
                + str(entry['id']) + " AND HEX(apache_directives)='" + entry['old'] + "'; SELECT ROW_COUNT();")
            if backup.query(sql) != '1': raise ValueError('El panel cambio durante la preparacion')
        # La recarga se coordina con el mismo bloqueo que el antipanico.
        control_target.write_bytes((LIB / 'vps-service-control.py').read_bytes()); control_target.chmod(0o755)
        policy = json.loads(POLICY.read_text()); policy['maintenance_reload_sites'] = sorted(SITES.values())
        POLICY.write_text(json.dumps(policy, indent=2) + '\n')
        control = module('vps-service-control')
        reloaded = True  # Tambien conciliar una recarga con resultado incierto.
        event = control.reload_maintenance(control.load_policy(), 'roleplus.app', 'sql-maintenance-install')
        if event['decision'] != 'ejecutado': raise ValueError('Recarga rechazada: ' + event['decision'])
        reloaded = True
        for entry in entries:
            command = ['/usr/bin/curl', '--silent', '--show-error', '--fail', '--noproxy', '*',
                '--resolve', entry['host'] + ':443:127.0.0.1', '--connect-timeout', '3', '--max-time', '8',
                'https://' + entry['host'] + '/__vigilante_maintenance?status=1']
            reply = subprocess.run(command, capture_output=True, timeout=10)
            if reply.returncode or json.loads(reply.stdout)['active'] is not False:
                raise ValueError('El CGI no responde inactivo: ' + entry['host'])
            result['sites'].append(dict(host=entry['host'], inactive_endpoint_verified=True,
                vhost_sha256=hashlib.sha256(Path(entry['vhost']).read_bytes()).hexdigest()))
        result['completed'] = True
    except Exception:
        # Restaurar solo las escrituras propias, conservando los originales.
        for entry in reversed(applied):
            path = Path(entry['vhost'])
            if path.read_text() != entry['after']: raise ValueError('Cambio concurrente: requiere conciliacion')
            path.write_text(entry['before'])
            current = backup.query('SELECT HEX(apache_directives) FROM web_domain WHERE domain_id=' + str(entry['id']) + ';')
            if current == entry['new']:
                backup.query("UPDATE web_domain SET apache_directives=UNHEX('" + entry['old'] + "') WHERE domain_id="
                    + str(entry['id']) + " AND HEX(apache_directives)='" + entry['new'] + "';")
            elif current != entry['old']: raise ValueError('Cambio concurrente en el panel')
        for path in reversed(created): path.unlink()
        if CONF.exists(): CONF.rmdir()
        # Mantener el controlador compatible hasta confirmar la recarga de retorno.
        if reloaded:
            control = module('vps-service-control')
            event = control.reload_maintenance(control.load_policy(), 'roleplus.app', 'sql-maintenance-install')
            if event['decision'] != 'ejecutado': raise ValueError('Revisar recarga de retorno')
        control_target.write_bytes((CASE / 'control-before.py').read_bytes()); control_target.chmod(0o755)
        POLICY.write_bytes((CASE / 'policy-before.json').read_bytes())
        result['rolled_back'] = True
        raise
    finally:
        save(CASE / 'result.json', json.dumps(result, indent=2).encode())
    print(json.dumps(result))


if __name__ == '__main__': main()
