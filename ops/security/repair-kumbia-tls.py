#!/usr/bin/env python3
"""Reparacion administrativa acotada; no se ofrece como herramienta del modelo."""
import concurrent.futures
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import time

BASE = Path('/root/codex-vigilante/tramo-11')
BACKUP = BASE/'backup'
POLICY = Path('/etc/vigilante/policy.json')
CONTROL = Path('/usr/local/lib/vigilante/vps-service-control.py')
SSL = Path('/var/www/clients/client1/web20/ssl')
PROFILE = Path('/root/.acme.sh/kumbia.app/kumbia.app.conf')
DUPLICATE = Path('/root/.acme.sh/www.kumbia.app/www.kumbia.app.conf')
RENEWAL = Path('/etc/letsencrypt/renewal/kumbia.app.conf')
RELOAD = '/usr/bin/python3 /usr/local/lib/vigilante/vps-service-control.py reload-tls kumbia.app --origin ispconfig-acme'


def run(args, timeout=30):
    result = subprocess.run(args, capture_output=True, timeout=timeout)
    if result.returncode:
        raise RuntimeError('Fallo de operacion: '+Path(args[0]).name)
    return result.stdout


def load_incident():
    spec = importlib.util.spec_from_file_location('incident', '/usr/local/lib/vigilante/vigilante-incident.py')
    module = importlib.util.module_from_spec(spec); spec.loader.exec_module(module)
    return module


def panel(value=None):
    # Se reconcilia solo el indicador; no se encola una regeneracion de vhosts.
    prefix = ("require_once '/usr/local/ispconfig/server/lib/config.inc.php'; "
              "require_once '/usr/local/ispconfig/server/lib/app.inc.php'; ")
    if value is not None:
        if value not in ('n', 'y'):
            raise ValueError('Indicador no valido')
        prefix += ("$app->db->query(\"UPDATE `web_domain` SET `ssl_letsencrypt`=? "
                   "WHERE `domain_id`=? AND `domain`=? AND `ssl`=?\", '"+value+"',20,'kumbia.app','y'); ")
    query = ("SELECT `domain_id`,`domain`,`document_root`,`subdomain`,`ssl`,`ssl_letsencrypt`,"
             "`ssl_domain`,`ssl_action`,`active` FROM `web_domain` WHERE `domain_id`=20")
    code = prefix+'echo json_encode($app->db->queryOneRecord('+json.dumps(query)+'));'
    result = json.loads(run(['/usr/bin/php', '-r', code], 10))
    if not result or result['domain'] != 'kumbia.app' or result['document_root'] != '/var/www/clients/client1/web20' or result['ssl'] != 'y' or result['ssl_domain'] != 'kumbia.app' or result['active'] != 'y':
        raise ValueError('La ficha del panel ha cambiado')
    if value is not None and result['ssl_letsencrypt'] != value:
        raise ValueError('No se guardo el indicador del panel')
    return result


def atomic(path, content, mode=0o600):
    temporary = path.with_name(path.name+'.vigilante-new')
    fd = os.open(temporary, os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW, mode)
    try:
        with os.fdopen(fd, 'wb') as stream:
            stream.write(content); stream.flush(); os.fsync(stream.fileno())
        os.replace(temporary, path)
    finally:
        if os.path.lexists(temporary):
            temporary.unlink()


def health(sites):
    incident = load_incident()
    def probe(site):
        result = incident.tls('127.0.0.1', site, site)
        http = subprocess.run(['/usr/bin/curl','-sS','-o','/dev/null','-w','%{http_code}',
                               '--max-time','8','https://'+site+'/'], capture_output=True, timeout=10)
        return site, {'tls': result, 'http_status': http.stdout.decode(), 'http_exit': http.returncode}
    with concurrent.futures.ThreadPoolExecutor(max_workers=3) as pool:
        return dict(pool.map(probe, sites))


