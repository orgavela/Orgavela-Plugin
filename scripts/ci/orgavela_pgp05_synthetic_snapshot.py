#!/usr/bin/env python3
"""Synthetic-only OrgaVela DB+files snapshot -> OpenPGP ciphertext-only handoff.

This is NOT an IONOS collector. No network calls, database connections, private
keys, production-mode option, release publishing, workflow integration or tokens.
"""
from __future__ import annotations

import argparse
import gzip
import hashlib
import io
import json
import os
from pathlib import Path
import re
import shutil
import stat
import subprocess
import sys
import tarfile
import tempfile

FPR = '7C9DC60D8E1B293E514696EB333D15A4D8BA625C'
SUBKEY_ID = 'D9E58CC6484AF1DE'
PUBLIC_SCHEMA = 'orgavela-github-release-backup-public-manifest/v1'
INTERNAL_SCHEMA = 'orgavela-backup-package/v1'
BACKUP_ID = 'orgavela-synthetic-pgp05-snapshot-001'
SYNTHETIC_SQL = b'CREATE TABLE orgavela_synthetic_only (id INTEGER);\n'
SYNTHETIC_MARKER = b'SYNTHETIC TEST ONLY - NO REAL SITE DATA\n'
SECRET_ENV = ('GH_TOKEN', 'GITHUB_TOKEN', 'BACKUP_GITHUB_TOKEN', 'IONOS_SSH_PASSWORD',
              'IONOS_DB_PASSWORD', 'IONOS_DB_HOST', 'SFTP_PASSWORD', 'SFTP_HOST',
              'IONOS_SSH_HOST', 'IONOS_SSH_USER', 'SSH_PRIVATE_KEY',
              'BACKUP_RECOVERY_PRIVATE_KEY', 'BACKUP_RECOVERY_PASSPHRASE',
              'ORGAVELA_BACKUP_PRIVATE_KEY', 'AGE_SECRET_KEY')


class Refused(ValueError):
    """Fail-closed refusal of an unsafe synthetic-only backup operation."""


def require(condition: bool, message: str):
    if not condition:
        raise Refused(message)


