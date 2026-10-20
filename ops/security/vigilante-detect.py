#!/usr/bin/env python3
"""Detector incremental y distribuidor cerrado; no repara ni reinicia servicios."""
import collections
import datetime
import importlib.util
import json
import os
from pathlib import Path
import re
import stat
import subprocess
import sys
import tempfile
import time
import uuid

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('event_data',HERE/'vigilante-event-data.py')
data = importlib.util.module_from_spec(spec); spec.loader.exec_module(data)
spec = importlib.util.spec_from_file_location('notifications',HERE/'vigilante-notifications.py')
notifications = importlib.util.module_from_spec(spec); spec.loader.exec_module(notifications)
STATE = Path('/var/lib/vigilante/automatic.json')
POLICY = Path('/etc/vigilante/policy.json')
OUTPUT = Path('/var/lib/vigilante-auth/control/event-analysis.jsonl')
PANEL = Path('/var/www/clients/client1/web17/web/webwatchdog/status.json')
LIMIT = 131072


def atomic(path, value, gid=None):
    data.trusted(path.parent,True)
    if path.exists():
        data.trusted(path)
    fd,name = tempfile.mkstemp(dir=path.parent)
    try:
        os.fchmod(fd,0o640 if gid is not None else 0o600)
        if gid is not None:
            os.fchown(fd,0,gid)
        with os.fdopen(fd,'w') as stream:
            json.dump(value,stream);stream.flush();os.fsync(stream.fileno())
        os.replace(name,path)
        directory = os.open(path.parent,os.O_RDONLY|os.O_DIRECTORY)
        try:os.fsync(directory)
        finally:os.close(directory)
    finally:
        if os.path.exists(name):os.unlink(name)


def event(site, kind, family='ninguna', code=0, count=1, severity='normal'):
    result = {'site':site,'kind':kind,'family':family,'code':code,'count':min(count,100000),'severity':severity}
    result['id'] = data.fingerprint({k:result[k] for k in ('site','kind','family','code')})
    return result


def access(line, now):
    parts = line.split(chr(34))
    if len(parts)<3:
        return None
    request=parts[1].split();status=parts[2].split()
    if len(request)!=3 or not status or not re.fullmatch(r'5\d\d',status[0]):
        return None
    stamp=re.search(r'\[([^\]]+)\]',parts[0])
    try:when=int(datetime.datetime.strptime(stamp[1],'%d/%b/%Y:%H:%M:%S %z').timestamp())
    except (ValueError,TypeError):return None
    if not 0<=now-when<=300:
        return None
    family=request[1].split('?')[0].split('/')
    family=family[1] if len(family)>1 and family[1] in data.ROUTES else 'otras'
    return 'http_5xx',family,int(status[0]),when


def error(line,now):
    stamp=re.match(r'\[([^\]]+)\]',line)
    try:
        when=int(datetime.datetime.strptime(stamp[1],'%a %b %d %H:%M:%S.%f %Y').timestamp())
    except (ValueError,TypeError):return None
    if not 0<=now-when<=300:
        return None
    # Nunca se exportan mensajes, variables, rutas, IP ni parametros.
    for needle,kind in [('Allowed memory size','memory_exhausted'),('PHP Fatal error','php_fatal'),('Permission denied','permission_denied'),
                        ('libs/thumbnail.php','invalid_image'),('Undefined variable','undefined_variable'),
                        ('kumbia_router.php','missing_controller'),('PHP Warning','php_warning')]:
        if needle in line:
            return kind,'ninguna',0,when
    return None


def read_delta(path,cursor,allowed=Path('/var/log/ispconfig/httpd')):
    """Lectura acotada, tolera cambio de inodo, truncado y ultima linea parcial."""
    path=path.resolve(strict=True)
    if not path.is_relative_to(allowed.resolve()):
        raise PermissionError('Registro fuera de alcance')
    fd=os.open(path,os.O_RDONLY|getattr(os,'O_NOFOLLOW',0)|getattr(os,'O_NONBLOCK',0))
    with os.fdopen(fd,'rb') as stream:
        meta=os.fstat(stream.fileno())
        if not stat.S_ISREG(meta.st_mode):raise ValueError('Registro no regular')
        identity=[meta.st_dev,meta.st_ino]
        rotated=bool(cursor.get('identity') and cursor['identity']!=identity)
        start=cursor.get('offset',0) if cursor.get('identity')==identity else 0
        truncated=start>meta.st_size
        if truncated:start=0
        clipped=meta.st_size-start>LIMIT
        if clipped:start=meta.st_size-LIMIT
        stream.seek(start);raw=stream.read(LIMIT)
        if clipped:raw=raw.split(b'\n',1)[-1];start=stream.tell()-len(raw)
        end=raw.rfind(b'\n')+1
        lines=[line.decode('utf-8',errors='replace') for line in raw[:end].splitlines() if len(line)<=8192]
        return lines,{'identity':identity,'offset':start+end,'clipped':clipped,'rotated':rotated,'truncated':truncated}


