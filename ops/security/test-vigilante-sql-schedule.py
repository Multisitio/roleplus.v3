import gzip
import hashlib
import importlib.util
import json
from datetime import datetime
from pathlib import Path
import tempfile
from types import SimpleNamespace
import unittest
from unittest.mock import Mock, patch
from zoneinfo import ZoneInfo

LIB=Path(__file__).resolve().parent
def load(name):
    spec=importlib.util.spec_from_file_location(name.replace('-','_'),LIB/(name+'.py'))
    mod=importlib.util.module_from_spec(spec);spec.loader.exec_module(mod);return mod

m=load('vigilante-sql-schedule')
stream=load('vigilante-sql-stream')

def stamp(value):return int(datetime.fromisoformat(value).replace(tzinfo=ZoneInfo('Europe/Madrid')).timestamp())


class ScheduleTests(unittest.TestCase):
    def setUp(self):
        self.temp=tempfile.TemporaryDirectory();self.addCleanup(self.temp.cleanup)
        self.root=Path(self.temp.name);self.state=self.root/'state';self.state.mkdir(mode=0o700)
        self.dest=self.root/'backups';self.dest.mkdir(mode=0o700)
        self.config=self.root/'config.json';self.config.write_text(json.dumps({'version':1,'first_at':stamp('2026-10-25T04:45:00')}));self.config.chmod(0o600)
        for attr,value in [('STATE',self.state),('BACKUPS',self.dest),('CONFIG',self.config)]:
            p=patch.object(m,attr,value);p.start();self.addCleanup(p.stop)

    def test_night_schedule_dst_and_week_boundary(self):
        self.assertFalse(m.schedule(stamp('2026-10-25T04:44:59'))[0])
        self.assertTrue(m.schedule(stamp('2026-10-25T04:45:00'))[0])
        self.assertFalse(m.schedule(stamp('2026-10-25T05:15:00'))[0])
        self.assertFalse(m.schedule(stamp('2026-10-24T04:50:00'))[0])
        next_at=m.schedule(stamp('2026-10-23T09:00:00'))[2]
        self.assertEqual(next_at,stamp('2026-10-25T04:45:00'))

    def test_daytime_and_duplicate_week_never_dump(self):
        week=m.schedule(stamp('2026-10-25T04:50:00'))[1]
        for now,ledger in [(stamp('2026-10-23T09:00:00'),{'archives':[]}),
                (stamp('2026-10-25T04:50:00'),{'archives':[],'attempted_week':week})]:
            m.save(self.state/'ledger.json',ledger)
            with patch.object(m,'ownership'),patch.object(m,'catalogue'),patch.object(m,'validate_aliases'),\
                    patch.object(m,'module'),patch.object(m.time,'time',return_value=now),patch.object(m,'archive') as write:
                m.main();write.assert_not_called()
            self.assertTrue(json.loads((self.state/'latest.json').read_text())['deferred'])

    def test_interrupted_copy_refuses_another_attempt(self):
        m.save(self.state/'ledger.json',{'archives':[],'pending':{'database':'c1_roleplus'}})
        with patch.object(m,'ownership'),patch.object(m,'catalogue'),patch.object(m,'validate_aliases'),\
                patch.object(m,'module'),patch.object(m,'archive') as write:
            with self.assertRaises(ValueError):m.main()
            write.assert_not_called()

    def test_archive_transforms_controls_then_registers_exact_file(self):
        ledger={'archives':[]}
        def dump(name,raw,hosts,seconds,counts):
            self.assertEqual(hosts,['roleplus.app','*.roleplus.app','.roleplus.app'])
            self.assertEqual(seconds,15)
            counts.update(t=1)
            raw.write_bytes(b"/*!40101 SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\nINSERT INTO `t` VALUES (1,'a\x03b');\n-- Dump completed on fixture\n")
            return .1
        connector=SimpleNamespace(LIMITS={'c1_roleplus':('roleplus.app',15)},dump=dump)
        backup=SimpleNamespace(query=Mock(),MAX_SECONDS=1.5)
        with patch.object(m.shutil,'disk_usage',return_value=SimpleNamespace(free=40*1024**3)):
            entry=m.archive(m.MAPPED[2],ledger,backup,connector,stream)
        data=gzip.decompress(Path(entry['source']).read_bytes())
        self.assertNotIn(b'\x03',data);self.assertIn(b'CHAR(3 USING utf8mb4)',data)
        self.assertEqual(entry['row_counts_under_lock'],{'t':1})
        self.assertIsNone(ledger['pending']);self.assertEqual(len(ledger['archives']),1)
        self.assertIn(entry['filename'],backup.query.call_args.args[0])
        self.assertFalse(entry['restored_in_isolation'])

    def test_dump_failure_publishes_no_valid_archive(self):
        ledger={'archives':[]};backup=SimpleNamespace(query=Mock(),MAX_SECONDS=1.5)
        connector=SimpleNamespace(LIMITS={'c1_roleplus':('roleplus.app',15)},dump=Mock(side_effect=RuntimeError('bounded failure')))
        with patch.object(m.shutil,'disk_usage',return_value=SimpleNamespace(free=40*1024**3)):
            with self.assertRaises(RuntimeError):m.archive(m.MAPPED[2],ledger,backup,connector,stream)
        self.assertEqual(list(self.dest.rglob('*.sql.gz')),[]);backup.query.assert_not_called()
        self.assertEqual(ledger['pending']['database'],'c1_roleplus')

    def test_failure_survives_deferred_checks_and_missing_window_is_reported(self):
        self.assertFalse(m.observation(stamp('2026-10-23T10:00:00'))['failed'])
        self.assertFalse(m.observation(stamp('2026-10-25T05:16:00'))['missed_window'])
        self.assertTrue(m.observation(stamp('2026-10-25T05:26:00'))['missed_window'])
        m.save(self.state/'last-run.json',{'checked_at':stamp('2026-10-25T04:45:00'),'completed':False,'failure_type':'RuntimeError'})
        m.save(self.state/'latest.json',{'deferred':True})
        self.assertTrue(m.observation(stamp('2026-10-26T10:00:00'))['failed'])

    def test_native_owner_drift_refuses_execution(self):
        native=self.root/'cron.php';native.write_text('reviewed');native.chmod(0o644)
        m.save(self.config,{'version':1,'cron_sha256':hashlib.sha256(b'reviewed').hexdigest()})
        with patch.object(m,'NATIVE',native),patch.object(m,'SYSTEMD',self.root/'systemd'):
            m.ownership();native.write_text('changed')
            with self.assertRaises(ValueError):m.ownership()

    def test_migration_rolls_back_hook_files_config_and_timer_when_service_fails(self):
        installer=load('install-vigilante-sql-cron.php')
        case=self.root/'case';case.mkdir(mode=0o700)
        lib=self.root/'lib';lib.mkdir();isp=self.root/'isp';isp.mkdir()
        temp=isp/'temp';temp.mkdir()
        targets={name:lib/name for name in installer.TARGETS}
        source=("from pathlib import Path\nSTATE=Path("+repr(str(self.state))+")\n"
            "def catalogue(backup):pass\ndef module(name):return None\n"
            "def validate_aliases():pass\ndef schedule(now):return False,'week',1792899900\n"
            "def ownership():pass\n").encode()
        before={};hashes={};originals={}
        for name,target in targets.items():
            data=source if name=='vigilante-sql-schedule.py' else b'candidate\n'
            (case/name).write_bytes(data);hashes[name]=hashlib.sha256(data).hexdigest()
            if name.endswith('.service'):before[name]=None
            else:
                original=('previous '+name+'\n').encode();target.write_bytes(original)
                before[name]=hashlib.sha256(original).hexdigest();originals[name]=original
        (case/'deployment.json').write_text(json.dumps({'source_sha256':hashes,'before_sha256':before,'source_commit':'fixture'}))
        config=self.root/'newconfig.json';calls=[]
        def run(args):
            calls.append(args)
            if args[0]=='/usr/bin/php':return SimpleNamespace(stdout=str(temp))
            if 'show' in args:return SimpleNamespace(stdout='inactive\n')
            if 'start' in args:raise RuntimeError('simulated service failure')
            return SimpleNamespace(stdout='')
        with patch.object(installer,'LIB',lib),patch.object(installer,'TARGETS',targets),\
                patch.object(installer,'CONFIG',config),patch.object(installer,'CASE_ROOT',self.root),\
                patch.object(installer,'ISP_ROOT',isp),patch.object(installer,'run',side_effect=run),\
                patch.object(installer.subprocess,'run',return_value=SimpleNamespace(stdout='enabled\n')),\
                patch.object(installer.sys,'argv',['install-vigilante-sql-cron.php.py']):
            with self.assertRaises(RuntimeError):installer.main(case)
        for name,original in originals.items():self.assertEqual(targets[name].read_bytes(),original)
        self.assertFalse(config.exists());self.assertFalse((temp/'.ispconfig_cron_lock').exists())
        self.assertFalse(targets['vigilante-sql-schedule.service'].exists())
        self.assertTrue(any('enable' in args and '--now' in args for args in calls))


if __name__=='__main__':unittest.main()
