#!/usr/bin/env python3
"""Ciphertext-only immutable release publisher with two complete remote readbacks."""
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess
import tempfile
import urllib.request
import urllib.error
from urllib.parse import urlparse, quote
from github_api import WriterError, NoRedirect, REPOSITORY, installation_token, verify_repository, request_json, reject_mixed_credentials
from crypto import PRIMARY_FPR, SUBKEY_FPR, inspect_cipher, regular
from collector import unique_pairs



def opener():
    return urllib.request.build_opener(NoRedirect())


def assets(work, *, synthetic=False):
    reject_mixed_credentials(os.environ,'writer')
    work=Path(work)
    if not work.is_dir() or work.is_symlink(): raise WriterError('invalid ciphertext directory')
    public=work/'manifest.json'
    if public.is_symlink() or not public.is_file() or not 0<public.stat().st_size<=16384:
        raise WriterError('invalid public manifest file')
    manifest=json.loads(public.read_text(),object_pairs_hook=unique_pairs)
    if not isinstance(manifest,dict): raise WriterError('invalid public manifest schema')
    bid=manifest.get('backup_id','')
    if type(synthetic) is not bool or not bid.startswith('orgavela-synthetic-' if synthetic else 'orgavela-wordpress-'): raise WriterError('artifact mode mismatch')
    if not re.fullmatch(r'orgavela-(synthetic|wordpress)-[A-Za-z0-9_-]{3,100}',bid): raise WriterError('invalid production backup ID')
    archive=bid+'.tar.gpg'; sidecar=archive+'.sha256'
    if set(p.name for p in work.iterdir())!={archive,sidecar,'manifest.json'}: raise WriterError('ciphertext directory contains unexpected files')
    result={}
    for name in (archive,sidecar,'manifest.json'):
        p=work/name
        if p.stat().st_nlink!=1: raise WriterError('asset hardlink refused')
        if p.is_symlink() or not p.is_file() or not 0<p.stat().st_size<=536870912: raise WriterError('unsafe or oversized asset')
        if name!=archive and p.stat().st_size>16384: raise WriterError('oversized public metadata')
        hasher=hashlib.sha256()
        with p.open('rb') as stream:
            while chunk:=stream.read(1024*1024): hasher.update(chunk)
        digest=hasher.hexdigest()
        result[name]={'path':p,'sha256':digest,'size':p.stat().st_size}
    expected={'schema':'orgavela-github-release-backup-public-manifest/v1','backup_id':bid,
        'synthetic':synthetic,'contains_production_data':not synthetic,'archive_sha256':result[archive]['sha256'],
        'archive_bytes':result[archive]['size'],'public_key_fingerprint':PRIMARY_FPR,
        'encrypted':True,'source_components_encrypted':True}
    if manifest!=expected or any(type(manifest.get(k)) is not type(v) for k,v in expected.items()): raise WriterError('public manifest policy mismatch')
    if (work/sidecar).read_text()!=result[archive]['sha256']+'  '+archive+'\n': raise WriterError('sidecar mismatch')
    try: inspect_cipher(work/archive,SUBKEY_FPR)
    except ValueError: raise WriterError('ciphertext packet recipient refused') from None
    return bid,result


def upload(token, release_id, name, entry):
    url='https://uploads.github.com/repos/'+REPOSITORY+'/releases/'+str(release_id)+'/assets?name='+quote(name,safe='')
    request=urllib.request.Request(url,method='POST',data=entry['path'].read_bytes(),headers={
        'Authorization':'Bearer '+token,'Accept':'application/vnd.github+json',
        'Content-Type':'application/octet-stream','X-GitHub-Api-Version':'2026-03-10','User-Agent':'orgavela-backup-writer'})
    try:
        with opener().open(request,timeout=120) as response: return json.loads(response.read(65536))
    except (OSError,ValueError): raise WriterError('asset upload failed; response suppressed') from None


