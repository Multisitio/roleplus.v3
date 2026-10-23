import hashlib
import importlib.util
import json
import os
from pathlib import Path
import tempfile
import unittest

spec=importlib.util.spec_from_file_location('export',Path(__file__).with_name('vigilante-replica-export.py'))
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)


class ExportTests(unittest.TestCase):
    def setUp(self):
        self.temp=tempfile.TemporaryDirectory();self.addCleanup(self.temp.cleanup)
        self.root=Path(self.temp.name);self.root.chmod(0o700)
        m.STATE=self.root
        self.path=self.root/'fixture.gz';self.path.write_bytes(b'fixture data')
        self.ident=hashlib.sha256(self.path.read_bytes()).hexdigest()
        self.entry={'id':self.ident,'path':str(self.path),'bytes':self.path.stat().st_size,
                    'fingerprint':m.fingerprint(self.path.stat())}
        (self.root/'catalog.json').write_text(json.dumps({'entries':[self.entry]}))

    def test_only_catalogued_id(self):
        self.assertEqual(m.get(self.ident,2,4),b'xtur')
        for ident in ['0'*64,'../../etc/passwd',';id',self.ident+' ']:
            with self.assertRaises(ValueError):m.get(ident,0,1)

    def test_range_is_bounded(self):
        for offset,size in [(-1,1),(0,0),(0,m.MAX_RANGE+1),(9,8)]:
            with self.assertRaises(ValueError):m.get(self.ident,offset,size)

    def test_changed_file_is_rejected(self):
        self.path.write_bytes(b'changed data')
        with self.assertRaises(ValueError):m.get(self.ident,0,1)

    def test_symlink_is_not_followed(self):
        other=self.root/'other.gz';other.write_bytes(b'sensitive')
        self.path.unlink();self.path.symlink_to(other)
        with self.assertRaises(OSError):m.get(self.ident,0,1)

    def test_shell_commands_are_not_accepted(self):
        for command in ['id','manifest; id','get /etc/passwd 0 10','get '+self.ident+' -1 1']:
            previous=os.environ.get('SSH_ORIGINAL_COMMAND');os.environ['SSH_ORIGINAL_COMMAND']=command
            try:
                with self.assertRaises(ValueError):m.main()
            finally:
                if previous is None:os.environ.pop('SSH_ORIGINAL_COMMAND',None)
                else:os.environ['SSH_ORIGINAL_COMMAND']=previous


if __name__=='__main__':unittest.main()
