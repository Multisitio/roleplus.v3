#!/usr/bin/env python3
"""Pruebas con unidades sinteticas: nunca invoca systemctl real."""
import importlib.util
import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest
import fcntl

spec = importlib.util.spec_from_file_location('control', Path(__file__).with_name('vps-service-control.py'))
control = importlib.util.module_from_spec(spec)
spec.loader.exec_module(control)


class RestartControlTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name)
        self.state = self.root / 'state'
        self.state.mkdir(mode=0o700)
        self.audit = self.root / 'audit.jsonl'
        self.lock = self.root / 'lock'
        self.policy = {'version': 1, 'ai_actions_enabled': False,
                       'restart_owner': 'webwatchdog', 'restart_units': ['fixture.service'],
                       'restart_cooldown_seconds': 600, 'sites': ['example.invalid']}
        self.calls = []

    def tearDown(self):
        self.temp.cleanup()

    def runner(self, args, **kwargs):
        self.calls.append(args)
        return subprocess.CompletedProcess(args, 0)

    def invoke(self, unit='fixture', origin='webwatchdog', runner=None):
        return control.restart(self.policy, unit, origin, state=self.state, audit=self.audit,
                               lock=self.lock, runner=runner or self.runner,
                               clock=lambda: 1000, uid=os.geteuid())

    def test_duplicate_requests_restart_once(self):
        self.assertEqual(self.invoke()['decision'], 'ejecutado')
        self.assertEqual(self.invoke()['decision'], 'espera')
        self.assertEqual(sum(x[1] == 'restart' for x in self.calls), 1)

    def test_origin_and_unit_fail_closed(self):
        for kwargs in ({'unit': 'other'}, {'origin': 'agent'}, {'unit': 'fixture;evil'}):
            with self.assertRaises(ValueError):
                self.invoke(**kwargs)
        self.assertEqual(self.calls, [])

    def test_existing_lock_prevents_restart(self):
        with self.lock.open('w') as stream:
            fcntl.flock(stream, fcntl.LOCK_EX | fcntl.LOCK_NB)
            self.assertEqual(self.invoke()['decision'], 'ocupado')
        self.assertEqual(self.calls, [])

    def test_world_writable_policy_rejected(self):
        path = self.root / 'policy.json'
        path.write_text(json.dumps(self.policy))
        path.chmod(0o666)
        with self.assertRaises(ValueError):
            control.load_policy(path, uid=os.geteuid())

    def test_audit_symlink_rejected(self):
        self.audit.symlink_to(self.root / 'elsewhere')
        with self.assertRaises(OSError):
            self.invoke()
        self.assertFalse((self.root / 'elsewhere').exists())

    def test_failed_restart_also_respects_wait(self):
        def failure(args, **kwargs):
            self.calls.append(args)
            return subprocess.CompletedProcess(args, 1 if args[1] == 'restart' else 0)
        self.assertEqual(self.invoke(runner=failure)['decision'], 'error')
        self.assertEqual(self.invoke()['decision'], 'espera')

    def test_unknown_exception_is_rejected(self):
        self.policy['restart_excluded_sites'] = ['other.invalid']
        path = self.root / 'policy.json'
        path.write_text(json.dumps(self.policy))
        path.chmod(0o600)
        with self.assertRaises(ValueError):
            control.load_policy(path, uid=os.geteuid())

    def test_enabling_ai_requires_new_implementation(self):
        self.policy['ai_actions_enabled'] = True
        path = self.root / 'policy.json'
        path.write_text(json.dumps(self.policy))
        path.chmod(0o600)
        with self.assertRaises(ValueError):
            control.load_policy(path, uid=os.geteuid())

    def reload(self, site='example.invalid', origin='ispconfig-acme', runner=None):
        self.policy['tls_reload_sites'] = ['example.invalid']
        return control.reload_tls(self.policy, site, origin, audit=self.audit, lock=self.lock,
                                  runner=runner or self.runner, clock=lambda: 1000, uid=os.geteuid())

    def test_tls_reload_uses_configtest_and_never_restart(self):
        self.assertEqual(self.reload()['decision'], 'ejecutado')
        self.assertEqual(self.calls[0], ['/usr/sbin/apache2ctl', 'configtest'])
        self.assertEqual(self.calls[-1], ['/usr/bin/systemctl', 'reload', 'apache2.service'])
        self.assertFalse(any('restart' in args for args in self.calls))

    def test_tls_reload_cannot_select_other_site_or_origin(self):
        for kwargs in ({'site': 'unknown.invalid'}, {'origin': 'agent'}, {'site': 'example.invalid;id'}):
            with self.assertRaises(ValueError):
                self.reload(**kwargs)
        self.assertEqual(self.calls, [])

    def test_bad_apache_configuration_prevents_reload(self):
        def failed(args, **kwargs):
            self.calls.append(args); return subprocess.CompletedProcess(args, 1)
        self.assertEqual(self.reload(runner=failed)['decision'], 'configuracion_invalida')
        self.assertEqual(len(self.calls), 1)

    def test_tls_reload_shares_antipanic_lock(self):
        with self.lock.open('w') as stream:
            fcntl.flock(stream, fcntl.LOCK_EX|fcntl.LOCK_NB)
            self.assertEqual(self.reload()['decision'], 'ocupado')
        self.assertEqual(self.calls, [])

    def test_maintenance_reload_is_separate_and_coordinated(self):
        self.policy['maintenance_reload_sites'] = ['example.invalid']
        kwargs = dict(audit=self.audit, lock=self.lock, runner=self.runner, uid=os.geteuid())
        for origin in ('agent', 'ispconfig-acme'):
            with self.assertRaises(ValueError):
                control.reload_maintenance(self.policy, 'example.invalid', origin, **kwargs)
        with self.lock.open('w') as stream:
            fcntl.flock(stream, fcntl.LOCK_EX | fcntl.LOCK_NB)
            result = control.reload_maintenance(self.policy, 'example.invalid', 'sql-maintenance-install', **kwargs)
            self.assertEqual(result['decision'], 'ocupado')
        self.assertEqual(self.calls, [])
        result = control.reload_maintenance(self.policy, 'example.invalid', 'sql-maintenance-install', **kwargs)
        self.assertEqual(result['decision'], 'ejecutado')
        self.assertEqual(result['action'], 'reload_maintenance')
        self.assertEqual(self.calls[0], ['/usr/sbin/apache2ctl', 'configtest'])
        self.assertEqual(self.calls[-1], ['/usr/bin/systemctl', 'reload', 'apache2.service'])

    def test_inactive_apache_not_started_by_certificate_renewal(self):
        def inactive(args, **kwargs):
            self.calls.append(args); return subprocess.CompletedProcess(args, int('is-active' in args))
        self.assertEqual(self.reload(runner=inactive)['decision'], 'servicio_inactivo')
        self.assertFalse(any('reload' in args for args in self.calls))


if __name__ == '__main__':
    unittest.main()
