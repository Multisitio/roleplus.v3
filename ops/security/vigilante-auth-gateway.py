#!/usr/bin/env python3
"""Pasarela temporal de login: UID, destino, DNS publico y SNI exactos."""
import argparse
import ipaddress
import json
import os
from pathlib import Path
import select
import socket
import socketserver
import struct
import threading
import time

HOST = 'auth.openai.com'
SOCKET = '/run/proxy/login.sock'


def target(header):
    if len(header) > 4096 or not header.endswith(b'\r\n\r\n'):
        raise ValueError('Cabecera no valida')
    lines = header.split(b'\r\n')
    if lines[0] != b'CONNECT auth.openai.com:443 HTTP/1.1':
        raise ValueError('Destino o metodo no permitido')
    hosts = [line.split(b':', 1)[1].strip() for line in lines[1:] if line.lower().startswith(b'host:')]
    if hosts and hosts != [b'auth.openai.com:443']:
        raise ValueError('Host distinto del destino')
    return HOST


def take(connection, length):
    data = b''
    while len(data) < length:
        chunk = connection.recv(length - len(data))
        if not chunk:
            raise ValueError('Conexion incompleta')
        data += chunk
    return data


def client_sni(record):
    """ClientHello completo en el primer registro; fragmentacion no admitida."""
    if len(record) < 9 or record[0] != 22 or record[5] != 1:
        raise ValueError('No es un saludo TLS')
    size = int.from_bytes(record[3:5], 'big')
    if len(record) != 5 + size or int.from_bytes(record[6:9], 'big') != size - 4:
        raise ValueError('Saludo TLS truncado o fragmentado')
    try:
        pos = 43
        pos += 1 + record[pos]
        size = int.from_bytes(record[pos:pos+2], 'big'); pos += 2 + size
        pos += 1 + record[pos]
        end = pos + 2 + int.from_bytes(record[pos:pos+2], 'big'); pos += 2
        if end != len(record):
            raise ValueError('Extensiones TLS no validas')
        names = []
        while pos < end:
            kind = int.from_bytes(record[pos:pos+2], 'big')
            length = int.from_bytes(record[pos+2:pos+4], 'big'); pos += 4
            extension = record[pos:pos+length]; pos += length
            if pos > end:
                raise ValueError('Extension truncada')
            if kind == 0:
                if len(extension) < 5 or int.from_bytes(extension[:2], 'big') != len(extension)-2:
                    raise ValueError('SNI no valido')
                if extension[2] != 0 or int.from_bytes(extension[3:5], 'big') != len(extension)-5:
                    raise ValueError('SNI ambiguo')
                names.append(extension[5:].decode('ascii'))
        if names != [HOST]:
            raise ValueError('SNI distinto del permitido')
        return names[0]
    except (IndexError, UnicodeError) as error:
        raise ValueError('Saludo TLS no valido') from error


def public_addresses(resolver=socket.getaddrinfo):
    addresses = resolver(HOST, 443, type=socket.SOCK_STREAM)
    if not addresses or any(not ipaddress.ip_address(x[4][0]).is_global for x in addresses):
        raise ValueError('DNS no publico')
    return addresses


def relay(left, right, seconds=180, max_bytes=8 * 1024 * 1024):
    until, total = time.monotonic() + seconds, 0
    left.settimeout(5); right.settimeout(5)
    while time.monotonic() < until:
        ready, _, _ = select.select([left, right], [], [], 1)
        for source in ready:
            data = source.recv(65536)
            if not data:
                return
            total += len(data)
            if total > max_bytes:
                raise ValueError('Limite de transferencia de login')
            (right if source is left else left).sendall(data)


def serve_connection(connection, allowed_uid):
    peer = struct.unpack('3i', connection.getsockopt(socket.SOL_SOCKET, socket.SO_PEERCRED, 12))[1]
    if peer != allowed_uid:
        raise PermissionError('Identidad no autorizada')
    connection.settimeout(5)
    header = b''
    while not header.endswith(b'\r\n\r\n') and len(header) <= 4096:
        header += take(connection, 1)
    try:
        target(header)
    except ValueError:
        connection.sendall(b'HTTP/1.1 403 Forbidden\r\nContent-Length: 0\r\nConnection: close\r\n\r\n')
        raise
    connection.sendall(b'HTTP/1.1 200 Connection established\r\n\r\n')
    prefix = take(connection, 5)
    length = int.from_bytes(prefix[3:5], 'big')
    if length > 16384:
        raise ValueError('Saludo TLS demasiado grande')
    hello = prefix + take(connection, length)
    client_sni(hello)
    for family, kind, protocol, _, address in public_addresses()[:4]:
        upstream = socket.socket(family, kind, protocol)
        try:
            upstream.settimeout(5)
            upstream.connect(address)  # Direccion numerica revisada; no resolver otra vez.
        except OSError:
            upstream.close()
            continue
        try:
            upstream.sendall(hello)
            print(json.dumps({'decision': 'tunel_tls_autorizado'}), flush=True)
            relay(connection, upstream)
            return
        finally:
            upstream.close()
    raise OSError('No se ha podido conectar con el destino autorizado')


class Server(socketserver.ThreadingMixIn, socketserver.UnixStreamServer):
    daemon_threads = True
    slots = threading.BoundedSemaphore(4)


class Handler(socketserver.BaseRequestHandler):
    def handle(self):
        if not self.server.slots.acquire(blocking=False):
            return
        try:
            serve_connection(self.request, self.server.allowed_uid)
        except (OSError, ValueError) as error:
            print(json.dumps({'decision': 'rechazado_o_cerrado', 'type': type(error).__name__}), flush=True)
        finally:
            self.server.slots.release()


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--uid', type=int, required=True)
    parser.add_argument('--gid', type=int, required=True)
    args = parser.parse_args()
    if args.uid <= 0 or args.gid <= 0 or Path(SOCKET).exists():
        raise ValueError('Identidad o socket no validos')
    with Server(SOCKET, Handler) as server:
        os.chown(SOCKET, -1, args.gid)
        os.chmod(SOCKET, 0o660)
        server.allowed_uid = args.uid
        server.serve_forever()


if __name__ == '__main__':
    main()
