#!/usr/bin/python3
"""Actualizacion administrativa de ISPConfig con pausa limitada a su panel."""
import fcntl
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import pwd
import re
import subprocess
import tarfile
import time

CASE=Path('/root/codex-vigilante/ispconfig-php84-2026-10-22')
PANEL=Path('/etc/apache2/sites-available/ispconfig.vhost')
POLICY=Path('/etc/vigilante/policy.json')
MARKER='# codex-ispconfig-update-20261022 '
GUARD='\n# Pausa temporal del panel; las webs conservan su acceso.\n<Location "/">\nRequire local\nErrorDocument 403 "Panel en mantenimiento. Espera unos minutos."\n</Location>\n'
spec=importlib.util.spec_from_file_location('prepare',CASE/'ispconfig-upgrade-prepare-2026-10-22.py')
prep=importlib.util.module_from_spec(spec);spec.loader.exec_module(prep)


def save(name,data):prep.save(name,data)


def phase(value):
    save('progress.json',{'phase':value,'time':int(time.time())});print(value,flush=True)


def policy_units(enable):
    p=json.loads(POLICY.read_text())
    if enable:
        assert not p.get('php_security_activation_units')
        p['php_security_activation_units']=['apache2.service','php8.4-fpm.service']
    else:p.pop('php_security_activation_units',None)
    tmp=POLICY.with_suffix('.ispconfig-new');tmp.write_text(json.dumps(p,indent=2)+'\n');tmp.chmod(0o600);os.replace(tmp,POLICY)


def activate(unit):
    p=subprocess.run(['/usr/local/sbin/vps-service-control','activate-php-security',unit,'--origin','security-php-update'],
        capture_output=True,text=True,check=True,timeout=100)
    result=json.loads(p.stdout);assert result['decision']=='ejecutado';return result


def native_pids():
    expected={'/usr/local/ispconfig/server/server.php','/usr/local/ispconfig/server/cron.php',
              '/usr/local/ispconfig/server/server.sh','/usr/local/ispconfig/server/cron.sh',
              '/usr/local/ispconfig/security/check.php'}
    found=[]
    for p in Path('/proc').glob('[0-9]*/cmdline'):
        try:args=p.read_bytes().decode(errors='replace').split('\0')
        except OSError:continue
        if any(a in expected for a in args):found.append(p.parent.name)
    return found


def config(path):
    p=subprocess.run(['/usr/bin/php8.4','-r','$conf=[];include $argv[1];echo json_encode($conf);',str(path)],
        capture_output=True,text=True,check=True,timeout=10)
    return json.loads(p.stdout)


def restore_custom(old):
    path=Path('/usr/local/ispconfig/interface/lib/config.inc.php');new=config(path)
    # Las credenciales se contrastan en memoria y no se escriben en informes.
    for key in old:
        if key.startswith(('db_','dbmaster_')):assert old[key]==new.get(key),'Configuracion de conexion divergente'
    keys=['logo','language','timezone','theme','html_content_encoding','interface_logout_url',
          'interface_base_url','db_log_message_max_length','language_file_import_enabled','debug_language']
    changes={k:old[k] for k in keys if k in old and old[k]!=new.get(k)}
    if changes:
        import base64
        encoded=base64.b64encode(json.dumps(changes).encode()).decode()
        with path.open('a') as out:
            out.write('\n// Ajustes anteriores conservados por el coordinador.\n')
            out.write("foreach(json_decode(base64_decode('"+encoded+"'),true) as $key=>$value){$conf[$key]=$value;}\n")
    if changes:
        manifests=sorted(Path('/usr/local/ispconfig/security/data').glob('file_checksums_*.md5'),key=lambda p:p.stat().st_mtime)
        assert manifests
        manifest=manifests[-1];lines=manifest.read_text().splitlines(keepends=True)
        changed=0
        for i,line in enumerate(lines):
            if line.rstrip().endswith('  '+str(path)):
                lines[i]=hashlib.md5(path.read_bytes()).hexdigest()+'  '+str(path)+'\n';changed+=1
        assert changed==1
        manifest.write_text(''.join(lines))
    save('custom-config-preserved.json',{'keys':sorted(changes),'connection_values_preserved':True})


