"""Dreamloud capture validation port. Live capture is deliberately disabled."""
import gzip
import hashlib
import json
import math
import os
from pathlib import Path, PurePosixPath
import tarfile

class ProbeError(ValueError): pass
NAMES={'database.sql.gz','wordpress-files.tar.gz','capture-evidence.json'}

def validate_evidence(data, work):
    if (not isinstance(data, dict) or data.get('schema') not in ('orgavela-ssh-capture-evidence/v3','orgavela-ssh-capture-evidence/v4') or
            data.get('capture_mode') != 'production' or data.get('filesystem_consistency') != 'quiesced' or
            data.get('database_consistency') != 'single-transaction' or
            data.get('scope') != dict.fromkeys(('database','wordpress_filesystem','uploads','wp_config'), True)):
        raise ProbeError('capture scope or consistency failed')
    from database_tls_policy import EXTRA
    unverified=data['schema']=='orgavela-ssh-capture-evidence/v4'
    if unverified and any(data.get(k)!=v or type(data.get(k)) is not type(v) for k,v in EXTRA.items()):raise ProbeError('documented TLS exception required')
    if set(data)- (set(EXTRA) if unverified else set())!={'schema','capture_mode','filesystem_consistency','database_consistency','scope','quiescence','table_count','filesystem_entries','components','database_tls_verified','database_server_certificate_verified'}:
        raise ProbeError('unexpected evidence fields')
    if data.get('database_tls_verified') is not True or data.get('database_server_certificate_verified') is not (False if unverified else True):raise ProbeError('verified database TLS required')
    if any(type(v) is not bool for v in data['scope'].values()): raise ProbeError('scope types')
    if type(data.get('filesystem_entries')) is not int or not 0<data['filesystem_entries']<=50000: raise ProbeError('entry count')
    q = data.get('quiescence', {})
    if not isinstance(q,dict) or set(q)!={'wordpress_maintenance','all_table_read_locks','stable_file_hashes','archive_matches_inventory','maintenance_removed','maintenance_origin_verified','source_origin_recovered','maintenance_seconds'}: raise ProbeError('quiescence fields')
    if type(data.get('components')) is not dict or set(data['components'])!={'database','wordpress'}: raise ProbeError('component scope')
    for flag in ('wordpress_maintenance','all_table_read_locks','stable_file_hashes','archive_matches_inventory','maintenance_removed','maintenance_origin_verified','source_origin_recovered'):
        if q.get(flag) is not True:
            raise ProbeError('missing real quiescence evidence')
    if type(q.get('maintenance_seconds')) not in (int,float) or not math.isfinite(q['maintenance_seconds']) or not 0 < q['maintenance_seconds'] < 105:
        raise ProbeError('maintenance deadline failed')
    if type(data.get('table_count')) is not int or data['table_count'] != 52:
        raise ProbeError('invalid table scope')
    for label, name in [('database','database.sql.gz'),('wordpress','wordpress-files.tar.gz')]:
        if not isinstance(data['components'][label],dict) or set(data['components'][label])!={'sha256'}: raise ProbeError('component fields')
        with (work/name).open('rb') as stream:
            hasher = hashlib.sha256()
            while chunk := stream.read(1024*1024): hasher.update(chunk)
            digest = hasher.hexdigest()
        if data.get('components',{}).get(label,{}).get('sha256') != digest:
            raise ProbeError('capture component hash mismatch')


