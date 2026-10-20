"""Renovaciones simuladas: sin perfil, red, llamadas a modelos ni servicios."""
import copy
import importlib.util
from pathlib import Path
import unittest

spec=importlib.util.spec_from_file_location('refresh',Path(__file__).with_name('vigilante-quota-refresh.py'))
r=importlib.util.module_from_spec(spec);spec.loader.exec_module(r)


class RenewalTests(unittest.TestCase):
    def setUp(self):
        self.now=100000
        self.cache=r.empty()
        self.snapshot={'checked_at':self.now,'buckets':{'codex':{'primary':{'used_percent':15,'resets_at':self.now+50000,'window_minutes':10080},'secondary':None,'provider_limit_reached':False}}}
        self.automatic={'attempts':[self.now-7200,self.now-3600],'informed':{'example':{'time':self.now-3600}},
                        'blocked_until':self.now+10000,'quota_block_reason':'reserva_del_usuario',
                        'quota_blocked_at':self.now-1800,'quota_retry_at':self.now+10000}

    def test_initial_check_is_due(self):
        self.assertEqual(r.due_at(self.cache,{},self.now),0)

    def test_success_checks_no_later_than_six_hours(self):
        value=r.after_attempt(self.cache,self.snapshot,self.now)
        self.assertEqual(value['next_check_at'],self.now+21600)
        self.assertEqual(value['failures'],0)

    def test_provider_reset_is_checked_after_five_seconds(self):
        self.snapshot['buckets']['codex']['primary']['resets_at']=self.now+300
        self.assertEqual(r.after_attempt(self.cache,self.snapshot,self.now)['next_check_at'],self.now+305)

    def test_failure_forgets_old_admission_and_backs_off(self):
        self.cache['snapshot']=self.snapshot
        first=r.after_attempt(self.cache,None,self.now)
        self.assertIsNone(first['snapshot'])
        self.assertEqual(first['next_check_at'],self.now+900)
        second=r.after_attempt(first,None,self.now)
        self.assertEqual(second['next_check_at'],self.now+1800)

    def test_expired_provider_reset_does_not_open_gate(self):
        self.snapshot['buckets']['codex']['primary']['resets_at']=self.now
        value=r.after_attempt(self.cache,self.snapshot,self.now)
        self.assertIsNone(value['snapshot'])
        self.assertEqual(value['next_check_at'],self.now+900)

    def test_quota_release_preserves_attempts_and_informed(self):
        original=copy.deepcopy(self.automatic)
        self.assertTrue(r.release(self.automatic,dict(self.cache,snapshot=self.snapshot),self.now))
        self.assertEqual(self.automatic['blocked_until'],0)
        self.assertEqual(self.automatic['attempts'],original['attempts'])
        self.assertEqual(self.automatic['informed'],original['informed'])

    def test_unknown_error_block_never_released(self):
        self.automatic['quota_block_reason']=None
        self.assertFalse(r.release(self.automatic,dict(self.cache,snapshot=self.snapshot),self.now))
        self.assertGreater(self.automatic['blocked_until'],self.now)

    def test_stale_future_or_older_confirmation_never_releases(self):
        for checked in [self.now-301,self.now+1,self.now-1801]:
            self.snapshot['checked_at']=checked
            self.assertFalse(r.release(self.automatic,dict(self.cache,snapshot=self.snapshot),self.now))

    def test_reserve_and_exhaustion_remain_blocked(self):
        for used in [90,100]:
            self.snapshot['buckets']['codex']['primary']['used_percent']=used
            self.assertFalse(r.release(self.automatic,dict(self.cache,snapshot=self.snapshot),self.now))

    def test_eight_checks_limit_cannot_be_bypassed_by_hint(self):
        self.cache.update(attempts=[self.now-i for i in range(1,9)],last_attempt=self.now-1)
        self.automatic['quota_blocked_at']=self.now
        self.automatic['quota_retry_at']=self.now
        self.assertEqual(r.due_at(self.cache,self.automatic,self.now),self.now-8+86400)

    def test_new_analysis_hint_can_bring_check_forward(self):
        self.cache.update(last_attempt=self.now-7200,next_check_at=self.now+21600)
        self.automatic['quota_retry_at']=self.now+600
        self.assertEqual(r.due_at(self.cache,self.automatic,self.now),self.now+600)

    def test_failed_check_backoff_not_overridden_by_old_hint(self):
        self.cache.update(last_attempt=self.now-60,next_check_at=self.now+900)
        self.automatic['quota_retry_at']=self.now-100
        self.assertEqual(r.due_at(self.cache,self.automatic,self.now),self.now+900)

    def test_future_attempt_clock_rejected(self):
        self.cache['last_attempt']=self.now+1
        with self.assertRaises(ValueError):r.due_at(self.cache,{},self.now)

    def test_legacy_wait_requires_exact_original_denial(self):
        original={'attempts':[self.now-1800],'blocked_until':self.now+10000}
        reply={'completed':False,'model_called':False,'admission':{'state':'reserva_del_usuario','routine_allowed':False,'next_reset_check_at':self.now+10000}}
        self.assertTrue(r.mark_legacy_wait(original,reply))
        self.assertEqual(original['quota_block_reason'],'reserva_del_usuario')
        original={'attempts':[self.now-1800],'blocked_until':self.now+12000}
        self.assertFalse(r.mark_legacy_wait(original,reply))
        self.assertNotIn('quota_block_reason',original)


if __name__=='__main__':unittest.main()
