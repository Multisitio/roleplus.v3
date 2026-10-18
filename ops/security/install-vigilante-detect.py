#!/usr/bin/env python3
"""Instala el detector sin activar llamadas hasta validar su primer ciclo."""
import hashlib
import json
import os
from pathlib import Path
import subprocess

LIB=Path('/usr/local/lib/vigilante')
UNITS=Path('/etc/systemd/system')
CONTROL=Path('/var/lib/vigilante-auth/control')
HERE=Path(__file__).resolve().parent
PROGRAMS=['vigilante-event-data.py','vigilante-event-task.py','vigilante-detect.py',
          'vigilante-incident-task.py','vigilante-model-check.py','vigilante-notifications.py']


def generate():
    client=(UNITS/'vigilante-incident-analyze.service').read_text()
    client=client.replace('vigilante-incident-read','vigilante-event-read')
    client=client.replace('/run/incident.sock','/run/event.sock').replace('incident-analysis.jsonl','event-analysis.jsonl')
    client=client.replace('ExecStart=/usr/bin/python3 -I -B /tools/incident-task.py','ExecStart=/usr/bin/python3 -I -B /tools/event-task.py')
    client=client.replace('Description=Diagnostico manual TLS sin cambios en las webs','Description=Analisis acotado de observaciones sin escrituras')
    client+=('BindReadOnlyPaths='+str(LIB/'vigilante-event-task.py')+':/tools/event-task.py\n'
             'BindReadOnlyPaths='+str(LIB/'vigilante-event-data.py')+':/tools/event-data.py\n')
    worker=(UNITS/'vigilante-incident-read@.service').read_text()
    worker=worker.replace('Lectura aislada de metadatos TLS','Lectura aislada de observaciones')
    worker=worker.replace('ExecStart=/usr/bin/python3 -I -B /input/tools/incident.py read',
                          'ExecStart=/usr/bin/python3 -I -B /input/tools/event-data.py read')
    worker+='BindReadOnlyPaths='+str(LIB/'vigilante-event-data.py')+':/input/tools/event-data.py\n'
    endpoint=(UNITS/'vigilante-incident-read.socket').read_text().replace('vigilante-incident-read','vigilante-event-read')
    endpoint=endpoint.replace('Lectura privada TLS','Lectura privada de observaciones')
    detector='''[Unit]
Description=Deteccion incremental y analisis de solo lectura
After=webwatchdog.service

[Service]
Type=oneshot
User=root
Group=root
ExecStart=/usr/bin/python3 -I -B /usr/local/lib/vigilante/vigilante-detect.py
UMask=0077
NoNewPrivileges=yes
CapabilityBoundingSet=CAP_DAC_READ_SEARCH CAP_CHOWN
AmbientCapabilities=
ProtectSystem=strict
ProtectHome=yes
PrivateTmp=yes
PrivateDevices=yes
PrivateNetwork=yes
RestrictAddressFamilies=AF_UNIX
IPAddressDeny=any
ProtectKernelTunables=yes
ProtectKernelModules=yes
ProtectKernelLogs=yes
ProtectControlGroups=yes
ProtectClock=yes
RestrictSUIDSGID=yes
RestrictNamespaces=yes
LockPersonality=yes
MemoryDenyWriteExecute=yes
SystemCallArchitectures=native
SystemCallFilter=@system-service
SystemCallErrorNumber=EPERM
InaccessiblePaths=/var/lib/vigilante-auth/profile
ReadWritePaths=/var/lib/vigilante /srv/vigilante-ai/input/events
ReadWritePaths=/run/lock/vigilante-state.lock /var/lib/vigilante-auth/control/event-analysis.jsonl
CPUQuota=5%
MemoryMax=128M
TasksMax=16
TimeoutStartSec=145s
TimeoutStopSec=5s
LimitCORE=0
LimitFSIZE=4M
Nice=15
IOWeight=10
StandardOutput=journal
StandardError=journal
Restart=no
'''
    hook='''[Unit]
OnFailure=vigilante-detect.service

[Service]
ExecStartPost=-/usr/bin/systemctl --no-block start vigilante-detect.service
'''
    return {'vigilante-event-analyze.service':client,'vigilante-event-read@.service':worker,
            'vigilante-event-read.socket':endpoint,'vigilante-detect.service':detector,
            'webwatchdog.service.d/30-vigilante-detect.conf':hook}


def main():
    if os.geteuid()!=0:raise PermissionError('Solo administrador')
    policy=json.loads(Path('/etc/vigilante/policy.json').read_text())
    if policy.get('automatic_analysis_enabled') is True or policy.get('ai_actions_enabled') is not False:
        raise PermissionError('Desactivar analisis automaticos antes de instalar')
    expected=json.loads((HERE/'expected.json').read_text())
    for path,digest in expected.items():
        if hashlib.sha256(Path(path).read_bytes()).hexdigest()!=digest:
            raise ValueError('Estado previo cambiado')
    units=generate();old={};created=[]
    destinations=[LIB/n for n in PROGRAMS]+[UNITS/n for n in units]+[CONTROL/'event-analysis.jsonl']
    for path in destinations:
        if os.path.lexists(path):
            if path not in [LIB/'vigilante-incident-task.py',LIB/'vigilante-model-check.py']:
                raise ValueError('Destino existente')
            if path.is_symlink() or path.stat().st_uid!=0 or path.stat().st_mode&0o022:
                raise PermissionError('Programa previo no confiable')
            old[path]=path.read_bytes()
    events=Path('/srv/vigilante-ai/input/events')
    if events.exists():raise ValueError('Carpeta de observaciones ya existente')
    events.mkdir(mode=0o750);os.chown(events,0,999)
    lock=Path('/run/lock/vigilante-state.lock')
    if not lock.exists():
        fd=os.open(lock,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600);os.close(fd)
    try:
        for name in PROGRAMS:
            target=LIB/name;content=(HERE/name).read_bytes()
            if target in old:(HERE/('previous-'+name)).write_bytes(old[target])
            temporary=target.with_suffix('.new')
            fd=os.open(temporary,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o644)
            with os.fdopen(fd,'wb') as stream:stream.write(content)
            os.replace(temporary,target);created.append(target)
        fd=os.open(CONTROL/'event-analysis.jsonl',os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600);os.close(fd)
        created.append(CONTROL/'event-analysis.jsonl')
        for name,text in units.items():
            target=UNITS/name;target.parent.mkdir(exist_ok=True,mode=0o755)
            fd=os.open(target,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o644)
            with os.fdopen(fd,'w') as stream:stream.write(text)
            created.append(target)
            (HERE/name.replace('/','__')).write_text(text)
        for name in units:
            if name.endswith('.conf'):continue
            check=subprocess.run(['systemd-analyze','verify',str(UNITS/name)],capture_output=True,text=True,timeout=20)
            if check.returncode or any(w in check.stderr for w in ('Unknown','Failed to parse')):
                raise ValueError('Unidad no valida')
        subprocess.run(['systemctl','daemon-reload'],check=True,timeout=15)
    except Exception:
        for path in reversed(created):
            if path in old:path.write_bytes(old[path])
            else:path.unlink()
        subprocess.run(['systemctl','daemon-reload'],capture_output=True,timeout=15)
        raise
    print(json.dumps({'installed':True,'automatic_analysis_enabled':False,'web_services_restarted':False}))


if __name__=='__main__':main()
