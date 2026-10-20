#!/usr/bin/env python3
"""Coordinador manual de ensayos; no conecta con MariaDB de produccion."""
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import time

STATE = Path('/var/lib/private/vigilante-restore-drill')
DROPIN = Path('/etc/systemd/system/vigilante-restore-drill.service.d/10-input.conf')
UNIT = 'vigilante-restore-drill.service'


def digest(path):
    h = hashlib.sha256()
    with path.open('rb') as stream:
        while chunk := stream.read(1024 * 1024):
            h.update(chunk)
    return h.hexdigest()


def main(plan_path):
    if os.geteuid() != 0:
        raise RuntimeError('Coordinacion manual reservada al administrador')
    stage = plan_path.parent
    if (plan_path.is_symlink() or stage.is_symlink() or stage.resolve() != stage
            or not str(stage).startswith('/root/codex-vigilante/')
            or stage.stat().st_uid != 0 or stage.stat().st_mode & 0o077):
        raise RuntimeError('Plan fuera de evidencias privadas')
    plan = json.loads(plan_path.read_text())
    if not 1 <= len(plan) <= 7 or len({i['database'] for i in plan}) != len(plan):
        raise ValueError('Seleccion no valida')
    for item in plan:
        source = Path(item['source'])
        if (not re.fullmatch(r'c1_[a-z0-9_]+', item['database'])
                or not re.fullmatch(r'/var/backup/web[0-9]+/db_' + item['database']
                                    + r'_[0-9-]+_[0-9-]+\.sql\.gz', str(source))
                or source.is_symlink() or source.resolve() != source
                or digest(source) != item['gzip_sha256']):
            raise ValueError('Origen no contrastado')
    if (stage / 'batch.json').exists() or (STATE / 'work').exists():
        raise RuntimeError('Hay un ensayo anterior que requiere revision')
    if subprocess.run(['systemctl', 'is-active', '--quiet', UNIT]).returncode == 0:
        raise RuntimeError('Ya hay un ensayo activo')
    previous_dropin = DROPIN.read_bytes()
    report = {'completed': False, 'started_at': int(time.time()), 'restores': [],
              'scope': 'selected_historical_archives_not_current_snapshot'}
    try:
        for item in plan:
            if shutil.disk_usage('/var/lib').free < 20 * 1024 ** 3:
                raise RuntimeError('Reserva de disco insuficiente')
            if int(Path('/proc/meminfo').read_text().split('MemAvailable:')[1].split()[0]) < 1536 * 1024:
                raise RuntimeError('Reserva de memoria insuficiente')
            folder = stage / item['database']
            folder.mkdir(mode=0o700)
            for name in ['report.json', 'private-errors.log']:
                old = STATE / name
                if old.exists():
                    raise RuntimeError('Conservar primero el informe anterior')
            archive = folder / 'input.sql.gz'
            shutil.copyfile(item['source'], archive)
            archive.chmod(0o644)  # El padre root/0700 sigue protegiendo el archivo.
            if digest(archive) != item['gzip_sha256']:
                raise RuntimeError('Copia de entrada no identica')
            metadata = folder / 'input.json'
            metadata.write_text(json.dumps(item) + '\n')
            metadata.chmod(0o644)
            DROPIN.write_text('[Service]\nBindReadOnlyPaths=' + str(archive)
                              + ':/run/vigilante-restore-drill/input.sql.gz\n'
                              + 'BindReadOnlyPaths=' + str(metadata)
                              + ':/run/vigilante-restore-drill/input.json\n')
            subprocess.run(['systemctl', 'daemon-reload'], check=True, timeout=30)
            subprocess.run(['systemctl', 'reset-failed', UNIT], check=False, timeout=10)
            result = subprocess.run(['systemctl', 'start', UNIT], timeout=780)
            for name in ['report.json', 'private-errors.log']:
                path = STATE / name
                if path.exists():
                    shutil.move(path, folder / name)
                    (folder / name).chmod(0o600)
            recovered = json.loads((folder / 'report.json').read_text())
            if result.returncode or not recovered['completed'] or (STATE / 'work').exists():
                raise RuntimeError('Restauracion o limpieza incompleta; revisar evidencia privada')
            recovered['source'] = item['source']
            recovered['gzip_sha256'] = item['gzip_sha256']
            recovered['work_removed'] = True
            report['restores'].append(recovered)
            # Solo la duplicacion de entrada propia; el volcado original se conserva.
            if digest(archive) != item['gzip_sha256']:
                raise RuntimeError('Entrada propia alterada')
            archive.unlink()
            (stage / 'progress.json').write_text(json.dumps(report, indent=2) + '\n')
        report['completed'] = True
    finally:
        DROPIN.write_bytes(previous_dropin)
        subprocess.run(['systemctl', 'daemon-reload'], check=True, timeout=30)
        report['finished_at'] = int(time.time())
        (stage / 'batch.json').write_text(json.dumps(report, indent=2) + '\n')
    print(json.dumps({'completed': report['completed'], 'databases': len(report['restores'])}))


if __name__ == '__main__':
    if len(sys.argv) != 2:
        raise SystemExit(2)
    main(Path(sys.argv[1]))
