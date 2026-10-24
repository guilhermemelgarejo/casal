import contextlib
import io
import socket
import ssl
import tempfile
import unittest
from pathlib import Path
from unittest.mock import MagicMock, Mock

from resolve_ftps_host import prepare_target, resolve_target, verify_endpoint


class ResolveFtpsHostTest(unittest.TestCase):
    def test_ip_keeps_exact_endpoint_without_reverse_dns(self):
        self.assertEqual(('hstgr.io', '192.0.2.10'), resolve_target('192.0.2.10'))

    def test_existing_hostname_is_normalized(self):
        self.assertEqual(('srv123.hstgr.io', ''), resolve_target(' SRV123.HSTGR.IO. '))

    def test_ipv6_address_is_normalized(self):
        self.assertEqual(('hstgr.io', '2001:db8::10'), resolve_target('2001:0db8:0000::10'))

    def test_explicit_hostinger_hostname_preserves_ip(self):
        self.assertEqual(('srv123.hstgr.io', '192.0.2.10'), resolve_target('192.0.2.10', 'srv123.hstgr.io'))

    def test_rejects_invalid_configuration_before_connecting(self):
        for server, hostname in [
            ('', ''),
            ('ftps://srv123.hstgr.io', ''),
            ('srv123.hstgr.io:21', ''),
            ('srv123.hstgr.io', 'another.hstgr.io'),
            ('192.0.2.10', '192.0.2.10'),
            ('192.0.2.10', 'attacker.example'),
            ('192.0.2.10', 'srv123.hstgr.io.attacker.example'),
            ('192.0.2.10', 'srv123.sub.hstgr.io'),
            ('192.0.2.10', 'srv123.hstgr.io\nhostname=attacker.example'),
        ]:
            with self.subTest(server=server, hostname=hostname):
                with self.assertRaises(ValueError):
                    resolve_target(server, hostname)

    def test_preflight_requires_ca_and_hostname_validation_without_login(self):
        factory = MagicMock()
        verify_endpoint('hstgr.io', '192.0.2.10', ftp_factory=factory)
        context = factory.call_args.kwargs['context']
        self.assertEqual(ssl.CERT_REQUIRED, context.verify_mode)
        self.assertTrue(context.check_hostname)
        connection = factory.return_value.__enter__.return_value
        connection.connect.assert_called_once_with('192.0.2.10', 21, timeout=15)
        self.assertEqual('hstgr.io', connection.host)
        connection.auth.assert_called_once_with()
        connection.login.assert_not_called()

    def test_failed_certificate_or_connection_produces_no_action_output(self):
        for error in [ssl.SSLCertVerificationError('untrusted certificate'), socket.timeout('timeout')]:
            with self.subTest(error=type(error).__name__), tempfile.TemporaryDirectory() as folder:
                output = Path(folder) / 'output'
                with self.assertRaises(type(error)):
                    prepare_target('192.0.2.10', '', output, verify=Mock(side_effect=error))
                self.assertFalse(output.exists())

    def test_action_output_preserves_ip_and_masks_target(self):
        with tempfile.TemporaryDirectory() as folder:
            output = Path(folder) / 'output'
            log = io.StringIO()
            verify = Mock()
            with contextlib.redirect_stdout(log):
                prepare_target('192.0.2.10', '', output, verify=verify)
            verify.assert_called_once_with('hstgr.io', '192.0.2.10')
            self.assertEqual('hostname=hstgr.io\naddress=192.0.2.10\n', output.read_text(encoding='utf-8'))
            self.assertIn('::add-mask::hstgr.io', log.getvalue())
            self.assertIn('::add-mask::192.0.2.10', log.getvalue())


if __name__ == '__main__':
    unittest.main()
