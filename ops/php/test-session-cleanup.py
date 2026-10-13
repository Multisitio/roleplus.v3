#!/usr/bin/python3
import importlib.util
import os
from pathlib import Path
import stat
import tempfile
import time
import unittest

spec = importlib.util.spec_from_file_location('cleanup', Path(__file__).with_name('clean-roleplus-sessions.py'))
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)

class Rules(unittest.TestCase):
    def test_retention_and_owner(self):
        now = time.time()
        def info(age, uid=42, mode=stat.S_IFREG, ctime_age=None):
            return type('Info', (), dict(st_mode=mode, st_uid=uid,
                        st_mtime=now-age, st_ctime=now-(age if ctime_age is None else ctime_age)))()
        cutoff = now-m.RETENTION
        self.assertFalse(m.eligible(info(30*86400),42,cutoff))
        self.assertTrue(m.eligible(info(32*86400),42,cutoff))
        self.assertFalse(m.eligible(info(32*86400,ctime_age=0),42,cutoff))
        self.assertFalse(m.eligible(info(32*86400,uid=99),42,cutoff))
        self.assertFalse(m.eligible(info(32*86400,mode=stat.S_IFLNK),42,cutoff))

    @unittest.skipUnless(os.name == 'posix', 'Real POSIX locks tested on production fixtures')
    def test_files_and_locks(self):
        import fcntl
        with tempfile.TemporaryDirectory(prefix='roleplus-cleanup-test-') as name:
            p = Path(name)
            for file in ['sess_old','sess_locked','sess_fresh','unrelated']:
                (p/file).write_text('fixture')
            os.symlink(p/'unrelated',p/'sess_link')
            now=time.time()
            # Future reference makes real ctime eligible without changing system time.
            future=now+m.RETENTION+100
            for file in ['sess_old','sess_locked']:
                os.utime(p/file,(now-1000,now-1000))
            original=m.eligible
            def rule(info,uid,cutoff):
                return original(info,uid,cutoff) and info.st_mtime < now-500
            m.eligible=rule
            try:
                with (p/'sess_locked').open('r+') as locked:
                    fcntl.flock(locked,fcntl.LOCK_EX|fcntl.LOCK_NB)
                    dry=m.clean(p,uid=os.getuid(),now=future)
                    self.assertEqual(dry['eligible'],2)
                    self.assertEqual(dry['deleted'],0)
                    result=m.clean(p,True,os.getuid(),future)
                    self.assertEqual(result['deleted'],1)
                    self.assertEqual(result['locked'],1)
                    self.assertTrue((p/'sess_locked').exists())
                    self.assertFalse((p/'sess_old').exists())
                self.assertTrue((p/'sess_link').is_symlink())
                self.assertTrue((p/'unrelated').exists())
                self.assertTrue((p/'sess_fresh').exists())
                m.LIMIT=1
                (p/'sess_second').write_text('fixture')
                os.utime(p/'sess_second',(now-1000,now-1000))
                result=m.clean(p,True,os.getuid(),future)
                self.assertEqual(result['deleted'],1)
                self.assertEqual(result['eligible'],2)
            finally:
                m.eligible=original
                m.LIMIT=500

if __name__=='__main__':
    unittest.main()
