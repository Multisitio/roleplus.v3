#!/usr/bin/env python3
"""Agrega un ensayo manual real conservando el aislamiento ya probado."""
import hashlib
import json
import os
from pathlib import Path
import subprocess

LIB = Path('/usr/local/lib/vigilante')
UNITS = Path('/etc/systemd/system')
CONTROL = Path('/var/lib/vigilante-auth/control')


def generate():
    client = (UNITS/'vigilante-model-check.service').read_text()
    client = client.replace('vigilante-tool-probe', 'vigilante-incident-read').replace('/run/tool-probe.sock', '/run/incident.sock')
    client = client.replace('ExecStart=/usr/bin/python3 -I -B /tools/model-check.py',
                            'ExecStart=/usr/bin/python3 -I -B /tools/incident-task.py')
    client = client.replace('model-check.jsonl', 'incident-analysis.jsonl')
    client = client.replace('Description=Prueba manual de IA con herramientas externas',
                            'Description=Diagnostico manual TLS sin cambios en las webs')
    client += ('BindReadOnlyPaths='+str(LIB/'vigilante-incident.py')+':/tools/incident.py\n'
               'BindReadOnlyPaths='+str(LIB/'vigilante-incident-task.py')+':/tools/incident-task.py\n')
    worker = (UNITS/'vigilante-tool-probe@.service').read_text()
    worker = worker.replace('ExecStart=/usr/bin/python3 -I -B /input/tools/tool-probe.py',
                            'ExecStart=/usr/bin/python3 -I -B /input/tools/incident.py read')
    worker = worker.replace('Description=Herramienta sintetica aislada del vigilante',
                            'Description=Lectura aislada de metadatos TLS')
    worker += 'BindReadOnlyPaths='+str(LIB/'vigilante-incident.py')+':/input/tools/incident.py\n'
    endpoint = (UNITS/'vigilante-tool-probe.socket').read_text().replace('vigilante-tool-probe', 'vigilante-incident-read')
    endpoint = endpoint.replace('Herramienta sintetica privada', 'Lectura privada TLS')
    return {'vigilante-incident-analyze.service': client,
            'vigilante-incident-read.socket': endpoint, 'vigilante-incident-read@.service': worker}


def main():
    if os.geteuid() != 0:
        raise PermissionError('Solo administrador')
    source = Path(__file__).resolve().parent
    for directory in (source, LIB, UNITS, CONTROL):
        info = directory.lstat()
        if not directory.is_dir() or directory.is_symlink() or info.st_uid != 0 or info.st_mode & 0o022:
            raise PermissionError('Directorio no confiable')
    generated = generate()
    output = CONTROL/'incident-analysis.jsonl'
    if any(os.path.lexists(UNITS/name) for name in generated) or os.path.lexists(output):
        raise ValueError('Destino ya existente')
    for name in ('vigilante-incident.py', 'vigilante-incident-task.py'):
        if os.path.lexists(LIB/name):
            raise ValueError('Programa ya existente')
    previous = (LIB/'vigilante-model-check.py').read_bytes()
    expected = json.loads((source/'expected.json').read_text())['model_helper_sha256']
    if hashlib.sha256(previous).hexdigest() != expected:
        raise ValueError('Ayudante compartido ha cambiado')
    # La actualizacion compartida es compatible con el ensayo sintetico anterior.
    for name in ('vigilante-model-check.py', 'vigilante-incident.py', 'vigilante-incident-task.py'):
        target = LIB/name
        if target.exists() and name != 'vigilante-model-check.py':
            raise ValueError('Destino ya existente')
        content = (source/name).read_bytes()
        if name == 'vigilante-model-check.py':
            previous = target.read_bytes()
            expected = json.loads((source/'expected.json').read_text())['model_helper_sha256']
            if hashlib.sha256(previous).hexdigest() != expected:
                raise ValueError('Ayudante compartido ha cambiado')
            (source/'previous-model-check.py').write_bytes(previous)
        temporary = target.with_suffix('.new')
        fd = os.open(temporary, os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW, 0o644)
        with os.fdopen(fd, 'wb') as stream:
            stream.write(content); stream.flush(); os.fsync(stream.fileno())
        os.replace(temporary, target)
    fd = os.open(output, os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW, 0o600); os.close(fd)
    for name, text in generated.items():
        fd = os.open(UNITS/name, os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW, 0o644)
        with os.fdopen(fd, 'w') as stream:
            stream.write(text)
        (source/name).write_text(text)
    for name in generated:
        result = subprocess.run(['systemd-analyze', 'verify', str(UNITS/name)], capture_output=True, text=True, timeout=20)
        if result.returncode or any(w in result.stderr for w in ('Unknown', 'Failed to parse')):
            raise ValueError('Unidad no valida')
    subprocess.run(['systemctl', 'daemon-reload'], check=True, timeout=20)
    print(json.dumps({'installed': True, 'started': False, 'automatic_repairs_enabled': False}))


if __name__ == '__main__':
    main()