def readback_asset(token, asset_id, expected):
    request=urllib.request.Request('https://api.github.com/repos/'+REPOSITORY+'/releases/assets/'+str(asset_id),headers={
        'Authorization':'Bearer '+token,'Accept':'application/octet-stream',
        'X-GitHub-Api-Version':'2026-03-10','User-Agent':'orgavela-backup-writer'})
    try:
        try: response=opener().open(request,timeout=60)
        except urllib.error.HTTPError as redirect:
            if redirect.code not in (301,302,303,307,308): raise WriterError('asset download API failed') from None
            url=redirect.headers.get('Location',''); parsed=urlparse(url)
            if parsed.scheme!='https' or parsed.hostname not in ('release-assets.githubusercontent.com','objects.githubusercontent.com') or parsed.port not in (None,443) or parsed.username or parsed.password:
                raise WriterError('unexpected asset redirect')
            # Never forward the installation token to signed storage URLs.
            response=opener().open(urllib.request.Request(url,headers={'User-Agent':'orgavela-backup-readback'}),timeout=60)
        digest=hashlib.sha256(); size=0
        with response:
            while chunk:=response.read(1024*1024):
                size+=len(chunk)
                if size>expected['size']: raise WriterError('remote asset size mismatch')
                digest.update(chunk)
        if size!=expected['size'] or digest.hexdigest()!=expected['sha256']: raise WriterError('remote asset hash mismatch')
    except OSError: raise WriterError('asset readback failed; details suppressed') from None


def verify_release(token, rid, bid, expected, *, draft):
    release=request_json('/repos/'+REPOSITORY+'/releases/'+str(rid),token)
    if release.get('id')!=rid or release.get('tag_name')!=bid or release.get('draft') is not draft:
        raise WriterError('release identity/state mismatch')
    if not draft and release.get('immutable') is not True: raise WriterError('published release is not immutable')
    remote=release.get('assets',[])
    if len(remote)!=len(expected) or {a.get('name') for a in remote}!=set(expected): raise WriterError('remote asset set mismatch')
    seen=set()
    for asset in remote:
        aid=asset.get('id'); entry=expected[asset['name']]
        if type(aid) is not int or aid<1 or aid in seen or asset.get('state')!='uploaded' or asset.get('size')!=entry['size']:
            raise WriterError('remote asset metadata mismatch')
        seen.add(aid)
        if asset.get('digest') != 'sha256:'+entry['sha256']: raise WriterError('provider digest mismatch')
        readback_asset(token,aid,entry)
    return release


def publish(token,bid,entries):
    path='/repos/'+REPOSITORY+'/releases'
    try: request_json(path+'/tags/'+bid,token)
    except WriterError as e:
        if e.status!=404: raise
    else: raise WriterError('backup release already exists; refusing overwrite')
    try: request_json('/repos/'+REPOSITORY+'/git/ref/tags/'+bid,token)
    except WriterError as error:
        if error.status!=404: raise
    else: raise WriterError('backup tag already exists; refusing overwrite')
    created=request_json(path,token,method='POST',data={'tag_name':bid,'name':bid,'target_commitish':'main',
        'body':'Client-side OpenPGP encrypted production backup. All assets fully read back before and after publication.',
        'draft':True,'prerelease':False,'make_latest':'false'})
    rid=created.get('id')
    if type(rid) is not int or rid<1 or created.get('draft') is not True or created.get('tag_name')!=bid:
        raise WriterError('draft creation result mismatch')
    print(json.dumps({'schema':'orgavela-backup-draft-created/v1','release_id':rid,'backup_id':bid},sort_keys=True),flush=True)
    for name,entry in entries.items():
        result=upload(token,rid,name,entry)
        if result.get('name')!=name or result.get('size')!=entry['size'] or result.get('state')!='uploaded': raise WriterError('upload receipt mismatch')
    verify_release(token,rid,bid,entries,draft=True)
    request_json(path+'/'+str(rid),token,method='PATCH',data={'draft':False,'make_latest':'false'})
    verify_release(token,rid,bid,entries,draft=False)
    return {'schema':'orgavela-production-release-evidence/v1','backup_id':bid,'release_id':rid,
        'repository':REPOSITORY,'immutable_verified':True,'draft_full_readback':True,
        'published_full_readback':True,'asset_count':len(entries),'restore_tested':False}


def main():
    # No contexts, credentials or network call can activate this offline candidate.
    print('ORGAVELA_REAL_WRITER=DISABLED')
    return 78

if __name__=='__main__': raise SystemExit(main())
