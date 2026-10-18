#!/usr/bin/env python3
"""Solo login/status: no acepta instrucciones, modelos ni comandos de trabajo."""
import argparse
import errno
import importlib.util
import json
import os
from pathlib import Path
import socket
import socketserver
import ssl
import subprocess
import threading
import time

spec = importlib.util.spec_from_file_location('gateway', '/tools/gateway.py')
gateway = importlib.util.module_from_spec(spec)
spec.loader.exec_module(gateway)


class Bridge(socketserver.ThreadingMixIn, socketserver.TCPServer):
    daemon_threads = True


class Handler(socketserver.BaseRequestHandler):
    def handle(self):
        with socket.socket(socket.AF_UNIX) as upstream:
            upstream.connect('/run/proxy/login.sock')
            gateway.relay(self.request, upstream)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('action', choices=['login', 'status', 'check'])
    args = parser.parse_args()
    profile = Path('/auth')
    if os.geteuid() == 0 or profile.stat().st_uid != os.geteuid() or profile.stat().st_mode & 0o077:
        raise PermissionError('Perfil no privado')
    base = ['/tools/codex', '-c', 'forced_login_method="chatgpt"', '-c', 'cli_auth_credentials_store="file"']
    if args.action == 'status':
        result = subprocess.run(base + ['login', 'status'], capture_output=True, text=True, timeout=15)
        authenticated = result.returncode == 0 and 'ChatGPT' in (result.stdout + result.stderr)
        print(json.dumps({'authenticated': authenticated, 'method': 'chatgpt' if authenticated else None}))
        return 0 if authenticated else 1
    if args.action == 'login' and (profile / 'auth.json').exists():
        raise ValueError('Ya existe una sesion; no reemplazarla')
    until = time.monotonic() + 10
    while not Path('/run/proxy/login.sock').exists():
        if time.monotonic() >= until:
            raise TimeoutError('Pasarela no disponible')
        time.sleep(0.1)
    if args.action == 'check':
        with socket.socket(socket.AF_INET) as direct:
            direct.settimeout(0.5)
            try:
                direct.connect(('198.51.100.1', 443))
                raise RuntimeError('La salida directa no esta bloqueada')
            except OSError as error:
                if error.errno not in (errno.ENETUNREACH, errno.EACCES, errno.EPERM):
                    raise
        with Bridge(('127.0.0.1', 0), Handler) as bridge:
            thread = threading.Thread(target=bridge.serve_forever, daemon=True); thread.start()
            try:
                with socket.create_connection(bridge.server_address, timeout=5) as connection:
                    connection.sendall(b'CONNECT example.com:443 HTTP/1.1\r\nHost: example.com:443\r\n\r\n')
                    assert connection.recv(1024).startswith(b'HTTP/1.1 403')
            finally:
                bridge.shutdown()
        with socket.socket(socket.AF_UNIX) as connection:
            connection.connect('/run/proxy/login.sock')
            connection.sendall(b'CONNECT example.com:443 HTTP/1.1\r\nHost: example.com:443\r\n\r\n')
            assert connection.recv(1024).startswith(b'HTTP/1.1 403')
        with socket.socket(socket.AF_UNIX) as connection:
            connection.connect('/run/proxy/login.sock')
            connection.sendall(b'CONNECT auth.openai.com:443 HTTP/1.1\r\nHost: auth.openai.com:443\r\n\r\n')
            header = b''
            while not header.endswith(b'\r\n\r\n'):
                header += gateway.take(connection, 1)
            assert header.startswith(b'HTTP/1.1 200')
            with ssl.create_default_context().wrap_socket(connection, server_hostname='auth.openai.com') as tls:
                tls.sendall(b'HEAD / HTTP/1.1\r\nHost: auth.openai.com\r\nConnection: close\r\n\r\n')
                response = tls.recv(1024).split(b'\r\n', 1)[0].decode('ascii')
        print(json.dumps({'unapproved_host_blocked': True, 'tls_verified': True,
                          'direct_egress_blocked': True, 'loopback_bridge_tested': True,
                          'network_namespace': os.readlink('/proc/self/ns/net'),
                          'http_status_line': response, 'ai_called': False}))
        return 0
    with Bridge(('127.0.0.1', 0), Handler) as bridge:
        thread = threading.Thread(target=bridge.serve_forever, daemon=True)
        thread.start()
        address = 'http://127.0.0.1:' + str(bridge.server_address[1])
        environment = dict(os.environ, HTTPS_PROXY=address, HTTP_PROXY=address, ALL_PROXY=address, NO_PROXY='')
        try:
            return subprocess.run(base + ['login', '--device-auth'], env=environment, timeout=840).returncode
        finally:
            bridge.shutdown()


if __name__ == '__main__':
    raise SystemExit(main())
