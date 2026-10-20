#!/usr/bin/env python3
"""Recuperacion manual y estrecha del generador de estadisticas bloqueado."""
import hashlib
import json
import os
from pathlib import Path
import re
import signal
import stat
import tempfile


def disable_dns(data):
    result, count = re.subn(rb'^DNSLookup=1\r?$', b'DNSLookup=0', data, flags=re.M)
    if count != 1:
        raise ValueError('La configuracion no coincide con la revisada')
    return result


def main(plan_path):
    plan_path = Path(plan_path)
    if os.geteuid() != 0 or plan_path.is_symlink():
        raise ValueError('Plan privado de administrador requerido')
    for p in [plan_path, plan_path.parent]:
        meta = p.stat()
        if meta.st_uid != 0 or meta.st_mode & 0o077:
            raise ValueError('Plan o carpeta no privados')
    plan = json.loads(plan_path.read_text())
    pid = plan['pid']
    if type(pid) is not int or pid < 2:
        raise ValueError('PID no valido')
    proc = Path('/proc') / str(pid)
    pidfd = os.pidfd_open(pid)
    try:
        args = (proc / 'cmdline').read_bytes().split(b'\0')
        start = (proc / 'stat').read_text().rsplit(')', 1)[1].split()[19]
        if (not any(x.endswith(b'/awstats.pl') for x in args)
                or b'-config=roleplus.app' not in args or b'-update' not in args
                or start != plan['start_ticks']):
            raise ValueError('El proceso no coincide con el observado')
        config = Path('/etc/awstats/awstats.conf')
        data = config.read_bytes()
        if hashlib.sha256(data).hexdigest() != plan['config_sha256']:
            raise ValueError('La configuracion ha cambiado')
        meta = config.stat()
        if config.is_symlink() or meta.st_uid != 0:
            raise ValueError('Configuracion no fiable')
        updated = disable_dns(data)
        # Copia de configuracion y de la estadistica mensual publicada; sin tocar logs.
        for name, content in [('awstats.conf.before', data),
                ('monthly-data.before', Path('/var/lib/awstats/awstats102026.roleplus.app.txt').read_bytes())]:
            p = plan_path.parent / name
            with p.open('xb') as out:
                os.chmod(p, 0o600)
                out.write(content)
                out.flush()
                os.fsync(out.fileno())
        fd, temp = tempfile.mkstemp(prefix='.vigilante-', dir=config.parent)
        try:
            with os.fdopen(fd, 'wb') as out:
                out.write(updated)
                os.fchown(out.fileno(), meta.st_uid, meta.st_gid)
                os.fchmod(out.fileno(), stat.S_IMODE(meta.st_mode))
                out.flush()
                os.fsync(out.fileno())
            if config.read_bytes() != data:
                raise ValueError('La configuracion ha cambiado durante la preparacion')
            os.replace(temp, config)
        finally:
            if os.path.exists(temp):
                os.unlink(temp)
        signal.pidfd_send_signal(pidfd, signal.SIGTERM)
        result = {'dns_lookup_disabled': True, 'terminated_awstats_pid': pid,
                  'web_services_restarted': False, 'statistics_regeneration_verified': False}
        p = plan_path.parent / 'awstats-recovery-result.json'
        with p.open('x') as out:
            os.chmod(p, 0o600)
            json.dump(result, out, indent=2)
        print(json.dumps(result))
    finally:
        os.close(pidfd)


if __name__ == '__main__':
    import sys
    if len(sys.argv) != 3 or sys.argv[1] != '--apply':
        raise SystemExit(2)
    main(sys.argv[2])
