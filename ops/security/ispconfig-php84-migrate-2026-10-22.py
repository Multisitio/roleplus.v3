#!/usr/bin/python3
"""Unifica el panel, las aplicaciones y el manejador general en PHP 8.4."""
import base64
import grp
import importlib.util
import json
import os
from pathlib import Path
import pwd
import re
import shutil
import subprocess
import time
import uuid

CASE=Path('/root/codex-vigilante/ispconfig-php84-2026-10-22')
spec=importlib.util.spec_from_file_location('upgrade',CASE/'ispconfig-upgrade-apply-2026-10-22.py')
upgrade=importlib.util.module_from_spec(spec);spec.loader.exec_module(upgrade)
WWW=Path('/etc/php/8.4/fpm/pool.d/www.conf')
CUSTOM=Path('/usr/local/ispconfig/server/conf-custom/install')
WRAPPERS=[Path('/var/www/php-fcgi-scripts')/n/'.php-fcgi-starter' for n in ['ispconfig','apps']]
LINK81=Path('/etc/apache2/conf-enabled/php8.1-fpm.conf')
LINK84=Path('/etc/apache2/conf-enabled/php8.4-fpm.conf')


def php(code):
    p=subprocess.run(['/usr/bin/php8.4','-d','disable_functions=','-d','disable_classes=','-d','open_basedir=','-r',code],capture_output=True,text=True,check=True,timeout=20)
    return p.stdout


PREFIX='require "/usr/local/ispconfig/server/lib/config.inc.php";require "/usr/local/ispconfig/server/lib/app.inc.php";require_once "/root/codex-vigilante/ispconfig-php84-2026-10-22/native-install.lib.php";'


def migrate_profile(restore=False,check_only=False):
    target=str(CASE/'server-config-before-php84.txt')
    if restore:
        code=PREFIX+'$app->db->query("UPDATE server SET config=? WHERE server_id=1",file_get_contents("'+target+'"));'
    else:
        code=PREFIX+'''
$row=$app->db->queryOneRecord("SELECT config FROM server WHERE server_id=1");
file_put_contents("TARGET",$row["config"]);chmod("TARGET",0600);
$cfg=ini_to_array(stripslashes($row["config"]));$before=$cfg;
$changes=[
"web"=>["php_fpm_init_script"=>"php8.4-fpm","php_fpm_ini_path"=>"/etc/php/8.4/fpm/php.ini",
"php_fpm_pool_dir"=>"/etc/php/8.4/fpm/pool.d","php_ini_path_cgi"=>"/etc/php/8.4/cgi/php.ini",
"php_ini_path_apache"=>"/etc/php/8.4/apache2/php.ini"],
"fastcgi"=>["fastcgi_bin"=>"/usr/bin/php-cgi8.4","fastcgi_phpini_path"=>"/etc/php/8.4/cgi/"]];
foreach($changes as $section=>$values){foreach($values as $key=>$value){$cfg[$section][$key]=$value;}}
$encoded=addslashes(array_to_ini($cfg));
if(ini_to_array(stripslashes($encoded))!==$cfg)exit(5);
if(CHECK_ONLY){echo "Formato del perfil comprobado sin escribir en la base.";return;}
$app->db->query("UPDATE server SET config=? WHERE server_id=1",$encoded);
$read=$app->db->queryOneRecord("SELECT config FROM server WHERE server_id=1");
$after=ini_to_array(stripslashes($read["config"]));
foreach($changes as $section=>$values){foreach($values as $key=>$value){if($after[$section][$key]!==$value)exit(3);}}
foreach($before as $section=>$values){foreach($values as $key=>$value){
if(!isset($changes[$section][$key]) && ($after[$section][$key]??null)!==$value)exit(4);
}}
echo "Perfil PHP actualizado y resto de valores conservados.";
'''.replace('TARGET',target).replace('CHECK_ONLY','true' if check_only else 'false')
    result=php(code)
    if result:print(result,flush=True)


def editable(path,content):
    if path in WRAPPERS:subprocess.run(['chattr','-i',str(path)],check=True,capture_output=True)
    path.write_bytes(content)
    if path in WRAPPERS:subprocess.run(['chattr','+i',str(path)],check=True,capture_output=True)


