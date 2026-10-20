#!/usr/bin/env python3
"""Coordinador de alarmas: texto cerrado, estado privado y transporte sin root."""
import importlib.util
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import tempfile
import time
import uuid

HERE=Path(__file__).resolve().parent
def load(name,filename):
    source=HERE/filename
    if not source.exists():source=HERE.parent/filename
    spec=importlib.util.spec_from_file_location(name,source)
    module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
    return module

data=load('push_data','vigilante-event-data.py')
notifications=load('push_notifications','vigilante-notifications.py')
detector=load('push_atomic','vigilante-detect.py')
CONFIG=Path('/etc/vigilante/roleplus-push.json')
STATE=Path('/var/lib/vigilante/push-delivery.json')
CACHE=Path('/var/lib/vigilante/push-subscriptions.json')
DETAILS=Path('/var/www/clients/client1/web6/private/temp/vigilante/details.json')


def projection(queue,state,recipient,now):
    """Copia acotada para la web: sin suscripciones, claves ni texto del modelo."""
    items=[]
    for identifier,item in queue['items'].items():
        event=item['event']
        if event['kind'] not in notifications.LABELS:continue
        episode=item.get('episode_started',item['first_seen'])
        delivered=state['items'].get(identifier+':'+str(episode),{})
        items.append({'reference':identifier,'site':event['site'],'kind':event['kind'],
                      'family':event['family'],'code':event['code'],'count':event['count'],
                      'first_seen':item['first_seen'],'last_seen':item['last_seen'],
                      'observed':item['currently_observed'],
                      'accepted':delivered.get('accepted',False)})
    return {'version':1,'recipient':recipient,'generated_at':now,'observed_at':queue['last_poll'],
            'last_test':state.get('last_test',0),'items':items}


def publish(value):
    data.trusted(DETAILS.parent,True)
    if DETAILS.exists():data.trusted(DETAILS)
    fd,name=tempfile.mkstemp(dir=DETAILS.parent)
    try:
        # La carpeta setgid transmite el grupo lector; no se necesita CAP_CHOWN.
        os.fchmod(fd,0o640)
        with os.fdopen(fd,'w') as stream:
            json.dump(value,stream);stream.flush();os.fsync(stream.fileno())
        os.replace(name,DETAILS)
    finally:
        if os.path.exists(name):os.unlink(name)


def read(path,limit=4194304):
    data.trusted(path.parent,True);data.trusted(path)
    if path.stat().st_size>limit:raise ValueError('Archivo excesivo')
    return json.loads(path.read_text())


def select(queue,state,sites,now):
    if set(queue)!={'version','last_poll','items'} or queue['version']!=1:
        raise ValueError('Bandeja no valida')
    if type(queue['last_poll']) is not int or not 0<=now-queue['last_poll']<=180:
        raise ValueError('Bandeja caducada')
    if not isinstance(queue['items'],dict) or len(queue['items'])>4096:
        raise ValueError('Bandeja excesiva')
    attempts=state.get('attempts',[])
    if any(type(t) is not int or t>now for t in attempts):raise ValueError('Reloj no valido')
    if sum(now-t<3600 for t in attempts)>=6 or sum(now-t<900 for t in attempts)>=3:
        return []
    selected=[]
    for identifier,item in sorted(queue['items'].items()):
        if item.get('currently_observed') is not True:continue
        event=item['event']
        value={'version':1,'id':str(uuid.uuid4()),'collected_at':now,'scope_site_count':len(sites),'events':[event]}
        value['sha256']=data.fingerprint(value);data.validate(value,now)
        if identifier!=event['id'] or event['site'] not in {*sites,'server'}:raise ValueError('Evento ajeno')
        if event['severity']!='high' or event['kind'] not in notifications.LABELS:continue
        if type(item['last_seen']) is not int or not 0<=now-item['last_seen']<=180:continue
        episode=item.get('episode_started',item['first_seen'])
        if type(episode) is not int or not 0<episode<=item['last_seen']:raise ValueError('Episodio no valido')
        key=identifier+':'+str(episode)
        previous=state.get('items',{}).get(key,{})
        if previous.get('accepted') or previous.get('attempts',0)>=3 or now-previous.get('last_attempt',0)<300:continue
        suffix=' ('+notifications.SERVICES[event['family']]+')' if event['family'] in notifications.SERVICES else ''
        text=notifications.LABELS[event['kind']]+': '+event['site']+suffix+'. Ref '+identifier[:12]+'.'
        selected.append((key,text))
    return selected[:5]


