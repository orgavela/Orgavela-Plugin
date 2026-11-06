"""Manual trusted-main entrypoint. Payload is encrypted before any handoff."""
import argparse
import os
from pathlib import Path
import re
import tempfile

from collector import ProbeError
from crypto import package
from github_api import reject_mixed_credentials
from transport import Binding, capture


def trusted_main(env):
    if (env.get('GITHUB_REPOSITORY') != 'orgavela/Orgavela-Plugin' or
            env.get('GITHUB_REF') != 'refs/heads/main' or
            env.get('GITHUB_EVENT_NAME') != 'workflow_dispatch' or
            not re.fullmatch(r'[0-9a-f]{40}', env.get('GITHUB_SHA', '')) or
            env.get('ORGAVELA_APPROVED_CODE_SHA') != env.get('GITHUB_SHA') or
            env.get('ORGAVELA_CONFIRM_MAINTENANCE') != 'orgavela.com'):
        raise ProbeError('trusted main and exact maintenance confirmation required')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', type=Path, required=True)
    parser.add_argument('--backup-id', required=True)
    args = parser.parse_args()
    trusted_main(os.environ)
    reject_mixed_credentials(os.environ, 'source')
    if not re.fullmatch(r'orgavela-wordpress-[A-Za-z0-9_-]{3,100}', args.backup_id):
        raise ProbeError('production backup identity required')
    if args.output.is_symlink() or not args.output.is_dir() or any(args.output.iterdir()) or args.output.stat().st_mode & 0o077:
        raise ProbeError('private empty ciphertext directory required')
    binding = Binding(os.environ.get('PGP05_SSH_HOST', ''), os.environ.get('PGP05_SSH_USER', ''),
                      os.environ.get('PGP05_WP_ROOT', ''), Path(os.environ.get('PGP05_KNOWN_HOSTS_FILE', '')))
    with tempfile.TemporaryDirectory(prefix='orgavela-private-components-') as tmp:
        capture(binding, Path(tmp), password=os.environ.get('PGP05_SSH_PASSWORD', ''), maintenance_approved=True)
        package(Path(tmp), args.output, args.backup_id)
    print('ORGAVELA_ENCRYPTED_CAPTURE=PASS')


if __name__ == '__main__':
    try:
        main()
    except Exception:
        raise SystemExit('ORGAVELA_ENCRYPTED_CAPTURE=REFUSED (private details suppressed)') from None
