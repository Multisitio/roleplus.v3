#!/usr/bin/python3
"""Comprueba activaciones con ejecutor simulado; no cambia servicios reales."""
import importlib.util
import os
from pathlib import Path
import subprocess
import tempfile
from unittest.mock import patch

spec=importlib.util.spec_from_file_location('control',Path(__file__).with_name('vps-service-control.py'))
control=importlib.util.module_from_spec(spec);spec.loader.exec_module(control)
with tempfile.TemporaryDirectory() as raw:
    root=Path(raw);root.chmod(0o700);audit=root/'audit';lock=root/'lock';calls=[]
    policy={'php_security_activation_units':['php8.4-fpm.service','adapterman-ws.service','apache2.service']}
    def runner(args,**kw):calls.append(args);return subprocess.CompletedProcess(args,0)
    with patch.object(control,'maintenance_active',return_value=False):
        result=control.activate_php_security(policy,'php8.4-fpm.service','security-php-update',audit,lock,runner,uid=os.getuid())
        assert result['decision']=='ejecutado' and calls[-1]==['/usr/bin/systemctl','reload','php8.4-fpm.service']
        assert calls[0]==['/usr/sbin/php-fpm8.4','--test']
        for unit,origin in [('mariadb.service','security-php-update'),('php8.4-fpm.service','ai')]:
            before=len(calls)
            try:control.activate_php_security(policy,unit,origin,audit,lock,runner,uid=os.getuid())
            except ValueError:pass
            else:raise RuntimeError('Se acepto una activacion no autorizada')
            assert len(calls)==before
    with patch.object(control,'maintenance_active',return_value=True):
        before=len(calls)
        result=control.activate_php_security(policy,'apache2.service','security-php-update',audit,lock,runner,uid=os.getuid())
        assert result['decision']=='mantenimiento' and len(calls)==before
    def invalid(args,**kw):calls.append(args);return subprocess.CompletedProcess(args,1)
    with patch.object(control,'maintenance_active',return_value=False):
        before=len(calls)
        result=control.activate_php_security(policy,'php8.4-fpm.service','security-php-update',audit,lock,invalid,uid=os.getuid())
        assert result['decision']=='configuracion_invalida' and len(calls)==before+1
print('Recarga tras validar configuracion; rechazos de unidad, origen, mantenimiento y configuracion invalida comprobados.')

with tempfile.TemporaryDirectory() as raw:
    root=Path(raw);root.chmod(0o700);calls=[]
    policy={'php_security_retirement_units':['php7.4-fpm.service','php8.4-fpm.service']}
    def runner(args,**kw):calls.append(args);return subprocess.CompletedProcess(args,0)
    with patch.object(control,'maintenance_active',return_value=False):
        result=control.activate_php_security(policy,'php7.4-fpm.service','security-php-update',root/'audit',root/'lock',runner,uid=os.getuid(),retirement=True)
        assert result['decision']=='ejecutado' and calls[-1]==['/usr/bin/systemctl','stop','php7.4-fpm.service']
        try:control.activate_php_security(policy,'php8.4-fpm.service','security-php-update',root/'audit',root/'lock',runner,uid=os.getuid(),retirement=True)
        except ValueError:pass
        else:raise RuntimeError('Se retiro una rama utilizada')
print('Retirada acotada sin afectar a las ramas utilizadas comprobada.')
