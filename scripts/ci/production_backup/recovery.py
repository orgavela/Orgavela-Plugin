"""Validate an operator-decrypted package before a separate private restore.

This module neither possesses a recovery key nor connects to the source. It
never imports SQL or executes recovered PHP; those operations belong only in an
isolated disposable target after this validation has passed.
"""
import hashlib
import json
import re
from crypto import PRIMARY_FPR, SUBKEY_FPR
from pathlib import Path
import tarfile

from collector import NAMES, ProbeError, unique_pairs, validate_components


def validate_package(decrypted, destination, *, primary=PRIMARY_FPR, subkey=SUBKEY_FPR):
    decrypted, destination = Path(decrypted), Path(destination)
    if decrypted.is_symlink() or not decrypted.is_file() or decrypted.stat().st_nlink != 1 or not 0 < decrypted.stat().st_size <= 140 * 1024 * 1024:
        raise ProbeError('invalid decrypted archive')
    if destination.is_symlink() or not destination.is_dir() or any(destination.iterdir()) or destination.stat().st_mode & 0o077:
        raise ProbeError('private empty recovery directory required')
    made = []
    try:
        with tarfile.open(decrypted, 'r:') as archive:
            seen = set()
            for member in archive:
                maximum = 16384 if member.name in ('manifest.json', 'capture-evidence.json') else 128 * 1024 * 1024
                if member.name not in NAMES | {'manifest.json'} or member.name in seen or not member.isreg() or not 0 < member.size <= maximum:
                    raise ProbeError('unexpected recovery member')
                seen.add(member.name)
                path = destination / member.name
                with path.open('xb') as target, archive.extractfile(member) as source:
                    made.append(path)
                    path.chmod(0o600)
                    while block := source.read(65536):
                        target.write(block)
            if seen != NAMES | {'manifest.json'}:
                raise ProbeError('incomplete recovery package')
            end = max(m.offset_data + ((m.size + 511) // 512) * 512 for m in archive.getmembers())
            with decrypted.open('rb') as source:
                source.seek(end)
                suffix = source.read()
            if len(suffix) < 1024 or any(suffix) or decrypted.stat().st_size % 512:
                raise ProbeError('unexpected recovery suffix')
        manifest = json.loads((destination / 'manifest.json').read_text(), object_pairs_hook=unique_pairs)
        if not isinstance(manifest, dict) or set(manifest) != {'schema', 'backup_id', 'mode', 'scope', 'components', 'encryption'}:
            raise ProbeError('invalid recovery manifest')
        if manifest['schema'] != 'orgavela-backup-package/v1' or manifest['mode'] not in ('synthetic', 'production') or set(manifest['components']) != NAMES:
            raise ProbeError('invalid recovery identity')
        prefix = 'orgavela-synthetic-' if manifest['mode'] == 'synthetic' else 'orgavela-wordpress-'
        if not isinstance(manifest['backup_id'], str) or not re.fullmatch(re.escape(prefix) + r'[A-Za-z0-9_-]{3,100}', manifest['backup_id']):
            raise ProbeError('recovery mode/identity mismatch')
        encryption = {'type': 'OpenPGP-public-key', 'public_key_fingerprint': primary,
                      'encryption_subkey_fingerprint': subkey, 'private_key_in_producer': False}
        if manifest['encryption'] != encryption or type(manifest['encryption'].get('private_key_in_producer')) is not bool:
            raise ProbeError('recovery recipient mismatch')
        for name in NAMES:
            data = destination / name
            expected = manifest['components'][name]
            if not isinstance(expected, dict) or set(expected) != {'sha256', 'bytes'} or type(expected['bytes']) is not int:
                raise ProbeError('invalid component manifest')
            digest = hashlib.sha256()
            with data.open('rb') as source:
                while chunk := source.read(65536):
                    digest.update(chunk)
            if data.stat().st_size != expected['bytes'] or digest.hexdigest() != expected['sha256']:
                raise ProbeError('recovery component mismatch')
        evidence = validate_components(destination)
        if manifest['scope'] != evidence['scope']:
            raise ProbeError('recovery scope mismatch')
        return {'component_hashes_verified': 3, 'mode': manifest['mode'], 'database_imported': False,
                'wordpress_bootstrapped': False, 'real_restore_proven': False}
    except Exception:
        for path in made:
            path.unlink(missing_ok=True)
        raise
