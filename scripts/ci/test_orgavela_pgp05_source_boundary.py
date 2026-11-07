"""No network or real source: negative tests for a simulated write-fenced snapshot."""
import copy
import hashlib
import json
import tempfile
from pathlib import Path
import unittest

import orgavela_pgp05_source_boundary as s


class SourceBoundaryTests(unittest.TestCase):
    def setUp(self):
        self.v = s.fixture()

    def reject(self, obj=None):
        with self.assertRaises(s.Refused):
            s.validate(self.v if obj is None else obj)

    def test_artificial_fixture_passes_simulation_only(self):
        result = s.validate(self.v)
        self.assertEqual(result['kind'], 'SIMULATION_ONLY')
        self.assertIs(result['production_authorized'], False)

    def test_real_origin_refused(self):
        self.v['source']['origin'] = 'https://orgavela.com'; self.reject()

    def test_secret_source_path_refused(self):
        self.v['source']['webroot'] = '/public'; self.reject()

    def test_actual_db_alias_refused(self):
        self.v['source']['database'] = 'customer_db'; self.reject()

    def test_production_mode_refused(self):
        self.v['mode'] = 'production'; self.reject()

    def test_other_marker_refused(self):
        self.v['marker_sha256'] = '0' * 64; self.reject()

    def test_unknown_schema_refused(self):
        self.v['schema'] = 'version2'; self.reject()

    def test_extra_unvalidated_field_refused(self):
        self.v['real_source_token'] = 'bad'; self.reject()

    def test_missing_event_refused(self):
        self.v['events'].pop(5); self.reject()

    def test_swapped_steps_refused(self):
        self.v['events'][6], self.v['events'][7] = self.v['events'][7], self.v['events'][6]; self.reject()

    def test_nonmonotonic_tick_refused(self):
        self.v['events'][6]['tick'] = self.v['events'][5]['tick']; self.reject()

    def test_boolean_tick_refused(self):
        self.v['events'][0]['tick'] = True; self.reject()

    def test_missing_release_fence_refused(self):
        self.v['events'][-4]['event'] = 'unrelated'; self.reject()

    def test_mysql_myisam_refused(self):
        self.v['engine']['tables']['synthetic_only'] = 'MyISAM'; self.reject()

    def test_non_single_transaction_refused(self):
        self.v['engine']['transaction'] = 'best-effort'; self.reject()

    def test_ddl_fence_missing_refused(self):
        self.v['engine']['ddl_fenced'] = False; self.reject()

    def test_cron_not_fenced_refused(self):
        self.v['consistency']['cron_fenced'] = False; self.reject()

    def test_external_writer_not_fenced_refused(self):
        self.v['consistency']['external_writers_fenced'] = False; self.reject()

    def test_app_writer_not_fenced_refused(self):
        self.v['consistency']['application_writes_fenced'] = False; self.reject()

    def test_missing_fence_release_refused(self):
        self.v['consistency']['fences_released_after_capture'] = False; self.reject()

    def test_file_hash_mismatch_refused(self):
        self.v['digests']['files_after'] = '0' * 64; self.reject()

    def test_malformed_hash_refused(self):
        self.v['digests']['database_export'] = 'abc'; self.reject()

    def test_owner_private_key_refused(self):
        self.v['isolation']['ci_has_owner_private_key'] = True; self.reject()

    def test_producer_with_upload_token_refused(self):
        self.v['isolation']['source_has_github_write_token'] = True; self.reject()

    def test_writer_with_ionos_secret_refused(self):
        self.v['isolation']['writer_has_ionos_secret'] = True; self.reject()

    def test_plaintext_workspace_refused(self):
        self.v['isolation']['workspace_contains_plaintext'] = True; self.reject()

    def test_real_capture_permission_refused(self):
        self.v['isolation']['real_capture_authorized'] = True; self.reject()

    def test_remote_upload_refused(self):
        self.v['isolation']['remote_upload_performed'] = True; self.reject()

    def test_duplicate_json_key_refused(self):
        bad = json.dumps(self.v).replace('"mode": "synthetic",', '"mode": "synthetic", "mode": "synthetic",')
        with self.assertRaises(s.Refused):
            json.loads(bad, object_pairs_hook=s.duplicate_reject)

    def test_cli_fixture_only(self):
        with tempfile.TemporaryDirectory() as td:
            path = Path(td)/'test.json'
            path.write_text(json.dumps(self.v), encoding='utf-8')
            self.assertEqual(s.main(['--check-fixture', str(path)]), 0)

    def test_cli_reject_bad_input(self):
        with tempfile.TemporaryDirectory() as td:
            path = Path(td)/'test.json'
            self.v['mode'] = 'production'
            path.write_text(json.dumps(self.v), encoding='utf-8')
            self.assertEqual(s.main(['--check-fixture', str(path)]), 1)

    def test_cli_fixture_gen_indicates_synthetic(self):
        import contextlib
        import io
        out = io.StringIO()
        with contextlib.redirect_stdout(out):
            self.assertEqual(s.main(['--print-artificial-fixture']), 0)
        self.assertIn('https://orgavela.invalid', out.getvalue())
        self.assertNotIn('https://orgavela.com', out.getvalue())


if __name__ == '__main__':
    unittest.main()
