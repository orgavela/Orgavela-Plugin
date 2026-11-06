"""Real child-process tests; no SSH endpoint or live source access."""
import hashlib
import io
import json
import os
from pathlib import Path
import sys
import tarfile
import tempfile
import unittest

from collector import ProbeError, accept_snapshot
from capture_cli import trusted_main
from recovery import validate_package
from crypto import PRIMARY_FPR, SUBKEY_FPR
from transport import Binding, live_program, receive
from unittest.mock import patch
import fixtures
import publish_cli
import restore_cli
from github_api import reject_mixed_credentials


class TransportTests(unittest.TestCase):
    def exercise(self, code, *, limit=4096, timeout=2, diagnostic_limit=1024):
        with tempfile.TemporaryDirectory() as folder:
            target = Path(folder) / 'snapshot.tar'
            try:
                receive([sys.executable, '-c', code], b'fixture', target, env={'PATH': os.defpath},
                        max_bytes=limit, timeout=timeout, diagnostic_limit=diagnostic_limit)
                return target.read_bytes(), target.stat().st_mode & 0o777
            except ProbeError:
                self.assertFalse(target.exists())
                raise

    def test_abort_program_has_no_unvalidated_output_or_snapshot(self):
        import transport
        expected={'schema':'orgavela-controlled-abort/v1','aborted_after_table_locks':True,
                  'fresh_connection_write_locks':True,'maintenance_removed':True,'origin_recovered':True,'snapshot_created':False}
        import json
        binding=type('Fixture',(),{'argv':lambda self:['synthetic']})()
        def receive_fixture(argv,program,path,**kwargs):
            self.assertIn(b'ORGAVELA_CAPTURE_ABORT_AFTER_LOCK = true',program)
            Path(path).write_text(json.dumps(expected))
        with patch.object(transport,'live_program',return_value=b'<?php fixture'),patch.object(transport,'receive',side_effect=receive_fixture):
            self.assertEqual(transport.controlled_abort(binding,password='fixture',maintenance_approved=True),expected)
        expected['snapshot_created']=True
        with patch.object(transport,'live_program',return_value=b'<?php fixture'),patch.object(transport,'receive',side_effect=receive_fixture),self.assertRaises(ProbeError):
            transport.controlled_abort(binding,password='fixture',maintenance_approved=True)

    def test_publisher_refuses_source_credentials_before_network(self):
        env = dict(GITHUB_REPOSITORY='orgavela/Orgavela-Plugin', GITHUB_REF='refs/heads/main',
                   GITHUB_EVENT_NAME='workflow_dispatch', GITHUB_SHA='a'*40,
                   ORGAVELA_APPROVED_CODE_SHA='a'*40, ORGAVELA_CONFIRM_MAINTENANCE='orgavela.com',
                   ORGAVELA_CONFIRM_PUBLISH='orgavela/orgavela-backups',
                   BACKUP_GITHUB_TOKEN='synthetic-token', PGP05_SSH_PASSWORD='synthetic-source')
        with patch.dict(os.environ, env, clear=True), patch.object(sys, 'argv', ['publish_cli', '--ciphertext', '/synthetic']), patch.object(publish_cli, 'authorize', return_value={}), patch.object(publish_cli, 'verify_repository') as network:
            with self.assertRaises(ValueError):
                publish_cli.main()
            network.assert_not_called()

    def test_restore_entrypoint_rejects_synthetic_claim_before_docker(self):
        with patch.object(sys, 'argv', ['restore_cli', '--decrypted-package', '/synthetic', '--confirm-private-target', 'orgavela.com']), patch.object(restore_cli, 'validate_package', return_value={'mode':'synthetic'}), patch.object(restore_cli, 'restore_components') as docker:
            with self.assertRaises(ValueError):
                restore_cli.main()
            docker.assert_not_called()

    def test_runtime_network_guard_is_preserved_but_private_keys_refused(self):
        reject_mixed_credentials({'CODEX_EXEC_SERVER_PROXY_PRIVATE_IPS_VIA_UPSTREAM':'1'}, 'source')
        with self.assertRaises(ValueError):
            reject_mixed_credentials({'BACKUP_PRIVATE_KEY':'fixture'}, 'source')

    def test_binary_transfer_and_private_mode(self):
        data, mode = self.exercise('import sys;sys.stdin.buffer.read();sys.stdout.buffer.write(b"a\\x00b");sys.stderr.write("private")')
        self.assertEqual(data, b'a\0b')
        self.assertEqual(mode, 0o600)

    def test_timeout_overflow_stderr_nonzero_and_empty_remove_partial(self):
        for code, arguments in [
            ('import time;time.sleep(10)', {'timeout': 0.15}),
            ('print("x"*5000)', {}),
            ('import sys;sys.stderr.write("secret"*1000)', {}),
            ('print("partial");exit(7)', {}), ('pass', {}),
        ]:
            with self.subTest(code=code), self.assertRaises(ProbeError) as error:
                self.exercise(code, **arguments)
            self.assertNotIn('secret', str(error.exception))

    def test_existing_file_and_symlink_are_not_clobbered(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            original = root / 'original'
            original.write_bytes(b'keep')
            for target in [original, root / 'link']:
                if target != original:
                    target.symlink_to(original)
                with self.assertRaises(OSError):
                    receive([sys.executable, '-c', 'print("bad")'], b'input', target, env={})
                self.assertEqual(original.read_bytes(), b'keep')

    def test_whole_snapshot_validated_after_real_pipe(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            fixture = root / 'source'
            fixtures.snapshot(fixture)
            target = root / 'received'
            receive([sys.executable, '-c', 'import sys;sys.stdout.buffer.write(open(sys.argv[1],"rb").read())', str(fixture)],
                    b'fixture', target, env={}, max_bytes=1024*1024)
            components = root / 'components'
            components.mkdir(mode=0o700)
            self.assertEqual(accept_snapshot(target, components)['table_count'], 52)

    def test_explicit_maintenance_and_pinned_ssh_options(self):
        with tempfile.TemporaryDirectory() as folder:
            pin = Path(folder) / 'known_hosts'
            pin.write_text('synthetic pin')
            binding = Binding('example.invalid', 'fixture_user', '/synthetic/wordpress', pin)
            with self.assertRaises(ProbeError):
                live_program(binding)
            self.assertIn(b'ENTRYPOINT_ENABLED = true;', live_program(binding, maintenance_approved=True))
            self.assertIn('StrictHostKeyChecking=yes', binding.argv())
            self.assertIn('GlobalKnownHostsFile=/dev/null', binding.argv())
            for host in ['-oProxyCommand=bad', 'host;bad', 'host\nother']:
                with self.assertRaises(ProbeError):
                    Binding(host, binding.user, binding.root, pin).argv()

    def test_main_binding_denies_pr_wrong_sha_and_missing_confirmation(self):
        allowed = dict(GITHUB_REPOSITORY='orgavela/Orgavela-Plugin', GITHUB_REF='refs/heads/main',
                       GITHUB_EVENT_NAME='workflow_dispatch', GITHUB_SHA='a'*40,
                       ORGAVELA_APPROVED_CODE_SHA='a'*40, ORGAVELA_CONFIRM_MAINTENANCE='orgavela.com')
        # Plain environment values are not a signed workflow identity.
        with self.assertRaises(ProbeError):
            trusted_main(allowed)
        for key in allowed:
            with self.subTest(key=key), self.assertRaises(ProbeError):
                trusted_main({**allowed,key:'wrong'})

    def test_recovery_component_tamper_and_trailing_data_rejected(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            source = root / 'source'
            evidence = fixtures.snapshot(source)
            with tarfile.open(source) as archive:
                entries = {m.name: archive.extractfile(m).read() for m in archive}
            manifest = dict(schema='orgavela-backup-package/v1', backup_id='orgavela-synthetic-recovery',
                            mode='synthetic', scope=evidence['scope'], encryption={'type':'OpenPGP-public-key','public_key_fingerprint':PRIMARY_FPR,'encryption_subkey_fingerprint':SUBKEY_FPR,'private_key_in_producer':False},
                            components={name: {'bytes': len(data), 'sha256': hashlib.sha256(data).hexdigest()}
                                        for name, data in entries.items()})
            for corrupt in [False, 'hash', 'suffix']:
                entries['manifest.json'] = json.dumps(manifest).encode()
                package = root / 'clear.tar'
                package.write_bytes(fixtures.tar_bytes(entries.items()) + (b'bad' if corrupt == 'suffix' else b''))
                if corrupt == 'hash':
                    manifest['components']['database.sql.gz']['sha256'] = '0'*64
                    entries['manifest.json'] = json.dumps(manifest).encode()
                    package.write_bytes(fixtures.tar_bytes(entries.items()))
                work = root / ('work-' + str(corrupt))
                work.mkdir(mode=0o700)
                if corrupt:
                    with self.assertRaises(ProbeError):
                        validate_package(package, work)
                    self.assertFalse(list(work.iterdir()))
                else:
                    self.assertEqual(validate_package(package, work)['component_hashes_verified'], 3)
