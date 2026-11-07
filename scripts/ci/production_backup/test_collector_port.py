"""Synthetic port security/integration tests. All publication/HTTP is mocked."""
import contextlib
import gzip
import hashlib
import io
import json
import os
from pathlib import Path
import subprocess
import sys
import tarfile
import tempfile
import unittest
from unittest.mock import Mock,patch
import urllib.error
import collector
import crypto
import fixtures
import github_api
import offline_integration
import process
import writer

class CollectorTests(unittest.TestCase):
    def fixture(self,root,mutate=None,extra=()):
        stream=root/'source.tar';fixtures.snapshot(stream,mutate,extra)
        work=root/'private';work.mkdir(mode=0o700)
        return stream,work

    def test_full_private_fixture_and_no_plaintext_handoff(self):
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp);source,work=self.fixture(root)
            self.assertEqual(collector.accept_snapshot(source,work)['table_count'],52)
            handoff=root/'handoff';handoff.mkdir(mode=0o700)
            manifest=crypto.package(work,handoff,'orgavela-synthetic-port-fixture')
            bid,entries=writer.assets(handoff,synthetic=True)
            self.assertEqual(len(entries),3);self.assertFalse(manifest['contains_production_data'])
            self.assertNotIn(fixtures.MARKER,(handoff/(bid+'.tar.gpg')).read_bytes())
            self.assertEqual({p.name for p in handoff.iterdir()},set(entries))
            self.assertNotIn('database',json.dumps(manifest))
            with self.assertRaises(github_api.WriterError): writer.assets(handoff)

    def test_each_consistency_refusal_cleans_partial_plaintext(self):
        mutations=[lambda e:e.update(table_count=95),lambda e:e.update(table_count=True),
            lambda e:e['components']['database'].update(sha256='0'*64),
            lambda e:e['scope'].update(database=1),lambda e:e.update(private='untrusted'),
            lambda e:e['quiescence'].update(maintenance_seconds=float('nan'))]
        for flag in ('wordpress_maintenance','all_table_read_locks','stable_file_hashes','archive_matches_inventory',
                     'maintenance_removed','maintenance_origin_verified','source_origin_recovered'):
            mutations.append(lambda e,flag=flag:e['quiescence'].update({flag:False}))
        for mutate in mutations:
            with tempfile.TemporaryDirectory() as tmp:
                source,work=self.fixture(Path(tmp),mutate)
                with self.assertRaises(ValueError): collector.accept_snapshot(source,work)
                self.assertEqual(list(work.iterdir()),[])

    def test_existing_files_are_never_removed_by_refusal(self):
        with tempfile.TemporaryDirectory() as tmp:
            source,work=self.fixture(Path(tmp));saved=work/'database.sql.gz';saved.write_bytes(b'existing synthetic')
            with self.assertRaises(ValueError): collector.accept_snapshot(source,work)
            self.assertEqual(saved.read_bytes(),b'existing synthetic')

    def test_uploads_type_ancestor_conflicts_and_actual_member_bound(self):
        for mode in ('uploads-file','ancestor-file','too-many'):
            with tempfile.TemporaryDirectory() as tmp:
                source,work=self.fixture(Path(tmp))
                with tarfile.open(source) as outer: parts={m.name:outer.extractfile(m).read() for m in outer}
                with tarfile.open(fileobj=io.BytesIO(parts['wordpress-files.tar.gz']),mode='r:gz') as inner:
                    entries=[(m.name,None if m.isdir() else inner.extractfile(m).read()) for m in inner]
                if mode=='uploads-file': entries=[(n,b'wrong type' if n=='wp-content/uploads' else b) for n,b in entries]
                elif mode=='ancestor-file': entries.append(('wp-content',b'conflict'))
                else: entries.extend((f'entry-{i}',b'') for i in range(50000))
                parts['wordpress-files.tar.gz']=gzip.compress(fixtures.tar_bytes(entries))
                evidence=json.loads(parts['capture-evidence.json']);evidence['components']['wordpress']['sha256']=hashlib.sha256(parts['wordpress-files.tar.gz']).hexdigest()
                parts['capture-evidence.json']=json.dumps(evidence).encode();source.write_bytes(fixtures.tar_bytes(parts.items()))
                with self.assertRaises(ValueError):collector.accept_snapshot(source,work)
                self.assertEqual(list(work.iterdir()),[])

    def test_wordpress_traversal_links_duplicate_or_maintenance_refused(self):
        for name in ('../escape','/escape','wp-config.php','.maintenance','bad\\path','bad\npath'):
            with tempfile.TemporaryDirectory() as tmp:
                source,work=self.fixture(Path(tmp),extra=[(name,b'synthetic')])
                with self.assertRaises(ValueError): collector.accept_snapshot(source,work)
                self.assertEqual(list(work.iterdir()),[])

    def test_outer_traversal_duplicate_link_and_suffix_refused(self):
        for mode in ('traversal','duplicate','link','suffix','duplicate-json'):
            with tempfile.TemporaryDirectory() as tmp:
                source,work=self.fixture(Path(tmp))
                if mode=='suffix': source.write_bytes(source.read_bytes()+b'not-zero'+b'\0'*504)
                elif mode=='link':
                    with tarfile.open(source,'w') as tar:
                        info=tarfile.TarInfo('database.sql.gz');info.type=tarfile.SYMTYPE;info.linkname='/synthetic';tar.addfile(info)
                elif mode=='duplicate-json':
                    with tarfile.open(source) as tar: data=[(m.name,tar.extractfile(m).read()) for m in tar]
                    data=[(n,b'{"schema":"wrong","schema":"wrong"}') if n=='capture-evidence.json' else (n,b) for n,b in data]
                    source.write_bytes(fixtures.tar_bytes(data))
                else: source.write_bytes(fixtures.tar_bytes([('../escape' if mode=='traversal' else 'database.sql.gz',b'a'),('database.sql.gz',b'b')]))
                with self.assertRaises(ValueError): collector.accept_snapshot(source,work)
                self.assertEqual(list(work.iterdir()),[])

    def test_remote_candidate_disabled_and_only_own_bindings(self):
        source=collector.build_remote_candidate('/synthetic/wordpress','fixture_user').decode()
        self.assertIn('const ORGAVELA_CAPTURE_ENTRYPOINT_ENABLED = false;',source)
        self.assertIn('https://orgavela.com/',source)
        self.assertNotIn('dreamloud.de',source);self.assertNotIn('transient_cache_dir',source)
        for root,user in [('/a/b;id','fixture'),('/a/../b','fixture'),('/a/b','user;id')]:
            with self.assertRaises(ValueError):collector.build_remote_candidate(root,user)
        for module in (collector,writer):
            with patch.dict(os.environ,{'ORGAVELA_PRODUCTION_BACKUP_APPROVED':'YES'},clear=True),contextlib.redirect_stdout(io.StringIO()),patch('urllib.request.OpenerDirector.open',side_effect=AssertionError('network')):
                self.assertEqual(module.main(),78)

    def test_full_local_public_only_integration(self):
        self.assertEqual(offline_integration.exercise()['remote_calls'],0)

