"""Signed controller and independent checkout/ancestry denial regressions."""
import base64
import json
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch
from cryptography.hazmat.primitives.asymmetric import rsa,padding
from cryptography.hazmat.primitives import hashes
import execution_identity as gate
from collector import ProbeError


def b64(value):
    return base64.urlsafe_b64encode(value).rstrip(b'=').decode()


class IdentityTests(unittest.TestCase):
    def setUp(self):
        self.claims=dict(repository=gate.REPOSITORY,repository_visibility='private',repository_id=gate.REPOSITORY_ID,repository_owner_id=gate.OWNER_ID,ref='refs/heads/main',
            event_name='workflow_dispatch',workflow_ref=gate.WORKFLOW,workflow_sha='a'*40,sha='a'*40,
            sub=gate.SUBJECT_PREFIX+':environment:orgavela-backup-source',aud='orgavela-o03-source',
            run_id='123',run_attempt='1',iss=gate.ISSUER,iat=1000,nbf=1000,exp=1300)

    def test_signed_jwt_signature_algorithm_audience_and_expiry(self):
        key=rsa.generate_private_key(public_exponent=65537,key_size=2048)
        public=key.public_key().public_numbers()
        jwks={'keys':[{'kid':'fixture','kty':'RSA','n':b64(public.n.to_bytes(256,'big')),'e':b64(public.e.to_bytes(3,'big'))}]}
        header=b64(json.dumps({'typ':'JWT','alg':'RS256','kid':'fixture'}).encode())
        payload=b64(json.dumps(self.claims).encode());data=header+'.'+payload
        token=data+'.'+b64(key.sign(data.encode(),padding.PKCS1v15(),hashes.SHA256()))
        self.assertEqual(gate.verify_jwt(token,jwks,'orgavela-o03-source',now=1100)['sha'],'a'*40)
        for audience,now in [('wrong',1100),('orgavela-o03-source',1400),('orgavela-o03-source',900)]:
            with self.assertRaises(ProbeError):gate.verify_jwt(token,jwks,audience,now=now)
        with self.assertRaises(ProbeError):gate.verify_jwt(token[:-5]+'abcde',jwks,'orgavela-o03-source',now=1100)

    def test_foreign_controller_refs_workflows_and_cross_role_denied(self):
        self.assertEqual(gate.controller_claims(self.claims,'source'),'a'*40)
        for name in ('repository','repository_id','repository_owner_id','repository_visibility','ref','event_name','workflow_ref','workflow_sha','sha','sub','run_id'):
            with self.subTest(name=name),self.assertRaises(ProbeError):gate.controller_claims({**self.claims,name:'wrong'},'source')
        with self.assertRaises(ProbeError):gate.controller_claims(self.claims,'writer')

    def test_engine_requires_approved_origin_clean_sha_and_main_ancestry(self):
        expected='b'*40
        for values in [[f'https://github.com/{gate.ENGINE_REPOSITORY}',expected,''],
                       ['https://github.com/foreign/repo',expected,''],
                       [f'https://github.com/{gate.ENGINE_REPOSITORY}','c'*40,''],
                       [f'https://github.com/{gate.ENGINE_REPOSITORY}',expected,' M collector.py']]:
            with patch.object(gate,'git',side_effect=values):
                if values[0].endswith(gate.ENGINE_REPOSITORY) and values[1]==expected and not values[2]:gate.checkout('/fixture',gate.ENGINE_REPOSITORY,expected)
                else:
                    with self.assertRaises(ProbeError):gate.checkout('/fixture',gate.ENGINE_REPOSITORY,expected)
        good={'status':'ahead','base_commit':{'sha':expected},'merge_base_commit':{'sha':expected}}
        gate.integrated_engine(expected,good)
        for bad in [{**good,'status':'diverged'},{**good,'merge_base_commit':{'sha':'c'*40}}]:
            with self.assertRaises(ProbeError):gate.integrated_engine(expected,bad)

    def test_oidc_token_is_not_sent_to_untrusted_endpoint(self):
        for endpoint in ['http://token.actions.githubusercontent.com','https://evil.invalid/token','https://actions.githubusercontent.com.evil.invalid/token']:
            with patch.object(gate,'fetch_json') as network,self.assertRaises(ProbeError):
                gate.authorize({'ACTIONS_ID_TOKEN_REQUEST_URL':endpoint,'ACTIONS_ID_TOKEN_REQUEST_TOKEN':'fixture'},'source')
            network.assert_not_called()


class RecoveryBoundaryTests(unittest.TestCase):
    def test_unpinned_core_refused_before_extraction(self):
        from trusted_core import verify_core
        for version in ('6.9','7.1.4','unapproved'):
            with self.assertRaises(ValueError):verify_core(b'not the approved archive',version)

    def test_snapshot_php_is_read_as_inert_data(self):
        import gzip
        from fixtures import tar_bytes
        from isolated_restore import inspect_snapshot_files
        with tempfile.TemporaryDirectory() as folder:
            marker=Path(folder)/'executed'
            code=('<?php file_put_contents('+json.dumps(str(marker))+',"executed");').encode()
            archive=Path(folder)/'snapshot.tar.gz'
            archive.write_bytes(gzip.compress(tar_bytes([('wp-content/db.php',code),('wp-content/object-cache.php',code),('wp-load.php',code)])))
            result=inspect_snapshot_files(archive)
            self.assertEqual(result['files'],3)
            self.assertFalse(marker.exists())
