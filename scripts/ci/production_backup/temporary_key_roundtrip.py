"""Local fictitious recovery test; disposable private key stays in memory only."""
import hashlib
import io
import json
from pathlib import Path
import tarfile
import tempfile
import warnings
import pgpy
from pgpy.constants import PubKeyAlgorithm, KeyFlags, HashAlgorithm, SymmetricKeyAlgorithm, CompressionAlgorithm
import collector
import crypto
import fixtures

def exercise():
    key=pgpy.PGPKey.new(PubKeyAlgorithm.RSAEncryptOrSign,2048)
    key.add_uid(pgpy.PGPUID.new('Disposable synthetic fixture'),usage={KeyFlags.Certify,KeyFlags.Sign},
        hashes=[HashAlgorithm.SHA256],ciphers=[SymmetricKeyAlgorithm.AES256],compression=[CompressionAlgorithm.ZLIB])
    sub=pgpy.PGPKey.new(PubKeyAlgorithm.RSAEncryptOrSign,2048)
    key.add_subkey(sub,usage={KeyFlags.EncryptCommunications,KeyFlags.EncryptStorage})
    with tempfile.TemporaryDirectory(prefix='orgavela-temporary-key-') as tmp:
        root=Path(tmp);public=root/'public.asc';public.write_text(str(key.pubkey))
        source=root/'source.tar';fixtures.snapshot(source)
        work=root/'components';work.mkdir(mode=0o700);collector.accept_snapshot(source,work)
        output=root/'ciphertext';output.mkdir(mode=0o700)
        bid='orgavela-synthetic-disposable-key'
        crypto.package(work,output,bid,key_file=public,primary=str(key.fingerprint),subkey=str(sub.fingerprint))
        encrypted=pgpy.PGPMessage.from_blob((output/(bid+'.tar.gpg')).read_bytes())
        with warnings.catch_warnings():
            warnings.simplefilter('ignore',UserWarning)
            clear=bytes(key.decrypt(encrypted).message)
        with tarfile.open(fileobj=io.BytesIO(clear)) as archive:
            assert set(archive.getnames())=={'manifest.json','database.sql.gz','wordpress-files.tar.gz','capture-evidence.json'}
            manifest=json.loads(archive.extractfile('manifest.json').read())
            assert manifest['mode']=='synthetic'
            for name,expected in manifest['components'].items():
                data=archive.extractfile(name).read()
                assert len(data)==expected['bytes'] and hashlib.sha256(data).hexdigest()==expected['sha256']
                assert data==(work/name).read_bytes()
        return {'synthetic_temporary_key_roundtrip':True,'component_hashes_verified':3,
            'owner_private_key_used':False,'actual_site_restore_tested':False,'remote_calls':0}

if __name__=='__main__': print(json.dumps(exercise(),sort_keys=True))
