import socket
import unittest
from unittest.mock import Mock

from resolve_ftps_host import resolve_host


class ResolveFtpsHostTest(unittest.TestCase):
    def setUp(self):
        self.reverse = Mock(return_value=("srv123.hstgr.io", [], ["192.0.2.10"]))
        self.lookup = Mock(return_value=[(socket.AF_INET, socket.SOCK_STREAM, 6, "", ("192.0.2.10", 21))])

    def resolve(self, server="192.0.2.10", hostname=""):
        return resolve_host(server, hostname, reverse=self.reverse, lookup=self.lookup)

    def test_ip_uses_hostinger_hostname_with_matching_forward_dns(self):
        self.assertEqual("srv123.hstgr.io", self.resolve())
        self.reverse.assert_called_once_with("192.0.2.10")
        self.lookup.assert_called_once_with("srv123.hstgr.io", 21, type=socket.SOCK_STREAM)

    def test_existing_hostname_does_not_need_reverse_dns(self):
        self.assertEqual("srv123.hstgr.io", self.resolve(" SRV123.HSTGR.IO. "))
        self.reverse.assert_not_called()
        self.lookup.assert_not_called()

    def test_ipv6_compares_addresses_after_normalization(self):
        self.lookup.return_value = [(socket.AF_INET6, socket.SOCK_STREAM, 6, "", ("2001:db8::10", 21, 0, 0))]
        self.assertEqual("srv123.hstgr.io", self.resolve("2001:0db8:0000::10"))

    def test_explicit_hostname_works_without_reverse_dns(self):
        self.assertEqual("srv123.hstgr.io", self.resolve(hostname="srv123.hstgr.io"))
        self.reverse.assert_not_called()

    def test_reverse_hostname_must_belong_to_hostinger_certificate_domain(self):
        for hostname in ["attacker.example", "srv123.hstgr.io.attacker.example", "srv123.sub.hstgr.io"]:
            with self.subTest(hostname=hostname):
                self.reverse.return_value = (hostname, [], [])
                with self.assertRaisesRegex(ValueError, "não corresponde à Hostinger"):
                    self.resolve()

    def test_rejects_hostname_pointing_to_another_server(self):
        self.lookup.return_value = [(socket.AF_INET, socket.SOCK_STREAM, 6, "", ("192.0.2.11", 21))]
        for hostname in ["", "srv123.hstgr.io"]:
            with self.subTest(hostname=hostname):
                with self.assertRaisesRegex(ValueError, "não aponta para o IP"):
                    self.resolve(hostname=hostname)

    def test_missing_reverse_dns_explains_manual_configuration(self):
        self.reverse.side_effect = socket.herror("missing PTR")
        with self.assertRaisesRegex(ValueError, "configure a variável FTP_HOSTNAME"):
            self.resolve()

    def test_rejects_invalid_configuration_before_upload(self):
        for server, hostname in [
            ("", ""),
            ("ftps://srv123.hstgr.io", ""),
            ("srv123.hstgr.io:21", ""),
            ("srv123.hstgr.io", "another.hstgr.io"),
            ("192.0.2.10", "192.0.2.10"),
            ("192.0.2.10", "srv123.hstgr.io\nhostname=attacker.example"),
        ]:
            with self.subTest(server=server, hostname=hostname):
                with self.assertRaises(ValueError):
                    self.resolve(server, hostname)


if __name__ == "__main__":
    unittest.main()
