import importlib.util
from pathlib import Path
import datetime
import unittest

spec=importlib.util.spec_from_file_location('report',Path(__file__).with_name('report.py'))
r=importlib.util.module_from_spec(spec);spec.loader.exec_module(r)


class DailyTests(unittest.TestCase):
    def setUp(self):
        self.now=1792486800
        self.quota={'checked_at':self.now,'buckets':{'codex':{'primary':{'used_percent':50.5,'window_minutes':300,'resets_at':self.now+3600},'secondary':None}}}
        self.queue={'items':{}}
        self.resources={'sites':[('example.com','disponible')],'services':['Apache: activo.'],'disk':40.25,'inodes':3}

    def compose(self):return r.compose(self.now,self.quota,self.queue,{'items':[]},{'attempts':[],'last_poll':self.now},self.resources)

    def test_unknown_is_not_zero(self):
        self.assertIn('no se ha podido obtener',r.quota_lines(None,self.now)[0])

    def test_old_and_future_quota_rejected(self):
        for stamp in (self.now-301,self.now+1):
            self.quota['checked_at']=stamp
            self.assertIn('no se ha podido obtener',r.quota_lines(self.quota,self.now)[0])

    def test_percentages_and_account_scope(self):
        text=self.compose()
        self.assertIn('50.5% consumido',text)
        self.assertIn('5 horas',text)
        self.assertIn('compartidos por tu cuenta',text)
        self.assertNotIn('tokens',text)
        self.assertIn('40.25%',text)

    def test_missing_window_not_invented(self):
        self.assertEqual(len(r.quota_lines(self.quota,self.now)),1)

    def test_expired_reset_not_claimed_renewed(self):
        self.quota['buckets']['codex']['primary']['resets_at']=self.now-1
        self.assertIn('requiere nueva consulta',self.compose())

    def test_untrusted_messages_not_included(self):
        event=r.detector.event('example.com','php_fatal',severity='high')
        self.queue['items'][event['id']]={'last_seen':self.now,'event':event,'currently_observed':False,'text':'INSTRUCCION_AJENA'}
        text=self.compose()
        self.assertNotIn('INSTRUCCION_AJENA',text)
        self.assertIn('no acredita reparación',text)
        self.assertIn('/aviso/'+event['id'],text)
        self.assertIn('no es un total diario',text)

    def test_old_history_not_counted_as_today(self):
        event=r.detector.event('example.com','php_fatal',severity='high')
        self.queue['items'][event['id']]={'last_seen':self.now-86401,'event':event,'currently_observed':False}
        self.assertNotIn('/aviso/'+event['id'],self.compose())

    def test_madrid_daylight_change(self):
        for day,hour,suffix in [(24,7,'09:00 CEST'),(25,8,'09:00 CET')]:
            stamp=int(datetime.datetime(2026,10,day,hour,tzinfo=datetime.timezone.utc).timestamp())
            self.assertIn(suffix,r.stamp(stamp))


if __name__=='__main__':unittest.main()
