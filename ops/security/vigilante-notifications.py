#!/usr/bin/env python3
"""Bandeja local de avisos urgentes. Sin red, credenciales ni texto del modelo."""
import importlib.util
import json
from pathlib import Path
import uuid

spec=importlib.util.spec_from_file_location('notification_data',Path(__file__).with_name('vigilante-event-data.py'))
data=importlib.util.module_from_spec(spec);spec.loader.exec_module(data)
PATH=Path('/var/lib/vigilante/notifications.json')
LABELS={
    'site_down':'Web no disponible',
    'service_down':'Servicio no disponible',
    'automation_failed':'Tarea programada pendiente de revision',
    'php_fatal':'Error fatal de PHP',
    'memory_exhausted':'Memoria agotada en PHP',
    'disk_space':'Espacio de disco en nivel critico',
    'inodes':'Inodos en nivel critico',
}
SERVICES={'apache2':'Apache','php_fpm':'PHP-FPM','mariadb':'MariaDB','watchdog':'Vigilante'}


def reconcile(previous,events,sites,now):
    # Copia completa: una entrada incorrecta nunca modifica parcialmente el estado.
    result=json.loads(json.dumps(previous))
    if set(result)!={'version','last_poll','items'} or result['version']!=1:
        raise ValueError('Bandeja no valida')
    if type(result['last_poll']) is not int or now<result['last_poll']:
        raise ValueError('Reloj anterior al estado de avisos')
    if not isinstance(result['items'],dict) or len(result['items'])>4096:
        raise ValueError('Bandeja excesiva')
    for start in range(0,len(events),16):
        value={'version':1,'id':str(uuid.uuid4()),'collected_at':now,
               'scope_site_count':len(sites),'events':events[start:start+16]}
        value['sha256']=data.fingerprint(value);data.validate(value,now)
    if len({e['id'] for e in events})!=len(events) or any(e['site'] not in {*sites,'server'} for e in events):
        raise ValueError('Observaciones ajenas al catalogo o duplicadas')
    for item in result['items'].values():
        item['currently_observed']=False
    for event in events:
        if event['severity']!='high' or event['kind'] not in LABELS:
            continue
        identifier=event['id']
        if identifier not in result['items']:
            if len(result['items'])>=4096:
                raise ValueError('Bandeja llena; requiere revision administrativa')
            result['items'][identifier]={
                'first_seen':now,'episode_started':now,'status':'pending_transport','channel':'roleplus',
                'delivery_confirmed':False,
            }
        item=result['items'][identifier]
        # Diez minutos sin observar la causa abren otro episodio, sin afirmar
        # que el servicio se haya recuperado durante ese intervalo.
        if now-item.get('last_seen',now)>=600:
            item['episode_started']=now
        item.setdefault('episode_started',item['first_seen'])
        item['channel']='roleplus'
        item.update(last_seen=now,currently_observed=True,event=dict(event))
        suffix=' ('+SERVICES[event['family']]+')' if event['family'] in SERVICES else ''
        item['text']=LABELS[event['kind']]+': '+event['site']+suffix+'. Referencia '+identifier[:12]+'.'
    result['last_poll']=now
    return result


def record(events,sites,now,atomic):
    if PATH.exists():
        data.trusted(PATH)
        if PATH.stat().st_size>4194304:
            raise ValueError('Bandeja excesiva')
        previous=json.loads(PATH.read_text())
    else:
        previous={'version':1,'last_poll':0,'items':{}}
    result=reconcile(previous,events,sites,now)
    # Se escribe bajo el bloqueo de estado ya adquirido por el detector.
    atomic(PATH,result)
    return sum(item['currently_observed'] for item in result['items'].values())
