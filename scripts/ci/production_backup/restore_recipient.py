"""Public-only, same-run ephemeral restore-recipient contract."""
import hashlib
import json
from pathlib import Path
import re
import time

FIELDS={'schema','run','attempt','controller_sha','primary','subkey','expires_at','public_sha256'}


def validate(record, *, env=None, require_live=False, minimum_remaining=0):
    if not isinstance(record,dict) or set(record)!=FIELDS or record['schema']!='orgavela-ephemeral-restore-recipient/v1':raise ValueError('restore recipient schema')
    if any(not isinstance(record[k],str) or not re.fullmatch(r'[A-F0-9]{40}',record[k]) for k in ('primary','subkey')) or record['primary']==record['subkey']:raise ValueError('restore key identities')
    if not all(isinstance(record[k],str) and re.fullmatch(r'[1-9][0-9]{0,19}',record[k]) for k in ('run','attempt')):raise ValueError('restore run identity')
    if not isinstance(record['controller_sha'],str) or not re.fullmatch(r'[a-f0-9]{40}',record['controller_sha']):raise ValueError('restore controller identity')
    if not isinstance(record['public_sha256'],str) or not re.fullmatch(r'[a-f0-9]{64}',record['public_sha256']) or type(record['expires_at']) is not int:raise ValueError('restore public identity')
    if require_live and not time.time()+minimum_remaining < record['expires_at'] <= time.time()+1200:raise ValueError('restore recipient expired')
    if env is not None and (record['run'],record['attempt'],record['controller_sha'])!=(env.get('GITHUB_RUN_ID'),env.get('GITHUB_RUN_ATTEMPT'),env.get('GITHUB_SHA')):raise ValueError('restore run/controller mismatch')
    return record


def load(folder, *, env=None, require_live=False, minimum_remaining=0):
    from crypto import regular
    folder=Path(folder)
    if folder.is_symlink() or not folder.is_dir() or {p.name for p in folder.iterdir()}!={'recipient.asc','recipient.json'}:raise ValueError('restore public file set')
    from collector import unique_pairs
    record=json.loads(regular(folder/'recipient.json',16384).read_bytes(),object_pairs_hook=unique_pairs)
    validate(record,env=env,require_live=require_live,minimum_remaining=minimum_remaining)
    key=regular(folder/'recipient.asc',16384).read_bytes()
    if b'-----BEGIN PGP PUBLIC KEY BLOCK-----' not in key or b'PRIVATE KEY' in key or hashlib.sha256(key).hexdigest()!=record['public_sha256']:raise ValueError('public-only restore key required')
    return record
