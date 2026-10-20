"""Pruebas de avisos sin red, modelo ni datos personales."""
import importlib.util
from pathlib import Path
import unittest


def load(name,file):
    spec=importlib.util.spec_from_file_location(name,Path(__file__).with_name(file))
    module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
    return module


n=load('notifications','vigilante-notifications.py')
d=load('detector','vigilante-detect.py')


class NotificationsTests(unittest.TestCase):
    def setUp(self):
        self.state={'version':1,'last_poll':0,'items':{}}
        self.event=d.event('example.com','site_down',severity='high')

    def run_events(self,events,now=100,state=None):
        return n.reconcile(self.state if state is None else state,events,['example.com'],now)

    def test_no_incident_no_notice(self):
        self.assertEqual(self.run_events([])['items'],{})

    def test_normal_errors_wait_for_daily_summary(self):
        self.assertEqual(self.run_events([d.event('example.com','php_warning',count=100)])['items'],{})

    def test_high_incident_is_pending_not_delivered(self):
        item=self.run_events([self.event])['items'][self.event['id']]
        self.assertEqual(item['status'],'pending_transport')
        self.assertFalse(item['delivery_confirmed'])
        self.assertIn('Web no disponible',item['text'])

    def test_repeated_counts_do_not_create_messages(self):
        state=self.run_events([self.event])
        event=dict(self.event,count=90)
        result=self.run_events([event],200,state)
        self.assertEqual(len(result['items']),1)
        self.assertEqual(result['items'][event['id']]['first_seen'],100)
        self.assertEqual(result['items'][event['id']]['last_seen'],200)
        self.assertEqual(state['items'][event['id']]['last_seen'],100)

    def test_absence_does_not_claim_recovery_or_delivery(self):
        state=self.run_events([self.event])
        item=self.run_events([],200,state)['items'][self.event['id']]
        self.assertFalse(item['currently_observed'])
        self.assertEqual(item['status'],'pending_transport')
        self.assertFalse(item['delivery_confirmed'])

    def test_reappearance_keeps_pending_reference(self):
        state=self.run_events([self.event])
        state=self.run_events([],200,state)
        result=self.run_events([self.event],300,state)
        self.assertEqual(len(result['items']),1)
        self.assertTrue(result['items'][self.event['id']]['currently_observed'])
        self.assertEqual(result['items'][self.event['id']]['first_seen'],100)

    def test_raw_text_or_unknown_site_rejected(self):
        for event in [dict(self.event,prompt='instrucciones'),d.event('other.example','site_down',severity='high')]:
            with self.assertRaises(ValueError):self.run_events([event])
        self.assertEqual(self.state['items'],{})

    def test_duplicate_event_rejected(self):
        with self.assertRaises(ValueError):self.run_events([self.event,self.event])

    def test_backward_clock_rejected(self):
        with self.assertRaises(ValueError):self.run_events([],99,self.run_events([self.event]))

    def test_critical_resources_have_fixed_text(self):
        for kind in ('disk_space','inodes','php_fatal','memory_exhausted','service_down'):
            event=d.event('server',kind,severity='high')
            self.assertEqual(len(self.run_events([event])['items']),1)

    def test_automation_failure_does_not_claim_service_down(self):
        event=d.event('server','automation_failed',severity='high')
        item=self.run_events([event])['items'][event['id']]
        self.assertTrue(item['text'].startswith('Tarea programada pendiente de revision:'))
        event=d.event('server','automation_failed',severity='normal')
        self.assertEqual(self.run_events([event])['items'],{})


if __name__=='__main__':unittest.main()