def unpack_snapshot(snapshot, work):
    if snapshot.is_symlink() or not snapshot.is_file() or snapshot.stat().st_nlink!=1 or not 0 < snapshot.stat().st_size <= 140*1024*1024:
        raise ProbeError('capture size limit')
    if not work.is_dir() or work.is_symlink() or any(work.iterdir()) or work.stat().st_mode & 0o077:
        raise ProbeError('private empty extraction directory required')
    with tarfile.open(snapshot,'r:') as archive:
        seen=set()
        for member in archive:
            if member.name not in NAMES or member.name in seen or not member.isreg() or not 0 < member.size <= 128*1024*1024:
                raise ProbeError('unexpected capture member')
            seen.add(member.name)
            if member.name=='capture-evidence.json' and member.size>16384:
                raise ProbeError('oversized evidence')
            with archive.extractfile(member) as source, (work/member.name).open('xb') as dest:
                while chunk:=source.read(1024*1024): dest.write(chunk)
        end=max((m.offset_data+((m.size+511)//512)*512 for m in archive.getmembers()),default=0)
        with snapshot.open('rb') as source:
            source.seek(end);tail=source.read()
        if len(tail)<1024 or any(tail) or snapshot.stat().st_size%512: raise ProbeError('trailing tar data')
        if seen != NAMES:
            raise ProbeError('incomplete capture')
    return validate_components(work)

def validate_components(work):
    with (work/'capture-evidence.json').open() as stream: evidence=json.load(stream, object_pairs_hook=unique_pairs)
    validate_evidence(evidence, work)
    for name in ('database.sql.gz','wordpress-files.tar.gz'):
        size=0
        with gzip.open(work/name,'rb') as stream:
            while chunk:=stream.read(1024*1024):
                size+=len(chunk)
                if size>2147483648: raise ProbeError('decompression limit')
        if not size: raise ProbeError('empty component')
    required={'wp-config.php','wp-load.php','index.php'}; found=set(); seen={}
    with tarfile.open(work/'wordpress-files.tar.gz','r:gz') as archive:
        for member in archive:
            path=PurePosixPath(member.name)
            if chr(92) in member.name or any(c in member.name for c in ('\x00','\r','\n')) or path.is_absolute() or '..' in path.parts or not (member.isreg() or member.isdir()) or str(path) in seen:
                raise ProbeError('unsafe WordPress archive')
            if len(seen)>=50000: raise ProbeError('WordPress archive entry limit')
            seen[str(path)]=member.isdir()
            if member.isreg() and str(path) in required: found.add(str(path))
            if str(path)=='.maintenance': raise ProbeError('maintenance marker must not enter backup')
        end=max((m.offset_data+((m.size+511)//512)*512 for m in archive.getmembers()),default=0)
        archive.fileobj.seek(end);padding=0
        while block:=archive.fileobj.read(65536):
            padding+=len(block)
            if any(block): raise ProbeError('hidden WordPress archive suffix')
        if padding<1024: raise ProbeError('missing WordPress end marker')
        for name in seen:
            for parent in PurePosixPath(name).parents:
                if str(parent) in seen and not seen[str(parent)]: raise ProbeError('WordPress ancestor type conflict')
        if found!=required or seen.get('wp-content/uploads') is not True:
            raise ProbeError('WordPress scope incomplete')
    return evidence


def unique_pairs(pairs):
    result={}
    for key,value in pairs:
        if key in result: raise ProbeError('duplicate JSON key')
        result[key]=value
    return result

def accept_snapshot(snapshot, work):
    """Private bounded extraction; remove partial outputs on every rejection."""
    work=Path(work)
    if not work.is_dir() or work.is_symlink() or any(work.iterdir()) or work.stat().st_mode & 0o077:
        raise ProbeError('private empty extraction directory required')
    try:
        return unpack_snapshot(Path(snapshot),work)
    except Exception:
        for name in NAMES:
            candidate=Path(work)/name
            if candidate.is_file() and not candidate.is_symlink(): candidate.unlink()
        raise

def build_remote_candidate(root,user):
    """Reviewable code only. Remains disabled regardless of environment flags."""
    import re
    if not re.fullmatch(r'/[A-Za-z0-9_.-]+(?:/[A-Za-z0-9_.-]+)+',root) or any(p in ('.','..') for p in root.split('/')): raise ProbeError('root')
    if not re.fullmatch(r'[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}',user): raise ProbeError('user')
    folder=Path(__file__).parent
    import base64
    process_helpers=(folder/'lib/backup_process_safety.php').read_bytes()
    prefix='\n'.join(['const ORGAVELA_CAPTURE_ENTRYPOINT_ENABLED = false;',
        'const ORGAVELA_PROCESS_HELPERS_BASE64 = '+json.dumps(base64.b64encode(process_helpers).decode())+';',
        'const ORGAVELA_EXPECTED_ROOT = '+json.dumps(root)+';',
        'const ORGAVELA_EXPECTED_USER = '+json.dumps(user)+';'])
    # No dynamic config evaluation, include or WordPress bootstrap.
    return b'\n'.join([(folder/'lib/backup_wp_config.php').read_bytes().replace(b'<?php',b'<?php\n'+prefix.encode(),1),
        process_helpers.removeprefix(b'<?php'),
        (folder/'lib/backup_capture_safety.php').read_bytes().removeprefix(b'<?php'),
        (folder/'lib/backup_tls_bridge.php').read_bytes().removeprefix(b'<?php'),
        (folder/'remote_capture.php').read_bytes().removeprefix(b'<?php')])

def main():
    print('ORGAVELA_REAL_CAPTURE=DISABLED')
    return 78

if __name__=='__main__': raise SystemExit(main())
