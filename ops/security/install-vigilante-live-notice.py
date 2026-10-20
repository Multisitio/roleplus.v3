#!/usr/bin/python3
"""Publica el aviso en documentos abiertos, conservando cambios ajenos."""
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import subprocess
import uuid

CASE = Path('/root/codex-vigilante/tramo-29-2026-10-20')
LIB = Path('/usr/local/lib/vigilante')
IDS = {'controlate.kumbia.app': 26, 'mtgsearch.kumbia.app': 4, 'roleplus.app': 6,
       'delixiomarket.com': 24, 'mansion.kumbia.app': 15, 'sitios.kumbia.app': 17, 'rola.kumbia.app': 16}


def sha(data): return hashlib.sha256(data).hexdigest()


def replace(path, data, uid, gid, mode):
    temp = path.with_name('.vigilante-' + uuid.uuid4().hex)
    with temp.open('xb') as stream:
        os.fchmod(stream.fileno(), mode); os.fchown(stream.fileno(), uid, gid)
        stream.write(data); stream.flush(); os.fsync(stream.fileno())
    os.replace(temp, path)


def reload():
    spec = importlib.util.spec_from_file_location('control', LIB / 'vps-service-control.py')
    control = importlib.util.module_from_spec(spec); spec.loader.exec_module(control)
    event = control.reload_maintenance(control.load_policy(), 'roleplus.app', 'sql-maintenance-install')
    if event['decision'] != 'ejecutado': raise ValueError('Recarga rechazada: ' + event['decision'])


def status(host):
    args = ['/usr/bin/curl', '--noproxy', '*', '--silent', '--show-error', '--fail', '--http1.1',
            '--resolve', host + ':443:127.0.0.1', '--connect-timeout', '3', '--max-time', '8']
    reply = subprocess.run(args + ['https://' + host + '/__vigilante_status'], capture_output=True, timeout=10)
    return reply.returncode == 0 and json.loads(reply.stdout) == {}