def probe_panel():
    root=Path('/usr/local/ispconfig/interface/web')/('codex-verify-'+uuid.uuid4().hex)
    owner=pwd.getpwnam('ispconfig');root.mkdir(mode=0o750);os.chown(root,owner.pw_uid,owner.pw_gid)
    try:
        (root/'.htaccess').write_text('Require local\n')
        (root/'index.php').write_text('''<?php
require dirname(__DIR__,2).'/lib/config.inc.php';
$conf['start_session']=false;
require dirname(__DIR__,2).'/lib/app.inc.php';
$r=$app->db->queryOneRecord("SELECT dbversion FROM server WHERE server_id=1");
header('Content-Type: application/json');
echo json_encode(['php'=>PHP_VERSION,'user'=>posix_geteuid(),'panel'=>ISPC_APP_VERSION,'schema'=>(int)$r['dbversion']]);
''')
        for p in root.iterdir():os.chown(p,owner.pw_uid,owner.pw_gid);p.chmod(0o640)
        p=subprocess.run(['curl','--silent','--show-error','--insecure','--max-time','15',
            'https://127.0.0.1:8080/'+root.name+'/'],capture_output=True,text=True,check=True,timeout=18)
        result=json.loads(p.stdout)
        assert result=={'php':'8.4.27','user':owner.pw_uid,'panel':'3.3.2p1','schema':107},result
        return result
    finally:
        assert root.parent==Path('/usr/local/ispconfig/interface/web') and root.name.startswith('codex-verify-')
        shutil.rmtree(root)


def probe_apps_and_generic():
    root=Path('/run/vigilante-php84-verify');root.mkdir(mode=0o755,exist_ok=True);root.chmod(0o755)
    p=root/'runtime.php';p.write_text('<?php echo json_encode(["php"=>PHP_VERSION,"user"=>function_exists("posix_geteuid")?posix_geteuid():null]);');p.chmod(0o644)
    env=dict(os.environ,REDIRECT_STATUS='200',REQUEST_METHOD='GET',SCRIPT_FILENAME=str(p),
             SCRIPT_NAME='/runtime.php',REQUEST_URI='/runtime.php',SERVER_PROTOCOL='HTTP/1.1')
    results={}
    try:
        out=subprocess.run(['runuser','-u','ispapps','--',str(WRAPPERS[1])],env=env,capture_output=True,text=True,check=True,timeout=15).stdout
        data=json.loads(out[out.index('{'):]);assert data=={'php':'8.4.27','user':pwd.getpwnam('ispapps').pw_uid}
        results['apps']=data
        out=subprocess.run(['/usr/bin/cgi-fcgi','-bind','-connect','/run/php/php8.4-fpm.sock'],
            env=env,capture_output=True,text=True,check=True,timeout=15).stdout
        data=json.loads(out[out.index('{'):]);assert data['php']=='8.4.27' and data['user'] in (None,pwd.getpwnam('www-data').pw_uid)
        results['generic']=data
        return results
    finally:p.unlink(missing_ok=True);root.rmdir()