def backup():
    if BACKUP.exists():
        if not (BASE/'rollback.json').exists():
            raise ValueError('Intervencion anterior sin restauracion confirmada')
        for entry in json.loads((BACKUP/'manifest.json').read_text()):
            path = Path(entry['path']); copy = Path(entry['copy'])
            if copy.is_symlink():
                if not path.is_symlink() or path.readlink() != copy.readlink():
                    raise ValueError('La copia anterior no corresponde al estado actual')
            elif path.is_symlink() or path.read_bytes() != copy.read_bytes():
                raise ValueError('El estado actual ha cambiado')
        return
    BACKUP.mkdir(mode=0o700)
    paths = [POLICY, CONTROL, PROFILE, DUPLICATE, RENEWAL, SSL/'kumbia.app-le.crt', SSL/'kumbia.app-le.key']
    manifest = []
    for index, path in enumerate(paths):
        destination = BACKUP/str(index)
        if path.is_symlink():
            destination.symlink_to(path.readlink())
        else:
            shutil.copy2(path, destination, follow_symlinks=False)
        manifest.append({'path': str(path), 'copy': str(destination)})
    atomic(BACKUP/'manifest.json', json.dumps(manifest).encode())
    atomic(BACKUP/'panel.json', json.dumps(panel()).encode())


def rollback():
    entries = json.loads((BACKUP/'manifest.json').read_text())
    # Restaurar certificados y perfiles antes de recargar con el control nuevo.
    for entry in entries:
        path = Path(entry['path']); copy = Path(entry['copy'])
        if path in (POLICY, CONTROL):
            continue
        if copy.is_symlink():
            temp = path.with_name(path.name+'.vigilante-link')
            temp.symlink_to(copy.readlink()); os.replace(temp, path)
        else:
            atomic(path, copy.read_bytes(), copy.stat().st_mode & 0o777)
    removed = DUPLICATE.with_suffix('.conf.removed')
    if removed.exists() and removed.read_bytes() == (BACKUP/'3').read_bytes():
        removed.unlink()
    panel(json.loads((BACKUP/'panel.json').read_text())['ssl_letsencrypt'])
    run(['/usr/bin/python3', str(CONTROL), 'reload-tls', 'kumbia.app', '--origin', 'ispconfig-acme'])
    for entry in entries:
        path = Path(entry['path'])
        if path in (POLICY, CONTROL):
            copy = Path(entry['copy']); atomic(path, copy.read_bytes(), copy.stat().st_mode & 0o777)
    atomic(BASE/'rollback.json', json.dumps({'restored': True, 'time': int(time.time())}).encode())