class BoundaryTests(unittest.TestCase):
    def test_source_writer_recovery_credential_separation(self):
        for role,fields in [('source',['GH_TOKEN','BACKUP_GITHUB_TOKEN']),('writer',['PGP05_SSH_PASSWORD','PGP05_WP_ROOT','BACKUP_SSH_HOST'])]:
            for name in fields:
                with self.assertRaises(ValueError):github_api.reject_mixed_credentials({name:'synthetic'},role)
        for role in ('source','writer'):
            with self.assertRaises(ValueError):github_api.reject_mixed_credentials({'BACKUP_RECOVERY_PRIVATE_KEY':'synthetic'},role)
            for field in ('DB_PASSWORD','MYSQL_PWD','WP_DB_PASSWORD','SSHPASS'):
                with self.assertRaises(ValueError):github_api.reject_mixed_credentials({field:'synthetic'},role)
        with self.assertRaises(ValueError):github_api.installation_token({'GH_TOKEN':'synthetic'})

    def test_backup_target_is_migrated_private_repo_only(self):
        # Failing closed is mandatory: the original project path must never be used.
        self.assertEqual(github_api.REPOSITORY,'orgavela/orgavela-backups')
        self.assertEqual(writer.REPOSITORY,github_api.REPOSITORY)
        for forbidden in ('/repos/janjrre/orgavela-backups',
                          '/repos/janjrre/orgavela-backups/releases',
                          '/repos/orgavela/Orgavela-Plugin/releases'):
            with patch('urllib.request.build_opener') as network,self.assertRaises(github_api.WriterError):
                github_api.request_json(forbidden,'synthetic')
            network.assert_not_called()

    def test_api_path_cannot_escape_fixed_repository(self):
        for suffix in ('/../../other/repo','/%2e%2e/escape','/./releases','//releases','/releases?target=foreign','/releases#fragment'):
            with patch('urllib.request.build_opener') as network,self.assertRaises(ValueError):
                github_api.request_json('/repos/'+github_api.REPOSITORY+suffix,'synthetic')
            network.assert_not_called()

    def test_process_bounds_stderr_stdout_timeout_and_no_echo(self):
        for code in ['print("x"*20000)','import sys;sys.stderr.write("x"*20000)','import time;time.sleep(2)']:
            with self.assertRaises(process.ProcessError):process.run([sys.executable,'-c',code],env={'PATH':'/usr/bin:/bin'},timeout=.1)