def main():
    assert os.geteuid()==0;os.umask(0o077)
    assert json.loads((CASE/'upgrade-verification.json').read_text())['completed']
    assert not WWW.exists() and not LINK84.is_symlink()
    paths=WRAPPERS+[WWW,CUSTOM/'apache_ispconfig_fcgi_starter.master',CUSTOM/'apache_apps_fcgi_starter.master']
    before={}
    for p in paths:
        if p.exists():
            st=p.stat();before[str(p)]={'text':base64.b64encode(p.read_bytes()).decode(),
                'uid':st.st_uid,'gid':st.st_gid,'mode':st.st_mode&0o7777}
        else:before[str(p)]=None
    old_link=os.readlink(LINK81);old_alternative=os.path.realpath('/usr/bin/php-cgi')
    upgrade.save('php84-files-before.json',before)
    old_active=upgrade.prep.sql('SELECT active FROM dbispconfig.server_php WHERE server_php_id=8').strip()
    assert upgrade.prep.sql('SELECT COUNT(*) FROM dbispconfig.web_domain WHERE server_php_id=8').strip()=='0'
    native=CASE/'native-install.lib.php'
    native.write_bytes((upgrade.prep.SOURCE/'install/lib/install.lib.php').read_bytes());native.chmod(0o600)
    migrate_profile(check_only=True)
    upgrade.policy_units(True);profile=False
    try:
        upgrade.phase('preparando_grupo_general_php84')
        pool=Path('/etc/php/8.1/fpm/pool.d/www.conf').read_text()
        assert '/run/php/php8.1-fpm.sock' in pool
        WWW.write_text(pool.replace('php8.1','php8.4'));WWW.chmod(0o644)
        upgrade.activate('php8.4-fpm.service')
        for p in WRAPPERS:
            text=p.read_text().replace('/etc/php/8.1/','/etc/php/8.4/').replace('/usr/bin/php-cgi','/usr/bin/php-cgi8.4')
            text=re.sub(r'(exec /usr/bin/php-cgi8\.4)\s+-d\s+(?=\\\n)',r'\1 ',text)
            editable(p,text.encode())
        for kind in ['ispconfig','apps']:
            template=CUSTOM/('apache_'+kind+'_fcgi_starter.master')
            # El mecanismo nativo de plantillas personalizadas evita volver
            # a 8.1 en futuras actualizaciones de ISPConfig.
            content=(upgrade.prep.SOURCE/'install/tpl'/template.name).read_text()
            content=content.replace('{fastcgi_bin}','/usr/bin/php-cgi8.4').replace('{fastcgi_phpini_path}','/etc/php/8.4/cgi/')
            # La plantilla apps deja una opcion -d sin valor si no hay INI extra.
            content=content.replace('-d {cgi_phpini_settings}','')
            template.write_text(content);template.chmod(0o640)
        profile=True;migrate_profile()
        subprocess.run(['update-alternatives','--set','php-cgi','/usr/bin/php-cgi8.4'],check=True,capture_output=True)
        assert (LINK81.parent/old_link).resolve()==Path('/etc/apache2/conf-available/php8.1-fpm.conf')
        LINK84.symlink_to('/etc/apache2/conf-available/php8.4-fpm.conf');LINK81.unlink()
        upgrade.prep.sql('UPDATE dbispconfig.server_php SET active="n" WHERE server_php_id=8')
        upgrade.prep.sql('UPDATE dbispconfig.server_php SET php_cli_binary="/usr/bin/php8.4",php_jk_section="php8_4" WHERE server_php_id=11')
        upgrade.activate('apache2.service')
        upgrade.phase('verificando_php84_real')
        panel=probe_panel();others=probe_apps_and_generic()
        p=subprocess.run(['curl','--silent','--show-error','--max-time','15',
            '--write-out','\n%{http_code}','http://127.0.0.1/phpmyadmin/'],capture_output=True,text=True,check=True,timeout=18)
        assert p.stdout.endswith('\n200') and ('pma_username' in p.stdout or 'phpMyAdmin' in p.stdout)
        oldcase=Path('/root/codex-vigilante/php-security-2026-10-22/php-security-update-2026-10-22.py')
        spec=importlib.util.spec_from_file_location('health',oldcase);health=importlib.util.module_from_spec(spec);spec.loader.exec_module(health)
        websites=health.health();upgrade.save('http-php84-first.json',websites)
        if any(x['exit'] or x['code']!='200' for x in websites.values()):
            time.sleep(2);websites=health.health();upgrade.save('http-php84-confirmed.json',websites)
        assert all(x['exit']==0 and x['code']=='200' for x in websites.values())
        upgrade.save('php84-verification.json',{'completed':True,'panel_http_runtime':panel,
            'other_runtimes':others,'phpmyadmin_http_200':True,'websites_http_200':len(websites),
            'php_cgi_alternative':'/usr/bin/php-cgi8.4','ispconfig_profile_updated':True,
            'legacy_website_socket_paths_preserved':True,'custom_templates_installed':True})
        upgrade.phase('panel_y_manejadores_en_php84')
    except BaseException:
        upgrade.phase('recuperando_manejadores_php81')
        if profile and (CASE/'server-config-before-php84.txt').exists():migrate_profile(restore=True)
        for name,info in before.items():
            p=Path(name)
            if info is None:p.unlink(missing_ok=True)
            else:
                editable(p,base64.b64decode(info['text']))
                if p not in WRAPPERS:os.chown(p,info['uid'],info['gid']);p.chmod(info['mode'])
        if LINK84.is_symlink():LINK84.unlink()
        if not LINK81.exists():LINK81.symlink_to(old_link)
        subprocess.run(['update-alternatives','--set','php-cgi',old_alternative],check=True,capture_output=True)
        upgrade.prep.sql('UPDATE dbispconfig.server_php SET active="'+old_active+'" WHERE server_php_id=8')
        upgrade.activate('php8.4-fpm.service');upgrade.activate('apache2.service')
        raise
    finally:upgrade.policy_units(False)


if __name__=='__main__':main()
