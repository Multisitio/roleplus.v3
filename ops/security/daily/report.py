#!/usr/bin/env python3
"""Resumen determinista diario: porcentajes, observaciones y enlaces privados."""
import collections
import datetime
import importlib.util
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import time
from zoneinfo import ZoneInfo

HERE=Path(__file__).resolve().parent
def load(name,filename):
    path=HERE/filename
    if not path.exists():path=HERE.parent/filename
    spec=importlib.util.spec_from_file_location(name,path)
    module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module);return module

data=load('daily_data','vigilante-event-data.py')
detector=load('daily_atomic','vigilante-detect.py')
notifications=detector.notifications
quota_reader=load('daily_quota','vigilante-quota.py')
STATE=Path('/var/lib/vigilante-daily')
QUOTA=Path('/var/lib/vigilante-auth/control/quota-output.jsonl')
MADRID=ZoneInfo('Europe/Madrid')


def read(path,limit=4194304):
    data.trusted(path.parent,True);data.trusted(path)
    if path.stat().st_size>limit:raise ValueError('Archivo excesivo')
    return json.loads(path.read_text())


def stamp(when):
    return datetime.datetime.fromtimestamp(when,MADRID).strftime('%d/%m/%Y %H:%M %Z')


def percentage(value):
    if type(value) not in (float,int) or not 0<=value<=100000:raise ValueError('Porcentaje no valido')
    return format(value,',.2f').replace(',',' ').rstrip('0').rstrip('.')+'%'


