#!/usr/bin/python3
"""Limita el generador externo de ISPConfig sin terminar el cron del panel."""
import importlib.util
import json
import os
from pathlib import Path
import subprocess
import sys
import time


def main():
    if os.geteuid()!=0:raise PermissionError('Solo el ejecutor de ISPConfig')
    path=Path('/usr/local/lib/vigilante/vigilante-scheduled-job.py')
    spec=importlib.util.spec_from_file_location('jobs',path)
    jobs=importlib.util.module_from_spec(spec);spec.loader.exec_module(jobs)
    plan=jobs.load();args={}
    for arg in sys.argv[1:]:
        key,sep,value=arg.partition('=')
        if key in args:raise ValueError('Argumento repetido')
        args[key]=value if sep else None
    if set(args)!={'-month','-year','-update','-config','-dir','-awstatsprog'}:
        raise ValueError('Argumentos no permitidos')
    site=args['-config']
    if site not in plan['statistics'] or args['-update'] is not None:
        raise ValueError('Sitio no registrado')
    if not 1<=int(args['-month'])<=12 or not 2000<=int(args['-year'])<=2100:
        raise ValueError('Periodo no valido')
    if (Path(args['-dir']).resolve()!=Path(plan['statistics'][site]).resolve()
            or args['-awstatsprog']!='/usr/lib/cgi-bin/awstats.pl'):
        raise ValueError('Destino no registrado')
    executable=Path('/usr/share/awstats/tools/awstats_buildstaticpages.pl')
    jobs.trusted(executable)
    name='awstats-'+site.replace('.','-')
    item={'seconds':180,'argv':[str(executable)]+sys.argv[1:],
          'working_directory':'/usr/local/ispconfig/server'}
    status={'job':name,'started_at':int(time.time()),'seconds_limit':180,'completed':False,
            'owner':'ispconfig'}
    target=jobs.STATE/(name+'.json');jobs.save(target,status)
    result=subprocess.run(jobs.command(name,item),timeout=210)
    status.update(finished_at=int(time.time()),completed=result.returncode==0,exit_code=result.returncode)
    jobs.save(target,status)
    return result.returncode


if __name__=='__main__':raise SystemExit(main())
