import hashlib
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import Mock

from upload_release import build_commands, lftp_quote, upload_release


class UploadReleaseTest(unittest.TestCase):
    def setUp(self):
        self.folder = tempfile.TemporaryDirectory()
        self.addCleanup(self.folder.cleanup)
        self.path = Path(self.folder.name)
        (self.path / 'release.zip').write_bytes(b'example release')
        (self.path / 'deploy_extractor.php').write_bytes(b'example extractor')

    def test_success_preserves_credentials_outside_arguments_and_outputs_digest_after_upload(self):
        password = 'private-password $";secret'
        output = self.path / 'output'
        run = Mock()
        upload_release('hstgr.io', 'user@example.test', password, 'domains/example.test/public_html/', self.path, output, run=run)
        args, kwargs = run.call_args
        self.assertEqual((['lftp', '--norc'],), args)
        self.assertNotIn(password, kwargs['input'])
        self.assertEqual(password, kwargs['env']['LFTP_PASSWORD'])
        self.assertTrue(kwargs['check'])
        digest = hashlib.sha256(b'example release').hexdigest()
        self.assertEqual(f'release_sha256={digest}\n', output.read_text(encoding='utf-8'))
        commands = kwargs['input']
        self.assertIn('user%40example.test@hstgr.io:21', commands)
        self.assertIn(f'.ftp-release-{digest}.zip.part', commands)
        self.assertLess(commands.index('put -c "release.zip"'), commands.index('"release.zip"\nput'))
        self.assertNotIn('mirror', commands)
        self.assertNotIn('\nrm ', commands)

    def test_network_failure_cannot_publish_extraction_output(self):
        output = self.path / 'output'
        run = Mock(side_effect=subprocess.CalledProcessError(1, ['lftp']))
        with self.assertRaises(subprocess.CalledProcessError):
            upload_release('hstgr.io', 'user', 'password', 'public_html/', self.path, output, run=run)
        self.assertFalse(output.exists())

    def test_partial_names_are_bound_to_each_artifact_not_a_shared_previous_release(self):
        commands = build_commands('hstgr.io', 'user', 'public_html/', self.path, 'a' * 64, 'b' * 64)
        self.assertIn('.ftp-release-' + 'a' * 64 + '.zip.part', commands)
        self.assertIn('.ftp-extractor-' + 'b' * 64 + '.php.part', commands)
        self.assertIn('set ftp:ssl-force true', commands)
        self.assertIn('set ssl:verify-certificate true', commands)
        self.assertIn('set ssl:check-hostname true', commands)
        self.assertIn('set ftp:ssl-protect-data true', commands)

    def test_untrusted_command_parameters_fail_before_running_client(self):
        for hostname, username, directory in [
            ('hstgr.io\n! command', 'user', 'public_html/'),
            ('hstgr.io', 'user\n! command', 'public_html/'),
            ('hstgr.io', 'user', '../public_html/'),
            ('hstgr.io', 'user', 'public_html/; command'),
        ]:
            with self.subTest(hostname=hostname, username=username, directory=directory):
                run = Mock()
                with self.assertRaises(ValueError):
                    upload_release(hostname, username, 'password', directory, self.path, self.path / 'output', run=run)
                run.assert_not_called()

    def test_command_quoting_does_not_allow_control_character_injection(self):
        with self.assertRaises(ValueError):
            lftp_quote('path\nbye')
        self.assertEqual('"a\\"\\$\\`\\\\b"', lftp_quote('a"$`\\b'))


if __name__ == '__main__':
    unittest.main()
