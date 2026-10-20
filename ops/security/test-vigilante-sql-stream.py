import gzip
import hashlib
import importlib.util
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('sqlstream', Path(__file__).with_name('vigilante-sql-stream.py'))
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


class StreamTests(unittest.TestCase):
    def setUp(self):
        if not hasattr(module.os, 'fchmod'):
            mode = patch.object(module.os, 'fchmod', lambda *args: None, create=True)
            mode.start()
            self.addCleanup(mode.stop)

    def test_text_and_controls_preserved(self):
        data = b"/*!40101 SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n"
        data += b"INSERT INTO `t` VALUES (1,'a\x03b'),(2,'plain');\n-- Dump completed on test\n"
        with tempfile.TemporaryDirectory() as folder:
            src, dst = Path(folder) / 'a.sql', Path(folder) / 'a.gz'
            src.write_bytes(data)
            result = module.compress(src, dst)
            encoded = gzip.decompress(dst.read_bytes())
            self.assertEqual(encoded, module.sqltext.encode_controls(data)[0])
            self.assertEqual(result['sql_bytes'], len(encoded))
            self.assertEqual(result['source_sql_sha256'], hashlib.sha256(data).hexdigest())
            self.assertEqual(result['escaped_control_characters'], 1)

    def test_refuses_binary_and_preserves_existing(self):
        with tempfile.TemporaryDirectory() as folder:
            src, dst = Path(folder) / 'a.sql', Path(folder) / 'a.gz'
            src.write_bytes(b'example\0invalid')
            with self.assertRaises(ValueError):
                module.compress(src, dst)
            previous = dst.read_bytes()
            with self.assertRaises(FileExistsError):
                module.compress(src, dst)
            self.assertEqual(dst.read_bytes(), previous)


if __name__ == '__main__':
    unittest.main()
