"""Pruebas locales, sin conexiones exteriores ni credenciales."""
import importlib.util
import os
from pathlib import Path
import socket
import ssl
import unittest
from unittest.mock import Mock, patch

spec = importlib.util.spec_from_file_location('gateway', Path(__file__).with_name('vigilante-auth-gateway.py'))
gateway = importlib.util.module_from_spec(spec)
spec.loader.exec_module(gateway)


class GatewayTests(unittest.TestCase):
    def test_login_tunnel_survives_three_minutes(self):
        left, right = Mock(), Mock()
        left.recv.side_effect = [b'continued-login', b'']
        with patch.object(gateway.time, 'monotonic', side_effect=[0, 181, 182]), \
                patch.object(gateway.select, 'select', return_value=([left], [], [])):
            gateway.relay(left, right)
        right.sendall.assert_called_once_with(b'continued-login')

    def hello(self, host):
        incoming, outgoing = ssl.MemoryBIO(), ssl.MemoryBIO()
        connection = ssl.create_default_context().wrap_bio(incoming, outgoing, server_hostname=host)
        try:
            connection.do_handshake()
        except ssl.SSLWantReadError:
            pass
        return outgoing.read()

    def test_exact_destination(self):
        self.assertEqual(gateway.target(b'CONNECT auth.openai.com:443 HTTP/1.1\r\nHost: auth.openai.com:443\r\n\r\n'), gateway.HOST)

    def test_other_hosts_ports_methods_and_header_conflicts(self):
        for line in [b'CONNECT example.com:443', b'CONNECT 127.0.0.1:443',
                     b'CONNECT auth.openai.com:80', b'GET https://auth.openai.com/',
                     b'CONNECT auth.openai.com.evil.invalid:443']:
            with self.subTest(line=line), self.assertRaises(ValueError):
                gateway.target(line + b' HTTP/1.1\r\n\r\n')
        with self.assertRaises(ValueError):
            gateway.target(b'CONNECT auth.openai.com:443 HTTP/1.1\r\nHost: example.com\r\n\r\n')

    def test_oversized_headers(self):
        with self.assertRaises(ValueError):
            gateway.target(b'A' * 4097 + b'\r\n\r\n')

    def test_real_client_hello(self):
        self.assertEqual(gateway.client_sni(self.hello(gateway.HOST)), gateway.HOST)

    def test_shared_ip_cannot_change_sni(self):
        with self.assertRaises(ValueError):
            gateway.client_sni(self.hello('example.com'))

    def test_all_truncated_hellos_rejected(self):
        record = self.hello(gateway.HOST)
        for size in range(len(record)):
            with self.subTest(size=size), self.assertRaises(ValueError):
                gateway.client_sni(record[:size])

    def test_plaintext_rejected(self):
        with self.assertRaises(ValueError):
            gateway.client_sni(b'GET / HTTP/1.1\r\nHost: auth.openai.com\r\n\r\n')

    def test_private_dns_and_mixed_answers_rejected(self):
        for addresses in [['127.0.0.1'], ['10.0.0.1'], ['169.254.169.254'], ['::1'], ['8.8.8.8', '192.168.1.1']]:
            def resolver(*args, **kwargs):
                return [(socket.AF_INET, socket.SOCK_STREAM, 0, '', (ip, 443)) for ip in addresses]
            with self.subTest(addresses=addresses), self.assertRaises(ValueError):
                gateway.public_addresses(resolver)

    def test_public_dns_retains_numeric_address(self):
        expected = [(socket.AF_INET, socket.SOCK_STREAM, 0, '', ('8.8.8.8', 443))]
        self.assertEqual(gateway.public_addresses(lambda *a, **kw: expected), expected)

    def test_wrong_peer_uid_rejected_before_reading(self):
        left, right = socket.socketpair(socket.AF_UNIX)
        with left, right, self.assertRaises(PermissionError):
            gateway.serve_connection(left, os.getuid() + 1000)


if __name__ == '__main__':
    unittest.main()
