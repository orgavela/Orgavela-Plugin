"""Fictitious source stream only; never reads an existing WordPress installation."""
import gzip
import hashlib
import io
import json
from pathlib import Path
import tarfile

SQL=b'-- SYNTHETIC ORGAVELA PORT FIXTURE ONLY\nCREATE TABLE fixture_only (id INT);\n'
MARKER=b'SYNTHETIC ORGAVELA PORT FIXTURE ONLY\n'

def tar_bytes(entries):
    out=io.BytesIO()
    with tarfile.open(fileobj=out,mode='w:') as tar:
        for name,body in entries:
            item=tarfile.TarInfo(name)
            if body is None: item.type=tarfile.DIRTYPE;tar.addfile(item)
            else: item.size=len(body);tar.addfile(item,io.BytesIO(body))
    return out.getvalue()

def snapshot(path,mutate=None,extra_wordpress=()):
    # No real config/DB names, credentials, users or participant data.
    wordpress=gzip.compress(tar_bytes([
        ('wp-config.php',b'<?php /* synthetic config, never executed */'),
        ('wp-load.php',b'<?php /* synthetic no-bootstrap fixture */'),
        ('index.php',b'<?php /* synthetic entry fixture */'),
        ('wp-content/uploads',None),('wp-content/uploads/fixture.txt',MARKER),
        *extra_wordpress]))
    database=gzip.compress(SQL)
    evidence={'schema':'orgavela-ssh-capture-evidence/v3','capture_mode':'production','database_tls_verified':True,'database_server_certificate_verified':True,
        'filesystem_consistency':'quiesced','database_consistency':'single-transaction',
        'scope':dict.fromkeys(('database','wordpress_filesystem','uploads','wp_config'),True),
        'quiescence':dict.fromkeys(('wordpress_maintenance','all_table_read_locks','stable_file_hashes','archive_matches_inventory',
            'maintenance_removed','maintenance_origin_verified','source_origin_recovered'),True),
        'table_count':52,'filesystem_entries':5,
        'components':{'database':{'sha256':hashlib.sha256(database).hexdigest()},
                      'wordpress':{'sha256':hashlib.sha256(wordpress).hexdigest()}}}
    evidence['quiescence']['maintenance_seconds']=2.5
    if mutate: mutate(evidence)
    Path(path).write_bytes(tar_bytes([('database.sql.gz',database),('wordpress-files.tar.gz',wordpress),
        ('capture-evidence.json',json.dumps(evidence).encode())]))
    return evidence
