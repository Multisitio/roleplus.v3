#!/usr/bin/env python3
"""Un cliente de copia no debe sobrevivir al proceso que lo controla."""
import ctypes
import os
import signal
import sys

if len(sys.argv) < 2 or sys.argv[1] not in {
        '/usr/bin/timeout', '/usr/bin/mysql', '/usr/bin/mariadb-dump'}:
    raise SystemExit(2)
parent = os.getppid()
if parent == 1:
    raise SystemExit(3)
libc = ctypes.CDLL(None, use_errno=True)
if libc.prctl(1, signal.SIGKILL, 0, 0, 0) != 0:
    raise SystemExit(4)
if os.getppid() != parent:
    raise SystemExit(5)
os.execv(sys.argv[1], sys.argv[1:])
