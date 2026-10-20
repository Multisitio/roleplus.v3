import gzip
import hashlib
import importlib.util
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('drill', Path(__file__).with_name('vigilante-restore-drill.py'))
drill = importlib.util.module_from_spec(spec)
spec.loader.exec_module(drill)


class RestoreTests(unittest.TestCase):
    def test_cleanup_only_recognized_workspace(self):
        with tempfile.TemporaryDirectory() as folder:
            state = Path(folder)
            work = state / 'work'
            work.mkdir()
            (state / 'original.txt').write_text('preserve')
            uid = work.stat().st_uid
            with patch.object(drill, 'STATE', state), patch.object(drill, 'WORK', work), \
                    patch.object(drill.os, 'geteuid', return_value=uid, create=True):
                # La limpieza real exige usuario sin privilegios; simularlo tambien
                # en plataformas que devuelven cero para st_uid.
                if uid == 0:
                    with self.assertRaises(RuntimeError):
                        drill.clean_work()
                    return
                with self.assertRaises(RuntimeError):
                    drill.clean_work()
                (work / '.vigilante-restore-work').write_text('temporary isolated restore\n')
                (work / 'database').mkdir()
                (work / 'database' / 'temporary').write_bytes(b'\0')
                drill.clean_work()
                self.assertFalse(work.exists())
                self.assertEqual((state / 'original.txt').read_text(), 'preserve')

    def test_exact_content(self):
        with tempfile.TemporaryDirectory() as folder:
            src, dst = Path(folder) / 'source.gz', Path(folder) / 'result'
            content = b'CREATE TABLE example(id INT);\n' * 100
            src.write_bytes(gzip.compress(content))
            result = drill.unpack(src, dst)
            self.assertEqual(dst.read_bytes(), content)
            self.assertEqual(result['sql_sha256'], hashlib.sha256(content).hexdigest())

    def test_truncated(self):
        with tempfile.TemporaryDirectory() as folder:
            src, dst = Path(folder) / 'source.gz', Path(folder) / 'result'
            src.write_bytes(gzip.compress(b'example')[:-5])
            with self.assertRaises(EOFError):
                drill.unpack(src, dst)

    def test_expansion_limit(self):
        with tempfile.TemporaryDirectory() as folder:
            src, dst = Path(folder) / 'source.gz', Path(folder) / 'result'
            src.write_bytes(gzip.compress(b'x' * 100))
            with self.assertRaises(ValueError):
                drill.unpack(src, dst, 10)

    def test_existing_target_preserved(self):
        with tempfile.TemporaryDirectory() as folder:
            src, dst = Path(folder) / 'source.gz', Path(folder) / 'result'
            src.write_bytes(gzip.compress(b'new'))
            dst.write_bytes(b'original')
            with self.assertRaises(FileExistsError):
                drill.unpack(src, dst)
            self.assertEqual(dst.read_bytes(), b'original')


if __name__ == '__main__':
    unittest.main()
