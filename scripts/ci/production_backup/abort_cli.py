"""Isolated maintenance rehearsal. Never captures, encrypts or publishes a backup."""
import argparse
import json
import os
from pathlib import Path

from collector import ProbeError
from execution_identity import authorize
from github_api import reject_mixed_credentials
from transport import Binding, controlled_abort, hard_disconnect_abort, abort_preflight


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--mode', choices=['preflight', 'exception', 'hard-disconnect'], required=True)
    args = parser.parse_args()
    if os.environ.get('ORGAVELA_CONFIRM_MAINTENANCE') != 'orgavela.com':
        raise ProbeError('explicit maintenance confirmation required')
    authorize(os.environ, 'abort')
    reject_mixed_credentials(os.environ, 'source')
    binding = Binding(os.environ.get('PGP05_SSH_HOST', ''), os.environ.get('PGP05_SSH_USER', ''),
                      os.environ.get('PGP05_WP_ROOT', ''), Path(os.environ.get('PGP05_KNOWN_HOSTS_FILE', '')))
    operation = {'preflight':abort_preflight, 'exception':controlled_abort, 'hard-disconnect':hard_disconnect_abort}[args.mode]
    evidence = operation(binding, password=os.environ.get('PGP05_SSH_PASSWORD', ''), maintenance_approved=True)
    print(json.dumps(evidence, sort_keys=True))


if __name__ == '__main__':
    try:
        main()
    except Exception:
        raise SystemExit('ORGAVELA_ABORT_ONLY=REFUSED (private details suppressed)') from None
