"""Pruebas sin credenciales ni llamadas al proveedor."""
import importlib.util
import json
from pathlib import Path
import subprocess
import sys
import unittest

spec = importlib.util.spec_from_file_location('quota', Path(__file__).with_name('vigilante-quota.py'))
quota = importlib.util.module_from_spec(spec); spec.loader.exec_module(quota)


class QuotaTests(unittest.TestCase):
    def snapshot(self, used=20, reset=2000):
        return quota.normalize({'rateLimitsByLimitId': {'codex': {'primary': {
            'usedPercent': used, 'windowDurationMins': 300, 'resetsAt': reset}, 'secondary': None}}}, 1000)

    def test_percent_and_reset_without_identity(self):
        result = {'rateLimits': {'primary': {'usedPercent': 33.5, 'windowDurationMins': 300, 'resetsAt': 2000}},
                  'account': {'email': 'discarded'}, 'other_data': 'discarded'}
        clean = quota.normalize(result, 1000)
        self.assertEqual(clean['buckets']['codex']['primary']['remaining_percent'], 66.5)
        self.assertNotIn('discarded', json.dumps(clean))

    def test_multiple_buckets_preferred_to_legacy(self):
        clean = quota.normalize({'rateLimitsByLimitId': {}, 'rateLimits': {'primary': {
            'usedPercent': 1, 'windowDurationMins': 1, 'resetsAt': 2000}}}, 1000)
        self.assertFalse(quota.admission(clean, 1001)['routine_allowed'])

    def test_missing_and_null_are_not_zero(self):
        for response in ({}, {'rateLimits': None}, {'rateLimits': {}}, {'rateLimitsByLimitId': {'codex': {}}}):
            self.assertFalse(quota.admission(quota.normalize(response, 1000), 1001)['routine_allowed'])

    def test_invalid_numeric_values_rejected(self):
        for value in (None, True, '20', float('nan'), float('inf'), -1):
            with self.subTest(value=value), self.assertRaises(ValueError):
                self.snapshot(value)

    def test_stale_future_and_expired_snapshots_blocked(self):
        for now in (999, 1301, 2001):
            self.assertFalse(quota.admission(self.snapshot(), now)['routine_allowed'])
        self.assertEqual(quota.admission(self.snapshot(reset=1001), 1002)['state'], 'requiere_nueva_consulta')

    def test_reserve_and_exhaustion(self):
        for used, state in [(89, 'disponible'), (90, 'reserva_del_usuario'), (100, 'agotada'), (102, 'agotada')]:
            with self.subTest(used=used):
                self.assertEqual(quota.admission(self.snapshot(used), 1001)['state'], state)

    def test_secondary_limit_blocks_and_sets_earliest_reset(self):
        snapshot = self.snapshot()
        snapshot['buckets']['codex']['secondary'] = {'used_percent': 99, 'resets_at': 1500}
        gate = quota.admission(snapshot, 1001)
        self.assertFalse(gate['routine_allowed'])
        self.assertEqual(gate['next_reset_check_at'], 1505)

    def test_provider_reached_flag_blocks(self):
        snapshot = self.snapshot()
        snapshot['buckets']['codex']['provider_limit_reached'] = True
        self.assertFalse(quota.admission(snapshot, 1001)['routine_allowed'])

    def run_rpc(self, script):
        process = subprocess.Popen([sys.executable, '-u', '-c', script], stdin=subprocess.PIPE, stdout=subprocess.PIPE)
        try:
            return quota.query(process)
        finally:
            process.kill(); process.wait(timeout=5)
            process.stdin.close(); process.stdout.close()

    def test_rpc_only_initializes_and_reads_limits(self):
        result = self.run_rpc('''import sys,json
a=json.loads(sys.stdin.readline());print(json.dumps({'id':a['id'],'result':{}}),flush=True)
b=json.loads(sys.stdin.readline());c=json.loads(sys.stdin.readline())
print(json.dumps({'id':c['id'],'result':{'methods':[a['method'],b['method'],c['method']]}}),flush=True)
sys.stdin.read()
''')
        self.assertEqual(result['methods'], ['initialize', 'initialized', 'account/rateLimits/read'])

    def test_rpc_server_request_is_rejected(self):
        with self.assertRaises(PermissionError):
            self.run_rpc('''import sys,json
sys.stdin.readline();print(json.dumps({'id':99,'method':'item/commandExecution/requestApproval'}),flush=True)
sys.stdin.read()
''')

    def test_rpc_error_does_not_expose_provider_message(self):
        with self.assertRaisesRegex(RuntimeError, '^Consulta rechazada$'):
            self.run_rpc('''import sys,json
sys.stdin.readline();print(json.dumps({'id':1,'error':{'message':'provider detail'}}),flush=True)
sys.stdin.read()
''')

    def test_rpc_eof_fails_closed(self):
        with self.assertRaises(RuntimeError):
            self.run_rpc('import sys;sys.stdin.readline()')


if __name__ == '__main__':
    unittest.main()