def log_locations(sites):
    result={}
    for source in sorted(Path('/etc/apache2/sites-enabled').iterdir()):
        path=source.resolve(strict=True)
        if not path.is_relative_to('/etc/apache2'):continue
        data.trusted(path)
        if path.stat().st_size>524288:raise ValueError('Configuracion excesiva')
        text=path.read_text(errors='replace')
        for _,body in re.findall(r'<VirtualHost\s+([^>]+)>(.*?)</VirtualHost\s*>',text,re.I|re.S):
            names=re.findall(r'^\s*ServerName\s+([^\s#]+)',body,re.I|re.M)
            logs=re.findall(r'^\s*ErrorLog\s+([^\s#]+)',body,re.I|re.M)
            for site in sites:
                expected=Path('/var/log/ispconfig/httpd')/site/'error.log'
                if site in names and str(expected) in logs:
                    result[site]={'error':expected,'access':expected.parent/'access.log'}
    return result


def panel_events(sites,now):
    data.trusted(PANEL);data.trusted(PANEL.parent,True)
    if PANEL.stat().st_size>65536:raise ValueError('Panel excesivo')
    value=json.loads(PANEL.read_text())
    stamp=int(datetime.datetime.fromisoformat(value['ts'].replace('Z','+00:00')).timestamp())
    if not 0<=now-stamp<=180:raise ValueError('Panel caducado')
    rows=value['sites']
    if len(rows)!=len(sites) or {r.get('url') for r in rows}!={'https://'+s for s in sites}:
        raise ValueError('Catalogo del panel discordante')
    result=[]
    for row in rows:
        if row.get('status') not in ('up','down'):raise ValueError('Estado no valido')
        if row['status']=='down':
            code=int(row.get('code','0'))
            result.append(event(row['url'][8:],'site_down',code=code if 500<=code<=599 else 0,severity='high'))
    return result


