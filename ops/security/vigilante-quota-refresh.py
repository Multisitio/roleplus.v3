#!/usr/bin/env python3
"""Renovacion de metadatos de cuota. Ninguna inferencia ni control de servicios web."""
import importlib.util
import json
import os
from pathlib import Path
import stat
import subprocess
import sys
import tempfile
import time

HERE=Path(__file__).resolve().parent
def load(name,file):
    spec=importlib.util.spec_from_file_location(name,HERE/file)
    module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module);return module

data=load('refresh_data','vigilante-event-data.py')
quota=load('refresh_quota','vigilante-quota.py')
PATH=Path('/var/lib/vigilante/quota-refresh.json')
AUTOMATIC=PATH.with_name('automatic.json')
POLICY=Path('/etc/vigilante/policy.json')
REASONS={'reserva_del_usuario','agotada','cuota_desconocida','datos_caducados','requiere_nueva_consulta'}


def empty():
    return {'version':1,'snapshot':None,'attempts':[],'last_attempt':0,'next_check_at':0,'failures':0}


def read(path):
    data.trusted(path.parent,True);data.trusted(path)
    if path.stat().st_size>4194304:raise ValueError('Datos excesivos')
    return json.loads(path.read_text())


def atomic(value):
    data.trusted(PATH.parent,True)
    if PATH.exists():data.trusted(PATH)
    fd,name=tempfile.mkstemp(dir=PATH.parent)
    try:
        os.fchmod(fd,0o600)
        with os.fdopen(fd,'w') as f:json.dump(value,f);f.flush();os.fsync(f.fileno())
        os.replace(name,PATH)
    finally:
        if os.path.exists(name):os.unlink(name)


def due_at(cache,automatic,now):
    if cache.get('version')!=1:raise ValueError('Version no valida')
    if type(cache.get('last_attempt')) is not int or cache['last_attempt']>now:raise ValueError('Reloj anterior al estado')
    attempts=cache.get('attempts')
    if not isinstance(attempts,list) or any(type(t) is not int or t>now for t in attempts):raise ValueError('Intentos no validos')
    recent=[t for t in attempts if now-t<86400]
    if len(recent)>=8:return min(recent)+86400
    next_check=cache.get('next_check_at')
    if type(next_check) is not int:raise ValueError('Fecha no valida')
    if automatic.get('quota_block_reason') in REASONS:
        blocked=automatic.get('quota_blocked_at',0)
        retry=automatic.get('quota_retry_at',now+900)
        if type(blocked) is not int or type(retry) is not int:raise ValueError('Espera no valida')
        if blocked>cache['last_attempt']:next_check=min(next_check,retry)
        snapshot=cache.get('snapshot')
        if snapshot and cache['last_attempt']<=snapshot['checked_at'] and now-snapshot['checked_at']>300 and quota.admission(snapshot,snapshot['checked_at']).get('routine_allowed'):
            next_check=min(next_check,now)
    return next_check


def after_attempt(cache,snapshot,now):
    value=dict(cache)
    if snapshot is not None:
        gate=quota.admission(snapshot,now)
        if gate['state'] not in ('datos_caducados','requiere_nueva_consulta','cuota_desconocida'):
            value.update(snapshot=snapshot,failures=0,next_check_at=min(gate['next_reset_check_at'],now+21600))
            return value
    failures=min(8,value.get('failures',0)+1)
    value.update(snapshot=None,failures=failures,next_check_at=now+min(21600,900*2**(failures-1)))
    return value


def release(automatic,cache,now):
    """Solo retira esperas de cuota acreditadas; conserva presupuesto e historial."""
    snapshot=cache.get('snapshot')
    if automatic.get('quota_block_reason') not in REASONS or not snapshot:return False
    if snapshot['checked_at']<=automatic.get('quota_blocked_at',0):return False
    if quota.admission(snapshot,now).get('routine_allowed') is not True:return False
    automatic['blocked_until']=0
    automatic['quota_block_reason']=None
    automatic['quota_retry_at']=0
    automatic['quota_released_at']=now
    return True


def mark_legacy_wait(automatic,reply):
    """Migracion administrativa: exigir el informe que produjo la espera exacta."""
    if automatic.get('quota_block_reason') is not None:return False
    gate=reply.get('admission',{})
    attempts=automatic.get('attempts',[])
    reset=gate.get('next_reset_check_at')
    if not attempts or any(type(t) is not int for t in attempts) or type(reset) is not int:return False
    if reply.get('model_called') is not False or reply.get('completed') is not False or gate.get('routine_allowed') is not False or gate.get('state') not in REASONS:return False
    if automatic.get('blocked_until')!=max(max(attempts)+3600,reset):return False
    automatic.update(quota_block_reason=gate['state'],quota_blocked_at=max(attempts),quota_retry_at=reset)
    return True


def request_if_due(automatic,now):
    cache=read(PATH) if PATH.exists() else empty()
    due_at(cache,automatic,now)  # No modificar una espera si el estado es invalido.
    released=release(automatic,cache,now)
    if due_at(cache,automatic,now)<=now:
        subprocess.run(['/usr/bin/systemctl','start','--no-block','vigilante-quota-refresh.service'],capture_output=True,timeout=5)
    return released


def main(force=False):
    import fcntl
    if os.geteuid()!=0:raise PermissionError('Solo administrador')
    policy=read(POLICY)
    if policy.get('automatic_quota_refresh_enabled') is not True and not force:return
    data.trusted(PATH.parent,True)
    fd=os.open(PATH.with_suffix('.lock'),os.O_RDWR|os.O_CREAT|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'a+') as lock:
        meta=os.fstat(lock.fileno())
        if meta.st_uid!=0 or not stat.S_ISREG(meta.st_mode) or meta.st_mode&0o077:raise PermissionError('Bloqueo no privado')
        fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
        now=int(time.time());cache=read(PATH) if PATH.exists() else empty()
        automatic=read(AUTOMATIC)
        due=due_at(cache,automatic,now)
        # La prueba manual tampoco puede superar el presupuesto de consultas.
        recent=[t for t in cache['attempts'] if now-t<86400]
        if len(recent)>=8 or (not force and due>now):return
        if force and now-cache['last_attempt']<300:return
        cache.update(last_attempt=now,attempts=recent+[now],next_check_at=now+900)
        atomic(cache)  # Reserva antes de llamar; caidas y errores quedan acotados.
        try:snapshot=quota.host_snapshot()
        except (OSError,ValueError,RuntimeError,subprocess.TimeoutExpired):snapshot=None
        now=int(time.time());cache=after_attempt(cache,snapshot,now);atomic(cache)
        gate=quota.admission(snapshot,now) if snapshot else {'state':'cuota_desconocida'}
        with PATH.with_name('diario.jsonl').open('a') as stream:
            stream.write(json.dumps({'time':now,'event':'consulta_renovacion_cuota','state':gate['state'],
                                     'next_check_at':cache['next_check_at'],'model_called':False})+'\n')
        print(json.dumps({'decision':'quota_refreshed' if snapshot else 'quota_unavailable','state':gate['state'],
                          'next_check_at':cache['next_check_at'],'model_called':False}),flush=True)


if __name__=='__main__':
    try:
        if sys.argv[1:] not in ([],['--check-now']):raise ValueError('Operacion no valida')
        main(sys.argv[1:]==['--check-now'])
    except BlockingIOError:pass
    except Exception as error:
        print(json.dumps({'decision':'error','error_type':type(error).__name__,'model_called':False}),flush=True)
        raise SystemExit(1)
