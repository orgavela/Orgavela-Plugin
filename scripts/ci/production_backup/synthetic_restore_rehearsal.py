"""Disposable real WP/MariaDB restore of an explicitly synthetic local donor.

Only the fixed workbench from the previous M3 task is accepted, never a remote
host. No port is published. The donor must identify itself as localhost and its
fixed synthetic database; decrypted components remain private and temporary.
This proves restore plumbing, not production capture/maintenance consistency.
"""
import argparse
import gzip
import hashlib
import io
import json
import os
from pathlib import Path
import secrets
import subprocess
import tarfile
import tempfile
import time
import warnings
import pgpy
from pgpy.constants import PubKeyAlgorithm, KeyFlags, HashAlgorithm, SymmetricKeyAlgorithm, CompressionAlgorithm
from crypto import package as encrypt_package

from fixtures import tar_bytes
from recovery import validate_package
from crypto import PRIMARY_FPR, SUBKEY_FPR

WP_IMAGE = 'wordpress@sha256:a4fff017eb08c03187e90fe52d4bff8d3a4d4a5103de05829a6e747c4ca3056e'
DB_IMAGE = 'mariadb@sha256:d769e37960821dfdcf096954f6e7ac89f747e116c1bba98e48edd265dfc7deae'
DONOR = 'orgavela-m3-4fb50cb'


def docker(*args, data=None):
    result = subprocess.run(['docker', '--host=unix:///var/run/docker.sock', *args],
                            input=data, capture_output=True)
    if result.returncode:
        raise RuntimeError('synthetic Docker operation failed; private output suppressed')
    return result.stdout


def run():
    # Reject a copied real WordPress source before requesting a filesystem/SQL export.
    identity = docker('exec', DONOR + '-db', 'sh', '-c',
        'mariadb --user=orgavela --password="$MARIADB_PASSWORD" --batch --skip-column-names '
        'orgavela_synthetic -e "SELECT option_value FROM wp_options WHERE option_name=\'home\'"')
    if identity.strip() != b'http://127.0.0.1:8762':
        raise RuntimeError('fixed synthetic donor identity refused')
    # Snapshot a stopped synthetic source. No production fence evidence is inferred.
    docker('stop', DONOR + '-web')
    try:
        files = docker('run', '--rm', '--network', 'none', '--user', '0:0', '--entrypoint', 'tar',
                       '-v', DONOR + '-wp:/source:ro', WP_IMAGE,
                       '-czf', '-', '--exclude=./.maintenance', '-C', '/source', '.')
        sql = docker('exec', DONOR + '-db', 'sh', '-c',
            'mariadb-dump --user=orgavela --password="$MARIADB_PASSWORD" '
            '--single-transaction --skip-lock-tables --skip-comments orgavela_synthetic')
    finally:
        docker('start', DONOR + '-web')
    if len(files) > 96*1024*1024 or len(sql) > 64*1024*1024:
        raise RuntimeError('synthetic fixture too large')
    # Retain the existing validator's exact 52-table fixture contract. The twelve
    # extra tables are fixture data only, never edits to the donor or live source.
    for index in range(12):
        sql += ('\nCREATE TABLE wp_restore_fixture_%d (id BIGINT PRIMARY KEY, marker VARCHAR(32));\n'
                'INSERT INTO wp_restore_fixture_%d VALUES (1,\'synthetic-only\');\n' % (index, index)).encode()
    components = {'database.sql.gz': gzip.compress(sql), 'wordpress-files.tar.gz': files}
    evidence = dict(schema='orgavela-ssh-capture-evidence/v1', capture_mode='production',
        filesystem_consistency='quiesced', database_consistency='single-transaction',
        scope=dict.fromkeys(('database', 'wordpress_filesystem', 'uploads', 'wp_config'), True),
        quiescence=dict.fromkeys(('wordpress_maintenance', 'all_table_read_locks', 'stable_file_hashes',
                                 'maintenance_removed', 'maintenance_origin_verified', 'source_origin_recovered'), True),
        table_count=52, filesystem_entries=1,
        components={'database': {'sha256': hashlib.sha256(components['database.sql.gz']).hexdigest()},
                    'wordpress': {'sha256': hashlib.sha256(files).hexdigest()}})
    # These are explicitly simulated transport assertions, not measured live facts.
    evidence['quiescence']['maintenance_seconds'] = 1.0
    components['capture-evidence.json'] = json.dumps(evidence).encode()
    with tempfile.TemporaryDirectory(prefix='orgavela-private-restore-') as folder:
        root = Path(folder)
        key = pgpy.PGPKey.new(PubKeyAlgorithm.RSAEncryptOrSign, 2048)
        key.add_uid(pgpy.PGPUID.new('Disposable synthetic WP restore'),
                    usage={KeyFlags.Certify, KeyFlags.Sign}, hashes=[HashAlgorithm.SHA256],
                    ciphers=[SymmetricKeyAlgorithm.AES256], compression=[CompressionAlgorithm.ZLIB])
        subkey = pgpy.PGPKey.new(PubKeyAlgorithm.RSAEncryptOrSign, 2048)
        key.add_subkey(subkey, usage={KeyFlags.EncryptCommunications, KeyFlags.EncryptStorage})
        public = root / 'synthetic-public.asc'
        public.write_text(str(key.pubkey))
        source_components = root / 'components'
        source_components.mkdir(mode=0o700)
        for name, value in components.items():
            (source_components / name).write_bytes(value)
            (source_components / name).chmod(0o600)
        ciphertext = root / 'ciphertext'
        ciphertext.mkdir(mode=0o700)
        encrypt_package(source_components, ciphertext, 'orgavela-synthetic-wp-restore',
                        key_file=public, primary=str(key.fingerprint), subkey=str(subkey.fingerprint))
        public_fingerprint = key.fingerprint
        message = pgpy.PGPMessage.from_blob((ciphertext / 'orgavela-synthetic-wp-restore.tar.gpg').read_bytes())
        with warnings.catch_warnings():
            warnings.simplefilter('ignore', UserWarning)
            clear = bytes(key.decrypt(message).message)
        archive = root / 'decrypted.tar'
        archive.write_bytes(clear)
        archive.chmod(0o600)
        del clear, message, key
        recovered = root / 'recovered'
        recovered.mkdir(mode=0o700)
        validate_package(archive, recovered, primary=str(public_fingerprint), subkey=str(subkey.fingerprint))
        from isolated_restore import restore_components
        restore_components(recovered, expected_home='http://127.0.0.1:8762')
    return {'synthetic_wp_db_restore': True, 'ephemeral_encryption_roundtrip': True, 'restored_tables': 52, 'restored_uop_tables': 28,
            'public_ports': 0, 'production_source_accessed': False, 'production_quiescence_proven': False,
            'real_snapshot_restore_proven': False}


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--synthetic-only', action='store_true', required=True)
    parser.parse_args()
    print(json.dumps(run(), sort_keys=True))