class PublisherTests(unittest.TestCase):
    def test_orphan_tag_and_http_error_abort_before_creation(self):
        for failure in ('existing','403','500'):
            def api(path,token,**kw):
                if '/releases/tags/' in path: raise github_api.WriterError('absent',404)
                if '/git/ref/tags/' in path:
                    if failure=='existing':return {'ref':'existing'}
                    raise github_api.WriterError('unavailable',int(failure))
                self.fail('must not create')
            with patch.object(writer,'request_json',side_effect=api),patch.object(writer,'upload') as upload,self.assertRaises(ValueError):
                writer.publish('synthetic','orgavela-wordpress-fixture',{})
            upload.assert_not_called()

    def test_publish_order_and_failed_draft_readback_never_publishes(self):
        for fail in (False,True):
            calls=[];entries={'cipher':{'size':3,'sha256':'fake'}}
            def api(path,token,**kw):
                method=kw.get('method','GET');calls.append(method)
                if '/tags/' in path:raise github_api.WriterError('absent',404)
                if method=='POST':return {'id':123,'draft':True,'tag_name':'orgavela-wordpress-fixture'}
                return {}
            def verify(*args,**kw):
                calls.append('VERIFY_DRAFT' if kw['draft'] else 'VERIFY_FINAL')
                if fail and kw['draft']:raise github_api.WriterError('mismatch')
            with patch.object(writer,'request_json',side_effect=api),patch.object(writer,'verify_release',side_effect=verify),patch.object(writer,'upload',return_value={'name':'cipher','size':3,'state':'uploaded'}),contextlib.redirect_stdout(io.StringIO()):
                if fail:
                    with self.assertRaises(ValueError):writer.publish('synthetic','orgavela-wordpress-fixture',entries)
                    self.assertNotIn('PATCH',calls)
                else:
                    self.assertTrue(writer.publish('synthetic','orgavela-wordpress-fixture',entries)['immutable_verified'])
                    self.assertLess(calls.index('VERIFY_DRAFT'),calls.index('PATCH'))
                    self.assertLess(calls.index('PATCH'),calls.index('VERIFY_FINAL'))

    def test_remote_digest_mutability_set_and_byte_mismatch_fail(self):
        expected={'cipher':{'size':3,'sha256':hashlib.sha256(b'abc').hexdigest()}}
        base={'id':123,'tag_name':'fixture','draft':False,'immutable':True,'assets':[{
            'id':456,'name':'cipher','state':'uploaded','size':3,'digest':'sha256:'+expected['cipher']['sha256']}]}
        import copy
        cases=[]
        for key,value in [('immutable',False),('draft',True),('assets',[])]:
            item=copy.deepcopy(base);item[key]=value;cases.append(item)
        for key,value in [('digest',None),('size',4),('state','new')]:
            item=copy.deepcopy(base);item['assets'][0][key]=value;cases.append(item)
        for item in cases:
            with patch.object(writer,'request_json',return_value=item),self.assertRaises(ValueError):writer.verify_release('synthetic',123,'fixture',expected,draft=False)
        fake=Mock();fake.open.return_value=io.BytesIO(b'abd')
        with patch.object(writer,'opener',return_value=fake),self.assertRaises(ValueError):writer.readback_asset('synthetic',456,expected['cipher'])

    def test_redirect_never_forwards_token_and_rejects_unsafe_target(self):
        for url in ['http://objects.githubusercontent.com/x','https://evil.invalid/x','https://release-assets.githubusercontent.com:444/x','https://user@objects.githubusercontent.com/x']:
            fake=Mock();fake.open.side_effect=urllib.error.HTTPError('mock',302,'redirect',{'Location':url},None)
            with patch.object(writer,'opener',return_value=fake),self.assertRaises(ValueError):writer.readback_asset('synthetic',123,{'size':3,'sha256':'fake'})
            self.assertEqual(fake.open.call_count,1)
        fake=Mock();fake.open.side_effect=[urllib.error.HTTPError('mock',302,'redirect',{'Location':'https://release-assets.githubusercontent.com/x'},None),io.BytesIO(b'abc')]
        with patch.object(writer,'opener',return_value=fake):writer.readback_asset('synthetic',123,{'size':3,'sha256':hashlib.sha256(b'abc').hexdigest()})
        self.assertIsNone(fake.open.call_args_list[1].args[0].get_header('Authorization'))

    def test_wrong_packet_recipient_even_with_rehashed_manifest_refused(self):
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp);source=root/'stream';fixtures.snapshot(source);plain=root/'plain';plain.mkdir(mode=0o700);collector.accept_snapshot(source,plain)
            out=root/'out';out.mkdir(mode=0o700);crypto.package(plain,out,'orgavela-synthetic-packets')
            with patch.object(writer,'SUBKEY_FPR','0'*40),self.assertRaises(ValueError):writer.assets(out,synthetic=True)
            (out/'unexpected-plaintext').write_bytes(b'synthetic')
            with self.assertRaises(ValueError):writer.assets(out,synthetic=True)

if __name__=='__main__':unittest.main()
