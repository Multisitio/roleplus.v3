"""Pruebas sin sesion ni modelo para el ensayo de herramientas externas."""
import importlib.util
from pathlib import Path
import unittest
import subprocess
import sys
from unittest.mock import patch


def load(name, file):
    spec = importlib.util.spec_from_file_location(name, Path(__file__).with_name(file))
    module = importlib.util.module_from_spec(spec); spec.loader.exec_module(module)
    return module


model = load('model', 'vigilante-model-check.py')
worker = load('worker', 'vigilante-tool-probe.py')
quota = load('quota', 'vigilante-quota.py')
rpc_module = load('rpc', 'vigilante-rpc.py')


class FakeRpc:
    def __init__(self, used=20):
        self.used = used
        self.requests = []
        self.messages = []

    def request(self, method, request_id, params=None):
        import time
        self.requests.append((method, params))
        if method == 'account/rateLimits/read':
            return {'rateLimits': {'primary': {'usedPercent': self.used, 'windowDurationMins': 300,
                                              'resetsAt': int(time.time())+1000}}}
        if method == 'model/list':
            return {'data': [{'model': 'test-model', 'isDefault': True,
                              'supportedReasoningEfforts': [{'reasoningEffort': 'low'}]}]}
        if method == 'thread/start':
            return {'thread': {'id': 'test-thread'}}
        return {}

    def send(self, message):
        self.messages.append(message)


class ModelTests(unittest.TestCase):
    def test_worker_rejects_commands_paths_and_extra_keys(self):
        for request in ({}, {'action': 'exec'}, {'action': 'probe', 'command': 'id'},
                        {'action': 'probe', 'path': '/auth/auth.json'}, ['probe'], None):
            with self.subTest(request=request), self.assertRaises(ValueError):
                worker.validate(request)
        worker.validate({'action': 'probe'})

    def test_quota_blocks_before_thread_and_model(self):
        for used in (90, 100):
            rpc = FakeRpc(used)
            report = model.run_check(rpc, quota)
            self.assertFalse(report['model_called'])
            self.assertNotIn('thread/start', [m for m, _ in rpc.requests])

    def test_unknown_repeated_cross_thread_and_nonempty_arguments_rejected(self):
        valid = {'threadId': 'test-thread', 'tool': 'comprobar_aislamiento', 'arguments': {}}
        bad = [dict(valid, threadId='other'), dict(valid, tool='exec'),
               dict(valid, arguments={'command': 'id'}), dict(valid, arguments=None)]
        for params in bad:
            with self.subTest(params=params), self.assertRaises(PermissionError):
                model.tool_reply(params, 'test-thread', False)
        with self.assertRaises(PermissionError):
            model.tool_reply(valid, 'test-thread', True)

    def test_thread_disables_all_local_environments(self):
        rpc = FakeRpc()
        rpc.read = lambda: {'method': 'turn/completed', 'params': {
            'threadId': 'test-thread', 'turn': {'status': 'completed'}}}
        result = model.run_check(rpc, quota)
        self.assertFalse(result['completed'])
        params = next(p for m, p in rpc.requests if m == 'thread/start')
        self.assertEqual(params['environments'], [])
        self.assertEqual(params['permissions'], ':read-only')
        self.assertFalse(params['allowProviderModelFallback'])
        self.assertTrue(params['ephemeral'])
        self.assertEqual(len(params['dynamicTools']), 1)

    def test_server_cannot_request_approval_or_another_operation(self):
        rpc = FakeRpc()
        rpc.read = lambda: {'id': 99, 'method': 'item/commandExecution/requestApproval', 'params': {}}
        with self.assertRaises(PermissionError):
            model.run_check(rpc, quota)

    def test_unknown_catalog_default_fails_before_inference(self):
        rpc = FakeRpc()
        original = rpc.request
        rpc.request = lambda method, request_id, params=None: ({'data': []} if method == 'model/list'
                                                            else original(method, request_id, params))
        with self.assertRaisesRegex(RuntimeError, 'Modelo predeterminado'):
            model.run_check(rpc, quota)
        self.assertNotIn('turn/start', [m for m, _ in rpc.requests])

    def test_real_tool_response_required_for_success(self):
        rpc = FakeRpc()
        messages = iter([{'id': 80, 'method': 'item/tool/call', 'params': {
            'threadId': 'test-thread', 'tool': 'comprobar_aislamiento', 'arguments': {}}},
            {'method': 'turn/completed', 'params': {'threadId': 'test-thread', 'turn': {'status': 'completed'}}}])
        rpc.read = lambda: next(messages)
        with patch.object(model, 'tool_reply', return_value={'passed': True}) as tool:
            report = model.run_check(rpc, quota)
        self.assertTrue(report['completed'])
        tool.assert_called_once()
        self.assertEqual(rpc.messages[-1]['id'], 80)
        self.assertTrue(rpc.messages[-1]['result']['success'])

    def rpc_process(self, script):
        process = subprocess.Popen([sys.executable, '-u', '-c', script], stdin=subprocess.PIPE, stdout=subprocess.PIPE)
        rpc = rpc_module.Rpc(process, seconds=3)
        self.addCleanup(lambda: (rpc.close(), process.kill(), process.wait(timeout=5),
                                 process.stdin.close(), process.stdout.close()))
        return rpc

    def test_rpc_retains_multiple_messages_in_same_buffer(self):
        rpc = self.rpc_process('import sys;sys.stdin.readline();print(\'{"id":1,"result":{}}\\n{"method":"next"}\',flush=True);sys.stdin.read()')
        self.assertEqual(rpc.request('initialize', 1), {})
        self.assertEqual(rpc.read()['method'], 'next')

    def test_rpc_rejects_server_requests_during_setup(self):
        rpc = self.rpc_process('import sys;sys.stdin.readline();print(\'{"id":2,"method":"exec"}\',flush=True);sys.stdin.read()')
        with self.assertRaises(PermissionError):
            rpc.request('initialize', 1)

    def test_rpc_omits_provider_error_details(self):
        rpc = self.rpc_process('import sys;sys.stdin.readline();print(\'{"id":1,"error":{"code":-32600,"message":"discarded"}}\',flush=True);sys.stdin.read()')
        with self.assertRaises(rpc_module.Rejected) as caught:
            rpc.request('initialize', 1)
        self.assertEqual(caught.exception.code, -32600)
        self.assertNotIn('discarded', str(caught.exception))

    def test_rpc_closed_process_fails(self):
        rpc = self.rpc_process('import sys;sys.stdin.readline()')
        with self.assertRaises(RuntimeError):
            rpc.request('initialize', 1)


if __name__ == '__main__':
    unittest.main()