def apply():
    incident = load_incident()
    for path in (BASE, POLICY.parent, CONTROL.parent):
        incident.trusted(path, True)
    for path in (POLICY, CONTROL, PROFILE, DUPLICATE, RENEWAL):
        incident.trusted(path)
    if DUPLICATE.with_suffix('.conf.removed').exists():
        raise ValueError('Existe una intervencion anterior')
    expected = json.loads((BASE/'expected.json').read_text())
    if any(hashlib.sha256(Path(p).read_bytes()).hexdigest() != h for p,h in expected.items()):
        raise ValueError('Estado previo cambiado')
    if panel()['ssl_letsencrypt'] != 'n':
        raise ValueError('El panel ha cambiado')
    run(['/usr/sbin/apache2ctl', 'configtest'])
    for unit in ('apache2.service','mariadb.service','php8.4-fpm.service'):
        run(['/usr/bin/systemctl','is-active','--quiet',unit])
    if subprocess.run(['/usr/bin/systemctl','is-active','--quiet','certbot.service']).returncode == 0:
        raise ValueError('Renovacion en curso')
    policy = json.loads(POLICY.read_text()); before = health(policy['sites'])
    attempt = BASE/('attempt-'+str(time.time_ns())); attempt.mkdir(mode=0o700)
    atomic(attempt/'before-health.json', json.dumps(before).encode())
    atomic(BASE/'before-health.json', json.dumps(before).encode())
    crt = Path('/root/.acme.sh/kumbia.app/fullchain.cer'); key = Path('/root/.acme.sh/kumbia.app/kumbia.app.key')
    incident.trusted(crt); incident.trusted(key)
    certificate = run(['/usr/bin/openssl','x509','-in',str(crt),'-noout','-ext','subjectAltName']).decode()
    if set(re.findall(r'DNS:([^,\s]+)', certificate)) != {'kumbia.app','www.kumbia.app'}:
        raise ValueError('Nombres del certificado inesperados')
    run(['/usr/bin/openssl','x509','-in',str(crt),'-checkend','604800','-noout'])
    public_crt = run(['/usr/bin/openssl','x509','-in',str(crt),'-pubkey','-noout'])
    public_key = run(['/usr/bin/openssl','pkey','-in',str(key),'-pubout'])
    if public_crt != public_key:
        raise ValueError('El certificado no corresponde a la clave')
    backup()
    try:
        atomic(CONTROL, (BASE/'vps-service-control.py').read_bytes(), 0o755)
        policy['tls_reload_sites'] = sorted(set(policy.get('tls_reload_sites', [])+['kumbia.app']))
        atomic(POLICY, json.dumps(policy, indent=2).encode())
        # Reemplazar los enlaces evita que acme.sh escriba dentro de Certbot.
        atomic(SSL/'kumbia.app-le.crt', crt.read_bytes(), 0o644)
        atomic(SSL/'kumbia.app-le.key', key.read_bytes(), 0o600)
        os.replace(RENEWAL, BACKUP/'retired-certbot-renewal.conf')
        run(['/root/.acme.sh/acme.sh','--remove','--domain','www.kumbia.app'], 20)
        output = run(['/root/.acme.sh/acme.sh','--install-cert','--domain','kumbia.app',
                      '--key-file',str(SSL/'kumbia.app-le.key'),'--fullchain-file',str(SSL/'kumbia.app-le.crt'),
                      '--reloadcmd',RELOAD], 45)
        atomic(BASE/'acme-install.txt', output)
        panel_after = panel('y')
        after = health(policy['sites'])
        atomic(attempt/'after-health.json', json.dumps(after).encode())
        changed = [site for site in policy['sites'] if site != 'kumbia.app' and before[site] != after[site]]
        if changed:
            # Conservar el primer resultado, contrastar solo las diferencias.
            time.sleep(1)
            retried = health(changed)
            atomic(attempt/'recheck-health.json', json.dumps(retried).encode())
            after.update(retried)
        for site in policy['sites']:
            if site == 'kumbia.app':
                if not after[site]['tls']['verified'] or after[site]['http_exit']:
                    raise ValueError('La reparacion no supera la comprobacion')
            else:
                if before[site] != after[site]:
                    raise ValueError('Otra web cambio durante la intervencion')
        public = [incident.tls(address, site, site+'@'+address)
                  for site in ('kumbia.app','www.kumbia.app')
                  for address in ('127.0.0.1','62.138.24.108')]
        if not all(p['verified'] for p in public):
            raise ValueError('Validacion TLS incompleta')
        report = {'repaired': True, 'sites_checked': len(policy['sites']), 'panel': panel_after,
                  'after_health': after, 'tls': public, 'renewal_owner': 'ispconfig_acme',
                  'retired_duplicate_profiles': ['certbot:kumbia.app','acme:www.kumbia.app'],
                  'full_service_restarts': 0, 'time': int(time.time())}
        atomic(BASE/'repair.json', json.dumps(report, indent=2).encode())
        print(json.dumps({'repaired': True, 'sites_checked': len(policy['sites']), 'ispconfig_consistent': True}))
    except Exception:
        rollback()
        raise


if __name__ == '__main__':
    if os.geteuid() != 0 or sys.argv[1:] not in (['apply'], ['rollback']):
        raise PermissionError('Operacion administrativa explicita requerida')
    apply() if sys.argv[1:] == ['apply'] else rollback()
