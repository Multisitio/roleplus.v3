import gzip
import hashlib
import importlib.util
from pathlib import Path
import tempfile
import unittest

spec = importlib.util.spec_from_file_location('drill', Path(__file__).with_name('vigilante-restore-drill.py'))
drill = importlib.util.module_from_spec(spec)
spec.loader.exec_module(drill)


class RestoreTests(unittest.TestCase):
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
