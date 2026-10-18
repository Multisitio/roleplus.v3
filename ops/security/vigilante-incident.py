#!/usr/bin/env python3
"""Recoge TLS como administrador; publica solo metadatos publicos tipados."""
import hashlib
import ipaddress
import json
import os
from pathlib import Path
import re
import socket
import ssl
import stat
import tempfile
import time
import uuid

SNAPSHOT = Path('/srv/vigilante-ai/input/incidents/current.json')
DNS = re.compile(r'(?:\*\.)?[a-z0-9](?:[a-z0-9.-]{0,250}[a-z0-9])?')


def trusted(path, directory=False):
    info = path.lstat()
    if (not (stat.S_ISDIR if directory else stat.S_ISREG)(info.st_mode)
            or info.st_uid != 0 or info.st_mode & 0o022):
        raise PermissionError('Fuente no confiable')


def fingerprint(data):
    return hashlib.sha256(json.dumps(data, sort_keys=True, separators=(',', ':')).encode()).hexdigest()


def tls(address, site, evidence_id):
    result = {'evidence_id': evidence_id, 'address': address, 'verified': False,
              'verification_code': None, 'failure': None, 'certificate': None}
    def connect(context):
        with socket.create_connection((address, 443), timeout=3) as stream:
            with context.wrap_socket(stream, server_hostname=site) as secure:
                return secure.getpeercert(binary_form=True)
    try:
        der = connect(ssl.create_default_context())
        result['verified'] = True
    except ssl.SSLCertVerificationError as error:
        result['verification_code'] = error.verify_code
        result['failure'] = 'verification'
        try:
            # Solo recuperar el certificado publico; nunca se envia HTTP.
            der = connect(ssl._create_unverified_context())
        except (OSError, ssl.SSLError):
            result['failure'] = 'handshake'; return result
    except (OSError, ssl.SSLError):
        result['failure'] = 'handshake'; return result
    with tempfile.NamedTemporaryFile(mode='w', suffix='.pem', delete=False) as stream:
        stream.write(ssl.DER_cert_to_PEM_cert(der)); name = stream.name
    try:
        decoded = ssl._ssl._test_decode_cert(name)
    finally:
        os.unlink(name)
    names = sorted(set(value.lower() for kind, value in decoded.get('subjectAltName', [])
                       if kind == 'DNS' and DNS.fullmatch(value.lower())))[:32]
    result['certificate'] = {'sha256': hashlib.sha256(der).hexdigest(), 'dns_names': names,
                             'not_before': int(ssl.cert_time_to_seconds(decoded['notBefore'])),
                             'not_after': int(ssl.cert_time_to_seconds(decoded['notAfter']))}
    return result


def apache_mapping(site):
    """No exporta rutas, claves ni lineas de configuracion."""
    found = []
    files = sorted(Path('/etc/apache2/sites-enabled').iterdir())
    if len(files) > 128:
        raise ValueError('Catalogo demasiado grande')
    for file in files:
        target = file.resolve(strict=True)
        if not target.is_relative_to('/etc/apache2'):
            raise PermissionError('Configuracion fuera de Apache')
        trusted(target)
        if target.stat().st_size > 524288:
            raise ValueError('Configuracion excesiva')
        text = target.read_text(errors='replace')
        for binding, body in re.findall(r'<VirtualHost\s+([^>]+)>(.*?)</VirtualHost\s*>', text, re.I|re.S):
            names = []
            for value in re.findall(r'^\s*Server(?:Name|Alias)\s+([^\r\n#]+)', body, re.I|re.M):
                names += [n.lower() for n in value.split() if DNS.fullmatch(n.lower())]
            if site not in names or not any(x.endswith(':443') for x in binding.split()):
                continue
            kinds = set()
            for item in binding.split():
                if not item.endswith(':443'):
                    continue
                address = item[:-4].strip('[]')
                if address in ('*', '_default_'):
                    kinds.add('wildcard')
                else:
                    try:
                        kinds.add('loopback' if ipaddress.ip_address(address).is_loopback else 'specific_nonloopback')
                    except ValueError:
                        kinds.add('named')
            found.append({'binding_kinds': sorted(kinds), 'names': sorted(set(names))[:32],
                          'certificate_configured': bool(re.search(r'^\s*SSLCertificateFile\s+', body, re.I|re.M))})
    return {'evidence_id': 'apache_mapping', 'matching_tls_vhosts': found[:16]}


