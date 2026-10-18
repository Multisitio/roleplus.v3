#!/usr/bin/env python3
"""Prueba administrativa del conector, sin elegir destinatarios ni textos."""
import json
import os
from pathlib import Path
import socket
import stat
import subprocess
import sys

PATH=Path('/run/vigilante-wa/control.sock')


def request(value):
    with socket.socket(socket.AF_UNIX) as stream:
        stream.settimeout(35)
        stream.connect(str(PATH));stream.sendall(json.dumps(value).encode()+b'\n')
        reply=b''
        while not reply.endswith(b'\n'):
            block=stream.recv(4096)
            if not block:raise RuntimeError('Respuesta incompleta')
            reply+=block
            if len(reply)>8192:raise ValueError('Respuesta excesiva')
        return json.loads(reply)


def check():
    import pwd
    account=pwd.getpwnam('vigilante-wa')
    for path,kind,mode in [(PATH,stat.S_ISSOCK,0o600),(PATH.parent,stat.S_ISDIR,0o700),
                           (Path('/var/lib/vigilante-wa'),stat.S_ISDIR,0o700)]:
        info=path.lstat()
        if not kind(info.st_mode) or info.st_uid!=account.pw_uid or info.st_mode&0o777!=mode:
            raise PermissionError('Permisos de transporte incorrectos')
    checks=3
    for value in ({'action':'send-test','to':'other'}, {'action':'send-test','text':'instruccion'},
                  {'action':'pair-code','phone':'other'}, {'action':'restart'},
                  {'action':'status','extra':True}, None, []):
        if request(value).get('ok') is not False:raise RuntimeError('Orden ajena aceptada')
        checks+=1
    value=request({'action':'status'})
    if value.get('ok') is not True or value.get('version')!=1:raise RuntimeError('Estado no valido')
    checks+=1
    pid=int(subprocess.check_output(['systemctl','show','vigilante-whatsapp.service','-p','MainPID','--value']))
    info=dict(line.split(':',1) for line in Path('/proc/'+str(pid)+'/status').read_text().splitlines() if ':' in line)
    if int(info['Uid'].split()[0])!=account.pw_uid or int(info['CapEff'].strip(),16)!=0 or info['NoNewPrivs'].strip()!='1':
        raise PermissionError('Aislamiento del proceso incorrecto')
    checks+=1
    services={unit:subprocess.run(['systemctl','is-active',unit],capture_output=True,text=True).stdout.strip()
              for unit in ('apache2','php8.4-fpm','mariadb','webwatchdog.timer')}
    if any(value!='active' for value in services.values()):raise RuntimeError('Servicio previo no activo')
    return {'checks_passed':checks,'state':value['state'],'connected':value['connected'],
            'uid':account.pw_uid,'capabilities':0,'no_new_privileges':True,'web_services':services}


if __name__=='__main__':
    if os.geteuid()!=0 or sys.argv[1:] not in (['check'],['status'],['send-test'],['pair-code']):
        raise SystemExit('Operacion administrativa no autorizada')
    print(json.dumps(check() if sys.argv[1]=='check' else request({'action':sys.argv[1]})))
