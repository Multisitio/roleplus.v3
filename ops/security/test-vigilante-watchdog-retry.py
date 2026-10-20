#!/usr/bin/python3
"""Simula tres ciclos: control ocupado, ejecucion aceptada y no duplicacion."""
from pathlib import Path
import subprocess
import tempfile

with tempfile.TemporaryDirectory(prefix='vigilante-watchdog-fixture-') as tmp:
    root=Path(tmp)
    source=Path(__file__).with_name('webwatchdog.sh').read_text()
    source=source.replace('/usr/local/sbin/vps-service-control',str(root/'control'))
    source=source.replace('/usr/bin/python3 -I -B /usr/local/lib/vigilante/vigilante-scheduled-job.py','/usr/bin/false')
    source=source.replace('/var/www/clients/client1/web17/web/webwatchdog',str(root/'panel'))
    source=source.replace('/var/lib/vigilante/antipanico',str(root/'state'))
    source=source.replace('/run/lock/vigilante-watchdog.lock',str(root/'watchdog.lock'))
    source=source.replace('/run/webwatchdog',str(root/'heartbeat'))
    source=source.replace('/usr/sbin/sendmail','/usr/bin/true')
    source=source.replace('logger -t webwatchdog -- "$*"',':')
    (root/'panel').mkdir();(root/'state').mkdir()
    (root/'watchdog.sh').write_text(source)
    (root/'control').write_text('#!/bin/sh\ncase "$1" in\nlist-sites) echo fixture.invalid;;\nsite-restart-allowed) exit 0;;\nrestart) n=$(cat '+str(root/'attempts')+' 2>/dev/null || echo 0); n=$((n+1)); echo "$n" > '+str(root/'attempts')+'; [ "$n" -gt 2 ];;\nesac\n')
    (root/'control').chmod(0o700)
    (root/'curl').write_text('#!/bin/sh\nprintf 503\n');(root/'curl').chmod(0o700)
    import os
    environment=dict(os.environ,PATH=str(root)+':'+os.environ['PATH'])
    prefix=root/'state/https___fixture.invalid'
    Path(str(prefix)+'.status').write_text('down')
    Path(str(prefix)+'.restart_done').write_text('0')
    for cycle,expected in [(1,'0'),(2,'1'),(3,'1')]:
        subprocess.run(['bash',str(root/'watchdog.sh')],env=environment,check=True,capture_output=True,timeout=15)
        assert Path(str(prefix)+'.restart_done').read_text()==expected,(cycle,expected)
    assert (root/'attempts').read_text().strip()=='4'
    print('Control ocupado: reintento posterior; un episodio aceptado no duplica reinicios. Sin acciones reales.')
