import importlib.util
from pathlib import Path
import unittest

spec = importlib.util.spec_from_file_location('sqltext', Path(__file__).with_name('vigilante-sql-text.py'))
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)
HEADER = b"/*!40101 SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n"


class SQLTextTests(unittest.TestCase):
    def test_exact_unchanged(self):
        data = HEADER + b"INSERT INTO `t` VALUES ('ordinary',NULL,3);\n"
        self.assertEqual(module.encode_controls(data), (data, 0))

    def test_repeated_boundary_quotes_and_unicode(self):
        data = HEADER + "INSERT INTO `t` VALUES (1,'\x03á\\\'b''c\x03\x03');\n".encode()
        result, count = module.encode_controls(data)
        self.assertEqual(count, 3)
        self.assertNotIn(b'\x03', result)
        self.assertIn(b"CONCAT('',CHAR(3 USING utf8mb4),", result)
        self.assertIn("á\\\'b''c".encode(), result)
        self.assertEqual(result.count(b'CHAR(3 USING utf8mb4)'), 3)

    def test_refuses_unsupported_contexts(self):
        cases = [b"INSERT INTO `t` VALUES (\x03);", b"-- comment\x03",
                 b"INSERT INTO `t` VALUES (_binary'a\x03b');",
                 b"INSERT INTO `t` VALUES ('a\\\x03b');",
                 b"INSERT INTO `t` VALUES ('a\x03b);", b"CREATE TABLE `t\x03` (a INT);"]
        for data in cases:
            with self.subTest(data=data), self.assertRaises(ValueError):
                module.encode_controls(HEADER + data)

    def test_refuses_unknown_sql_mode(self):
        with self.assertRaises(ValueError):
            module.encode_controls(b"INSERT INTO `t` VALUES ('\x03');")


if __name__ == '__main__':
    unittest.main()
