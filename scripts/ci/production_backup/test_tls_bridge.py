import copy
import json
from pathlib import Path
from types import SimpleNamespace
import unittest
from unittest.mock import patch
import transport

class BridgeContract(unittest.TestCase):
    def binding(self):return SimpleNamespace(root='/tmp/fixture',user='fixture',argv=lambda:['not-executed'])
    def proof(self):
        return {'schema':'orgavela-exact-client-tls-negative/v1','database_login':False,'maintenance_created':False,'source_database_accessed':False,'snapshot_created':False,'own_processes_cleaned':True,'synthetic_loopback_only':True,'results':[{'kind':kind,'path':path,'sha256':sha,'version':'10.11.19','client_started':True,'plaintext_refused':True,'remote_authentication_bytes':0,'forwarded_output_bytes':0}for kind,path,sha in [('client','/usr/bin/mariadb','e375617e0bad12f0880e85a4f1125a68480bd7b9b4160d86e48d0ef4210c5a76'),('dump','/usr/bin/mariadb-dump','a61d09007f25c5df6bcbdf02ff34fe13bcd86426cff4bb40efc5d216cbd74dea')]]}
    def test_negative_entrypoint_has_no_capture_or_maintenance_execution(self):
        p=transport.client_tls_negative_program(self.binding())
        self.assertNotIn(Path(transport.__file__).with_name('remote_capture.php').read_bytes().removeprefix(b'<?php'),p)
        self.assertIn(b'const ORGAVELA_CAPTURE_ENTRYPOINT_ENABLED = false;',p)
        self.assertEqual(p.count(b'const ORGAVELA_TLS_NEGATIVE_ONLY = true;'),1)
    def test_refuse_wrong_client_leaked_data_skipped_client_or_forged_bool(self):
        def run(proof):
            def receive(argv,program,target,**kwargs):target.write_text(json.dumps(proof))
            with patch.object(transport,'receive',side_effect=receive):return transport.client_tls_negative(self.binding(),password='synthetic')
        self.assertEqual(run(self.proof()),self.proof())
        for change in ({'sha256':'0'*64},{'remote_authentication_bytes':1},{'forwarded_output_bytes':1},{'client_started':False},{'client_started':1}):
            p=copy.deepcopy(self.proof());p['results'][1].update(change)
            with self.assertRaises(ValueError):run(p)
