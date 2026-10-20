import importlib.util
import io
from pathlib import Path
import tarfile
import tempfile
import unittest

spec = importlib.util.spec_from_file_location('text_backups', Path(__file__).with_name('vigilante-text-backups.py'))
module = importlib.util.module_from_spec(spec)
try:
    spec.loader.exec_module(module)
except ModuleNotFoundError as error:
    if error.name == 'fcntl':
        raise SystemExit('Ejecutar en Linux: el bloqueo es del sistema operativo')
    raise


class TextBackupTests(unittest.TestCase):
    def test_source_and_legacy_text(self):
        self.assertTrue(module.text_content('código\n'.encode()))
        self.assertTrue(module.text_content('contraseña\r\n'.encode('cp1252')))

    def test_binary_renamed_as_php(self):
        for data in [b'\x89PNG\r\n', b'<?php\0data', b'MZexample', b'\x1f\x8bcompressed', b'PK\x03\x04']:
            self.assertFalse(module.text_content(data))

    def test_control_character_policy(self):
        for number in range(160):
            data = chr(number).encode('utf-8')
            expected = not ((number < 32 and chr(number) not in '\t\n\r\f') or 127 <= number < 160)
            self.assertEqual(module.text_content(data), expected)

    def test_selection(self):
        self.assertTrue(module.candidate('private/config/keys.php'))
        for p in ['web/img/source.svg', 'private/config/remoto.sql', 'web/app.woff', '../file.php', '.git/config']:
            self.assertFalse(module.candidate(p))

    def test_archive_content_and_symlinks(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder) / 'source';root.mkdir()
            (root / 'source.php').write_bytes(b'<?php echo 1;\n')
            (root / 'binary.php').write_bytes(b'\x89PNG\0image')
            (root / 'logo.png').write_bytes(b'\x89PNG\0image')
            (root / 'link.php').symlink_to('/etc/passwd')
            out = Path(folder) / 'archive.tar.gz'
            manifest = module.archive(root, out)
            self.assertEqual([x['path'] for x in manifest['files']], ['source.php'])
            module.verify(out, manifest)
            self.assertEqual(out.stat().st_mode & 0o777, 0o600)

    def test_reject_tampered_archive(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder) / 'source';root.mkdir();(root / 'a.php').write_bytes(b'original')
            out = Path(folder) / 'a.tar.gz';manifest = module.archive(root, out)
            with tarfile.open(out, 'w:gz') as tar:
                entry = tarfile.TarInfo('./a.php');entry.size = 3;tar.addfile(entry, io.BytesIO(b'bad'))
            with self.assertRaises(ValueError):module.verify(out, manifest)

    def test_parent_symlink_not_followed(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder);(root / 'alias').symlink_to('/etc', target_is_directory=True)
            with self.assertRaises(OSError):module.read_regular(root / 'alias' / 'passwd')


if __name__ == '__main__':unittest.main()
