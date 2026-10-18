#!/usr/bin/env python3
"""Comprueba barreras reales sin leer secretos, escribir en webs ni usar IA."""
import errno
import json
import os
from pathlib import Path
import resource
import socket
import tempfile

WORK = Path('/workspace')
INPUT = Path('/input/sample.json')
CANARY = Path('/readonly-canary')


def unavailable(path, flags=os.O_RDONLY):
    try:
        fd = os.open(path, flags | os.O_NOFOLLOW | os.O_NONBLOCK)
    except OSError as error:
        return error.errno in (errno.EACCES, errno.EPERM, errno.ENOENT, errno.ENOTDIR, errno.EROFS)
    else:
        # Comprobar apertura basta: nunca se lee ni escribe el recurso prohibido.
        os.close(fd)
        return False


def denied_socket(family):
    try:
        connection = socket.socket(family, socket.SOCK_STREAM)
    except OSError as error:
        return error.errno in (errno.EACCES, errno.EPERM, errno.EAFNOSUPPORT)
    else:
        connection.close()  # No enviar paquetes aunque falte una barrera.
        return False


def main():
    status = dict(line.split(':', 1) for line in Path('/proc/self/status').read_text().splitlines() if ':' in line)
    checks = {
        'usuario_sin_privilegios': os.geteuid() != 0 and os.getuid() == os.geteuid(),
        'sin_grupos_adicionales': set(os.getgroups()) <= {os.getegid()},
        'sin_capacidades': int(status['CapEff'].strip(), 16) == 0,
        'sin_nuevos_privilegios': status['NoNewPrivs'].strip() == '1',
        'filtro_de_llamadas_activo': status['Seccomp'].strip() == '2',
        'sin_volcados_de_memoria': resource.getrlimit(resource.RLIMIT_CORE)[0] == 0,
        'sin_red_ipv4': denied_socket(socket.AF_INET),
        'sin_red_ipv6': denied_socket(socket.AF_INET6),
        'entrada_seleccionada_legible': json.loads(INPUT.read_text()) == {
            'version': 1, 'kind': 'isolation-fixture', 'events': 0},
        'entrada_no_escribible': unavailable(INPUT, os.O_WRONLY),
        # Este archivo pertenece al usuario del servicio: prueba el montaje RO.
        'montaje_externo_solo_lectura': unavailable(CANARY, os.O_WRONLY),
    }
    forbidden = {
        'webs_ocultas': '/var/www', 'registros_ocultos': '/var/log',
        'administrador_oculto': '/root', 'reglas_ocultas': '/etc/vigilante',
        'cola_autoritativa_oculta': '/var/lib/vigilante',
        'cuentas_privadas_ocultas': '/etc/shadow',
        'credenciales_bd_ocultas': '/etc/mysql/debian.cnf',
        'claves_ssh_ocultas': '/etc/ssh',
        'conexion_php_oculta': '/run/php', 'conexion_bd_oculta': '/run/mysqld',
        'control_de_servicios_oculto': '/run/systemd/private', 'bus_del_sistema_oculto': '/run/dbus',
        'panel_del_servidor_oculto': '/usr/local/ispconfig',
        'copias_del_servidor_ocultas': '/var/backup',
        'programas_locales_del_servidor_ocultos': '/usr/local/bin',
        'directorio_opt_del_servidor_oculto': '/opt',
        'entorno_de_pid1_oculto': '/proc/1/environ',
    }
    checks.update({name: unavailable(path) for name, path in forbidden.items()})
    checks['vista_raiz_limitada'] = set(os.listdir('/')) <= {
        'usr', 'lib', 'lib64', 'dev', 'proc', 'sys', 'tmp', 'etc', 'run', 'root',
        'home', 'var', 'bin', 'input', 'workspace', 'probe.py', 'readonly-canary'}
    alias = Path('/bin')
    checks['alias_bin_limitado_al_ejecutor'] = (not os.path.lexists(alias) or (
        alias.is_symlink() and os.readlink(alias) == 'usr/bin'
        and alias.samefile('/usr/bin')))
    try:
        os.setuid(0)
        checks['escalada_a_administrador_rechazada'] = False
    except PermissionError:
        checks['escalada_a_administrador_rechazada'] = True
    with tempfile.NamedTemporaryFile(dir=WORK, delete=False) as stream:
        temporary = Path(stream.name)
        stream.write(b'comprobacion de escritura propia\n')
        stream.flush()
        os.fsync(stream.fileno())
    checks['espacio_propio_escribible'] = temporary.read_bytes() == b'comprobacion de escritura propia\n'
    temporary.unlink()
    report = {'version': 1, 'uid': os.geteuid(), 'gid': os.getegid(), 'checks': checks,
              'passed': all(checks.values()), 'ai_called': False,
              'root_entries': sorted(os.listdir('/')),
              'namespaces': {name: os.readlink('/proc/self/ns/' + name) for name in ('mnt', 'net')}}
    with tempfile.NamedTemporaryFile(dir=WORK, delete=False, mode='w') as stream:
        json.dump(report, stream, ensure_ascii=False, indent=2)
        stream.write('\n')
        stream.flush()
        os.fsync(stream.fileno())
        result = Path(stream.name)
    result.replace(WORK / 'isolation-result.json')
    print(json.dumps({'checks': len(checks), 'passed': report['passed'], 'ai_called': False}))
    return 0 if report['passed'] else 1


if __name__ == '__main__':
    raise SystemExit(main())
