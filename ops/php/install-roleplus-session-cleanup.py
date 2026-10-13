#!/usr/bin/python3
"""Install/remove only the RolePlus cleanup timer; never modify PHP pools."""
import os
from pathlib import Path
import shutil
import subprocess
import sys

SOURCE=Path(__file__).resolve().parent
NAMES=('roleplus-session-cleanup.service','roleplus-session-cleanup.timer')
TARGET=Path('/etc/systemd/system')

def run(*args):
    subprocess.run(args,check=True)

def main():
    if os.geteuid()!=0:
        raise SystemExit('Run as root')
    if '--remove' in sys.argv:
        run('systemctl','disable','--now',NAMES[1])
        run('systemctl','stop',NAMES[0])
        for name in NAMES:
            (TARGET/name).unlink(missing_ok=True)
        run('systemctl','daemon-reload')
        return
    if any((TARGET/name).exists() for name in NAMES):
        raise SystemExit('Existing unit; review before updating')
    # Simulation only: no existing session data is removed by the installer.
    run('runuser','-u','web6','--','python3','-B',str(SOURCE/'clean-roleplus-sessions.py'))
    copied=[]
    try:
        for name in NAMES:
            path=TARGET/name
            shutil.copyfile(SOURCE/name,path)
            os.chmod(path,0o644)
            copied.append(path)
        run('systemd-analyze','verify',*(str(TARGET/name) for name in NAMES))
        run('systemctl','daemon-reload')
        run('systemctl','enable','--now',NAMES[1])
    except Exception:
        subprocess.run(['systemctl','disable','--now',NAMES[1]],check=False)
        for path in copied:
            path.unlink(missing_ok=True)
        subprocess.run(['systemctl','daemon-reload'],check=False)
        raise
    print('Timer enabled; PHP-FPM and Apache untouched')

if __name__=='__main__':
    main()