def sha(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def no_secrets():
    require(all(not os.environ.get(name) for name in SECRET_ENV),
            'unexpected credentials in offline synthetic producer')


def safe_dir(path: Path):
    require(path.is_absolute() and path != Path('/') and '..' not in path.parts,
            'directory must be a safe absolute path')
    require(all(not node.is_symlink() for node in (path, *path.parents)),
            'symlink in path rejected')
    require(path.is_dir(), 'directory does not exist')
    require(not path.stat().st_mode & stat.S_IWOTH, 'world-writable directory rejected')


def fixed_tar(members: dict[str, bytes]) -> bytes:
    """Single-purpose deterministic synthetic tar; no user-supplied member paths."""
    buffer = io.BytesIO()
    with tarfile.open(fileobj=buffer, mode='w', format=tarfile.USTAR_FORMAT) as tar:
        for path, contents in sorted(members.items()):
            require(path and not path.startswith('/') and '..' not in Path(path).parts,
                    'unsafe archive member')
            info = tarfile.TarInfo(path)
            info.size = len(contents)
            info.mode = 0o600
            info.mtime = 0
            info.uid = info.gid = 0
            info.uname = info.gname = ''
            tar.addfile(info, io.BytesIO(contents))
    return buffer.getvalue()


def synthetic_files_snapshot() -> bytes:
    return gzip.compress(fixed_tar({'wp-content/uploads/synthetic.txt': SYNTHETIC_MARKER}),
                         mtime=0)


def snapshot_inventory(source: Path):
    """Allowlist entire synthetic fixture, no symlink/extra path, hash regular file."""
    safe_dir(source)
    root_expected = {'wp-content'}
    require({p.name for p in source.iterdir()} == root_expected, 'unexpected root files')
    expected_dirs = [source / 'wp-content', source / 'wp-content/uploads']
    for folder in expected_dirs:
        require(folder.is_dir() and not folder.is_symlink(), 'non-directory or symlink')
    require({p.name for p in expected_dirs[0].iterdir()} == {'uploads'}, 'unexpected WordPress paths')
    require({p.name for p in expected_dirs[1].iterdir()} == {'synthetic.txt'}, 'unexpected upload paths')
    file = expected_dirs[1] / 'synthetic.txt'
    require(not file.is_symlink(), 'symlink source file')
    st = file.lstat()
    require(stat.S_ISREG(st.st_mode) and st.st_nlink == 1, 'nonregular or hardlinked source')
    require(0 < st.st_size < 256, 'unexpected synthetic file size')
    # O_NOFOLLOW and fstat protect against a simple symlink swap.
    fd = os.open(file, os.O_RDONLY | getattr(os, 'O_NOFOLLOW', 0) |
                 getattr(os, 'O_NONBLOCK', 0))
    try:
        before = os.fstat(fd)
        require(stat.S_ISREG(before.st_mode) and before.st_nlink == 1,
                'file changed to nonregular source')
        data = os.read(fd, 257)
        after = os.fstat(fd)
        require((before.st_size, before.st_mtime_ns, before.st_ctime_ns) ==
                (after.st_size, after.st_mtime_ns, after.st_ctime_ns),
                'source changed while reading')
        require(data == SYNTHETIC_MARKER, 'not the exact synthetic-only marker')
        return {'path': 'wp-content/uploads/synthetic.txt', 'size': before.st_size,
                'sha256': sha(data), 'modified_ns': before.st_mtime_ns,
                'ctime_ns': before.st_ctime_ns}
    finally:
        os.close(fd)


def verify_stable_source(source: Path, between=None):
    first = snapshot_inventory(source)
    if between:
        between()
    second = snapshot_inventory(source)
    require(first == second, 'source changed between snapshot passes')
    return first


def public_key_path() -> Path:
    return Path(__file__).resolve().parents[2] / '.github/backup/orgavela-backup-candidate-public.asc'


def run_gpg(env, *args, allow_failure=False):
    p = subprocess.run(['gpg', '--batch', '--no-tty', *args], env=env,
                       stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                       timeout=30, check=False)
    if p.returncode and not allow_failure:
        raise Refused('GnuPG validation/encryption failed')
    return p.stdout


def encrypt_public_only(package: bytes, private_work: Path) -> bytes:
    key = public_key_path()
    require(key.is_file() and not key.is_symlink(), 'versioned public key missing')
    home = private_work / 'gnupg'
    home.mkdir(mode=0o700)
    env = {**os.environ, 'GNUPGHOME': str(home)}
    run_gpg(env, '--import', str(key))
    listing = run_gpg(env, '--with-colons', '--fingerprint', '--list-keys').decode()
    fingerprints = [line.split(':')[9] for line in listing.splitlines()
                    if line.startswith('fpr:')]
    require(len(fingerprints) == 2 and fingerprints[0] == FPR, 'wrong public key')
    require(not run_gpg(env, '--with-colons', '--list-secret-keys').strip(),
            'unexpected private key')
    plaintext = private_work / 'synthetic-package.tar'
    ciphertext = private_work / 'synthetic-package.tar.gpg'
    plaintext.write_bytes(package)
    plaintext.chmod(0o600)
    run_gpg(env, '--yes', '--trust-model', 'always', '--recipient', FPR,
            '--output', str(ciphertext), '--encrypt', str(plaintext))
    packets = run_gpg(env, '--list-packets', str(ciphertext), allow_failure=True).decode('utf-8', 'replace')
    require(':pubkey enc packet:' in packets and ':encrypted data packet:' in packets
            and 'keyid ' + SUBKEY_ID in packets, 'wrong OpenPGP recipient/packet')
    encrypted = ciphertext.read_bytes()
    require(encrypted and sha(encrypted) != sha(package), 'encryption produced no ciphertext')
    return encrypted


def produce(out: Path, *, between=None):
    """Atomically publish only 3 encrypted SYNTHETIC files; never a live-site capture."""
    no_secrets()
    require(out.is_absolute() and out != Path('/') and '..' not in out.parts,
            'unsafe output path')
    safe_dir(out.parent)
    require(not out.exists() and not out.is_symlink(), 'output must not exist')
    with tempfile.TemporaryDirectory(prefix='.pgp05-private-', dir=out.parent) as work:
        private_work = Path(work)
        source = private_work / 'fake-wordpress'
        upload = source / 'wp-content/uploads'
        upload.mkdir(parents=True, mode=0o700)
        file = upload / 'synthetic.txt'
        file.write_bytes(SYNTHETIC_MARKER)
        file.chmod(0o600)
        inventory = verify_stable_source(source, between=between)
        db = gzip.compress(SYNTHETIC_SQL, mtime=0)
        wp = synthetic_files_snapshot()
        internal = {
            'schema': INTERNAL_SCHEMA, 'mode': 'synthetic', 'backup_id': BACKUP_ID,
            'site': 'orgavela_synthetic_only',
            'consistency': {'filesystem': 'two-pass-identical',
                            'database': 'fixed-synthetic-sql-only', 'inventory': inventory},
            'components': {'database.sql.gz': sha(db), 'wordpress-files.tar.gz': sha(wp)},
            'encryption': {'type': 'OpenPGP-public-key', 'public_key_fingerprint': FPR},
        }
        package = fixed_tar({'database.sql.gz': db, 'wordpress-files.tar.gz': wp,
                             'manifest.json': (json.dumps(internal, sort_keys=True) + '\n').encode()})
        # Re-check source before encryption/persisting ciphertext.
        verify_stable_source(source)
        encrypted = encrypt_public_only(package, private_work)
        name = BACKUP_ID + '.tar.gpg'
        outer = {
            'schema': PUBLIC_SCHEMA, 'backup_id': BACKUP_ID,
            'synthetic': True, 'contains_production_data': False,
            'archive_sha256': sha(encrypted), 'archive_bytes': len(encrypted),
            'public_key_fingerprint': FPR, 'encrypted': True,
            'source_components_encrypted': True,
        }
        # Only these three files are placed in the dedicated ciphertext handoff.
        ready = private_work / 'ciphertext-ready'
        ready.mkdir(mode=0o700)
        (ready / name).write_bytes(encrypted)
        (ready / (name + '.sha256')).write_text(sha(encrypted) + '  ' + name + '\n')
        (ready / 'manifest.json').write_text(json.dumps(outer, sort_keys=True) + '\n')
        for file in ready.iterdir():
            file.chmod(0o600)
        # Prevent stage leaving before all policy checks. Import is local-only.
        from orgavela_pgp05_asset_gate import validate
        validate(str(ready))
        require(not out.exists() and not out.is_symlink(), 'output appeared during capture')
        os.rename(ready, out)
    return {'id': BACKUP_ID, 'handoff': str(out), 'files': 3}


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--synthetic-only', action='store_true', default=False)
    parser.add_argument('--output', required=True, help='Nonexistent absolute ciphertext workspace')
    args = parser.parse_args(argv)
    try:
        require(args.synthetic_only, 'synthetic-only flag required; production forbidden')
        produce(Path(args.output))
    except (Refused, OSError, ValueError, subprocess.SubprocessError) as e:
        print('PGP05_SYNTHETIC_CAPTURE_REFUSED=' + type(e).__name__, file=sys.stderr)
        return 1
    print('PGP05_SYNTHETIC_SOURCE_CIPHERTEXT_ONLY=PASS')
    return 0


if __name__ == '__main__':
    sys.exit(main())
