"""Pruebas aisladas: nunca usan repositorios, claves ni webs reales."""
import importlib.util
import json
from pathlib import Path
import subprocess
import tempfile
import unittest

spec = importlib.util.spec_from_file_location('release', Path(__file__).with_name('vigilante-release-plan.py'))
release = importlib.util.module_from_spec(spec)
spec.loader.exec_module(release)


class ReleaseTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name) / 'repository'
        self.root.mkdir()
        self.run_git('init', '-q')
        self.run_git('config', 'core.autocrlf', 'false')
        self.run_git('config', 'user.name', 'Prueba local')
        self.run_git('config', 'user.email', 'test@example.invalid')
        (self.root / 'ops').mkdir()
        (self.root / 'ops/example.py').write_bytes(b'print(1)\n')
        self.run_git('add', 'ops/example.py')
        self.run_git('commit', '-qm', 'Inicial')
        self.commit = self.run_git('rev-parse', 'HEAD').strip()
        self.output = Path(self.temp.name) / 'package'

    def run_git(self, *args):
        return subprocess.check_output(['git', '-C', str(self.root), *args]).decode()

    def test_dirty_local_uses_committed_bytes_and_reports_difference(self):
        (self.root / 'ops/example.py').write_bytes(b'print(2)\n')
        result = release.prepare(self.root, self.commit, ['ops/example.py'], self.output)
        self.assertFalse(result['files'][0]['local_matches_commit'])
        self.assertEqual((self.output / 'files/ops/example.py').read_bytes(), b'print(1)\n')
        self.assertEqual((self.root / 'ops/example.py').read_bytes(), b'print(2)\n')
        self.assertFalse(json.loads((self.output / 'manifest.json').read_text())['deployment_enabled'])

    def test_protected_paths_and_traversal(self):
        for name in ['../secret', '/etc/passwd', 'ops/../config/a.php', 'ops//a.py',
                     'private/config/keys.php', 'config/db.sql', 'web/.htaccess',
                     'web/index.php', 'web/img/upload/a.png', 'web/css/plantillas/a.css',
                     'ops/a.key', 'ops\\a.py', 'ops/.env.production']:
            with self.subTest(name=name), self.assertRaises(ValueError):
                release.source_path(name)

    def test_no_ancestor_repository(self):
        with self.assertRaises(ValueError):
            release.prepare(self.root / 'ops', self.commit, ['ops/example.py'], self.output)

    def test_no_branches_or_short_ids(self):
        for ref in ['HEAD', self.commit[:8]]:
            with self.assertRaises(ValueError):
                release.prepare(self.root, ref, ['ops/example.py'], self.output)

    def test_no_overwrite(self):
        self.output.mkdir()
        marker = self.output / 'keep'
        marker.write_bytes(b'original')
        with self.assertRaises(ValueError):
            release.prepare(self.root, self.commit, ['ops/example.py'], self.output)
        self.assertEqual(marker.read_bytes(), b'original')

    def test_no_directories_duplicates_or_missing_files(self):
        for names in [['ops'], ['ops/missing.py'], ['ops/example.py'] * 2]:
            with self.assertRaises(ValueError):
                release.prepare(self.root, self.commit, names, self.output)
        self.assertFalse(self.output.exists())

    def test_no_git_symlinks(self):
        blob = self.run_git('hash-object', 'ops/example.py').strip()
        self.run_git('update-index', '--add', '--cacheinfo', '120000,' + blob + ',ops/link')
        self.run_git('commit', '-qm', 'Enlace de prueba')
        commit = self.run_git('rev-parse', 'HEAD').strip()
        with self.assertRaises(ValueError):
            release.prepare(self.root, commit, ['ops/link'], self.output)
        self.assertFalse(self.output.exists())


if __name__ == '__main__':
    unittest.main()
