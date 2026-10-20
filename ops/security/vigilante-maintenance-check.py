#!/usr/bin/env python3
"""Ensayo de Apache/CGI en red privada, sin tocar las webs."""
import json
import os
from pathlib import Path
import socket
import subprocess
import time
import urllib.request
import urllib.error
import uuid

STATE = Path('/var/lib/vigilante-maintenance-check')
RUN = Path('/run/vigilante-maintenance-check')


def main():
    if os.geteuid() == 0:
        raise RuntimeError('El servidor de prueba no puede ser root')
    web = RUN / 'www'
    web.mkdir(mode=0o700)
    (web / 'static.js').write_text('/* recurso de ensayo */\n')
    (web / 'index.php').write_text('entrada simulada; no es PHP ejecutable\n')
    writer = Path('/usr/local/lib/vigilante/vigilante-maintenance-writer-check.cgi')
    (RUN / 'counter').write_text('0')
    data_path = RUN / 'state.json'
    data = {'window': uuid.uuid4().hex, 'hosts': ['maintenance.test'],
            'boot_id': Path('/proc/sys/kernel/random/boot_id').read_text().strip(),
            'monotonic_until': time.monotonic() + 60, 'estimated_until': time.time() + 30}
    data_path.write_text(json.dumps(data))
    data_path.chmod(0o644)
    modules = ['mpm_event', 'authz_core', 'authz_host', 'alias', 'mime', 'env', 'headers', 'rewrite', 'cgid']
    conf = 'ServerRoot "' + str(RUN) + '"\n'
    conf += ''.join('LoadModule ' + n + '_module /usr/lib/apache2/modules/mod_' + n + '.so\n' for n in modules)
    conf += 'Listen 127.0.0.1:18089\nServerName maintenance.test\n'
    conf += 'PidFile ' + str(RUN / 'httpd.pid') + '\nErrorLog ' + str(STATE / 'apache-errors.log') + '\n'
    conf += 'TypesConfig /etc/mime.types\nUser www-data\nGroup www-data\n'
    conf += 'LogLevel warn rewrite:trace5\n'
    conf += 'ScriptSock ' + str(RUN / 'cgid.sock') + '\n'
    conf += Path('/usr/local/lib/vigilante/vigilante-maintenance-map.conf').read_text().replace(
        '--map /run/vigilante-maintenance/state.json', '--map ' + str(data_path))
    conf += '<VirtualHost 127.0.0.1:18089>\nServerName maintenance.test\nDocumentRoot ' + str(web) + '\n'
    conf += 'SetEnv VIGILANTE_MAINTENANCE_STATE ' + str(data_path) + '\n'
    conf += Path('/usr/local/lib/vigilante/vigilante-maintenance-apache.conf').read_text()
    conf += '\nScriptAlias /app ' + str(writer) + '\n<Directory ' + str(RUN) + '>\nOptions -Indexes\nRequire all granted\n</Directory>\n'
    conf += '<Directory /usr/local/lib/vigilante>\nOptions +ExecCGI -Indexes\nRequire all granted\n</Directory>\n</VirtualHost>\n'
    config = RUN / 'httpd.conf'
    config.write_text(conf)
    report = {'completed': False, 'uid': os.geteuid(), 'public_network': False, 'checks': []}
    env = dict(os.environ, VIGILANTE_MAINTENANCE_STATE=str(data_path))
    server = None
    with (STATE / 'private-errors.log').open('w') as errors:
        def request(path, method='GET', host='maintenance.test', ajax=False):
            headers = {'Host': host}
            if ajax:
                headers['Accept'] = 'application/json'
            req = urllib.request.Request('http://127.0.0.1:18089' + path,
                data=b'' if method == 'POST' else None, headers=headers, method=method)
            try:
                response = urllib.request.urlopen(req, timeout=5)
            except urllib.error.HTTPError as exc:
                response = exc
            with response:
                return response.status, dict(response.headers), response.read()
        try:
            subprocess.run(['/usr/sbin/apache2', '-t', '-f', str(config)], env=env,
                stderr=errors, stdout=errors, check=True, timeout=10)
            probe = subprocess.run(['/usr/local/lib/vigilante/vigilante-maintenance.py', '--map'],
                input='maintenance.test\nunaffected.test\n', capture_output=True, text=True,
                env=env, check=True, timeout=5)
            assert probe.stdout.splitlines() == ['on', 'off']
            report['checks'].append('public_state_reader_on_off')
            server = subprocess.Popen(['/usr/sbin/apache2', '-X', '-f', str(config)],
                env=env, stderr=errors, stdout=errors)
            for _ in range(100):
                if server.poll() is not None:
                    raise RuntimeError('Apache de ensayo no arranco')
                try:
                    with socket.create_connection(('127.0.0.1', 18089), timeout=.2):
                        break
                except OSError:
                    time.sleep(.1)
            else:
                raise RuntimeError('Puerto privado no disponible')
            for path, method in [('/app', 'GET'), ('/app', 'POST'), ('/index.php', 'POST'),
                                  ('/missing.png', 'GET'), ('/app?status=1', 'GET'), ('/app?status=1', 'POST')]:
                status, headers, body = request(path, method)
                assert status == 503 and headers.get('X-Vigilante-Maintenance') == data['window'], (path, status)
                if method == 'GET':
                    assert b'Modo de solo lectura' in body and b'Tiempo estimado' in body
                else:
                    assert json.loads(body)['active']
                assert b'<style' not in body and b' style=' not in body
            assert (RUN / 'counter').read_text() == '0'
            report['checks'].append('dynamic_get_post_and_direct_php_blocked_before_writer')
            status, headers, body = request('/app', 'POST', ajax=True)
            assert status == 503 and json.loads(body)['active'] and b'<html' not in body
            report['checks'].append('ajax_gets_json_and_retry_after')
            status, headers, body = request('/__vigilante_maintenance?status=1')
            assert status == 200 and json.loads(body)['active']
            report['checks'].append('status_200_only_on_reserved_get_endpoint')
            assert request('/static.js')[0] == 200
            assert request('/static.js', 'POST')[0] == 503
            assert request('/app', host='unaffected.test')[0] == 200
            report['checks'].append('static_reads_and_other_hosts_preserved')
            data['monotonic_until'] = time.monotonic() - 1
            data_path.write_text(json.dumps(data))
            assert request('/app')[0] == 200
            assert request('/app', 'POST')[0] == 200
            assert (RUN / 'counter').read_text() == '1'
            report['checks'].append('expired_window_restores_reads_and_writes_without_reload')
            report['completed'] = True
        finally:
            if server is not None and server.poll() is None:
                server.terminate()
                server.wait(timeout=10)
            (STATE / 'report.json').write_text(json.dumps(report, indent=2) + '\n')
    print(json.dumps(report))


if __name__ == '__main__':
    main()
