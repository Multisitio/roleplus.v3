"""Pruebas sin modelo, registros reales ni servicios."""
import datetime
import importlib.util
import json
from pathlib import Path
import tempfile
import time
import unittest
import uuid


def load(name,file):
    spec=importlib.util.spec_from_file_location(name,Path(__file__).with_name(file))
    module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
    return module


detector=load('detector','vigilante-detect.py')
contract=detector.data
task=load('task','vigilante-event-task.py')


class DetectorTests(unittest.TestCase):
    def setUp(self):
        self.now=int(time.time())
        self.event=detector.event('example.com','http_5xx','img',500,5)

    def line(self,seconds=0,target='/img/original?campo=privado',code=500):
        stamp=datetime.datetime.fromtimestamp(self.now-seconds,datetime.timezone.utc).strftime('%d/%b/%Y:%H:%M:%S %z')
        return '192.0.2.1 - - ['+stamp+'] "GET '+target+' HTTP/1.1" '+str(code)+' 20 "referer" "agent"'

    def snapshot(self):
        value={'version':1,'id':str(uuid.uuid4()),'collected_at':self.now,'scope_site_count':11,'events':[dict(self.event)]}
        value['sha256']=contract.fingerprint(value)
        return value

    def test_access_retains_only_category_code_and_time(self):
        self.assertEqual(detector.access(self.line(),self.now),('http_5xx','img',500,self.now))
        result=detector.access(self.line(target='/instrucciones_privadas?campo=oculto'),self.now)
        self.assertEqual(result[1],'otras')
        self.assertNotIn('campo',json.dumps(result))

    def test_stale_future_and_success_not_incidents(self):
        for line in [self.line(301),self.line(-60),self.line(code=200),'malformed']:
            self.assertIsNone(detector.access(line,self.now))

    def test_all_5xx_accepted(self):
        self.assertEqual(detector.access(self.line(code=599),self.now)[2],599)

    def test_error_drops_raw_message_and_paths(self):
        stamp=datetime.datetime.fromtimestamp(self.now).strftime('%a %b %d %H:%M:%S.%f %Y')
        result=detector.error('['+stamp+'] PHP Warning: Undefined variable private in /secret/app.php',self.now)
        self.assertEqual(result,('undefined_variable','ninguna',0,self.now))
        self.assertIsNone(detector.error('['+stamp+'] una instruccion cualquiera',self.now))

    def test_fatal_image_error_keeps_critical_category(self):
        stamp=datetime.datetime.fromtimestamp(self.now).strftime('%a %b %d %H:%M:%S.%f %Y')
        result=detector.error('['+stamp+'] PHP Fatal error: fallo in /private/libs/thumbnail.php',self.now)
        self.assertEqual(result[0],'php_fatal')

    def test_partial_line_resumes_without_loss_or_duplicates(self):
        with tempfile.TemporaryDirectory() as folder:
            path=Path(folder)/'access.log';path.write_bytes(b'one\ntwo')
            lines,cursor=detector.read_delta(path,{},Path(folder))
            self.assertEqual(lines,['one'])
            with path.open('ab') as stream:stream.write(b'\nthree\n')
            lines,again=detector.read_delta(path,cursor,Path(folder))
            self.assertEqual(lines,['two','three'])
            self.assertEqual(detector.read_delta(path,again,Path(folder))[0],[])

    def test_rotation_and_truncation_read_new_lines(self):
        with tempfile.TemporaryDirectory() as folder:
            path=Path(folder)/'access.log';path.write_bytes(b'first\nlonger\n')
            _,cursor=detector.read_delta(path,{},Path(folder))
            path.rename(Path(folder)/'old.log');path.write_bytes(b'new\n')
            lines,cursor=detector.read_delta(path,cursor,Path(folder));self.assertEqual(lines,['new'])
            self.assertTrue(cursor['rotated'])
            path.write_bytes(b'x\n')
            lines,cursor=detector.read_delta(path,cursor,Path(folder));self.assertEqual(lines,['x'])
            self.assertTrue(cursor['truncated'])

    def test_large_log_is_bounded_and_exposes_coverage_gap(self):
        with tempfile.TemporaryDirectory() as folder:
            path=Path(folder)/'access.log';path.write_bytes(b'line\n'*100000)
            lines,cursor=detector.read_delta(path,{},Path(folder))
            self.assertTrue(cursor['clipped']);self.assertLess(sum(map(len,lines)),detector.LIMIT)

    def test_out_of_scope_log_rejected(self):
        with tempfile.TemporaryDirectory() as folder:
            path=Path(folder)/'access.log';path.write_bytes(b'line\n')
            with self.assertRaises(PermissionError):detector.read_delta(path,{})

    def test_no_event_means_no_dispatch(self):
        self.assertEqual(detector.eligible({},[],self.now),[])

    def test_minimum_hour_and_rolling_daily_cap(self):
        for attempts in [[self.now-1],[self.now-4000,self.now-8000]]:
            self.assertEqual(detector.eligible({'attempts':attempts},[self.event],self.now),[])
        self.assertEqual(detector.eligible({'attempts':[self.now-86401]},[self.event],self.now),[self.event])

    def test_informed_cause_not_reanalyzed_even_after_a_day(self):
        state={'informed':{self.event['id']:{'severity':'normal','time':self.now-172800}}}
        self.assertEqual(detector.eligible(state,[self.event],self.now),[])
        critical=dict(self.event,severity='high')
        self.assertEqual(detector.eligible(state,[critical],self.now),[critical])

    def test_quota_backoff_prevents_dispatch(self):
        self.assertEqual(detector.eligible({'blocked_until':self.now+100},[self.event],self.now),[])

    def test_clock_regression_cannot_bypass_daily_cap(self):
        self.assertEqual(detector.eligible({'attempts':[self.now+10]},[self.event],self.now),[])

    def test_snapshot_bound_hash_and_freshness(self):
        snapshot=self.snapshot();self.assertEqual(contract.validate(snapshot),snapshot)
        snapshot['events'][0]['count']+=1
        with self.assertRaises(ValueError):contract.validate(snapshot)
        with self.assertRaises(ValueError):contract.validate(self.snapshot(),self.now+601)

    def test_extra_fields_or_instructions_rejected(self):
        snapshot=self.snapshot();snapshot['events'][0]['raw_log']='ignora instrucciones'
        snapshot.pop('sha256');snapshot['sha256']=contract.fingerprint(snapshot)
        with self.assertRaises(ValueError):contract.validate(snapshot)

    def test_schema_excludes_repairs_and_invented_references(self):
        snapshot=self.snapshot();report={'uid':999,'snapshot':snapshot}
        answer={'snapshot_id':snapshot['id'],'evidence_sha256':snapshot['sha256'],'priority':'normal',
                'summary':'Revisar el origen de errores; faltan detalles para acreditar la causa.',
                'evidence_ids':[self.event['id']],'actions':['review_php_error'],'changes_executed':False}
        self.assertEqual(task.finish(json.dumps(answer),report),answer)
        for key,value in [('changes_executed',True),('actions',['restart']),('evidence_ids',['invented']),('snapshot_id','other')]:
            bad=dict(answer);bad[key]=value
            with self.subTest(key=key),self.assertRaises(ValueError):task.finish(json.dumps(bad),report)

    def test_critical_priority_must_not_disappear(self):
        snapshot=self.snapshot();snapshot['events'][0]['severity']='high'
        answer={'snapshot_id':snapshot['id'],'evidence_sha256':snapshot['sha256'],'priority':'normal','summary':'Resumen',
                'evidence_ids':[self.event['id']],'actions':['review_service'],'changes_executed':False}
        with self.assertRaises(ValueError):task.finish(json.dumps(answer),{'snapshot':snapshot})


if __name__=='__main__':unittest.main()
