import importlib.util
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

spec = importlib.util.spec_from_file_location('guard', Path(__file__).with_name('vigilante-git-guard.py'))
guard = importlib.util.module_from_spec(spec);spec.loader.exec_module(guard)


class SyncTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory();self.addCleanup(self.temp.cleanup)
        base = Path(self.temp.name);self.repo = base / 'repo';self.repo.mkdir();self.remote = base / 'remote.git'
        self.git('init', '-q', '-b', 'main');self.git('config', 'user.name', 'Prueba')
        self.git('config', 'user.email', 'test@example.invalid')
        self.git('config', 'core.autocrlf', 'false')
        subprocess.run(['git', 'init', '--bare', '-q', str(self.remote)], check=True)
        self.git('remote', 'add', 'origin', str(self.remote))
        (self.repo / 'a.py').write_text('print(1)\n')
        self.git('add', 'a.py');self.git('commit', '-qm', 'Inicial');self.git('push', '-q', 'origin', 'main')
        self.head = self.git('rev-parse', 'HEAD')

    def git(self, *args):
        return subprocess.check_output(['git', '-C', str(self.repo), *args], stderr=subprocess.PIPE).decode().strip()

    def test_common_revision(self):
        self.assertTrue(guard.verify(self.repo, self.head)['synchronized'])

    def test_pending_changes_preserved(self):
        (self.repo / 'a.py').write_text('print(2)\n')
        with self.assertRaises(ValueError):guard.verify(self.repo, self.head)
        self.assertEqual((self.repo / 'a.py').read_text(), 'print(2)\n')

    def test_unpublished_commit_rejected(self):
        (self.repo / 'a.py').write_text('print(2)\n');self.git('commit', '-qam', 'Pendiente')
        with self.assertRaises(ValueError):guard.verify(self.repo, self.git('rev-parse', 'HEAD'))

    def test_remote_divergence_rejected(self):
        old = self.head
        (self.repo / 'a.py').write_text('print(2)\n');self.git('commit', '-qam', 'Otro')
        newer = self.git('rev-parse', 'HEAD');self.git('push', '-q', 'origin', 'main')
        self.git('checkout', '-q', old)
        with self.assertRaises(ValueError):guard.verify(self.repo, old)
        self.assertEqual(self.git('rev-parse', 'HEAD'), old)
        self.assertNotEqual(newer, old)

    def test_ancestor_repository_rejected(self):
        (self.repo / 'nested').mkdir()
        with self.assertRaises(ValueError):guard.verify(self.repo / 'nested', self.head)

    def test_release_cli_blocks_dirty_source(self):
        (self.repo / 'ops').mkdir();(self.repo / 'ops/a.py').write_text('print(1)\n')
        self.git('add', 'ops/a.py');self.git('commit', '-qm', 'Fuente');self.git('push', '-q', 'origin', 'main')
        head = self.git('rev-parse', 'HEAD');(self.repo / 'a.py').write_text('pendiente\n')
        output = self.repo.parent / 'package'
        result = subprocess.run([sys.executable, str(Path(__file__).with_name('vigilante-release-plan.py')),
            '--repository', str(self.repo), '--commit', head, '--output', str(output), 'ops/a.py'], capture_output=True)
        self.assertNotEqual(result.returncode, 0)
        self.assertFalse(output.exists())

    def test_release_cli_records_common_revision(self):
        (self.repo / 'ops').mkdir();(self.repo / 'ops/a.py').write_text('print(1)\n')
        self.git('add', 'ops/a.py');self.git('commit', '-qm', 'Fuente');self.git('push', '-q', 'origin', 'main')
        head = self.git('rev-parse', 'HEAD');output = self.repo.parent / 'package'
        result = subprocess.run([sys.executable, str(Path(__file__).with_name('vigilante-release-plan.py')),
            '--repository', str(self.repo), '--commit', head, '--output', str(output), 'ops/a.py'], capture_output=True)
        self.assertEqual(result.returncode, 0, result.stderr)
        import json
        manifest = json.loads((output / 'manifest.json').read_text())
        self.assertEqual(manifest['git_sync']['remote_head'], head)
        self.assertFalse(manifest['deployment_enabled'])


if __name__ == '__main__':unittest.main()