def validate(data, now=None):
    if not isinstance(data, dict) or set(data) != {'version', 'incident', 'collected_at', 'scope_site_count', 'endpoints', 'apache', 'sha256'}:
        raise ValueError('Estructura no autorizada')
    expected = dict(data); digest = expected.pop('sha256')
    if not isinstance(digest, str) or digest != fingerprint(expected):
        raise ValueError('Huella no valida')
    if data['version'] != 1 or type(data['collected_at']) is not int or not 0 <= (int(time.time()) if now is None else now)-data['collected_at'] <= 600:
        raise ValueError('Instantanea caducada')
    incident = data['incident']
    if set(incident) != {'id', 'site', 'type'} or str(uuid.UUID(incident['id'])) != incident['id'] or not DNS.fullmatch(incident['site']) or incident['type'] != 'tls_hostname_mismatch':
        raise ValueError('Incidencia no valida')
    if type(data['scope_site_count']) is not int or not 1 <= data['scope_site_count'] <= 256:
        raise ValueError('Catalogo no valido')
    endpoints = data['endpoints']
    if not isinstance(endpoints, list) or not 1 <= len(endpoints) <= 4:
        raise ValueError('Destinos no validos')
    for index, endpoint in enumerate(endpoints):
        if set(endpoint) != {'evidence_id', 'address', 'verified', 'verification_code', 'failure', 'certificate'} or endpoint['evidence_id'] != ('local_tls' if index == 0 else 'public_tls_'+str(index)):
            raise ValueError('Evidencia no valida')
        address = ipaddress.ip_address(endpoint['address'])
        if (index == 0 and str(address) != '127.0.0.1') or (index > 0 and not address.is_global):
            raise ValueError('Destino no autorizado')
        if type(endpoint['verified']) is not bool or endpoint['failure'] not in (None, 'verification', 'handshake') or (endpoint['verification_code'] is not None and type(endpoint['verification_code']) is not int):
            raise ValueError('Estado TLS no valido')
        cert = endpoint['certificate']
        if cert is not None:
            if set(cert) != {'sha256', 'dns_names', 'not_before', 'not_after'} or not re.fullmatch('[a-f0-9]{64}', cert['sha256']) or not isinstance(cert['dns_names'], list) or len(cert['dns_names']) > 32 or any(not isinstance(n, str) or not DNS.fullmatch(n) for n in cert['dns_names']) or any(type(cert[n]) is not int for n in ('not_before', 'not_after')):
                raise ValueError('Certificado no valido')
    mapping = data['apache']
    if set(mapping) != {'evidence_id', 'matching_tls_vhosts'} or mapping['evidence_id'] != 'apache_mapping' or len(mapping['matching_tls_vhosts']) > 16:
        raise ValueError('Mapa no valido')
    for entry in mapping['matching_tls_vhosts']:
        if set(entry) != {'binding_kinds', 'names', 'certificate_configured'} or type(entry['certificate_configured']) is not bool or not isinstance(entry['binding_kinds'], list) or len(entry['binding_kinds']) > 4 or any(k not in ('wildcard', 'loopback', 'specific_nonloopback', 'named') for k in entry['binding_kinds']) or not isinstance(entry['names'], list) or len(entry['names']) > 32 or any(not isinstance(n, str) or not DNS.fullmatch(n) for n in entry['names']):
            raise ValueError('Mapa no autorizado')
    return data


def collect():
    if os.geteuid() != 0:
        raise PermissionError('Recogida reservada al administrador')
    policy_path = Path('/etc/vigilante/policy.json'); queue_path = Path('/var/lib/vigilante/work-queue.json')
    for path in (policy_path, queue_path):
        trusted(path.parent, True); trusted(path)
    policy = json.loads(policy_path.read_text()); queue = json.loads(queue_path.read_text())
    if policy.get('ai_actions_enabled') is not False:
        raise PermissionError('Modo no autorizado')
    candidates = [x for x in queue['items'] if x.get('type') == 'tls_hostname_mismatch' and x.get('status') == 'pending_diagnosis' and x.get('site') in policy['sites']]
    if not candidates:
        raise ValueError('No hay incidencia TLS pendiente')
    incident = {k: candidates[0][k] for k in ('id', 'site', 'type')}
    if not DNS.fullmatch(incident['site']) or '*' in incident['site']:
        raise ValueError('Dominio no autorizado')
    addresses = sorted(set(x[4][0] for x in socket.getaddrinfo(incident['site'], 443, type=socket.SOCK_STREAM) if ipaddress.ip_address(x[4][0]).is_global))[:3]
    endpoints = [tls('127.0.0.1', incident['site'], 'local_tls')]
    endpoints += [tls(a, incident['site'], 'public_tls_'+str(i)) for i, a in enumerate(addresses, 1)]
    data = {'version': 1, 'incident': incident, 'collected_at': int(time.time()),
            'scope_site_count': len(policy['sites']), 'endpoints': endpoints, 'apache': apache_mapping(incident['site'])}
    data['sha256'] = fingerprint(data); validate(data)
    trusted(SNAPSHOT.parent.parent, True)
    if not SNAPSHOT.parent.exists():
        SNAPSHOT.parent.mkdir(mode=0o750); os.chown(SNAPSHOT.parent, 0, 999)
    trusted(SNAPSHOT.parent, True)
    fd, name = tempfile.mkstemp(dir=SNAPSHOT.parent)
    try:
        os.fchown(fd, 0, 999); os.fchmod(fd, 0o640)
        with os.fdopen(fd, 'w') as stream:
            json.dump(data, stream); stream.flush(); os.fsync(stream.fileno())
        os.replace(name, SNAPSHOT)
    finally:
        if os.path.exists(name):
            os.unlink(name)
    print(json.dumps(data))