def quota_lines(snapshot,now):
    if not isinstance(snapshot,dict) or type(snapshot.get('checked_at')) is not int or not 0<=now-snapshot['checked_at']<=300:
        return ['Cuota no disponible: no se ha podido obtener una consulta reciente. No equivale a 0% consumido.']
    rows=[]
    for key,bucket in sorted(snapshot.get('buckets',{}).items()):
        if not re.fullmatch(r'[A-Za-z0-9_.-]{1,80}',key) or not isinstance(bucket,dict):continue
        for label,name in [('principal','primary'),('secundaria','secondary')]:
            w=bucket.get(name)
            if not w:continue
            if type(w.get('resets_at')) is not int or type(w.get('window_minutes')) is not int:continue
            minutes=w['window_minutes']
            duration=(str(minutes//1440)+' días' if minutes%1440==0 else str(minutes//60)+' horas' if minutes%60==0 else str(minutes)+' minutos')
            rows.append(key+' / '+label+' ('+duration+'): '+percentage(w['used_percent'])+
                        ' consumido. Renovación anunciada: '+stamp(w['resets_at'])+
                        ('. Ya vencida; requiere nueva consulta.' if w['resets_at']<=now else '.'))
    return rows or ['Cuota desconocida: el proveedor no ha comunicado ventanas utilizables.']


def compose(now,quota,queue,work,automatic,resources):
    rows=['VIGILANTE DEL VPS',stamp(now),'',
          'CUOTA DE LA CUENTA',*quota_lines(quota,now),
          'Son limites compartidos por tu cuenta; no miden el consumo exclusivo del VPS.',
          'El último 10% queda reservado para tu trabajo.',
          'Una fecha de renovación anunciada no acredita por si sola que se haya renovado la cuota.','',
          'ESTADO DE LAS WEBS']
    for site,status in resources['sites']:
        rows.append(site+': '+status+'.')
    rows+=['','SERVICIOS Y RECURSOS',*resources['services'],
           'Disco: '+percentage(resources['disk'])+' ocupado. Inodos: '+percentage(resources['inodes'])+' ocupados.','',
           'OBSERVACIONES URGENTES DE LAS ÚLTIMAS 24 HORAS']
    items=[(key,item) for key,item in queue['items'].items() if now-86400<=item['last_seen']<=now]
    for key,item in sorted(items,key=lambda pair:pair[1]['last_seen'],reverse=True)[:30]:
        event=item['event']
        if not re.fullmatch('[a-f0-9]{64}',key) or event['kind'] not in notifications.LABELS:continue
        # Campos numericos y categorias del contrato; nunca mensajes originales.
        site=event['site']
        if not re.fullmatch('[a-z0-9]+(?:[.-][a-z0-9]+)*',site):continue
        count=event['count']
        if type(count) is not int or not 1<=count<=100000:continue
        label=notifications.LABELS[event['kind']]
        rows+=['',label+' - '+site+'. Última observación: '+stamp(item['last_seen'])+'.',
               'Estado de la última lectura: '+('observado.' if item['currently_observed'] else 'no observado; no acredita reparación.'),
               'Recuento de la última lectura: '+format(count,',').replace(',',' ')+' (no es un total diario).',
               'Detalle: https://roleplus.app/registrados/vigilante/aviso/'+key]
    if not items:rows.append('No constan observaciones urgentes en ese intervalo; esto no acredita ausencia de todos los errores PHP.')
    rows+=['','TRABAJO DEL VIGILANTE',
           'Análisis iniciados en las últimas 24 horas: '+str(sum(now-86400<=t<=now for t in automatic.get('attempts',[]) if type(t) is int))+'.',
           'La IA mantiene las reparaciones automáticas deshabilitadas. El antipánico conserva el control de reinicios.']
    counts=collections.Counter()
    for item in work.get('items',[]):
        status=item.get('status')
        if status in {'pending','pending_diagnosis','pending_analysis','diagnosed_proposal_pending','blocked','done','completed','resolved','review_required'}:counts[status]+=1
        else:counts['other']+=1
    labels={'pending':'pendientes','pending_diagnosis':'pendientes de diagnóstico','pending_analysis':'a la espera de análisis','diagnosed_proposal_pending':'propuestas pendientes de revisión',
            'blocked':'bloqueados','done':'marcados como terminados','completed':'marcados como terminados','resolved':'marcados como resueltos','review_required':'requieren revisión','other':'otros estados'}
    rows.extend(labels[key]+': '+str(count)+'.' for key,count in sorted(counts.items()))
    rows+=['','Comprobación del detector: '+stamp(automatic.get('last_poll',0))+'.',
           'Ficha del vigilante: https://roleplus.app/registrados/vigilante/aviso/prueba',
           'Coste adicional: 0 EUR. Informe generado sin llamar a un modelo.',
           'Las alarmas urgentes siguen llegando por R+; este correo no las sustituye.']
    return '\n'.join(rows)+'\n'


def quota_query():
    return quota_reader.host_snapshot()


def transport(request):
    args=['/usr/bin/systemd-run','--quiet','--wait','--pipe','--collect','--unit=vigilante-daily-mail-worker',
          '-p','User=web6','-p','Group=client1','-p','NoNewPrivileges=yes','-p','CapabilityBoundingSet=',
          '-p','ProtectSystem=strict','-p','ProtectHome=yes','-p','PrivateTmp=yes','-p','PrivateDevices=yes',
          '-p','RestrictSUIDSGID=yes','-p','RestrictNamespaces=yes','-p','LockPersonality=yes',
          '-p','RestrictAddressFamilies=AF_UNIX AF_INET AF_INET6','-p','IPAddressDeny=any','-p','IPAddressAllow=localhost',
          '-p','InaccessiblePaths=/etc/vigilante /var/lib/vigilante /var/lib/vigilante-daily /var/lib/vigilante-auth /var/lib/vigilante-wa /srv/vigilante-ai',
          '-p','MemoryMax=128M','-p','CPUQuota=10%','-p','TasksMax=8','-p','RuntimeMaxSec=45s','-p','LimitCORE=0',
          '/usr/bin/php','-d','display_errors=0','-d','log_errors=0','/usr/local/lib/vigilante/daily-mail-worker.php']
    r=subprocess.run(args,input=json.dumps(request),capture_output=True,text=True,timeout=55)
    if r.returncode or len(r.stdout)>4096:raise RuntimeError('Transporte no disponible')
    value=json.loads(r.stdout)
    import pwd
    if value.get('uid')!=pwd.getpwnam('web6').pw_uid or type(value.get('accepted')) is not bool or type(value.get('fresh')) is not bool:
        raise ValueError('Identidad o respuesta no validas')
    if not isinstance(value.get('email'),str) or not re.fullmatch(r'[^\s@<>]+@[^\s@<>]+',value['email']):raise ValueError('Destinatario no valido')
    return value


def resources(sites,now):
    rows=[(site,'sin lectura reciente') for site in sites]
    try:
        panel=read(Path('/run/vigilante-daily/panel.json'),65536)
        when=int(datetime.datetime.fromisoformat(panel['ts'].replace('Z','+00:00')).timestamp())
        if not 0<=now-when<=180:raise ValueError('Panel caducado')
        mapping={row['url'][8:]:row['status'] for row in panel['sites']}
        rows=[(site,{'up':'disponible','down':'no disponible'}.get(mapping.get(site),'sin lectura reciente')) for site in sites]
    except (OSError,ValueError,KeyError,TypeError):pass
    services=[]
    for unit,label in [('apache2.service','Apache'),('php8.4-fpm.service','PHP-FPM'),('mariadb.service','MariaDB'),('webwatchdog.timer','Antipanico')]:
        r=subprocess.run(['/usr/bin/systemctl','is-active',unit],capture_output=True,text=True,timeout=5)
        services.append(label+': '+('activo.' if r.stdout.strip()=='active' else 'no consta activo.'))
    stats=[os.statvfs(p) for p in ('/','/var/www','/var/log','/var/lib/mysql')]
    return {'sites':rows,'services':services,'disk':round(max(100*(1-s.f_bavail/s.f_blocks) for s in stats if s.f_blocks),2),
            'inodes':round(max(100*(1-s.f_favail/s.f_files) for s in stats if s.f_files),2)}


def main(initial=False,preview=False):
    import fcntl
    if os.geteuid()!=0:raise PermissionError('Solo administrador')
    data.trusted(STATE,True)
    fd=os.open('/run/vigilante-daily/report.lock',os.O_RDWR|os.O_CREAT|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'a+') as lock:
        fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
        now=int(time.time());day=datetime.datetime.fromtimestamp(now,MADRID).date().isoformat()
        key=day
        ledgerpath=STATE/'delivery.json'
        ledger=read(ledgerpath) if ledgerpath.exists() else {'version':1,'days':{}}
        if key in ledger['days'] and not preview:
            print(json.dumps({'decision':'already_attempted','date':day}));return
        config=read(Path('/etc/vigilante/roleplus-push.json'),4096)
        recipient=config['recipient']
        if not re.fullmatch('[a-f0-9]{8}',recipient):raise ValueError('Destinatario no valido')
        quota=quota_query()
        now=int(time.time())
        policy=read(detector.POLICY)
        body=compose(now,quota,read(notifications.PATH),read(Path('/var/lib/vigilante/work-queue.json')),
                     read(detector.STATE),resources(policy['sites'],now))
        detector.atomic(STATE/('preview-report.json' if preview else 'latest-report.json'),{'created_at':now,'body':body,'model_called':False})
        if preview:
            print(json.dumps({'decision':'preview','model_called':False,'quota_available':bool(quota),'site_count':len(policy['sites'])}),flush=True)
            return
        cachepath=STATE/'recipient.json';cache=read(cachepath,4096) if cachepath.exists() else {}
        request={'mode':'send','recipient':recipient,'body':body,'subject':'Vigilante VPS - Resumen'+(' inicial' if initial else '')+' '+day,
                 'cached_email':cache.get('email','') if cache.get('recipient')==recipient and 0<=now-cache.get('updated',0)<=604800 else ''}
        # Un cierre entre aceptar el correo y guardar estado no duplica el envio.
        ledger['days'][key]={'attempted_at':now,'accepted':False,'uncertain':True}
        ledger['days']={k:v for k,v in ledger['days'].items() if now-v['attempted_at']<7776000}
        detector.atomic(ledgerpath,ledger)
        result=transport(request)
        if result['fresh']:detector.atomic(cachepath,{'recipient':recipient,'updated':now,'email':result['email']})
        ledger['days'][key].update(accepted=result['accepted'],uncertain=False)
        detector.atomic(ledgerpath,ledger)
        if not result['accepted']:raise RuntimeError('Correo no aceptado')
        print(json.dumps({'decision':'accepted_by_local_mail','date':day,'model_called':False,'quota_available':bool(quota),'delivery_confirmed':False}),flush=True)


if __name__=='__main__':
    try:
        if sys.argv[1:] not in ([],['--initial'],['--preview']):raise ValueError('Operacion no valida')
        main(sys.argv[1:]==['--initial'],sys.argv[1:]==['--preview'])
    except BlockingIOError:print('{"decision":"busy"}')
    except Exception as error:
        print(json.dumps({'decision':'error','error_type':type(error).__name__,'model_called':False}),flush=True)
        raise SystemExit(1)
