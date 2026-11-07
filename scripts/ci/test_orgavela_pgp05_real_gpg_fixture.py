"""Public-key-only OpenPGP integration fixture for PGP-05 asset contract.

No owner private key, no real WordPress data, and no network or GitHub upload.
"""
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

from orgavela_pgp05_asset_gate import FPR, SCHEMA, validate

ROOT = Path(__file__).resolve().parents[2]
PUBLIC_KEY = ROOT / '.github/backup/orgavela-backup-candidate-public.asc'


def gpg(*args, env, allow_no_private_decrypt=False):
    result = subprocess.run(['gpg', '--batch', '--no-tty', *args], env=env,
                            capture_output=True, text=True, timeout=20, check=False)
    if result.returncode and not allow_no_private_decrypt:
        raise AssertionError('GnuPG command failed on synthetic fixture')
    return result.stdout


class ActualPublicKeyFixture(unittest.TestCase):
    def test_public_only_gnupg_ciphertext_passes_synthetic_contract(self):
        if shutil.which('gpg') is None:
            self.skipTest('GnuPG unavailable; this separate interop test cannot run')
        if not PUBLIC_KEY.is_file():
            self.fail('OrgaVela committed PUBLIC key fixture absent')
        with tempfile.TemporaryDirectory() as temp:
            top = Path(temp)
            home = top / 'gnupg'
            home.mkdir(mode=0o700)
            workspace = top / 'ciphertext-only'
            workspace.mkdir(mode=0o700)
            env = {**os.environ, 'GNUPGHOME': str(home)}
            gpg('--import', str(PUBLIC_KEY), env=env)
            listing = gpg('--with-colons', '--fingerprint', '--list-keys', env=env)
            fingerprints = [line.split(':')[9] for line in listing.splitlines()
                            if line.startswith('fpr:')]
            self.assertEqual(fingerprints[0], FPR)
            self.assertNotIn('sec:', gpg('--with-colons', '--list-secret-keys', env=env))

            synthetic = top / 'synthetic.txt'
            synthetic.write_text('SYNTHETIC TEST ONLY - NO REAL ORGAVELA DATA\n')
            identifier = 'orgavela-synthetic-interop-001'
            archive = workspace / (identifier + '.tar.gpg')
            gpg('--yes', '--trust-model', 'always', '--recipient', FPR,
                '--output', str(archive), '--encrypt', str(synthetic), env=env)
            synthetic.unlink()
            # GnuPG may exit non-zero when it can inspect packets but has no private key.
            packet_text = gpg('--list-packets', str(archive), env=env,
                              allow_no_private_decrypt=True)
            self.assertIn(':pubkey enc packet:', packet_text)
            self.assertIn(':encrypted data packet:', packet_text)
            self.assertIn('keyid D9E58CC6484AF1DE', packet_text)
            sha = hashlib.sha256(archive.read_bytes()).hexdigest()
            (workspace / (archive.name + '.sha256')).write_text(
                sha + '  ' + archive.name + '\n', encoding='ascii')
            manifest = {'schema': SCHEMA, 'backup_id': identifier, 'synthetic': True,
                        'contains_production_data': False, 'archive_sha256': sha,
                        'archive_bytes': archive.stat().st_size,
                        'public_key_fingerprint': FPR,
                        'encrypted': True, 'source_components_encrypted': True}
            (workspace / 'manifest.json').write_text(json.dumps(manifest), encoding='utf-8')
            self.assertEqual(len(list(workspace.iterdir())), 3)
            self.assertEqual(validate(str(workspace))['sha256'], sha)
            self.assertNotIn('sec:', gpg('--with-colons', '--list-secret-keys', env=env))


if __name__ == '__main__':
    unittest.main()
