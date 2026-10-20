"""Pruebas Linux del PHP sin privilegios; averia SQL simulada sin tocar MariaDB."""
import base64
import json
import os
from pathlib import Path
import pwd
import secrets
import subprocess
import tempfile
import unittest


class WorkerTests(unittest.TestCase):
    def setUp(self):
        self.directory=tempfile.TemporaryDirectory(prefix='vigilante-push-test-')
        self.root=Path(self.directory.name);self.root.chmod(0o755)
        source=Path(__file__).with_name('worker.php').read_text()
        config=self.root/'config.php';config.write_text('<?php class Config { static function get($key) { throw new RuntimeException(); } }');config.chmod(0o644)
        vendor=self.root/'autoload.php';vendor.write_text('<?php');vendor.chmod(0o644)
        source=source.replace('/var/www/clients/client1/core/kumbia/config.php',str(config))
        source=source.replace('/var/www/clients/client1/vendor/autoload.php',str(vendor))
        self.worker=self.root/'worker.php';self.worker.write_text(source);self.worker.chmod(0o644)
        account=pwd.getpwnam('web6')
        self.identity={'user':account.pw_uid,'group':account.pw_gid,'extra_groups':[]}
        self.request={'mode':'refresh','recipient':secrets.token_hex(4),'cached_subscriptions':[{
            'suscripcion':'https://fcm.googleapis.com/test/'+secrets.token_urlsafe(32),
            'p256dh':base64.urlsafe_b64encode(secrets.token_bytes(65)).decode(),
            'auth':base64.urlsafe_b64encode(secrets.token_bytes(16)).decode()}]}

    def tearDown(self):self.directory.cleanup()

    def run_worker(self,request=None,root=False):
        return subprocess.run(['php',str(self.worker)],input=json.dumps(request or self.request),
                              capture_output=True,text=True,timeout=10,**({} if root else self.identity))

    def test_sql_unavailable_keeps_cached_subscription(self):
        r=self.run_worker();self.assertEqual(r.returncode,0)
        value=json.loads(r.stdout)
        self.assertFalse(value['fresh'])
        self.assertEqual(value['uid'],self.identity['user'])
        self.assertEqual(value['subscriptions'],self.request['cached_subscriptions'])
        self.assertEqual(value['reports'],[])

    def test_arbitrary_endpoint_rejected(self):
        self.request['cached_subscriptions'][0]['suscripcion']='http://127.0.0.1/private'
        self.assertNotEqual(self.run_worker().returncode,0)

    def test_missing_recipient_never_broadcasts(self):
        self.request['recipient']=''
        self.assertNotEqual(self.run_worker().returncode,0)

    def test_root_execution_refused(self):
        self.assertNotEqual(self.run_worker(root=True).returncode,0)


if __name__=='__main__':unittest.main()
