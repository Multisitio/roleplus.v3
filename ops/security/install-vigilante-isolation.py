#!/usr/bin/env python3
"""Instala solo la cuenta, el espacio aislado y una comprobacion manual acotada."""
import hashlib
import json
import os
from pathlib import Path
import pwd
import grp
import stat
import subprocess
import sys

SOURCE = Path(__file__).resolve().parent
NAME = 'vigilante-ai'
STATE = Path('/var/lib/vigilante-ai')
WORK = STATE / 'workspace'
INPUT = Path('/srv/vigilante-ai')
UNIT = Path('/etc/systemd/system/vigilante-isolation-check.service')
PROBE = Path('/usr/local/lib/vigilante/vigilante-isolation-probe.py')
SNAPSHOT = Path('/root/codex-vigilante/tramo-5')


def run(args):
    return subprocess.run(args, check=True, capture_output=True, text=True, timeout=45)


def trusted_directory(path):
    for part in [path, *path.parents]:
        info = part.lstat()
        if (not stat.S_ISDIR(info.st_mode) or info.st_uid != 0 or info.st_mode & 0o022):
            raise ValueError('Directorio de instalacion no fiable')


def write_new(path, data, mode=0o600, uid=0, gid=0):
    fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, mode)
    with os.fdopen(fd, 'wb') as stream:
        stream.write(data)
        os.fchown(stream.fileno(), uid, gid)
        os.fchmod(stream.fileno(), mode)
        stream.flush()
        os.fsync(stream.fileno())


def main():
    if os.geteuid() != 0:
        raise PermissionError('Instalacion solo como administrador')
    trusted_directory(SOURCE)
    trusted_directory(SNAPSHOT)
    for path in (STATE, INPUT, UNIT, PROBE):
        if os.path.lexists(path):
            raise ValueError('Ya existe un destino; revisar antes de modificar: ' + str(path))
        trusted_directory(path.parent)
    for lookup in (pwd.getpwnam, grp.getgrnam):
        try:
            lookup(NAME)
        except KeyError:
            pass
        else:
            raise ValueError('Ya existe la identidad; no se reutiliza sin revision')
    unit = (SOURCE / UNIT.name).read_bytes()
    probe = (SOURCE / PROBE.name).read_bytes()
    # Validar antes de crear la cuenta. La advertencia por usuario aun inexistente
    # no convierte una directiva desconocida en aceptable: se revisa la salida.
    validation = run(['systemd-analyze', 'verify', str(SOURCE / UNIT.name)])
    if any(word in validation.stderr for word in ('Unknown', 'Failed to parse', 'Failed to resolve')):
        raise ValueError('La unidad contiene directivas no admitidas')
    live_paths = [Path('/usr/local/lib/vigilante/vps-service-control.py'),
                  Path('/usr/local/sbin/webwatchdog.sh'), Path('/etc/vigilante/policy.json')]
    fingerprints = {str(p): hashlib.sha256(p.read_bytes()).hexdigest() for p in live_paths}
    run(['/usr/sbin/useradd', '--system', '--user-group', '--home-dir', str(WORK),
         '--shell', '/usr/sbin/nologin', '--no-create-home', NAME])
    account = pwd.getpwnam(NAME)
    uid, gid = account.pw_uid, account.pw_gid
    created_files = []
    try:
        STATE.mkdir(mode=0o711)
        WORK.mkdir(mode=0o700)
        os.chown(WORK, uid, gid)
        INPUT.mkdir(mode=0o755)
        (INPUT / 'root').mkdir(mode=0o755)
        for name in ('usr', 'lib', 'lib64', 'dev', 'proc', 'sys', 'tmp', 'etc',
                     'run', 'root', 'home', 'input', 'workspace'):
            (INPUT / 'root' / name).mkdir(mode=0o755)
        (INPUT / 'input').mkdir(mode=0o750)
        os.chown(INPUT / 'input', 0, gid)
        sample = json.dumps({'version': 1, 'kind': 'isolation-fixture', 'events': 0}).encode()
        write_new(INPUT / 'input/sample.json', sample, 0o640, 0, gid)
        write_new(INPUT / 'readonly-canary', b'Comprobacion de montaje; sin datos reales.\n', 0o600, uid, gid)
        write_new(PROBE, probe, 0o644)
        created_files.append(PROBE)
        write_new(UNIT, unit, 0o644)
        created_files.append(UNIT)
        run(['systemd-analyze', 'verify', str(UNIT)])
        run(['systemctl', 'daemon-reload'])
        run(['systemctl', 'start', UNIT.name])
        report = json.loads((WORK / 'isolation-result.json').read_text())
        if not report.get('passed') or not report.get('checks') or not all(report['checks'].values()):
            raise ValueError('La comprobacion real no ha superado todas las barreras')
        for name in ('mnt', 'net'):
            if report['namespaces'][name] == os.readlink('/proc/self/ns/' + name):
                raise ValueError('El proceso comparte un espacio de nombres del instalador')
        if report['uid'] != uid or report['gid'] != gid:
            raise ValueError('Identidad del proceso incorrecta')
        if any(hashlib.sha256(Path(p).read_bytes()).hexdigest() != value for p, value in fingerprints.items()):
            raise ValueError('Ha cambiado el control existente durante la instalacion')
        status = run(['/usr/bin/passwd', '-S', NAME]).stdout.split()
        if len(status) < 2 or status[1] != 'L' or os.getgrouplist(NAME, gid) != [gid]:
            raise ValueError('La cuenta no esta bloqueada o tiene grupos adicionales')
        result = {'status': 'instalado_y_comprobado', 'uid': uid, 'gid': gid,
                  'checks': report, 'existing_control_sha256': fingerprints,
                  'unit_sha256': hashlib.sha256(unit).hexdigest(),
                  'probe_sha256': hashlib.sha256(probe).hexdigest(),
                  'ai_called': False, 'timer_created': False, 'web_services_restarted': False}
        write_new(SNAPSHOT / 'installation-result.json', (json.dumps(result, indent=2) + '\n').encode())
        print(json.dumps({'status': result['status'], 'checks': len(report['checks']),
                          'ai_called': False, 'timer_created': False}))
    except Exception:
        # Retirar solo las dos entradas nuevas; conservar cuenta bloqueada y
        # evidencia privada para diagnosticar. Nunca borrar carpetas recursivamente.
        subprocess.run(['systemctl', 'stop', UNIT.name], capture_output=True, timeout=10)
        for path in reversed(created_files):
            expected = unit if path == UNIT else probe
            if path.is_file() and not path.is_symlink() and path.read_bytes() == expected:
                path.unlink()
        subprocess.run(['systemctl', 'daemon-reload'], capture_output=True, timeout=15)
        raise


if __name__ == '__main__':
    try:
        main()
    except (OSError, ValueError, subprocess.SubprocessError) as error:
        print(json.dumps({'status': 'fallo', 'type': type(error).__name__, 'detail': str(error)}))
        raise SystemExit(1)