def read():
    if os.geteuid() == 0 or json.loads(__import__('sys').stdin.buffer.readline(257)) != {'action': 'read_incident'}:
        raise PermissionError('Lectura no autorizada')
    path = Path('/input/incidents/current.json')
    trusted(path.parent, True); trusted(path)
    if path.stat().st_size > 32768:
        raise ValueError('Instantanea excesiva')
    report = validate(json.loads(path.read_text()))
    print(json.dumps({'uid': os.geteuid(), 'snapshot': report}), flush=True)


def record():
    """Archiva datos, no ejecuta ni transforma la propuesta en una regla."""
    import fcntl
    import importlib.util
    if os.geteuid() != 0:
        raise PermissionError('Registro reservado al administrador')
    source = Path('/var/lib/vigilante-auth/control/incident-analysis.jsonl')
    trusted(source.parent, True); trusted(source)
    if source.stat().st_size > 131072:
        raise ValueError('Informe excesivo')
    result = json.loads(source.read_text().strip().splitlines()[-1])
    if result.get('completed') is not True or result.get('tool_calls') != 1 or result.get('automatic_repairs_enabled') is not False:
        raise ValueError('Analisis no confirmado')
    snapshot = validate(result['tool']['snapshot'])
    task_path = Path('/usr/local/lib/vigilante/vigilante-incident-task.py'); trusted(task_path)
    spec = importlib.util.spec_from_file_location('task', task_path)
    task = importlib.util.module_from_spec(spec); spec.loader.exec_module(task)
    diagnosis = task.finish(json.dumps(result['diagnosis']), result['tool'])
    state = Path('/var/lib/vigilante'); trusted(state, True)
    queue_path = state/'work-queue.json'; diary = state/'diario.jsonl'
    trusted(queue_path)
    if diary.exists():
        trusted(diary)
    fd = os.open('/run/lock/vigilante-state.lock', os.O_RDWR|os.O_CREAT|os.O_NOFOLLOW, 0o600)
    with os.fdopen(fd, 'a+') as lock:
        fcntl.flock(lock, fcntl.LOCK_EX|fcntl.LOCK_NB)
        queue = json.loads(queue_path.read_text())
        matches = [x for x in queue['items'] if x['id'] == diagnosis['incident_id'] and x['site'] == snapshot['incident']['site']]
        if len(matches) != 1 or matches[0]['status'] != 'pending_diagnosis':
            raise ValueError('Cola modificada')
        directory = state/'diagnosticos'
        if not directory.exists():
            directory.mkdir(mode=0o700)
        trusted(directory, True)
        report_path = directory/(diagnosis['incident_id']+'.json')
        if os.path.lexists(report_path):
            raise ValueError('Conservar informe anterior antes de reemplazar')
        fd = os.open(report_path, os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW, 0o600)
        with os.fdopen(fd, 'w') as stream:
            json.dump(result, stream); stream.flush(); os.fsync(stream.fileno())
        event = {'time': int(time.time()), 'event': 'diagnostico_tls_solo_lectura',
                 'incident_id': diagnosis['incident_id'], 'evidence_sha256': snapshot['sha256'],
                 'report': str(report_path), 'status': 'proposal_pending_review', 'changes_executed': False}
        matches[0]['last_diagnosis'] = event
        fd, temporary = tempfile.mkstemp(dir=state)
        try:
            os.fchmod(fd, 0o600)
            with os.fdopen(fd, 'w') as stream:
                json.dump(queue, stream); stream.flush(); os.fsync(stream.fileno())
            os.replace(temporary, queue_path)
        finally:
            if os.path.exists(temporary):
                os.unlink(temporary)
        fd = os.open(diary, os.O_WRONLY|os.O_APPEND|os.O_CREAT|os.O_NOFOLLOW, 0o600)
        with os.fdopen(fd, 'a') as stream:
            stream.write(json.dumps(event)+'\n'); stream.flush(); os.fsync(stream.fileno())
    print(json.dumps(event))


if __name__ == '__main__':
    import sys
    try:
        if sys.argv[1:] == ['collect']:
            collect()
        elif sys.argv[1:] == ['read']:
            read()
        elif sys.argv[1:] == ['record']:
            record()
        else:
            raise ValueError('Operacion no autorizada')
    except Exception as error:
        print(json.dumps({'completed': False, 'error_type': type(error).__name__}), flush=True)
        raise SystemExit(1)
