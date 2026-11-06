"""Operator-side private restore. Input must be decrypted outside public CI."""
import argparse
import json
from pathlib import Path
import tempfile

from recovery import validate_package
from isolated_restore import restore_components


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--decrypted-package', type=Path, required=True)
    parser.add_argument('--confirm-private-target', required=True, choices=['orgavela.com'])
    args = parser.parse_args()
    with tempfile.TemporaryDirectory(prefix='orgavela-private-restore-components-') as folder:
        validated = validate_package(args.decrypted_package, Path(folder))
        if validated['mode'] != 'production':
            raise ValueError('real restore requires production snapshot identity')
        evidence = restore_components(Path(folder), expected_home='https://orgavela.com')
    print(json.dumps({'component_hashes_verified': 3, **evidence}, sort_keys=True))


if __name__ == '__main__':
    try:
        main()
    except Exception:
        raise SystemExit('ORGAVELA_PRIVATE_RESTORE=REFUSED (private details suppressed)') from None
