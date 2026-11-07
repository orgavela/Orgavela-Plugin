#!/usr/bin/env python3
"""Actual local source-fixture -> encrypted-only handoff -> independent writer gate.

No SSH, HTTP, context, token, private key, release or real WordPress read.
"""
import argparse
import contextlib
import json
import os
from pathlib import Path
import tempfile
from collector import accept_snapshot
from crypto import package
from writer import assets
from github_api import reject_mixed_credentials
from fixtures import snapshot

def exercise():
    reject_mixed_credentials(os.environ,'source')
    reject_mixed_credentials(os.environ,'writer')
    with tempfile.TemporaryDirectory(prefix='orgavela-port-offline-') as tmp:
        root=Path(tmp);source=root/'synthetic-stream.tar';snapshot(source)
        components=root/'plaintext';components.mkdir(mode=0o700)
        accept_snapshot(source,components)
        handoff=root/'ciphertext-only';handoff.mkdir(mode=0o700)
        record=package(components,handoff,'orgavela-synthetic-collector-port')
        backup_id,checked=assets(handoff,synthetic=True)
        if len(checked)!=3 or record['contains_production_data'] is not False:
            raise ValueError('synthetic handoff failed')
        return {'schema':'orgavela-collector-port-offline/v1','synthetic_only':True,
            'source_fixture_validated':True,'encrypted_handoff_assets':3,
            'approved_recipient_packet_verified':True,'remote_calls':0,
            'production_capture_allowed':False,'actual_site_restore_tested':False}

def main():
    parser=argparse.ArgumentParser();parser.add_argument('--synthetic-only',action='store_true')
    args=parser.parse_args()
    try:
        if not args.synthetic_only: raise ValueError('opt in required')
        result=exercise()
    except Exception:
        print('ORGAVELA_COLLECTOR_PORT_OFFLINE=REFUSED');return 78
    print(json.dumps(result,sort_keys=True));return 0

if __name__=='__main__': raise SystemExit(main())