def worker(request):
    args=['/usr/bin/systemd-run','--quiet','--wait','--pipe','--collect','--unit=vigilante-push-worker',
          '-p','User=web6','-p','Group=client1','-p','NoNewPrivileges=yes','-p','CapabilityBoundingSet=',
          '-p','ProtectSystem=strict','-p','ProtectHome=yes','-p','PrivateTmp=yes','-p','PrivateDevices=yes',
          '-p','ProtectKernelTunables=yes','-p','ProtectKernelModules=yes','-p','ProtectKernelLogs=yes',
          '-p','ProtectControlGroups=yes','-p','RestrictSUIDSGID=yes','-p','RestrictNamespaces=yes',
          '-p','LockPersonality=yes','-p','RestrictAddressFamilies=AF_UNIX AF_INET AF_INET6',
          '-p','InaccessiblePaths=/etc/vigilante /var/lib/vigilante /var/lib/vigilante-auth /var/lib/vigilante-wa /srv/vigilante-ai',
          '-p','CPUQuota=10%','-p','MemoryMax=128M','-p','TasksMax=8','-p','RuntimeMaxSec=85s',
          '-p','LimitCORE=0','-p','UMask=0077',
          '/usr/bin/php','-d','display_errors=0','-d','log_errors=0','-d','opcache.enable_cli=0',
          '/usr/local/lib/vigilante/roleplus-push-worker.php']
    result=subprocess.run(args,input=json.dumps(request),capture_output=True,text=True,timeout=95)
    if result.returncode or len(result.stdout)>65536:raise RuntimeError('Transporte fallido')
    value=json.loads(result.stdout)
    import pwd
    if value.get('version')!=1 or value.get('uid')!=pwd.getpwnam('web6').pw_uid:
        raise ValueError('Identidad del ejecutor no valida')
    if type(value.get('fresh')) is not bool or not isinstance(value.get('subscriptions'),list) or len(value['subscriptions'])>5:
        raise ValueError('Respuesta no valida')
    if not isinstance(value.get('reports'),list) or len(value['reports'])>5:raise ValueError('Informes no validos')
    for row in value['reports']:
        if set(row)!={'accepted','status'} or type(row['accepted']) is not bool or type(row['status']) is not int:
            raise ValueError('Informe no valido')
    return value


def main(test=False):
    import fcntl
    if os.geteuid()!=0:raise PermissionError('Solo administrador')
    config=read(CONFIG,4096)
    if set(config)!={'version','enabled','recipient'} or config['version']!=1 or not re.fullmatch('[a-f0-9]{8}',config['recipient']):
        raise ValueError('Configuracion no valida')
    if config['enabled'] is not True and not test:
        print(json.dumps({'decision':'disabled'}));return
    # Bloqueo propio: no se espera al analisis ni se modifica el estado antipánico.
    lockpath=Path('/run/vigilante-push/sender.lock')
    data.trusted(lockpath.parent,True)
    fd=os.open(lockpath,os.O_RDWR|os.O_CREAT|os.O_NOFOLLOW,0o600)
    data.trusted(lockpath)
    with os.fdopen(fd,'a+') as lock:
        fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
        now=int(time.time())
        state=read(STATE) if STATE.exists() else {'version':1,'attempts':[],'items':{}}
        if state.get('version')!=1 or len(state.get('items',{}))>4096:raise ValueError('Estado no valido')
        state['attempts']=[t for t in state['attempts'] if now-t<86400]
        state['items']={k:v for k,v in state['items'].items() if now-v['last_attempt']<2592000}
        queue=read(notifications.PATH)
        sites=read(detector.POLICY)['sites']
        # Validar tambien las observaciones antes de publicarlas en la web.
        selected=select(queue,state,sites,now)
        if test:selected=[]
        cache=read(CACHE,65536) if CACHE.exists() else {}
        cached=cache.get('subscriptions',[]) if cache.get('recipient')==config['recipient'] and 0<=now-cache.get('updated',0)<=604800 else []
        request={'recipient':config['recipient'],'cached_subscriptions':cached,'mode':'send' if test or selected else 'refresh'}
        if test:
            if state.get('last_test',0)>now-300:raise ValueError('Prueba ya enviada recientemente')
            state['last_test']=now
            request['reference']='prueba'
            request['body']='Prueba de activacion del vigilante: los avisos urgentes se enviaran por R+. Ref '+uuid.uuid4().hex[:8]+'.'
        elif selected:
            if len(state['items'])+sum(k not in state['items'] for k,_ in selected)>4096:
                raise ValueError('Estado lleno; requiere revision administrativa')
            request['body']='\n'.join(text for _,text in selected)
            request['reference']=selected[0][0].split(':',1)[0]
            state['attempts'].append(now)
            for key,_ in selected:
                item=state['items'].setdefault(key,{'attempts':0,'accepted':False})
                item.update(attempts=item['attempts']+1,last_attempt=now)
        elif cache.get('updated',0)>now-3600:
            publish(projection(queue,state,config['recipient'],now))
            print(json.dumps({'decision':'quiet','events':0}));return
        detector.atomic(STATE,state)  # Reserva antes del envio: un cierre no genera un bucle.
        publish(projection(queue,state,config['recipient'],now))
        response=worker(request)
        if response['fresh']:
            detector.atomic(CACHE,{'recipient':config['recipient'],'updated':now,'subscriptions':response['subscriptions']})
        accepted=bool(response['reports']) and all(r['accepted'] for r in response['reports'])
        if selected:
            for key,_ in selected:state['items'][key]['accepted']=accepted
            detector.atomic(STATE,state)
            publish(projection(queue,state,config['recipient'],now))
        print(json.dumps({'decision':'test' if test else 'sent' if selected else 'refreshed',
                          'events':len(selected),'accepted':accepted,'device_count':len(response['subscriptions']),
                          'subscription_refreshed':response['fresh'],'delivery_confirmed':False}),flush=True)


if __name__=='__main__':
    try:
        if sys.argv[1:] not in ([],['--test']):raise ValueError('Operacion no autorizada')
        main(sys.argv[1:]==['--test'])
    except BlockingIOError:print('{"decision":"busy"}')
    except Exception as error:
        print(json.dumps({'decision':'error','error_type':type(error).__name__}),flush=True)
        raise SystemExit(1)
