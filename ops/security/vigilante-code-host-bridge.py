#!/usr/bin/python3 -I
"""Puente binario fijo hacia el auxiliar aislado; no interpreta codigo ni perfiles."""
import os
import select
import socket
import stat
import sys
import time


def main():
    if sys.argv[1:] not in ([], ['--listen', 'stdio'], ['--listen', 'stdio://']):
        raise ValueError('Transporte no autorizado')
    path = '/run/code-host.sock'
    meta = os.lstat(path)
    if not stat.S_ISSOCK(meta.st_mode) or meta.st_uid != 0 or meta.st_mode & 0o007:
        raise PermissionError('Conexion no fiable')
    total = 0
    until = time.monotonic()+100
    with socket.socket(socket.AF_UNIX) as connection:
        connection.settimeout(5)
        connection.connect(path)
        while time.monotonic() < until:
            ready, _, _ = select.select([0, connection], [], [], 1)
            for source in ready:
                data = os.read(0, 65536) if source == 0 else connection.recv(65536)
                if not data:
                    return
                total += len(data)
                if total > 16*1024*1024:
                    raise ValueError('Transferencia excesiva')
                if source == 0:
                    connection.sendall(data)
                else:
                    view = memoryview(data)
                    while view:
                        view = view[os.write(1, view):]
    raise TimeoutError('Tiempo del auxiliar agotado')


if __name__ == '__main__':
    main()
