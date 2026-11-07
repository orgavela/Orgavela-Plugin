#!/usr/bin/env python3
"""PGP05 writer-side standalone OFFLINE inspection of synthetic ciphertext.

Default-off, no remote calls, no token, no restore/decryption and NO production mode.
Checks the real PR28 asset validator plus OpenPGP packet recipient independently.
"""
import argparse
import os
from pathlib import Path
import re
import subprocess
import sys
import tempfile

from orgavela_pgp05_asset_gate import Refused, validate

EXPECTED_SUBKEY = 'D9E58CC6484AF1DE'
FORBIDDEN_ENV = ('GH_TOKEN', 'GITHUB_TOKEN', 'BACKUP_GITHUB_TOKEN',
                 'SFTP_HOST', 'SFTP_PASSWORD', 'IONOS_SSH_HOST', 'IONOS_DB_PASSWORD',
                 'BACKUP_RECOVERY_PRIVATE_KEY', 'BACKUP_RECOVERY_PASSPHRASE',
                 'AGE_SECRET_KEY', 'SSH_PRIVATE_KEY')


def check(workspace):
    if any(os.environ.get(name) for name in FORBIDDEN_ENV):
        raise Refused('offline writer must have no credentials')
    attestation = validate(str(workspace))
    path = Path(workspace) / (attestation['id'] + '.tar.gpg')
    with tempfile.TemporaryDirectory(prefix='pgp05-writer-public-only-') as work:
        home = Path(work) / 'gnupg'
        home.mkdir(mode=0o700)
        env = {**os.environ, 'GNUPGHOME': str(home)}
        # gpg often returns nonzero when there is no private key. The inspected
        # packet output (not the exit code) is the public-only evidence.
        proc = subprocess.run(['gpg', '--batch', '--no-tty', '--list-packets', str(path)],
                              capture_output=True, timeout=30, check=False, env=env)
        packets = proc.stdout.decode('utf-8', 'replace')
        keys = re.findall(r'^:pubkey enc packet:.*keyid ([0-9A-F]{16})\s*$',
                          packets, flags=re.MULTILINE)
        if keys != [EXPECTED_SUBKEY] or packets.count(':encrypted data packet:') != 1:
            raise Refused('wrong/missing OpenPGP packet recipient')
        if ':literal data packet:' in packets or ':compressed packet:' in packets:
            raise Refused('unencrypted public payload detected')
        if ':signature packet:' in packets:
            raise Refused('unexpected extra packet')
    return attestation


def main(argv=None):
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--synthetic-only',action='store_true')
    parser.add_argument('--workspace',required=True)
    args=parser.parse_args(argv)
    try:
        if not args.synthetic_only:
            raise Refused('explicit synthetic-only flag required')
        check(args.workspace)
    except (Refused, OSError, ValueError, subprocess.SubprocessError) as e:
        print('PGP05_WRITER_PREFLIGHT_REFUSED=' + type(e).__name__, file=sys.stderr)
        return 1
    print('PGP05_WRITER_PUBLIC_ONLY_PREFLIGHT=PASS')
    return 0


if __name__=='__main__':
    sys.exit(main())
