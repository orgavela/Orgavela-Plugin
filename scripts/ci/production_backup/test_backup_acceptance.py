"""Real crypto/file reconstruction, boundary denials; synthetic bytes only."""
import copy
import gzip
import hashlib
import io
import json
import os
from pathlib import Path
import tempfile
import time
import unittest
from unittest.mock import patch
import warnings

import collector
import crypto
import fixtures
import recovery
import restore_recipient
import transport
import writer
from github_api import reject_mixed_credentials
from isolated_restore import inspect_snapshot_files, restore_snapshot_files


class BackupAcceptance(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        import pgpy
        from pgpy.constants import PubKeyAlgorithm,KeyFlags,HashAlgorithm,SymmetricKeyAlgorithm
        cls.key=pgpy.PGPKey.new(PubKeyAlgorithm.RSAEncryptOrSign,2048)
        cls.key.add_uid(pgpy.PGPUID.new('synthetic disposable receiver'),usage={KeyFlags.Certify,KeyFlags.Sign},hashes=[HashAlgorithm.SHA256],ciphers=[SymmetricKeyAlgorithm.AES256])
        sub=pgpy.PGPKey.new(PubKeyAlgorithm.RSAEncryptOrSign,2048)
        cls.key.add_subkey(sub,usage={KeyFlags.EncryptStorage,KeyFlags.EncryptCommunications})
        cls.sub=str(sub.fingerprint)

    def prepare(self, root):
        public=root/'public';public.mkdir(mode=0o700)
        key=str(self.key.pubkey).encode();(public/'recipient.asc').write_bytes(key)
        record={'schema':'orgavela-ephemeral-restore-recipient/v1','run':'123','attempt':'1','controller_sha':'a'*40,'primary':str(self.key.fingerprint),'subkey':self.sub,'expires_at':int(time.time())+1100,'public_sha256':hashlib.sha256(key).hexdigest()}
        (public/'recipient.json').write_text(json.dumps(record))
        return public,record

    def test_same_published_cipher_can_be_decrypted_by_temporary_receiver(self):
        import pgpy
        with tempfile.TemporaryDirectory() as folder,patch.dict(os.environ,{'GITHUB_RUN_ID':'123','GITHUB_RUN_ATTEMPT':'1','GITHUB_SHA':'a'*40}):
            root=Path(folder);public,record=self.prepare(root)
            source=root/'source.tar';fixtures.snapshot(source)
            components=root/'components';components.mkdir(mode=0o700);collector.accept_snapshot(source,components)
            out=root/'cipher';out.mkdir(mode=0o700)
            manifest=crypto.package(components,out,'orgavela-synthetic-acceptance',restore_public=public,restore_record=record)
            self.assertEqual(manifest['restore_recipient'],record)
            self.assertTrue(crypto.inspect_cipher(out/'orgavela-synthetic-acceptance.tar.gpg',restore_subkey=self.sub))
            with self.assertRaises(ValueError):crypto.inspect_cipher(out/'orgavela-synthetic-acceptance.tar.gpg')
            with warnings.catch_warnings():
                warnings.simplefilter('ignore')
                clear=bytes(self.key.decrypt(pgpy.PGPMessage.from_blob((out/'orgavela-synthetic-acceptance.tar.gpg').read_bytes())).message)
            plain=root/'decrypted.tar';plain.write_bytes(clear)
            restored=root/'restored';restored.mkdir(mode=0o700)
            checked=recovery.validate_package(plain,restored,restore_record=record)
            self.assertEqual(checked['backup_id'],'orgavela-synthetic-acceptance')
            for name in collector.NAMES:self.assertEqual((components/name).read_bytes(),(restored/name).read_bytes())
            self.assertEqual(len(writer.assets(out,synthetic=True,restore_record=record)[1]),3)
            with self.assertRaises(ValueError):writer.assets(out,synthetic=True)

    def test_foreign_expired_mixed_private_or_tampered_public_recipient_refused(self):
        with tempfile.TemporaryDirectory() as folder:
            root=Path(folder);public,record=self.prepare(root)
            env={'GITHUB_RUN_ID':'123','GITHUB_RUN_ATTEMPT':'1','GITHUB_SHA':'a'*40}
            restore_recipient.load(public,env=env,require_live=True)
            for field,value in [('run','124'),('attempt','2'),('controller_sha','b'*40),('expires_at',int(time.time())-1),('public_sha256','0'*64)]:
                bad={**record,field:value};(public/'recipient.json').write_text(json.dumps(bad))
                with self.subTest(field=field),self.assertRaises(ValueError):restore_recipient.load(public,env=env,require_live=True)
            (public/'recipient.json').write_text(json.dumps(record))
            (public/'recipient.asc').write_bytes(b'-----BEGIN PGP PRIVATE KEY BLOCK-----')
            with self.assertRaises(ValueError):restore_recipient.load(public,env=env,require_live=True)

    def test_restore_role_cannot_receive_source_writer_or_private_key_material(self):
        reject_mixed_credentials({'RESTORE_READ_TOKEN':'synthetic'},'restore')
        for key in ('PGP05_SSH_PASSWORD','BACKUP_GITHUB_TOKEN','GITHUB_TOKEN','OWNER_PRIVATE_KEY','DB_PASSWORD'):
            with self.subTest(key=key),self.assertRaises(ValueError):reject_mixed_credentials({key:'synthetic'},'restore')

    def test_download_hash_failure_removes_only_new_partial_target(self):
        class Response(io.BytesIO):pass
        class Opener:
            def open(self,*args,**kwargs):return Response(b'actual')
        with tempfile.TemporaryDirectory() as folder,patch.object(writer,'opener',return_value=Opener()):
            target=Path(folder)/'cipher'
            with self.assertRaises(ValueError):writer.readback_asset('fixture',1,{'size':6,'sha256':'0'*64},destination=target)
            self.assertFalse(target.exists())
            writer.readback_asset('fixture',1,{'size':6,'sha256':hashlib.sha256(b'actual').hexdigest()},destination=target)
            self.assertEqual(target.read_bytes(),b'actual');self.assertEqual(target.stat().st_mode & 0o777,0o600)
            with self.assertRaises(FileExistsError):writer.readback_asset('fixture',1,{'size':6,'sha256':'0'*64},destination=target)
            self.assertEqual(target.read_bytes(),b'actual')

    def test_full_file_tree_restored_as_data_without_php_execution(self):
        with tempfile.TemporaryDirectory() as folder:
            root=Path(folder);archive=root/'files.tar.gz'
            archive.write_bytes(gzip.compress(fixtures.tar_bytes([('wp-load.php',b'<?php throw new Exception("never execute");'),('wp-content/uploads/data',b'\x00snapshot')])) )
            target=root/'data';target.mkdir(mode=0o700)
            self.assertEqual(restore_snapshot_files(archive,target),inspect_snapshot_files(archive))
            self.assertEqual((target/'wp-content/uploads/data').read_bytes(),b'\x00snapshot')
            self.assertEqual((target/'wp-load.php').stat().st_mode & 0o777,0o600)

    def test_resource_probe_requires_tls_and_actual_completed_limits(self):
        binding=type('Fixture',(),{'argv':lambda self:['fixture']})()
        valid={'schema':'orgavela-database-dump-probe/v2','tls_verified':True,'server_certificate_verified':True,'table_count':52,'row_data_logged':False,'completed':True,'maintenance_created':False,'table_locks_used':False,'data_discarded':True,'backup_saved':False,'compressed_bytes':100,'seconds':1}
        for field in (None,'tls_verified','server_certificate_verified','completed','compressed_bytes','seconds'):
            evidence=copy.deepcopy(valid)
            if field:evidence[field]=False if field not in ('compressed_bytes','seconds') else 1000000000
            def receive(argv,program,target,**kwargs):Path(target).write_text(json.dumps(evidence))
            with patch.object(transport,'live_program',return_value=b'<?php fixture'),patch.object(transport,'receive',side_effect=receive):
                if field:
                    with self.assertRaises(ValueError):transport.resource_probe(binding,'database',password='fixture',maintenance_approved=True)
                else:self.assertEqual(transport.resource_probe(binding,'database',password='fixture',maintenance_approved=True),valid)


if __name__=='__main__':unittest.main()