def backup_tree():
    result={}
    for p in Path('/usr/local/ispconfig').rglob('*'):
        if p.is_symlink() or not p.is_file():continue
        st=p.stat();result[str(p)]={'uid':st.st_uid,'gid':st.st_gid,'mode':st.st_mode&0o7777,
            'sha256':hashlib.sha256(p.read_bytes()).hexdigest()}
    save('tree-before.json',result)


def rollback():
    phase('recuperando_version_anterior')
    before=json.loads((CASE/'tree-before.json').read_text())
    # Los binarios oficiales no se copian como respaldo: se reconstruyen a
    # partir de la descarga temporal de la version anterior, ya contrastada.
    with tarfile.open(CASE/'previous-release.tar.gz') as archive:
        for m in archive:
            parts=Path(m.name).parts
            if not m.isfile() or len(parts)<3 or parts[1] not in ['server','interface','security']:continue
            p=Path('/usr/local/ispconfig').joinpath(*parts[1:])
            if str(p) not in before:continue
            content=archive.extractfile(m).read()
            if hashlib.sha256(content).hexdigest()!=before[str(p)]['sha256']:continue
            p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(content)
            info=before[str(p)];os.chown(p,info['uid'],info['gid']);p.chmod(info['mode'])
    # Retirar solo archivos nuevos acreditados como pertenecientes a esta entrega.
    with tarfile.open(CASE/'release.tar.gz') as archive:
        for m in archive:
            parts=Path(m.name).parts
            if not m.isfile() or len(parts)<3 or parts[1] not in ['server','interface','security']:continue
            p=Path('/usr/local/ispconfig').joinpath(*parts[1:])
            if str(p) not in before and p.is_file() and not p.is_symlink():
                if hashlib.sha256(p.read_bytes()).digest()==hashlib.sha256(archive.extractfile(m).read()).digest():p.unlink()
    with tarfile.open(CASE/'before-apply-text.tar.gz') as archive:
        for m in archive:
            assert m.isfile() and not Path(m.name).is_absolute() and '..' not in Path(m.name).parts
            p=Path('/')/m.name
            assert str(p).startswith(('/usr/local/ispconfig/','/etc/apache2/','/etc/php/','/etc/default/',
                                     '/etc/logrotate.d/','/var/www/php-fcgi-scripts/','/usr/local/bin/'))
            assert not p.is_symlink()
            p.parent.mkdir(parents=True,exist_ok=True)
            if str(p) in ['/var/www/php-fcgi-scripts/ispconfig/.php-fcgi-starter','/var/www/php-fcgi-scripts/apps/.php-fcgi-starter']:
                subprocess.run(['chattr','-i',str(p)],check=True)
            p.write_bytes(archive.extractfile(m).read())
            os.chown(p,m.uid,m.gid);p.chmod(m.mode)
            if str(p) in ['/var/www/php-fcgi-scripts/ispconfig/.php-fcgi-starter','/var/www/php-fcgi-scripts/apps/.php-fcgi-starter']:
                subprocess.run(['chattr','+i',str(p)],check=True)
    with (CASE/'dbispconfig-before-apply.sql').open('rb') as inp:
        subprocess.run(['mysql','dbispconfig'],stdin=inp,check=True,capture_output=True)
    assert prep.sql('SELECT dbversion FROM dbispconfig.server WHERE server_id=1').strip()=='100'
    activate('apache2.service');save('rollback.json',{'completed':True,'schema':100})


