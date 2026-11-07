"""Offline-only synthetic archive boundary tests (no GnuPG secrets or web calls)."""
import contextlib
import gzip
import io
import json
from pathlib import Path
import tarfile
import tempfile
import unittest
from unittest.mock import patch

import orgavela_pgp05_synthetic_archive_verify as verify
import orgavela_pgp05_synthetic_snapshot as maker


def valid_inner():
    sql = gzip.compress(maker.SYNTHETIC_SQL, mtime=0)
    wp = maker.synthetic_files_snapshot()
    inventory = {'path': verify.WP_NAME, 'size': len(verify.MARKER),
                 'sha256': verify.digest(verify.MARKER), 'modified_ns': 1, 'ctime_ns': 1}
    manifest = {'schema': 'orgavela-backup-package/v1', 'mode': 'synthetic',
                'backup_id': verify.ID, 'site': 'orgavela_synthetic_only',
                'consistency': {'filesystem': 'two-pass-identical',
                                'database': 'fixed-synthetic-sql-only',
                                'inventory': inventory},
                'components': {'database.sql.gz': verify.digest(sql),
                               'wordpress-files.tar.gz': verify.digest(wp)},
                'encryption': {'type': 'OpenPGP-public-key',
                               'public_key_fingerprint': verify.FPR}}
    return {'database.sql.gz': sql, 'wordpress-files.tar.gz': wp,
            'manifest.json': json.dumps(manifest).encode()}


def make_tar(members):
    buf = io.BytesIO()
    with tarfile.open(fileobj=buf, mode='w', format=tarfile.USTAR_FORMAT) as tar:
        for name, b in members:
            info = tarfile.TarInfo(name)
            info.size = len(b)
            tar.addfile(info, io.BytesIO(b))
    return buf.getvalue()


