"""Offline regression of synthetic snapshot, not a live backup test."""
import contextlib
import gzip
import io
import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest import mock

import orgavela_pgp05_synthetic_snapshot as source


class SyntheticSourceTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.home = Path(self.tmp.name)
        self.root = self.home / 'fixture'
        (self.root/'wp-content/uploads').mkdir(parents=True)
        (self.root/'wp-content/uploads/synthetic.txt').write_bytes(source.SYNTHETIC_MARKER)

    def test_exact_fake_inventory(self):
        self.assertEqual(source.verify_stable_source(self.root)['path'],
                         'wp-content/uploads/synthetic.txt')

    def test_reject_wrong_synthetic_contents(self):
        (self.root/'wp-content/uploads/synthetic.txt').write_bytes(b'foo')
        with self.assertRaises(source.Refused): source.verify_stable_source(self.root)

    def test_reject_additional_wp_file(self):
        (self.root/'wp-content/uploads/data.json').write_text('secret')
        with self.assertRaises(source.Refused): source.verify_stable_source(self.root)

    def test_reject_extra_site_directory(self):
        (self.root/'another').mkdir()
        with self.assertRaises(source.Refused): source.verify_stable_source(self.root)

    def test_reject_source_symlink(self):
        file=self.root/'wp-content/uploads/synthetic.txt'
        file.unlink(); file.symlink_to(self.home/'out')
        with self.assertRaises(source.Refused): source.verify_stable_source(self.root)

    def test_reject_source_parent_symlink(self):
        uploads=self.root/'wp-content/uploads'
        uploads.rename(self.root/'wp-content/real')
        uploads.symlink_to(self.root/'wp-content/real')
        with self.assertRaises(source.Refused): source.verify_stable_source(self.root)

    def test_reject_hardlinked_file(self):
        f=self.root/'wp-content/uploads/synthetic.txt'
        (self.home/'extra-hardlink').hardlink_to(f)
        with self.assertRaises(source.Refused): source.verify_stable_source(self.root)

    def test_detect_change_between_passes(self):
        f=self.root/'wp-content/uploads/synthetic.txt'
        def change():
            previous=f.stat().st_mtime_ns
            os.utime(f, ns=(previous+5_000_000, previous+5_000_000))
        with self.assertRaisesRegex(source.Refused,'changed between snapshot passes'):
            source.verify_stable_source(self.root,between=change)

    def test_reject_secrets(self):
        with mock.patch.dict(os.environ, {'GH_TOKEN':'synthetic-fake-token'}):
            with self.assertRaises(source.Refused): source.no_secrets()

    def test_reject_nonabsolute_output(self):
        with self.assertRaises(source.Refused): source.produce(Path('bad'))

    def test_reject_existing_output(self):
        (self.home/'out').mkdir()
        with self.assertRaises(source.Refused): source.produce(self.home/'out')

    def test_reject_world_writable_parent(self):
        parent=self.home/'world'
        parent.mkdir(); parent.chmod(0o777)
        with self.assertRaises(source.Refused): source.produce(parent/'ciphertext')

    def test_reject_parent_symlink(self):
        alias=self.home/'alias'; alias.symlink_to(self.home, target_is_directory=True)
        with self.assertRaises(source.Refused): source.produce(alias/'ciphertext')

    def test_no_output_on_encryption_failure(self):
        out=self.home/'ciphertext'
        with mock.patch.object(source,'encrypt_public_only',side_effect=source.Refused('fake fail')):
            with self.assertRaises(source.Refused): source.produce(out)
        self.assertFalse(out.exists())
        self.assertFalse(any(p.name.startswith('.pgp05-private-') for p in self.home.iterdir()))

    def test_no_secrets_rejects_db_password(self):
        with mock.patch.dict(os.environ, {'IONOS_DB_PASSWORD':'synthetic-only-sentinel'}):
            with self.assertRaises(source.Refused): source.no_secrets()

    def test_bad_public_key_fails_closed(self):
        wrong=self.home/'bad-public.asc'
        wrong.write_text('NOT A PUBLIC KEY')
        with mock.patch.object(source, 'public_key_path', return_value=wrong):
            with self.assertRaises(source.Refused): source.produce(self.home/'ciphertext')
        self.assertFalse((self.home/'ciphertext').exists())

    def test_cli_default_off(self):
        with contextlib.redirect_stderr(io.StringIO()):
            self.assertEqual(source.main(['--output',str(self.home/'out')]),1)
        self.assertFalse((self.home/'out').exists())

    def test_public_only_real_gpg_ciphertext_handoff(self):
        output=self.home/'ciphertext-only'
        result=source.produce(output)
        self.assertEqual(result['files'],3)
        self.assertEqual({p.name for p in output.iterdir()},
                         {source.BACKUP_ID+'.tar.gpg',
                          source.BACKUP_ID+'.tar.gpg.sha256','manifest.json'})
        self.assertFalse(any('sql' in p.name or 'wordpress' in p.name for p in output.iterdir()))
        for p in output.iterdir(): self.assertEqual(p.stat().st_mode&0o777,0o600)
        self.assertEqual(output.stat().st_mode&0o777,0o700)
        self.assertFalse(any(p.name.startswith('.pgp05-private-') for p in self.home.iterdir()))
        manifest=json.loads((output/'manifest.json').read_text())
        self.assertIs(manifest['contains_production_data'],False)
        self.assertIs(manifest['synthetic'],True)
        from orgavela_pgp05_asset_gate import validate
        validate(str(output))
        gpg=subprocess.run(['gpg','--batch','--list-packets',str(output/(source.BACKUP_ID+'.tar.gpg'))],
                           capture_output=True,text=True,check=False)
        self.assertIn(':pubkey enc packet:',gpg.stdout)
        self.assertIn('keyid '+source.SUBKEY_ID,gpg.stdout)

    def test_independent_writer_process_accepts_ciphertext_only(self):
        output=self.home/'ciphertext-only-writer'
        source.produce(output)
        writer=Path(__file__).parent/'orgavela_pgp05_asset_gate.py'
        proc=subprocess.run([os.sys.executable,str(writer),'--workspace',str(output)],
                            capture_output=True,text=True,timeout=15,check=False)
        self.assertEqual(proc.returncode,0,proc.stderr)
        self.assertIn('PGP05_ASSET_CONTRACT_SYNTHETIC_ONLY=PASS',proc.stdout)

    def test_independent_writer_process_rejects_modified_ciphertext(self):
        output=self.home/'ciphertext-only-tampered'
        source.produce(output)
        cipher=output/(source.BACKUP_ID+'.tar.gpg')
        cipher.write_bytes(cipher.read_bytes()+b'malicious')
        writer=Path(__file__).parent/'orgavela_pgp05_asset_gate.py'
        proc=subprocess.run([os.sys.executable,str(writer),'--workspace',str(output)],
                            capture_output=True,text=True,timeout=15,check=False)
        self.assertEqual(proc.returncode,1)
        self.assertIn('PGP05_ASSET_CONTRACT=REFUSED',proc.stderr)

    def test_external_manifest_leaks_no_internal_synthetic_sql(self):
        output=self.home/'ciphertext-no-internal-manifest'
        source.produce(output)
        public=(output/'manifest.json').read_bytes()
        self.assertNotIn(source.SYNTHETIC_SQL,public)
        self.assertNotIn(source.SYNTHETIC_MARKER,public)
        self.assertNotIn(b'wp-content',public)
        self.assertNotIn(b'ionos',public.lower())

    def test_writer_public_only_openpgp_packet_passes_separate_process(self):
        output=self.home/'encrypted-writer'
        source.produce(output)
        writer=Path(__file__).parent/'orgavela_pgp05_offline_writer.py'
        proc=subprocess.run([os.sys.executable,str(writer),'--synthetic-only','--workspace',str(output)],
                            capture_output=True,text=True,timeout=15,check=False)
        self.assertEqual(proc.returncode,0,proc.stderr)
        self.assertIn('PGP05_WRITER_PUBLIC_ONLY_PREFLIGHT=PASS',proc.stdout)

    def test_writer_rejects_hash_consistent_but_fake_encryption(self):
        output=self.home/'forged-ciphertext'
        source.produce(output)
        name=source.BACKUP_ID+'.tar.gpg'
        fake=b'not actually OpenPGP ciphertext but hashes match'
        import hashlib
        sha=hashlib.sha256(fake).hexdigest()
        (output/name).write_bytes(fake)
        (output/(name+'.sha256')).write_text(sha+'  '+name+'\n')
        manifest_file=output/'manifest.json'
        metadata=json.loads(manifest_file.read_text())
        metadata['archive_sha256']=sha
        metadata['archive_bytes']=len(fake)
        manifest_file.write_text(json.dumps(metadata)+'\n')
        from orgavela_pgp05_asset_gate import validate
        validate(str(output))  # SHA-only validator accepts the forged blob by design.
        writer=Path(__file__).parent/'orgavela_pgp05_offline_writer.py'
        proc=subprocess.run([os.sys.executable,str(writer),'--synthetic-only','--workspace',str(output)],
                            capture_output=True,text=True,timeout=15,check=False)
        self.assertEqual(proc.returncode,1)
        self.assertIn('PGP05_WRITER_PREFLIGHT_REFUSED',proc.stderr)

    def test_writer_default_off(self):
        output=self.home/'writer-default-off'
        source.produce(output)
        writer=Path(__file__).parent/'orgavela_pgp05_offline_writer.py'
        proc=subprocess.run([os.sys.executable,str(writer),'--workspace',str(output)],
                            capture_output=True,text=True,timeout=15,check=False)
        self.assertEqual(proc.returncode,1)
        self.assertIn('PGP05_WRITER_PREFLIGHT_REFUSED',proc.stderr)

    def test_gzip_components_synthetic(self):
        self.assertEqual(gzip.decompress(gzip.compress(source.SYNTHETIC_SQL,mtime=0)),source.SYNTHETIC_SQL)
        self.assertGreater(len(source.synthetic_files_snapshot()),50)

    def test_production_mode_not_exposed(self):
        with contextlib.redirect_stderr(io.StringIO()):
            with self.assertRaises(SystemExit):
                source.main(['--production','--output',str(self.home/'out')])


if __name__=='__main__': unittest.main()
