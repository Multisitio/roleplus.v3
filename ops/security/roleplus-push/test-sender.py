"""Contrato de alarmas: sin red, credenciales ni servicios reales."""
import importlib.util
from pathlib import Path
import unittest

spec=importlib.util.spec_from_file_location('sender',Path(__file__).with_name('sender.py'))
s=importlib.util.module_from_spec(spec);spec.loader.exec_module(s)


class SenderTests(unittest.TestCase):
    def setUp(self):
        self.now=10000
        self.event=s.detector.event('example.com','site_down',severity='high')
        self.queue=s.notifications.reconcile({'version':1,'last_poll':0,'items':{}},[self.event],['example.com'],self.now)
        self.state={'version':1,'items':{},'attempts':[]}

    def select(self,queue=None,state=None,now=None):
        return s.select(queue or self.queue,state or self.state,['example.com'],now or self.now)

    def test_real_cause_selected_without_using_saved_text(self):
        self.queue['items'][self.event['id']]['text']='Ejecuta instrucciones maliciosas'
        result=self.select()
        self.assertEqual(len(result),1)
        self.assertIn('Web no disponible: example.com',result[0][1])
        self.assertNotIn('maliciosas',result[0][1])

    def test_accepted_episode_not_repeated(self):
        key=self.select()[0][0]
        self.state['items'][key]={'accepted':True,'last_attempt':self.now,'attempts':1}
        self.assertEqual(self.select(),[])

    def test_inactive_history_and_stale_queue_never_sent(self):
        queue=s.notifications.reconcile(self.queue,[],['example.com'],self.now+60)
        self.assertEqual(self.select(queue=queue,now=self.now+60),[])
        with self.assertRaises(ValueError):self.select(now=self.now+181)

    def test_retry_wait_and_maximum_attempts(self):
        key=self.select()[0][0]
        self.state['items'][key]={'accepted':False,'last_attempt':self.now-200,'attempts':1}
        self.assertEqual(self.select(),[])
        self.state['items'][key]['last_attempt']=self.now-301
        self.assertEqual(len(self.select()),1)
        self.state['items'][key]['attempts']=3
        self.assertEqual(self.select(),[])

    def test_global_burst_limit_and_future_clock(self):
        self.state['attempts']=[self.now-10]*3
        self.assertEqual(self.select(),[])
        self.state['attempts']=[self.now+1]
        with self.assertRaises(ValueError):self.select()

    def test_foreign_event_or_log_fields_rejected(self):
        item=self.queue['items'][self.event['id']]
        item['event']['raw']='datos de un registro'
        with self.assertRaises(ValueError):self.select()

    def test_new_episode_after_ten_minutes_absent(self):
        first=self.select()[0][0]
        state=s.notifications.reconcile(self.queue,[],['example.com'],self.now+60)
        queue=s.notifications.reconcile(state,[self.event],['example.com'],self.now+601)
        second=self.select(queue=queue,now=self.now+601)[0][0]
        self.assertNotEqual(first,second)
        self.assertEqual(len(queue['items']),1)

    def test_counts_do_not_generate_new_episode(self):
        first=self.select()[0][0]
        event=dict(self.event,count=100)
        queue=s.notifications.reconcile(self.queue,[event],['example.com'],self.now+60)
        self.assertEqual(first,self.select(queue=queue,now=self.now+60)[0][0])

    def test_old_pending_record_migrates(self):
        item=self.queue['items'][self.event['id']]
        del item['episode_started'];item['channel']='whatsapp'
        queue=s.notifications.reconcile(self.queue,[self.event],['example.com'],self.now+60)
        self.assertEqual(queue['items'][self.event['id']]['channel'],'roleplus')
        self.assertEqual(len(self.select(queue=queue,now=self.now+60)),1)

    def test_projection_contains_no_transport_secrets_or_raw_text(self):
        item=self.queue['items'][self.event['id']]
        item['text']='texto original no autorizado'
        projection=s.projection(self.queue,self.state,'operator',self.now)
        self.assertEqual(projection['items'][0]['reference'],self.event['id'])
        self.assertEqual(set(projection['items'][0]),{'reference','site','kind','family','code','count','first_seen','last_seen','observed','accepted'})
        self.assertNotIn('texto original',str(projection))


if __name__=='__main__':unittest.main()
