"""Ensayos Linux aislados: nunca llaman a servicios ni escriben en webs."""
import fcntl
import importlib.util
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch


def load(name, filename):
    spec = importlib.util.spec_from_file_location(name, Path(__file__).with_name(filename))
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


engine = load('engine', 'vigilante-release-transaction.py')
control = load('control', 'vps-service-control.py')


class TransactionTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.base = self.root / 'site'
        self.package = self.root / 'package'
        self.transactions = self.root / 'transactions'
        for path in (self.base, self.package, self.transactions):
            path.mkdir(mode=0o700)
        self.lock = self.root / 'lock'
        self.approval = self.root / 'approval.json'
        self.transaction = self.transactions / 'attempt'
        self.names = ['ops/first.py', 'ops/second.py']
        self.before = {}
        entries = []
        for index, name in enumerate(self.names):
            target = self.base / name
            target.parent.mkdir(mode=0o700, exist_ok=True)
            target.write_bytes(('value = %s\n' % index).encode())
            target.chmod(0o640)
            data = ('value = %s\n' % (index + 10)).encode()
            packed = self.package / 'files' / name
            packed.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
            packed.write_bytes(data)
            packed.chmod(0o600)
            self.before[name] = engine.read_file(target)[1]
            entries.append({'path': name, 'sha256': engine.plan.digest(data),
                            'bytes': len(data), 'local_matches_commit': True})
        self.manifest = {'version': 1, 'commit': 'a' * 40, 'scope': 'selected-files-only',
                         'deployment_enabled': False, 'files': entries}
        self.write_review()

    def write_review(self):
        engine.save(self.package / 'manifest.json', self.manifest)
        raw = (self.package / 'manifest.json').read_bytes()
        engine.save(self.approval, {'version': 1, 'reviewed': True,
                    'manifest_sha256': engine.plan.digest(raw), 'base': str(self.base),
                    'before': self.before})

    def execute(self, health=lambda: True):
        return engine.execute(self.base, self.package, self.approval, self.transaction,
                              self.lock, engine.syntax, health)

    def recover(self):
        with engine.action_lock(self.lock):
            return engine.rollback(self.base, self.transaction)

    def assert_original(self):
        for name in self.names:
            self.assertEqual(engine.read_file(self.base / name)[1], self.before[name])

    def test_apply_preserves_metadata_and_explicit_rollback(self):
        self.assertEqual(self.execute()['status'], 'aplicado')
        for name in self.names:
            actual = engine.read_file(self.base / name)[1]
            self.assertEqual(actual['mode'], 0o640)
            self.assertEqual(actual['uid'], self.before[name]['uid'])
            self.assertEqual(actual['gid'], self.before[name]['gid'])
        self.assertEqual(self.recover()['status'], 'revertido')
        self.assert_original()

    def test_health_failure_rolls_everything_back(self):
        self.assertEqual(self.execute(lambda: False)['status'], 'revertido')
        self.assert_original()

    def test_stale_review_writes_nothing(self):
        (self.base / self.names[1]).write_bytes(b'value = 99\n')
        with self.assertRaises(ValueError):
            self.execute()
        self.assertFalse(self.transaction.exists())
        self.assertEqual(engine.read_file(self.base / self.names[0])[1], self.before[self.names[0]])

    def test_new_change_after_deployment_is_preserved_on_rollback(self):
        def health():
            (self.base / self.names[0]).write_bytes(b'value = 99\n')
            return False
        result = self.execute(health)
        self.assertEqual(result['status'], 'requiere_intervencion')
        self.assertEqual(result['conflicts'], [self.names[0]])
        self.assertEqual((self.base / self.names[0]).read_bytes(), b'value = 99\n')
        self.assertEqual(engine.read_file(self.base / self.names[1])[1], self.before[self.names[1]])

    def test_no_simultaneous_restart_under_same_lock(self):
        calls = []
        def health():
            result = control.restart({'restart_owner': 'webwatchdog',
                       'restart_units': ['fixture.service']}, 'fixture', 'webwatchdog',
                       state=self.transactions, audit=self.root / 'audit.jsonl',
                       lock=self.lock, runner=lambda *a, **kw: calls.append(a), uid=os.geteuid())
            self.assertEqual(result['decision'], 'ocupado')
            return True
        self.assertEqual(self.execute(health)['status'], 'aplicado')
        self.assertEqual(calls, [])

    def test_deployment_rejected_while_other_action_holds_lock(self):
        with engine.action_lock(self.lock), self.assertRaises(BlockingIOError):
            self.execute()
        self.assertFalse(self.transaction.exists())

    def test_symlink_in_destination_parent_and_package_rejected(self):
        target = self.base / 'ops'
        target.rename(self.base / 'original')
        target.symlink_to(self.base / 'original', target_is_directory=True)
        with self.assertRaises(OSError):
            self.execute()
        self.assertFalse(self.transaction.exists())

    def test_symlink_in_package_rejected(self):
        path = self.package / 'files' / self.names[0]
        path.rename(path.with_suffix('.original'))
        path.symlink_to(path.with_suffix('.original'))
        with self.assertRaises(OSError):
            self.execute()
        self.assertFalse(self.transaction.exists())

    def test_failure_during_second_write_reverts_first(self):
        original_atomic = engine.atomic
        def fail_second(path, data, metadata=None, expected=None):
            if path == self.base / self.names[1] and data == b'value = 11\n':
                raise OSError('Fallo de escritura simulado')
            return original_atomic(path, data, metadata, expected)
        with patch.object(engine, 'atomic', fail_second):
            self.assertEqual(self.execute()['status'], 'revertido')
        self.assert_original()

    def test_tampered_package_or_review_rejected(self):
        (self.package / 'files' / self.names[0]).write_bytes(b'value = 999\n')
        with self.assertRaises(ValueError):
            self.execute()
        self.assertFalse(self.transaction.exists())

    def test_invalid_syntax_before_first_write(self):
        data = b'def broken(\n'
        (self.package / 'files' / self.names[1]).write_bytes(data)
        self.manifest['files'][1].update(sha256=engine.plan.digest(data), bytes=len(data))
        self.write_review()
        with self.assertRaises(SyntaxError):
            self.execute()
        self.assertFalse(self.transaction.exists())
        self.assert_original()

    def test_recovery_after_real_process_exit(self):
        # Salida abrupta: no se ejecuta el except ni se hace rollback automatico.
        code = '''import importlib.util,sys,os
from pathlib import Path
s=importlib.util.spec_from_file_location('e',sys.argv[1]); e=importlib.util.module_from_spec(s); s.loader.exec_module(e)
r=Path(sys.argv[2]); original=e.atomic
def interrupted(path,data,metadata=None,expected=None):
 original(path,data,metadata,expected)
 if path==r/'site/ops/first.py': os._exit(73)
e.atomic=interrupted
e.execute(r/'site',r/'package',r/'approval.json',r/'transactions/attempt',r/'lock',e.syntax,lambda:True)
'''
        result = subprocess.run([sys.executable, '-c', code, str(Path(engine.__file__)), str(self.root)])
        self.assertEqual(result.returncode, 73)
        self.assertEqual(json.loads((self.transaction / 'journal.json').read_text())['status'], 'preparado')
        self.assertEqual(self.recover()['status'], 'revertido')
        self.assert_original()

    def test_rollback_rejects_corrupt_backup(self):
        self.execute()
        (self.transaction / '0.before').write_bytes(b'value = 999\n')
        result = self.recover()
        self.assertEqual(result['status'], 'requiere_intervencion')
        self.assertIn(self.names[0], result['conflicts'])

    def test_no_hard_links(self):
        os.link(self.base / self.names[0], self.root / 'alias')
        with self.assertRaises(ValueError):
            self.execute()
        self.assertFalse(self.transaction.exists())


if __name__ == '__main__':
    unittest.main()
