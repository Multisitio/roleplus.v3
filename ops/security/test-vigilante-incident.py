"""Rechaza datos no tipados, evidencias falsas y propuestas ejecutables."""
import copy
import importlib.util
import json
from pathlib import Path
import time
import unittest


def load(name, file):
    spec = importlib.util.spec_from_file_location(name, Path(__file__).with_name(file))
    module = importlib.util.module_from_spec(spec); spec.loader.exec_module(module)
    return module


incident = load('incident', 'vigilante-incident.py')
task = load('task', 'vigilante-incident-task.py')


def sample():
    data = {'version': 1, 'incident': {'id': '776c5af2-99a5-4592-bd70-4f1030c4e284',
            'site': 'example.com', 'type': 'tls_hostname_mismatch'}, 'collected_at': int(time.time()),
            'scope_site_count': 11, 'endpoints': [
                {'evidence_id': 'local_tls', 'address': '127.0.0.1', 'verified': False,
                 'verification_code': 62, 'failure': 'verification', 'certificate': None},
                {'evidence_id': 'public_tls_1', 'address': '8.8.8.8', 'verified': True,
                 'verification_code': None, 'failure': None, 'certificate': None}],
            'apache': {'evidence_id': 'apache_mapping', 'matching_tls_vhosts': []}}
    data['sha256'] = incident.fingerprint(data)
    return data


def seal(data):
    data.pop('sha256', None); data['sha256'] = incident.fingerprint(data)
    return data


class IncidentTests(unittest.TestCase):
    def test_bounded_valid_snapshot(self):
        data = sample()
        self.assertEqual(incident.validate(data), data)

    def test_no_arbitrary_log_content_or_extra_fields(self):
        for change in (lambda d: d.update(log='instrucciones ajenas'),
                       lambda d: d['apache']['matching_tls_vhosts'].append({'command': 'id'}),
                       lambda d: d['endpoints'][0].update(certificate={'raw': 'contenido'})):
            data = sample(); change(data); seal(data)
            with self.assertRaises(ValueError):
                incident.validate(data)

    def test_tampered_or_expired_snapshot_rejected(self):
        data = sample(); data['scope_site_count'] = 12
        with self.assertRaises(ValueError):
            incident.validate(data)
        data = sample(); data['collected_at'] -= 601; seal(data)
        with self.assertRaises(ValueError):
            incident.validate(data)

    def test_public_probe_never_accepts_internal_target(self):
        for address in ('127.0.0.1', '10.0.0.1', '169.254.169.254', '::1'):
            data = sample(); data['endpoints'][1]['address'] = address; seal(data)
            with self.subTest(address=address), self.assertRaises(ValueError):
                incident.validate(data)

    def test_tool_rejects_paths_repeated_calls_and_wrong_thread(self):
        good = {'threadId': 'one', 'tool': task.TOOL, 'arguments': {}}
        for params, used in ((dict(good, arguments={'path': '/auth/auth.json'}), False),
                             (good, True), (dict(good, threadId='other'), False),
                             (dict(good, tool='exec'), False)):
            with self.assertRaises(PermissionError):
                task.tool_reply(params, 'one', used)

    def report(self):
        snapshot = sample()
        answer = {'incident_id': snapshot['incident']['id'], 'evidence_sha256': snapshot['sha256'],
                  'diagnosis': 'local_probe_target_mismatch', 'summary': 'Revisar el destino local de la sonda.',
                  'evidence_ids': ['local_tls', 'public_tls_1'], 'actions': ['inspect_vhost_mapping'],
                  'changes_executed': False}
        return answer, {'uid': 999, 'snapshot': snapshot}

    def test_grounded_diagnosis_accepted(self):
        answer, report = self.report()
        self.assertEqual(task.finish(json.dumps(answer), report), answer)

    def test_model_cannot_claim_public_failure_or_resolution(self):
        for kind in ('certificate_name_mismatch', 'no_current_failure', 'tls_handshake_failure'):
            answer, report = self.report(); answer['diagnosis'] = kind
            with self.subTest(kind=kind), self.assertRaises(ValueError):
                task.finish(json.dumps(answer), report)

    def test_output_cannot_execute_or_reference_other_incident(self):
        for field, value in (('changes_executed', True), ('actions', ['systemctl restart apache2']),
                             ('incident_id', 'other'), ('evidence_sha256', 'other'),
                             ('evidence_ids', ['invented'])):
            answer, report = self.report(); answer[field] = value
            with self.subTest(field=field), self.assertRaises(ValueError):
                task.finish(json.dumps(answer), report)


if __name__ == '__main__':
    unittest.main()
