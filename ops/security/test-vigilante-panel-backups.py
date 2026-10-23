#!/usr/bin/python3
"""Pruebas acotadas, sin bases ni configuracion de produccion."""
import importlib.util
import json
from pathlib import Path
import tempfile
import tarfile
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parent


def load(name):
    spec = importlib.util.spec_from_file_location(name, ROOT / (name + '.py'))
    obj = importlib.util.module_from_spec(spec); spec.loader.exec_module(obj); return obj


class Checks(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(); self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.text = load('vigilante-text-backups'); self.panel = load('vigilante-panel-backups')
        self.stream = load('vigilante-sql-stream')
        self.configroot = self.root / 'config'; self.configroot.mkdir()
        (self.configroot / 'fstab').write_text('configuracion sin extension\n')
        (self.configroot / 'binary.conf').write_bytes(b'\x89PNG\x00imagen')
        (self.configroot / 'fuera').symlink_to('/no-se-debe-leer')
        self.state = self.root / 'state'; self.state.mkdir(mode=0o700)
        self.dest = self.root / 'dest'; self.dest.mkdir(mode=0o700)
        self.cron = self.root / 'cron.php'; self.cron.write_text('cron')
        self.config = self.root / 'config.json'
        import hashlib
        self.config.write_text(json.dumps({'copies': 3, 'cron_sha256': hashlib.sha256(b'cron').hexdigest()}))
        self.config.chmod(0o600)
        for obj, attr, value in [(self.text,'SYSTEM_ROOTS',(self.configroot,)),
            (self.panel,'STATE',self.state),(self.panel,'DEST',self.dest),
            (self.panel,'CONFIG',self.config),(self.panel,'CRON',self.cron),
            (self.panel,'ROOTS',{'etc':self.configroot})]:
            p=patch.object(obj,attr,value);p.start();self.addCleanup(p.stop)
        def modules(name):
            return {'vigilante-text-backups':self.text,'vigilante-sql-stream':self.stream}[name]
        p=patch.object(self.panel,'module',modules);p.start();self.addCleanup(p.stop)
        def capture(path):
            path.write_bytes(b"-- SQL_MODE='NO_AUTO_VALUE_ON_ZERO'\nCREATE TABLE t(id int);\n-- Dump completed on test\n")
            path.with_suffix('.errors').write_bytes(b'');return 1
        self.capture=patch.object(self.panel,'capture_sql',capture);self.capture.start();self.addCleanup(self.capture.stop)

    def test_text_metadata_no_follow_and_no_binary(self):
        target=self.root/'copy.tar.gz'
        manifest=self.text.archive(self.configroot,target,system_config=True)
        self.text.verify(target,manifest)
        self.assertEqual(manifest['excluded']['binary'],1)
        with tarfile.open(target) as tar:
            self.assertEqual({m.name for m in tar},{'./fstab','./.vigilante-metadata.json'})
            meta=json.load(tar.extractfile('./.vigilante-metadata.json'))
            self.assertEqual(meta['links'][0]['target'],'/no-se-debe-leer')
        with self.assertRaises(ValueError):self.text.archive(self.root,self.root/'bad.tar.gz',system_config=True)

    def test_transactional_publication_and_duplicate(self):
        self.panel.run();self.panel.run()
        ledger=json.loads((self.state/'ledger.json').read_text())
        self.assertEqual(len(ledger['generations']),1)
        self.assertTrue(json.loads((self.state/'status.json').read_text())['ok'])
        self.assertFalse((self.state/'pending.json').exists())

    def test_failure_preserves_good_and_blocks_blind_retry(self):
        self.panel.run()
        ledgerpath=self.state/'ledger.json';before=json.loads(ledgerpath.read_text())
        before['generations'][-1]['day']='anterior';self.text.save(ledgerpath,before)
        with patch.object(self.panel,'capture_sql',side_effect=ValueError('fallo')):
            with self.assertRaises(ValueError):self.panel.run()
        self.assertEqual(json.loads(ledgerpath.read_text()),before)
        self.assertFalse(json.loads((self.state/'status.json').read_text())['ok'])
        with self.assertRaises(ValueError):self.panel.run()

    def test_retention_only_owned_and_hashed(self):
        for _ in range(4):
            if (self.state/'ledger.json').exists():
                ledger=json.loads((self.state/'ledger.json').read_text())
                ledger['generations'][-1]['day']='anterior';self.text.save(self.state/'ledger.json',ledger)
            self.panel.run()
        self.assertEqual(len(list(self.dest.iterdir())),3)
        (self.dest/'ajeno.txt').write_text('conservar')
        ledger=json.loads((self.state/'ledger.json').read_text());ledger['generations'][-1]['day']='anterior'
        self.text.save(self.state/'ledger.json',ledger)
        with self.assertRaises(ValueError):self.panel.run()
        self.assertTrue((self.dest/'ajeno.txt').exists())

    def test_installer_restores_originals_when_first_capture_fails(self):
        import hashlib
        import sys
        from types import SimpleNamespace
        name='install-vigilante-panel-cron.php'
        spec=importlib.util.spec_from_file_location('installer',ROOT/(name+'.py'))
        installer=importlib.util.module_from_spec(spec);spec.loader.exec_module(installer)
        case=self.root/'case';case.mkdir(mode=0o700)
        native=self.root/'isp';native.mkdir()
        original=native/'original.php';original.write_bytes(b'original')
        new=self.root/'vigilante-panel-backups.service';config=self.root/'migration.json'
        sources={'vigilante-panel-cron.php':b'changed','vigilante-panel-backups.service':b'unit'}
        for n,raw in sources.items():(case/n).write_bytes(raw)
        plan={'source_commit':'test','source_sha256':{n:hashlib.sha256(raw).hexdigest() for n,raw in sources.items()},
            'before_sha256':{'vigilante-panel-cron.php':hashlib.sha256(b'original').hexdigest(),'vigilante-panel-backups.service':None}}
        (case/'deployment.json').write_text(json.dumps(plan))
        def execute(args,timeout=20):
            if args[:2]==['/usr/bin/php','-r']:return SimpleNamespace(stdout=str(native))
            if args[:2]==['/usr/bin/systemctl','start']:raise RuntimeError('capture failed')
            return SimpleNamespace(stdout='')
        with patch.object(installer,'CASE_ROOT',self.root),patch.object(installer,'ISP_ROOT',native),\
             patch.object(installer,'TARGETS',{'vigilante-panel-cron.php':original,'vigilante-panel-backups.service':new}),\
             patch.object(installer,'CONFIG',config),patch.object(installer,'execute',execute),\
             patch.object(installer.subprocess,'run',return_value=SimpleNamespace(stdout='inactive')),\
             patch.object(sys,'argv',[name+'.py']):
            with self.assertRaises(RuntimeError):installer.main(case)
        self.assertEqual(original.read_bytes(),b'original')
        self.assertFalse(new.exists());self.assertFalse(config.exists())
        self.assertFalse((native/'.ispconfig_cron_lock').exists())
        self.assertTrue(json.loads((case/'deployment-result.json').read_text())['rolled_back'])

    def test_engine_and_owner_drift_refused(self):
        with patch.object(self.panel,'sql',return_value='t\tBASE TABLE\tMyISAM'):
            with self.assertRaises(ValueError):self.panel.inventory()
        self.cron.write_text('cambiado')
        with self.assertRaises(ValueError):self.panel.run()


if __name__=='__main__':unittest.main()