def main():
    if os.geteuid() != 0: raise PermissionError('Solo administrador')
    os.umask(0o077)
    if (CASE / 'live-notice-result.json').exists(): raise ValueError('No repetir un plan ya aplicado')
    for p in (CASE, CASE / 'hook-plan.json', CASE / 'old-apache.conf'):
        info = p.lstat()
        if p.is_symlink() or info.st_uid != 0 or info.st_mode & 0o077:
            raise ValueError('Plan no privado')
    check = json.loads(Path('/var/lib/vigilante-maintenance-check/report.json').read_text())
    if not check.get('completed') or 'static_status_json_without_php_or_session_cookie' not in check['checks']:
        raise ValueError('Falta ensayo privado del estado estatico')
    if json.loads(Path('/run/vigilante-maintenance/state.json').read_text()).get('hosts'):
        raise ValueError('No desplegar durante una ventana')
    old_conf = (CASE / 'old-apache.conf').read_text()
    new_conf = (LIB / 'vigilante-maintenance-apache.conf').read_text()
    edits, additions = [], []
    plan = json.loads((CASE / 'hook-plan.json').read_text())
    for host, ident in IDS.items():
        vhost = Path('/etc/apache2/sites-available') / (host + '.vhost')
        text = vhost.read_text()
        web = Path('/var/www/clients/client1/web' + str(ident) + '/web')
        if set(re.findall(r'(?m)^\s*DocumentRoot\s+(\S+)', text)) != {str(web)}:
            raise ValueError('DocumentRoot diferente al revisado')
        if not re.search(r'(?m)^\s*ServerName\s+' + re.escape(host) + r'\s*$', text):
            raise ValueError('Servidor virtual no acreditado')
        cgi = web.parent / 'cgi-bin/vigilante-maintenance.cgi'
        def site_conf(source):
            return source.replace('/usr/lib/vigilante-maintenance/maintenance.cgi', str(cgi)).replace(
                '<Directory /usr/lib/vigilante-maintenance>', '<Directory ' + str(cgi.parent) + '>').encode()
        target = Path('/etc/vigilante/maintenance-apache') / (host + '.conf')
        if target.read_bytes() != site_conf(old_conf): raise ValueError('Configuracion divergente')
        edits.append((target, target.read_bytes(), site_conf(new_conf)))
        for source, rel in [('vigilante-maintenance-client.js', 'javascript/vigilante-maintenance-client.js'),
                            ('vigilante-maintenance-client.css', 'css/vigilante-maintenance-client.css')]:
            target = web / rel
            if target.exists() or target.is_symlink() or target.parent.is_symlink():
                raise ValueError('Activo ya existente o directorio enlazado')
            owner = web.stat()
            additions.append((target, (CASE / source).read_bytes(), owner.st_uid, owner.st_gid))
    shared = Path('/usr/share/vigilante-maintenance/maintenance.js')
    if shared.read_bytes() != (CASE / 'old-maintenance.js').read_bytes():
        raise ValueError('Script de aviso divergente')
    edits.append((shared, shared.read_bytes(), (CASE / 'new-maintenance.js').read_bytes()))
    infrastructure_count = len(edits)
    for item in plan:
        if 'remote' not in item: continue  # No instalar plantillas locales antiguas en produccion.
        path = Path(item['remote'])
        expected_root = Path('/var/www/clients/client1/web' + str(IDS[item['host']]))
        if path != expected_root / item['relative'] or path.is_symlink(): raise ValueError('Ruta no revisada')
        raw = path.read_bytes()
        if sha(raw) != item['remote_sha256'] or raw.count(b'</head>') != 1:
            raise ValueError('Plantilla cambiada desde la preparacion: ' + item['host'] + '/' + path.name)
        newline = b'\r\n' if b'\r\n' in raw else b'\n'
        hook = item['hook'].encode().rstrip(b'\r\n')
        after = re.sub(rb'(?m)^([ \t]*)</head>', lambda m:m[1]+hook+newline+m[1]+b'</head>', raw, count=1)
        if after == raw: raise ValueError('Insercion no realizada')
        edits.append((path, raw, after))
    changed, created = [], []
    result = dict(completed=False, real_database_locks_taken=False, changed_templates=0,
                  source_revisions=json.loads((CASE / 'revisions.json').read_text()))
    try:
        def apply_edit(index, edit):
            path, before, after = edit
            if path.read_bytes() != before: raise ValueError('Cambio concurrente')
            info = path.stat()
            (CASE / ('before-' + str(index) + '.txt')).write_bytes(before)
            replace(path, after, info.st_uid, info.st_gid, info.st_mode & 0o777)
            changed.append((path, before, after, info))
        for path, content, uid, gid in additions:
            with path.open('xb') as stream:
                os.fchmod(stream.fileno(), 0o644); os.fchown(stream.fileno(), uid, gid); stream.write(content)
            created.append((path, content))
        for index, edit in enumerate(edits[:infrastructure_count]): apply_edit(index, edit)
        reload()
        # Esperar a la generacion nueva antes de acreditar el Alias estatico.
        import time
        for host in IDS:
            for _ in range(6):
                try:
                    if status(host): break
                except (ValueError, subprocess.SubprocessError): pass
                time.sleep(1)
            else: raise ValueError('Estado estatico no disponible en ' + host)
        # El cliente solo se carga cuando sus recursos y el estado ya responden.
        for index, edit in enumerate(edits[infrastructure_count:], start=infrastructure_count): apply_edit(index, edit)
        result.update(completed=True, changed_templates=len(changed)-infrastructure_count,
            sites=len(IDS), static_assets=len(created), automatic_navigation=False,
            targets=[dict(path=str(path),before_sha256=sha(before),after_sha256=sha(after))
                     for path,before,after,_ in changed])
    except Exception:
        for path, before, after, info in reversed(changed):
            if path.read_bytes() != after: raise ValueError('Conciliar cambio concurrente antes del retorno')
            replace(path, before, info.st_uid, info.st_gid, info.st_mode & 0o777)
        for path, content in reversed(created):
            if path.read_bytes() != content: raise ValueError('Activo modificado; requiere conciliacion')
            path.unlink()
        reload()
        result['rolled_back'] = True
        raise
    finally:
        (CASE / 'live-notice-result.json').write_text(json.dumps(result, indent=2) + '\n')
    print(json.dumps({k:v for k,v in result.items() if k != 'targets'}))


if __name__ == '__main__': main()
