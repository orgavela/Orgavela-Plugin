#!/usr/bin/env python3
"""Offline synthetic-only consistency transcript validator for PGP-05.

This DOES NOT perform a backup, authenticate IONOS, prove a real source identity,
or authorize production. It refuses any real/unknown source and credentials.
"""
import argparse
import hashlib
import json
from pathlib import Path
import re
import sys

SCHEMA = 'orgavela-pgp05-source-boundary/synthetic-v1'
MARKER = 'PGP05 ARTIFICIAL SOURCE ONLY / NEVER PRODUCTION'
MARKER_HASH = hashlib.sha256(MARKER.encode('ascii')).hexdigest()
EVENTS = (
    'source_identity_checked', 'application_write_fence_activated',
    'cron_and_external_writers_fenced', 'schema_ddl_fence_activated',
    'db_consistent_transaction_started', 'filesystem_capture_started',
    'filesystem_capture_completed', 'db_export_completed',
    'source_inventory_rechecked', 'db_transaction_completed',
    'schema_ddl_fence_released', 'application_write_fence_released',
    'public_key_encryption_completed', 'ciphertext_only_handoff_verified',
)
HEXDIGEST = re.compile(r'[0-9a-f]{64}\Z', re.ASCII)

class Refused(ValueError):
    """Fail closed; transcript is never usable as production authorization."""


def require(test, reason):
    if not test:
        raise Refused(reason)


def exact(obj, fields, label):
    require(type(obj) is dict and set(obj) == set(fields), f'{label}: unexpected fields')
    return obj


def duplicate_reject(pairs):
    obj = {}
    for k, v in pairs:
        require(k not in obj, 'duplicate JSON key')
        obj[k] = v
    return obj


def validate(value):
    """Check a simulated *ordered* DB/files capture protocol; no live reads."""
    exact(value, ('schema', 'mode', 'marker_sha256', 'source', 'engine',
                  'consistency', 'events', 'digests', 'isolation'), 'root')
    require(value['schema'] == SCHEMA and value['mode'] == 'synthetic',
            'synthetic-only evidence schema required')
    require(value['marker_sha256'] == MARKER_HASH,
            'unknown artificial marker')
    src = exact(value['source'], ('origin', 'webroot', 'database', 'identity'), 'source')
    require(src == {'origin': 'https://orgavela.invalid',
                    'webroot': 'fictional-fixture-only',
                    'database': 'fictional-fixture-only',
                    'identity': 'synthetic-test-double'}, 'live source identities refused')
    engine = exact(value['engine'], ('tables', 'transaction', 'ddl_fenced'), 'engine')
    require(engine['tables'] == {'synthetic_only': 'InnoDB'} and
            engine['transaction'] == 'single-transaction' and
            engine['ddl_fenced'] is True, 'transactional InnoDB-only test expected')
    con = exact(value['consistency'], ('application_writes_fenced',
                 'cron_fenced', 'external_writers_fenced', 'schema_ddl_fenced',
                 'file_inventory_stable', 'fences_released_after_capture'), 'consistency')
    require(all(con[k] is True for k in con), 'incomplete simulated write barrier')
    events = value['events']
    require(type(events) is list and len(events) == len(EVENTS),
            'wrong capture trace length')
    prior_tick = -1
    for i, step in enumerate(events):
        exact(step, ('event', 'tick'), 'trace step')
        require(step['event'] == EVENTS[i] and type(step['tick']) is int and
                0 <= step['tick'] <= 1000000 and step['tick'] > prior_tick,
                'missing, unordered or invalid capture step')
        prior_tick = step['tick']
    digests = exact(value['digests'], ('files_before', 'files_after',
                    'database_export', 'encrypted_handoff'), 'digests')
    require(all(type(v) is str and HEXDIGEST.fullmatch(v) for v in digests.values()),
            'sha256 digest format invalid')
    require(digests['files_before'] == digests['files_after'],
            'source inventory changed across capture window')
    iso = exact(value['isolation'], ('source_has_github_write_token',
               'writer_has_ionos_secret', 'ci_has_owner_private_key',
               'workspace_contains_plaintext', 'real_capture_authorized',
               'remote_upload_performed'), 'isolation')
    require(all(v is False for v in iso.values()),
            'synthetic isolation violation or live privilege requested')
    return {'kind': 'SIMULATION_ONLY', 'checks': len(EVENTS), 'production_authorized': False}


def fixture():
    fake = hashlib.sha256(b'word-press-FAKE-fixture').hexdigest()
    return {'schema': SCHEMA, 'mode': 'synthetic', 'marker_sha256': MARKER_HASH,
            'source': {'origin': 'https://orgavela.invalid',
                       'webroot': 'fictional-fixture-only',
                       'database': 'fictional-fixture-only',
                       'identity': 'synthetic-test-double'},
            'engine': {'tables': {'synthetic_only': 'InnoDB'},
                       'transaction': 'single-transaction', 'ddl_fenced': True},
            'consistency': {'application_writes_fenced': True, 'cron_fenced': True,
                            'external_writers_fenced': True, 'schema_ddl_fenced': True,
                            'file_inventory_stable': True,
                            'fences_released_after_capture': True},
            'events': [{'event': name, 'tick': i} for i, name in enumerate(EVENTS, 1)],
            'digests': {'files_before': fake, 'files_after': fake,
                        'database_export': hashlib.sha256(b'FAKE SQL').hexdigest(),
                        'encrypted_handoff': hashlib.sha256(b'FAKE ENC').hexdigest()},
            'isolation': {'source_has_github_write_token': False,
                          'writer_has_ionos_secret': False,
                          'ci_has_owner_private_key': False,
                          'workspace_contains_plaintext': False,
                          'real_capture_authorized': False,
                          'remote_upload_performed': False}}


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    mode = parser.add_mutually_exclusive_group(required=True)
    mode.add_argument('--check-fixture', metavar='FILE')
    mode.add_argument('--print-artificial-fixture', action='store_true')
    args = parser.parse_args(argv)
    try:
        if args.print_artificial_fixture:
            print(json.dumps(fixture(), indent=2, sort_keys=True))
            return 0
        path = Path(args.check_fixture)
        require(path.is_file() and not path.is_symlink() and path.stat().st_size <= 8192,
                'fixture must be a small regular JSON file')
        content = json.loads(path.read_text('utf-8'), object_pairs_hook=duplicate_reject)
        validate(content)
    except (OSError, UnicodeError, ValueError, json.JSONDecodeError) as exc:
        print('PGP05_SOURCE_SIMULATION=REFUSED: ' + str(exc), file=sys.stderr)
        return 1
    print('PGP05_SOURCE_SIMULATION_ONLY=PASS / NOT PRODUCTION EVIDENCE')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
