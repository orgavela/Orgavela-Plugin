"""Separately wired trusted-main ciphertext publisher; no source credentials."""
import argparse
import json
import os
from pathlib import Path

from execution_identity import authorize
from github_api import WriterError, reject_mixed_credentials, verify_repository
from writer import assets, publish


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--ciphertext', type=Path, required=True)
    args = parser.parse_args()
    authorize(os.environ, 'writer')
    reject_mixed_credentials(os.environ, 'writer')
    if os.environ.get('ORGAVELA_CONFIRM_PUBLISH') != 'orgavela/orgavela-backups':
        raise WriterError('explicit private repository confirmation required')
    token = os.environ.get('BACKUP_GITHUB_TOKEN', '')
    if not token or any(char.isspace() for char in token):
        raise WriterError('dedicated backup repository token required')
    backup_id, entries = assets(args.ciphertext)
    verify_repository(token)
    evidence = publish(token, backup_id, entries)
    print(json.dumps(evidence, sort_keys=True))


if __name__ == '__main__':
    try:
        main()
    except Exception:
        raise SystemExit('ORGAVELA_PRIVATE_PUBLISH=REFUSED (private details suppressed)') from None