class ArchiveTests(unittest.TestCase):
    def setUp(self):
        self.items = valid_inner()

    def pack(self):
        return make_tar(list(self.items.items()))

    def denied(self, blob=None):
        with self.assertRaises((verify.Refused, tarfile.TarError, ValueError, OSError, __import__('zlib').error)):
            verify.validate_archive(self.pack() if blob is None else blob)

    def manifest(self):
        return json.loads(self.items['manifest.json'])

    def put(self, data):
        self.items['manifest.json'] = json.dumps(data).encode()

    def test_valid_artificial_archive(self):
        result = verify.validate_archive(self.pack())
        self.assertEqual(result['result'], 'SYNTHETIC_ARCHIVE_ONLY')
        self.assertFalse(result['production_ready'])

    def test_missing_member(self):
        del self.items['database.sql.gz']; self.denied()

    def test_unexpected_member(self):
        self.items['wp-config.php'] = b'secret fake'; self.denied()

    def test_duplicate_name(self):
        self.denied(make_tar(list(self.items.items()) + [('manifest.json', b'x')]))

    def test_parent_traversal(self):
        self.denied(make_tar([('../'+k, v) if k=='manifest.json' else (k,v)
                              for k,v in self.items.items()]))

    def test_plaintext_sql_rejected(self):
        self.items['database.sql.gz'] = verify.SQL; self.denied()

    def test_wrong_sql_but_correct_hash_rejected(self):
        self.items['database.sql.gz'] = gzip.compress(b'FAKE PRIVATE DATA', mtime=0)
        p=self.manifest();p['components']['database.sql.gz']=verify.digest(self.items['database.sql.gz'])
        self.put(p); self.denied()

    def test_wrong_wp_marker_but_correct_hash_rejected(self):
        self.items['wordpress-files.tar.gz']=gzip.compress(make_tar([(verify.WP_NAME,b'CHANGED')]),mtime=0)
        p=self.manifest();p['components']['wordpress-files.tar.gz']=verify.digest(self.items['wordpress-files.tar.gz'])
        self.put(p);self.denied()

    def test_outer_component_digest_refused(self):
        self.items['database.sql.gz']+=b'0';self.denied()

    def test_production_flag_rejected(self):
        p=self.manifest();p['mode']='production';self.put(p);self.denied()

    def test_wrong_site_refused(self):
        p=self.manifest();p['site']='orgavela.com';self.put(p);self.denied()

    def test_wrong_primary_fpr(self):
        p=self.manifest();p['encryption']['public_key_fingerprint']='0'*40;self.put(p);self.denied()

    def test_wrong_consistency(self):
        p=self.manifest();p['consistency']['filesystem']='unknown';self.put(p);self.denied()

    def test_wrong_inventory(self):
        p=self.manifest();p['consistency']['inventory']['sha256']='0'*64;self.put(p);self.denied()

    def test_wrong_inventory_type(self):
        p=self.manifest();p['consistency']['inventory']['ctime_ns']=True;self.put(p);self.denied()

    def test_extra_manifest_field(self):
        p=self.manifest();p['password']='DONOTSTORE';self.put(p);self.denied()

    def test_duplicate_json(self):
        self.items['manifest.json']=self.items['manifest.json'][:-1]+b',"mode":"synthetic"}'
        self.denied()

    def test_gzip_trailing_junk_refused(self):
        self.items['database.sql.gz']+=b'EXTRA'
        p=self.manifest();p['components']['database.sql.gz']=verify.digest(self.items['database.sql.gz'])
        self.put(p); self.denied()

    def test_excessive_gzip_bomb_refused(self):
        blob = gzip.compress(b'X'*(verify.MAX_DECOMPRESSED+100),mtime=0)
        with self.assertRaises(verify.Refused): verify.bounded_gzip(blob)

    def test_nested_archive_symlink_refused(self):
        def tarlink():
            buf=io.BytesIO()
            with tarfile.open(fileobj=buf,mode='w') as tar:
                inf=tarfile.TarInfo(verify.WP_NAME)
                inf.type=tarfile.SYMTYPE;inf.linkname='/etc/passwd';tar.addfile(inf)
            return buf.getvalue()
        self.items['wordpress-files.tar.gz']=gzip.compress(tarlink(),mtime=0)
        p=self.manifest();p['components']['wordpress-files.tar.gz']=verify.digest(self.items['wordpress-files.tar.gz'])
        self.put(p);self.denied()

    def test_outer_archive_symlink_refused(self):
        buf=io.BytesIO()
        with tarfile.open(fileobj=buf,mode='w') as tar:
            for k,v in self.items.items():
                info=tarfile.TarInfo(k)
                if k=='manifest.json':
                    info.type=tarfile.SYMTYPE;info.linkname='/tmp/bad'
                else: info.size=len(v)
                tar.addfile(info, io.BytesIO(v) if info.isfile() else None)
        self.denied(buf.getvalue())

    def test_refuse_hidden_trailing_tar_bytes(self):
        self.denied(self.pack()+b'ADDED DATA')

    def test_producer_actual_inner_tar_is_accepted(self):
        # Verify the real synthetic packer schema: capture its PRE-encryption
        # test archive in memory. No owner private key or decrypt is involved.
        captured = []
        def save_synthetic_package(package, work):
            captured.append(package)
            return b'ARTIFICIAL OFFLINE CIPHERTEXT STAND-IN'  # local fake only
        with tempfile.TemporaryDirectory() as td:
            out=Path(td)/'ciphertext-only'
            with patch.object(maker,'encrypt_public_only',side_effect=save_synthetic_package):
                maker.produce(out)
            self.assertEqual(len(captured),1)
            self.assertEqual(verify.validate_archive(captured[0])['result'],
                             'SYNTHETIC_ARCHIVE_ONLY')
            self.assertTrue((out/'manifest.json').exists())

    def test_bad_archive_header(self):
        self.denied(b'not a tarfile')

    def test_no_cli_without_opt_in(self):
        with tempfile.TemporaryDirectory() as td:
            p=Path(td)/'archive.tar';p.write_bytes(self.pack())
            self.assertEqual(verify.main(['--decrypted-archive',str(p)]),1)

    def test_cli_accepts_synthetic(self):
        with tempfile.TemporaryDirectory() as td:
            p=Path(td)/'archive.tar';p.write_bytes(self.pack())
            self.assertEqual(verify.main(['--synthetic-only','--decrypted-archive',str(p)]),0)

    def test_cli_rejects_symlink_file(self):
        with tempfile.TemporaryDirectory() as td:
            p=Path(td)/'archive.tar';p.write_bytes(self.pack())
            symlink=Path(td)/'link.tar';symlink.symlink_to(p)
            self.assertEqual(verify.main(['--synthetic-only','--decrypted-archive',str(symlink)]),1)

    def test_cli_rejects_relative(self):
        self.assertEqual(verify.main(['--synthetic-only','--decrypted-archive','relative.tar']),1)


if __name__ == '__main__': unittest.main()
