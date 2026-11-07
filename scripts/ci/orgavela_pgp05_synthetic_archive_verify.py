#!/usr/bin/env python3
"""Offline verification of an ALREADY DECRYPTED PGP-05 synthetic test archive.

No GnuPG decrypt, owner key, website, database, or release API. Refuses any real
site contents. It never extracts an archive member to the filesystem.
"""
import argparse
import gzip
import hashlib
import io
import json
import os
from pathlib import Path
import stat
import sys
import tarfile
import zlib

ID = 'orgavela-synthetic-pgp05-snapshot-001'
FPR = '7C9DC60D8E1B293E514696EB333D15A4D8BA625C'
SQL = b'CREATE TABLE orgavela_synthetic_only (id INTEGER);\n'
MARKER = b'SYNTHETIC TEST ONLY - NO REAL SITE DATA\n'
WP_NAME = 'wp-content/uploads/synthetic.txt'
OUTER = {'database.sql.gz', 'wordpress-files.tar.gz', 'manifest.json'}
MAX_ARCHIVE = 8 * 1024 * 1024
MAX_MEMBER = 256 * 1024
MAX_DECOMPRESSED = 512 * 1024


class Refused(ValueError):
    pass


def require(ok, msg):
    if not ok:
        raise Refused(msg)


def digest(b):
    return hashlib.sha256(b).hexdigest()


def unique_pairs(items):
    result = {}
    for key, value in items:
        require(key not in result, 'repeated JSON key')
        result[key] = value
    return result


def exact_keys(obj, keys):
    require(type(obj) is dict and set(obj) == set(keys), 'unexpected manifest keys')
    return obj


def tar_members(blob, expected):
    require(type(blob) is bytes and 0 < len(blob) <= MAX_ARCHIVE, 'unsafe tar size')
    with tarfile.open(fileobj=io.BytesIO(blob), mode='r:') as tf:
        seen = {}
        last_member_end = 0
        for member in tf:
            require(member.name in expected and member.name not in seen,
                    'unexpected or duplicate archive name')
            require(member.isfile() and not member.issym() and not member.islnk(),
                    'non-regular archive member')
            require(0 < member.size <= MAX_MEMBER, 'unsafe member size')
            stream = tf.extractfile(member)
            require(stream is not None, 'unreadable member')
            content = stream.read(MAX_MEMBER + 1)
            require(len(content) == member.size, 'truncated member')
            seen[member.name] = content
            last_member_end = max(last_member_end, member.offset_data +
                                  ((member.size + 511) // 512) * 512)
    # Python tarfile ignores bytes after the end marker. Refuse hidden appendices.
    tail = blob[last_member_end:]
    require(len(blob) % 512 == 0 and len(tail) >= 1024 and
            not any(tail), 'non-zero or missing TAR end padding')
    require(set(seen) == set(expected), 'missing required member')
    return seen


def bounded_gzip(blob):
    require(0 < len(blob) <= MAX_MEMBER, 'unsafe gzip input')
    decoder = zlib.decompressobj(16 + zlib.MAX_WBITS)
    data = decoder.decompress(blob, MAX_DECOMPRESSED + 1)
    require(len(data) <= MAX_DECOMPRESSED and decoder.eof and
            not decoder.unused_data and not decoder.unconsumed_tail,
            'unsafe, truncated or trailing gzip data')
    return data


def validate_archive(blob):
    outer = tar_members(blob, OUTER)
    try:
        manifest = json.loads(outer['manifest.json'].decode('utf-8'),
                              object_pairs_hook=unique_pairs)
    except (UnicodeError, ValueError) as exc:
        raise Refused('invalid synthetic internal manifest') from exc
    exact_keys(manifest, ('schema', 'mode', 'backup_id', 'site',
                          'consistency', 'components', 'encryption'))
    require(manifest['schema'] == 'orgavela-backup-package/v1' and
            manifest['mode'] == 'synthetic' and manifest['backup_id'] == ID and
            manifest['site'] == 'orgavela_synthetic_only', 'non-synthetic archive')
    enc = exact_keys(manifest['encryption'], ('type', 'public_key_fingerprint'))
    require(enc == {'type': 'OpenPGP-public-key', 'public_key_fingerprint': FPR},
            'wrong encryption policy metadata')
    comps = exact_keys(manifest['components'], ('database.sql.gz', 'wordpress-files.tar.gz'))
    for filename in ('database.sql.gz', 'wordpress-files.tar.gz'):
        require(comps[filename] == digest(outer[filename]), 'component digest mismatch')
    consistency = exact_keys(manifest['consistency'], ('filesystem', 'database', 'inventory'))
    require(consistency['filesystem'] == 'two-pass-identical' and
            consistency['database'] == 'fixed-synthetic-sql-only',
            'missing synthetic consistency assertion')
    inventory = exact_keys(consistency['inventory'],
                           ('path', 'size', 'sha256', 'modified_ns', 'ctime_ns'))
    require(inventory['path'] == WP_NAME and
            type(inventory['size']) is int and inventory['size'] == len(MARKER) and
            inventory['sha256'] == digest(MARKER) and
            all(type(inventory[key]) is int and inventory[key] >= 0
                for key in ('modified_ns', 'ctime_ns')),
            'synthetic file inventory mismatch')
    require(bounded_gzip(outer['database.sql.gz']) == SQL,
            'non-synthetic database content')
    wordpress = tar_members(bounded_gzip(outer['wordpress-files.tar.gz']), {WP_NAME})
    require(wordpress[WP_NAME] == MARKER, 'non-synthetic WordPress content')
    return {'result': 'SYNTHETIC_ARCHIVE_ONLY', 'members': 3, 'production_ready': False}


def read_restricted(path):
    path = Path(path)
    require(path.is_absolute() and path != Path('/') and '..' not in path.parts,
            'unsafe archive path')
    require(not any(parent.is_symlink() for parent in (path, *path.parents)),
            'symlink path')
    fd = os.open(path, os.O_RDONLY | getattr(os, 'O_NOFOLLOW', 0) |
                 getattr(os, 'O_NONBLOCK', 0))
    try:
        before = os.fstat(fd)
        require(stat.S_ISREG(before.st_mode) and before.st_nlink == 1 and
                0 < before.st_size <= MAX_ARCHIVE, 'unsafe decrypted archive file')
        data = b''
        while len(data) <= MAX_ARCHIVE:
            part = os.read(fd, min(128 * 1024, MAX_ARCHIVE + 1 - len(data)))
            if not part:
                break
            data += part
        after = os.fstat(fd)
        require(len(data) == before.st_size and
                (before.st_size, before.st_mtime_ns, before.st_ctime_ns) ==
                (after.st_size, after.st_mtime_ns, after.st_ctime_ns),
                'archive changed during inspection')
        return data
    finally:
        os.close(fd)


def main(args=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--synthetic-only', action='store_true')
    parser.add_argument('--decrypted-archive', required=True)
    options = parser.parse_args(args)
    try:
        require(options.synthetic_only, 'explicit synthetic-only opt-in required')
        validate_archive(read_restricted(options.decrypted_archive))
    except (Refused, OSError, ValueError, tarfile.TarError, zlib.error) as exc:
        print('PGP05_SYNTHETIC_ARCHIVE=REFUSED: ' + type(exc).__name__, file=sys.stderr)
        return 1
    print('PGP05_SYNTHETIC_ARCHIVE=PASS / NO REAL RESTORE')
    return 0


if __name__ == '__main__':
    sys.exit(main())