def collect(state,sites,now,global_log=None):
    cursors=state.setdefault('cursors',{});pending=state.setdefault('pending',{})
    mapping=log_locations(sites);coverage={'mapped_sites':len(mapping),'catalog_sites':len(sites),'read_errors':0,'clipped_logs':0,'rotated_logs':0,'truncated_logs':0,
                                         'sites_without_dedicated_log':[s for s in sites if s not in mapping]}
    if global_log:
        path=Path(global_log)
        if not path.resolve().is_relative_to('/var/log/apache2'):
            raise PermissionError('Registro global fuera de alcance')
        mapping['server']={'error':path};coverage['global_error_log']=True
    for site,paths in mapping.items():
        for kind,path in paths.items():
            key=str(path)
            try:lines,cursors[key]=read_delta(path,cursors.get(key,{}),Path('/var/log/apache2') if site=='server' else Path('/var/log/ispconfig/httpd'))
            except (OSError,ValueError):coverage['read_errors']+=1;continue
            coverage['clipped_logs']+=int(cursors[key]['clipped'])
            coverage['rotated_logs']+=int(cursors[key]['rotated']);coverage['truncated_logs']+=int(cursors[key]['truncated'])
            for line in lines:
                parsed=(access if kind=='access' else error)(line,now)
                if not parsed:continue
                category,family,code,when=parsed
                item=event(site,category,family,code,severity='high' if category in ('php_fatal','memory_exhausted') else 'normal')
                saved=pending.setdefault(item['id'],{'event':item,'minutes':{}})
                minute=str(when//60);saved['minutes'][minute]=min(100000,saved['minutes'].get(minute,0)+1)
    events=[]
    for key,saved in list(pending.items()):
        saved['minutes']={m:c for m,c in saved['minutes'].items() if int(m)>=now//60-4}
        if not saved['minutes']:del pending[key];continue
        item=dict(saved['event']);item['count']=min(100000,sum(saved['minutes'].values()))
        threshold=1 if item['severity']=='high' else 5 if item['kind'] in ('http_5xx','invalid_image') else 10
        if item['count']>=threshold:events.append(item)
    try:events+=panel_events(sites,now);coverage['panel_fresh']=True
    except (OSError,ValueError,KeyError,TypeError):coverage['panel_fresh']=False
    for unit,family in [('apache2.service','apache2'),('php8.4-fpm.service','php_fpm'),('mariadb.service','mariadb'),('webwatchdog.service','watchdog')]:
        result=subprocess.run(['/usr/bin/systemctl','is-active',unit],capture_output=True,text=True,timeout=5)
        if result.stdout.strip()=='failed' or (unit!='webwatchdog.service' and result.stdout.strip()=='inactive'):
            events.append(event('server','service_down',family,severity='high'))
    disks=[];inodes=[]
    for path in ('/','/var/www','/var/log','/var/lib/mysql'):
        fs=os.statvfs(path)
        if fs.f_blocks:disks.append(round(100*(1-fs.f_bavail/fs.f_blocks)))
        if fs.f_files:inodes.append(round(100*(1-fs.f_favail/fs.f_files)))
    for kind,values in [('disk_space',disks),('inodes',inodes)]:
        if values and max(values)>=90:events.append(event('server',kind,count=max(values),severity='high' if max(values)>=95 else 'normal'))
    unique={item['id']:item for item in events}
    state['coverage']=coverage;state['last_poll']=now
    return sorted(unique.values(),key=lambda e:(e['severity']!='high',e['id']))


def eligible(state,events,now):
    if any(t>now for t in state.get('attempts',[])):return []
    attempts=[t for t in state.get('attempts',[]) if 0<=now-t<86400]
    if len(attempts)>=2 or now<state.get('blocked_until',0) or (attempts and now-max(attempts)<3600):return []
    informed=state.get('informed',{})
    return [e for e in events if e['id'] not in informed or
            (e['severity']=='high' and informed[e['id']]['severity']!='high' and now-informed[e['id']]['time']>=86400)][:16]


def dispatch(state,events,sites,now,origin='automatic'):
    snapshot={'version':1,'id':str(uuid.uuid4()),'collected_at':now,'scope_site_count':len(sites),'events':events}
    snapshot['sha256']=data.fingerprint(snapshot);data.validate(snapshot)
    atomic(data.SNAPSHOT,snapshot,999)
    data.trusted(OUTPUT)
    if OUTPUT.stat().st_size>1048576:raise ValueError('Salida previa excesiva')
    # Reservar antes de llamar: una caida no provoca llamadas ilimitadas.
    state['attempts']=[t for t in state.get('attempts',[]) if now-t<86400]+[now]
    state['last_snapshot_id']=snapshot['id'];atomic(STATE,state)
    with OUTPUT.open('w'):pass
    result=subprocess.run(['/usr/bin/systemctl','start','vigilante-event-analyze.service'],capture_output=True,timeout=120)
    if OUTPUT.stat().st_size>131072:raise ValueError('Resultado excesivo')
    rows=OUTPUT.read_text().strip().splitlines()
    reply=json.loads(rows[-1]) if rows else {}
    report_path=STATE.parent/'diagnosticos'/('auto-'+snapshot['id']+'.json')
    if reply.get('completed') is True:
        spec=importlib.util.spec_from_file_location('task',HERE/'vigilante-event-task.py')
        task=importlib.util.module_from_spec(spec);spec.loader.exec_module(task)
        if reply.get('tool_calls')!=1 or reply.get('tool',{}).get('snapshot')!=snapshot or reply.get('automatic_repairs_enabled') is not False:
            raise ValueError('Respuesta ajena a las observaciones')
        task.finish(json.dumps(reply['diagnosis']),reply['tool'])
        state.setdefault('informed',{}).update({e['id']:{'time':now,'severity':e['severity']} for e in events})
        decision='diagnosed_proposal_pending'
    else:
        gate=reply.get('admission',{})
        reset=gate.get('next_reset_check_at')
        state['blocked_until']=max(now+3600,reset if type(reset) is int else 0)
        reasons={'reserva_del_usuario','agotada','cuota_desconocida','datos_caducados','requiere_nueva_consulta'}
        state['quota_block_reason']=gate.get('state') if reply.get('model_called') is False and gate.get('routine_allowed') is False and gate.get('state') in reasons else None
        if state['quota_block_reason']:
            state.update(quota_blocked_at=now,quota_retry_at=reset if type(reset) is int else now+3600)
        decision='pending_analysis'
    atomic(report_path,reply)
    queue_path=STATE.parent/'work-queue.json';data.trusted(queue_path)
    queue=json.loads(queue_path.read_text())
    queue['items'].append({'id':snapshot['id'],'site':'server','type':'automatic_observations','status':decision,'origin':origin,
                          'report':str(report_path),'event_ids':[e['id'] for e in events]})
    atomic(queue_path,queue)
    fd=os.open(STATE.parent/'diario.jsonl',os.O_WRONLY|os.O_APPEND|os.O_CREAT|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'a') as stream:
        stream.write(json.dumps({'time':now,'event':'analisis_automatico_solo_lectura','id':snapshot['id'],
                                'decision':decision,'origin':origin,'report':str(report_path),'changes_executed':False})+'\n');stream.flush();os.fsync(stream.fileno())
    state['last_decision']=decision;atomic(STATE,state)
    return {'events':len(events),'decision':decision,'model_called':reply.get('model_called',False),'unit_exit':result.returncode}


def main(validate_current=False):
    import fcntl
    if os.geteuid()!=0:raise PermissionError('Detector reservado al administrador')
    data.trusted(POLICY.parent,True);data.trusted(POLICY);data.trusted(STATE.parent,True)
    policy=json.loads(POLICY.read_text())
    if policy.get('ai_actions_enabled') is not False:raise PermissionError('Autonomia de escritura no autorizada')
    sites=policy['sites']
    if not isinstance(sites,list) or not sites or len(sites)>256 or any(not re.fullmatch(r'[a-z0-9]+(?:[.-][a-z0-9]+)*',s) for s in sites):raise ValueError('Catalogo no valido')
    fd=os.open('/run/lock/vigilante-state.lock',os.O_RDWR|os.O_CREAT|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'a+') as lock:
        fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
        if STATE.exists():data.trusted(STATE)
        state=json.loads(STATE.read_text()) if STATE.exists() else {'version':1}
        if state.get('version')!=1:raise ValueError('Estado no valido')
        now=int(time.time())
        if now<state.get('last_poll',0):raise ValueError('Reloj anterior al estado persistido')
        events=collect(state,sites,now,policy.get('global_error_log'))
        # Los avisos no esperan al modelo ni dependen de su cuota.
        try:
            notifications.record(events,sites,now,atomic)
            if Path('/etc/systemd/system/vigilante-notify.service').is_file():
                subprocess.run(['/usr/bin/systemctl','--no-block','start','vigilante-notify.service'],
                               capture_output=True,timeout=5)
        except Exception as error:
            print(json.dumps({'notification_queue':'error','error_type':type(error).__name__}),flush=True)
        if policy.get('automatic_quota_refresh_enabled') is True:
            try:
                spec=importlib.util.spec_from_file_location('quota_refresh',HERE/'vigilante-quota-refresh.py')
                refresh=importlib.util.module_from_spec(spec);spec.loader.exec_module(refresh)
                if refresh.request_if_due(state,now):
                    print(json.dumps({'quota_wait_released':True,'model_called':False}),flush=True)
            except Exception as error:
                print(json.dumps({'quota_refresh':'error','error_type':type(error).__name__}),flush=True)
        selected=eligible(state,events,now)
        if validate_current:
            # Ensayo administrativo con observaciones reales, sin inflar recuentos.
            observed=[dict(saved['event'],count=min(100000,sum(saved['minutes'].values())))
                      for saved in state.get('pending',{}).values() if saved['minutes']]
            selected=eligible(state,observed,now)
        atomic(STATE,state)
        if selected and (policy.get('automatic_analysis_enabled') is True or validate_current):
            print(json.dumps(dispatch(state,selected,sites,now,origin='validation' if validate_current else 'automatic')),flush=True)
        else:
            print(json.dumps({'events':len(events),'model_called':False,'analysis_enabled':policy.get('automatic_analysis_enabled') is True,'coverage':state['coverage']}),flush=True)


if __name__=='__main__':
    try:
        if sys.argv[1:] not in ([],['--validate-current']):raise ValueError('Operacion no autorizada')
        main(sys.argv[1:]==['--validate-current'])
    except BlockingIOError:print(json.dumps({'decision':'ocupado','model_called':False}),flush=True)
    except Exception as error:
        print(json.dumps({'decision':'error','error_type':type(error).__name__,'automatic_repairs_enabled':False}),flush=True)
        raise SystemExit(1)