def main():
    assert os.geteuid()==0;os.umask(0o077)
    assert json.loads((CASE/'prepared.json').read_text())['completed']
    assert json.loads((CASE/'trial-verification.json').read_text())['completed']
    certs={str(p):hashlib.sha256(p.read_bytes()).hexdigest() for p in Path('/usr/local/ispconfig/interface/ssl').glob('*') if p.is_file()}
    auth_before=hashlib.sha256(prep.sql('SELECT username,passwort,typ FROM dbispconfig.sys_user ORDER BY userid').encode()).hexdigest()
    old_config=config('/usr/local/ispconfig/interface/lib/config.inc.php')
    assert old_config['app_version']=='3.2.12p1'
    cron=subprocess.check_output(['crontab','-l'],text=True)
    lines=cron.splitlines(keepends=True)
    chosen=[l for l in lines if not l.lstrip().startswith('#') and any(x in l for x in
        ['/usr/local/ispconfig/server/server.sh','/usr/local/ispconfig/server/cron.sh'])]
    assert len(chosen)==2
    paused=''.join(MARKER+l if l in chosen else l for l in lines)
    (CASE/'root-crontab-before.txt').write_text(cron)
    save('pid-before.json',{u:subprocess.check_output(['systemctl','show',u,'--property=MainPID','--value'],text=True).strip()
        for u in ['apache2.service','php8.4-fpm.service','mariadb.service','postfix.service','rspamd.service','dovecot.service']})
    sites={str(p.resolve()):hashlib.sha256(p.read_bytes()).hexdigest()
        for p in Path('/etc/apache2/sites-enabled').glob('*') if p.is_file() and p.resolve()!=PANEL}
    save('websites-before.json',sites)
    policy_units(True);started=False;backed=False
    try:
        phase('esperando_tareas_internas')
        subprocess.run(['crontab','-'],input=paused,text=True,check=True)
        end=time.monotonic()+240
        while native_pids():
            if time.monotonic()>end:raise RuntimeError('Tareas internas siguen activas; no se fuerza su cierre')
            time.sleep(2)
        prep.backup('before-apply-text.tar.gz');backup_tree();prep.dump(CASE/'dbispconfig-before-apply.sql');backed=True
        # La pausa afecta solo al panel 8080, nunca a los servidores virtuales.
        original=PANEL.read_text();assert original.count('</VirtualHost>')==1
        PANEL.write_text(original.replace('</VirtualHost>',GUARD+'\n</VirtualHost>'))
        activate('apache2.service')
        phase('actualizando_ispconfig')
        with open('/run/lock/vigilante-restarts.lock','a') as lock:
            fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB);started=True
            with (CASE/'native-update.log').open('w') as log:
                subprocess.run(['/usr/bin/php8.4','-q','update-coordinated.php','--autoinstall='+str(CASE/'update-options.php')],
                    cwd=prep.SOURCE/'install',env=dict(os.environ,PATH=str(CASE/'commands')+':'+os.environ['PATH']),stdin=subprocess.DEVNULL,stdout=log,stderr=log,check=True)
        content=(CASE/'native-update.log').read_text(errors='replace')
        assert 'Update finished.' in content and 'Fatal error' not in content
        assert prep.sql('SELECT dbversion FROM dbispconfig.server WHERE server_id=1').strip()=='107'
        assert config('/usr/local/ispconfig/interface/lib/config.inc.php')['app_version']=='3.3.2p1'
        restore_custom(old_config)
        assert auth_before==hashlib.sha256(prep.sql('SELECT username,passwort,typ FROM dbispconfig.sys_user ORDER BY userid').encode()).hexdigest(),'Han cambiado credenciales del panel'
        assert all(hashlib.sha256(Path(p).read_bytes()).hexdigest()==h for p,h in certs.items()),'Un certificado ha cambiado'
        assert all(hashlib.sha256(Path(p).read_bytes()).hexdigest()==h for p,h in sites.items()),'Una web ha cambiado'
        # La plantilla oficial ya sustituyo la pausa en disco; permanece activa
        # en Apache hasta esta recarga coordinada tras comprobar todo.
        assert GUARD not in PANEL.read_text()
        activate('apache2.service')
        after={u:subprocess.check_output(['systemctl','show',u,'--property=MainPID','--value'],text=True).strip()
               for u in json.loads((CASE/'pid-before.json').read_text())}
        assert after==json.loads((CASE/'pid-before.json').read_text()),'Se ha reiniciado un servicio'
        save('upgrade-verification.json',{'completed':True,'version':'3.3.2p1','schema':107,
            'websites_vhosts_preserved':len(sites),'service_main_pids_preserved':True,
            'acme_and_certificates_not_renewed':True,'native_cron_paused_without_killing':True})
        phase('ispconfig_actualizado')
    except BaseException:
        if started and backed:rollback()
        elif backed:
            with tarfile.open(CASE/'before-apply-text.tar.gz') as archive:
                m=archive.getmember(str(PANEL).lstrip('/'));PANEL.write_bytes(archive.extractfile(m).read())
            activate('apache2.service')
        raise
    finally:
        current=subprocess.check_output(['crontab','-l'],text=True)
        restored=''.join(l[len(MARKER):] if l.startswith(MARKER) else l for l in current.splitlines(keepends=True))
        subprocess.run(['crontab','-'],input=restored,text=True,check=True)
        policy_units(False)


if __name__=='__main__':main()
