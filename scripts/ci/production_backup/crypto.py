"""Dreamloud-style private packaging and exact-recipient OpenPGP inspection.

Library used only by offline fixtures in this candidate. No network or live CLI.
"""
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess
import tarfile
import tempfile
from process import run, ProcessError

PRIMARY_FPR='7C9DC60D8E1B293E514696EB333D15A4D8BA625C'
SUBKEY_FPR='CF5D8A8498F65C6D894ECE2FD9E58CC6484AF1DE'
KEY_FILE=Path(__file__).resolve().parents[3]/'.github/backup/orgavela-backup-candidate-public.asc'
MAX_CIPHER=140*1024*1024

class PackageError(ValueError): pass

def digest_file(path):
    h=hashlib.sha256()
    with Path(path).open('rb') as stream:
        while chunk:=stream.read(1024*1024): h.update(chunk)
    return h.hexdigest()

def regular(path, limit):
    path=Path(path)
    if path.is_symlink() or not path.is_file() or path.stat().st_nlink!=1 or not 0<path.stat().st_size<=limit:
        raise PackageError('unsafe file')
    return path

def command(args,home,*,data=None,ok=True):
    result=run(['/usr/bin/gpg','--homedir',str(home),'--batch','--no-tty','--no-options','--no-autostart']+args,
        input_data=data or b'',timeout=90,
        env={'PATH':'/usr/bin:/bin','HOME':str(home),'LC_ALL':'C'})
    if ok and result.returncode: raise PackageError('OpenPGP operation refused')
    return result

def inspect_cipher(path, subkey=SUBKEY_FPR):
    path=regular(path,MAX_CIPHER)
    with tempfile.TemporaryDirectory(prefix='orgavela-public-packets-') as tmp:
        result=command(['--list-packets',str(path)],Path(tmp),ok=False)
        text=result.stdout.decode('ascii','replace')
        ids=re.findall(r'^:pubkey enc packet:.*keyid ([A-F0-9]{16})\s*$',text,re.MULTILINE)
        tags=re.findall(r'^:([^:]+):',text,re.MULTILINE)
        if ids!=[subkey[-16:]] or tags!=['pubkey enc packet','encrypted data packet']:
            raise PackageError('ciphertext recipient/packet policy refused')
    return True

def package(components,output,backup_id,*,key_file=KEY_FILE,primary=PRIMARY_FPR,subkey=SUBKEY_FPR):
    """Private supplied components -> exactly three local encrypted/public assets.

    Explicit key arguments are dependency injection for temporary-key offline tests;
    there is no CLI/environment override and no live producer in this candidate.
    """
    from github_api import reject_mixed_credentials
    reject_mixed_credentials(os.environ,'source')
    from collector import validate_components
    components,output=Path(components),Path(output)
    if not re.fullmatch(r'orgavela-(?:synthetic|wordpress)-[A-Za-z0-9_-]{3,100}',backup_id):
        raise PackageError('backup identity')
    if output.is_symlink() or not output.is_dir() or any(output.iterdir()) or output.stat().st_mode & 0o077:
        raise PackageError('private empty output directory required')
    expected={'database.sql.gz','wordpress-files.tar.gz','capture-evidence.json'}
    if components.is_symlink() or not components.is_dir() or {p.name for p in components.iterdir()}!=expected or components.stat().st_mode & 0o077:
        raise PackageError('private exact component directory required')
    for name in expected: regular(components/name,128*1024*1024 if name!='capture-evidence.json' else 16384)
    evidence=validate_components(components)
    key=regular(key_file,16384).read_bytes()
    if b'-----BEGIN PGP PUBLIC KEY BLOCK-----' not in key or b'PRIVATE KEY' in key:
        raise PackageError('public-only key required')
    if not all(re.fullmatch(r'[A-F0-9]{40}',v) for v in (primary,subkey)):
        raise PackageError('key identities')
    synthetic=backup_id.startswith('orgavela-synthetic-')
    made=[]
    try:
        with tempfile.TemporaryDirectory(prefix='orgavela-pack-',dir=output.parent) as tmp:
            stage=Path(tmp);home=stage/'gnupg';home.mkdir(mode=0o700)
            # Inspect and encrypt directly from public material: no key-agent or
            # private-key store is needed, including on restricted workers.
            key_path=stage/'recipient-public.asc';key_path.write_bytes(key)
            inventory=command(['--with-colons','--fingerprint','--show-keys',str(key_path)],home).stdout.decode('ascii')
            rows=[line.split(':') for line in inventory.splitlines()]
            if any(row[0] in ('sec','ssb') for row in rows): raise PackageError('private material forbidden')
            if any(row[0] in ('pub','sub') and row[1] in ('r','e','d') for row in rows): raise PackageError('invalid public recipient')
            identities=[row[9] for row in rows if row[0]=='fpr']
            if identities!=[primary,subkey] or sum(row[0]=='pub' for row in rows)!=1 or sum(row[0]=='sub' for row in rows)!=1:
                raise PackageError('public recipient identity mismatch')
            internal={'schema':'orgavela-backup-package/v1','backup_id':backup_id,
                'mode':'synthetic' if synthetic else 'production','scope':evidence['scope'],
                'components':{name:{'sha256':digest_file(components/name),'bytes':(components/name).stat().st_size}
                    for name in ('database.sql.gz','wordpress-files.tar.gz','capture-evidence.json')},
                'encryption':{'type':'OpenPGP-public-key','public_key_fingerprint':primary,'encryption_subkey_fingerprint':subkey,'private_key_in_producer':False}}
            manifest=stage/'manifest.json';manifest.write_text(json.dumps(internal,sort_keys=True))
            plain=stage/'private.tar'
            with tarfile.open(plain,'x:') as tar:
                tar.add(manifest,arcname='manifest.json',recursive=False)
                for name in sorted(expected): tar.add(components/name,arcname=name,recursive=False)
            archive=backup_id+'.tar.gpg';cipher=stage/archive
            command(['--trust-model','always','--recipient-file',str(key_path),'--output',str(cipher),'--encrypt',str(plain)],home)
            inspect_cipher(cipher,subkey)
            sha=digest_file(cipher);size=cipher.stat().st_size
            public={'schema':'orgavela-github-release-backup-public-manifest/v1','backup_id':backup_id,
                'synthetic':synthetic,'contains_production_data':not synthetic,'archive_sha256':sha,'archive_bytes':size,
                'public_key_fingerprint':primary,'encrypted':True,'source_components_encrypted':True}
            side=stage/(archive+'.sha256');side.write_text(sha+'  '+archive+'\n')
            pub=stage/'public.json';pub.write_text(json.dumps(public,sort_keys=True)+'\n')
            for source,name in [(cipher,archive),(side,archive+'.sha256'),(pub,'manifest.json')]:
                dest=output/name
                with dest.open('xb') as target,source.open('rb') as content:
                    made.append(dest);os.chmod(dest,0o600)
                    while chunk:=content.read(1024*1024): target.write(chunk)
            return public
    except Exception:
        for path in made: path.unlink(missing_ok=True)
        raise
