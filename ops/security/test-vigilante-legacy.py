#!/usr/bin/python3
"""Ensayo de limites y concurrencia con procesos sinteticos, sin tareas web."""
import importlib.util
import json
import os
from pathlib import Path
import select
import subprocess
import sys
import tempfile
import time
import uuid


def main():
    spec=importlib.util.spec_from_file_location('jobs',Path(__file__).with_name('vigilante-scheduled-job.py'))
    jobs=importlib.util.module_from_spec(spec);spec.loader.exec_module(jobs)
    jobs.CONFIG=Path(__file__).with_name('vigilante-scheduled-jobs.json')
    assert len(jobs.load()['jobs'])==9
    try:jobs.run('unregistered')
    except ValueError:pass
    else:raise RuntimeError('Se acepto una tarea desconocida')
    name='fixture-'+uuid.uuid4().hex[:8]
    # El intermediario muere; systemd conserva plazo y grupo de procesos.
    fixture="import subprocess,time,os; p=subprocess.Popen(['/usr/bin/sleep','60']); print(str(os.getpid())+' '+str(p.pid),flush=True); time.sleep(60)"
    process=subprocess.Popen(jobs.command(name,{'seconds':2,'argv':['/usr/bin/python3','-c',fixture]}),stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True)
    try:
        if not select.select([process.stdout],[],[],10)[0]:raise RuntimeError('No arranco el ensayo')
        pids=[int(x) for x in process.stdout.readline().split()]
        duplicate=subprocess.run(jobs.command(name,{'seconds':2,'argv':['/usr/bin/true']}),capture_output=True,timeout=5)
        assert duplicate.returncode!=0,'Se permitio un duplicado'
        process.kill();process.wait(timeout=3)
        end=time.monotonic()+10
        while time.monotonic()<end:
            live=[]
            for pid in pids:
                path=Path('/proc')/str(pid)/'stat'
                if path.exists() and path.read_text().rsplit(')',1)[1].split()[0]!='Z':live.append(pid)
            if not live:break
            time.sleep(0.1)
        assert not live,'El plazo no termino todos los procesos'
        print(json.dumps({'completed':True,'duplicate_rejected':True,
            'controller_killed':True,'whole_process_group_expired':True,'real_web_jobs_executed':False}))
    finally:
        if process.poll() is None:process.kill();process.wait()
        subprocess.run(['systemctl','stop','vigilante-job-'+name],capture_output=True,timeout=10)
        process.stdout.close();process.stderr.close()


if __name__=='__main__':main()
