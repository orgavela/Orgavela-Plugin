"""Disposable TLS CA/name/dump fixture; no source, source credentials or public ports."""
from datetime import datetime,timedelta,timezone
import io
import json
from pathlib import Path
import re
import secrets
import tarfile
import time
from cryptography import x509
from cryptography.hazmat.primitives import hashes,serialization
from cryptography.hazmat.primitives.asymmetric import rsa
from cryptography.x509.oid import NameOID
from isolated_restore import docker,WP_IMAGE,DB_IMAGE


def fixture_certificates(host):
    now=datetime.now(timezone.utc)
    ca_key=rsa.generate_private_key(public_exponent=65537,key_size=2048)
    ca_name=x509.Name([x509.NameAttribute(NameOID.COMMON_NAME,'Synthetic fixture CA only')])
    ca=(x509.CertificateBuilder().subject_name(ca_name).issuer_name(ca_name).public_key(ca_key.public_key()).serial_number(x509.random_serial_number()).not_valid_before(now-timedelta(minutes=1)).not_valid_after(now+timedelta(hours=1)).add_extension(x509.BasicConstraints(ca=True,path_length=0),critical=True).sign(ca_key,hashes.SHA256()))
    key=rsa.generate_private_key(public_exponent=65537,key_size=2048)
    cert=(x509.CertificateBuilder().subject_name(x509.Name([x509.NameAttribute(NameOID.COMMON_NAME,host)])).issuer_name(ca_name).public_key(key.public_key()).serial_number(x509.random_serial_number()).not_valid_before(now-timedelta(minutes=1)).not_valid_after(now+timedelta(hours=1)).add_extension(x509.SubjectAlternativeName([x509.DNSName(host)]),critical=False).add_extension(x509.BasicConstraints(ca=False,path_length=None),critical=True).sign(ca_key,hashes.SHA256()))
    return {'ca.pem':ca.public_bytes(serialization.Encoding.PEM),'server.pem':cert.public_bytes(serialization.Encoding.PEM),'server-key.pem':key.private_bytes(serialization.Encoding.PEM,serialization.PrivateFormat.TraditionalOpenSSL,serialization.NoEncryption())}


def run():
    name='orgavela-tls-'+secrets.token_hex(6);host=name+'-db';wrong=name+'-wrong';volume=name+'-certs';made=[]
    password=secrets.token_urlsafe(24);certs=fixture_certificates(host)
    try:
        docker('network','create','--internal',name);made.append(('network',name))
        docker('volume','create',volume);made.append(('volume',volume))
        uid=docker('run','--rm','--network','none','--entrypoint','id',DB_IMAGE,'-u','mysql').decode().strip()
        if not re.fullmatch(r'[1-9][0-9]{0,5}',uid):raise RuntimeError('fixture account')
        bundle=io.BytesIO()
        with tarfile.open(fileobj=bundle,mode='w:') as archive:
            for filename,data in certs.items():
                item=tarfile.TarInfo(filename);item.size=len(data);item.mode=0o600;item.uid=int(uid);item.gid=int(uid);archive.addfile(item,io.BytesIO(data))
        docker('run','--rm','-i','--network','none','--user','0:0','-v',volume+':/tls','--entrypoint','sh',WP_IMAGE,'-c','tar -xf - -C /tls',data=bundle.getvalue())
        docker('run','-d','--name',host,'--network',name,'--network-alias',wrong,'-e','MARIADB_DATABASE=fixture','-e','MARIADB_USER=fixture','-e','MARIADB_PASSWORD='+password,'-e','MARIADB_RANDOM_ROOT_PASSWORD=1','--tmpfs','/var/lib/mysql:rw,nosuid,nodev,size=256m','-v',volume+':/tls:ro',DB_IMAGE,'--ssl-ca=/tls/ca.pem','--ssl-cert=/tls/server.pem','--ssl-key=/tls/server-key.pem','--require-secure-transport=ON');made.append(('container',host))
        for _ in range(60):
            try:docker('exec',host,'healthcheck.sh','--connect','--innodb_initialized');break
            except RuntimeError:time.sleep(1)
        else:raise RuntimeError('TLS fixture unavailable')
        docker('run','-d','--name',name,'--network',name,'--user','0:0','--entrypoint','sh',WP_IMAGE,'-c','sleep 240');made.append(('container',name))
        lib=Path(__file__).with_name('lib')
        helper=(lib/'backup_process_safety.php').read_bytes()+b'\n'+(lib/'backup_capture_safety.php').read_bytes().removeprefix(b'<?php')
        php=helper+b'\necho json_encode(["tls"=>orgavela_database_tls_args(),"dump"=>orgavela_dump_client_args(shell_exec("mariadb-dump --no-defaults --help"),false)],JSON_THROW_ON_ERROR);'
        args=json.loads(docker('exec','-i',name,'php',data=php))
        def client(command,*,target=host):
            config=('[client]\nuser=fixture\npassword='+password+'\nhost='+target+'\n').encode()
            return docker('exec','-i',name,'sh','-c','exec 3<&0; exec "$@"','fixture-client',*command,data=config)
        query=['mariadb','--defaults-file=/proc/self/fd/3',*args['tls'],'--connect-timeout=5','--batch','--skip-column-names','--execute',"SHOW SESSION STATUS LIKE 'Ssl_cipher'",'--','fixture']
        try:client(query)
        except RuntimeError:pass
        else:raise RuntimeError('untrusted fixture CA accepted')
        # Only this disposable client container trust store is extended; never host/Source.
        docker('exec','-i',name,'sh','-c','cat >> /etc/ssl/certs/ca-certificates.crt',data=certs['ca.pem'])
        cipher=client(query)
        if not re.fullmatch(rb'Ssl_cipher\t[A-Za-z0-9_-]+\n',cipher):raise RuntimeError('fixture TLS not negotiated')
        try:client(query,target=wrong)
        except RuntimeError:pass
        else:raise RuntimeError('wrong TLS hostname accepted')
        client(['mariadb','--defaults-file=/proc/self/fd/3',*args['tls'],'--execute',"CREATE TABLE fixture_only(id INT PRIMARY KEY, marker VARCHAR(20)); INSERT INTO fixture_only VALUES(1,'synthetic-only');",'--','fixture'])
        dump=client([*args['dump'],'--','fixture'])
        if b'CREATE TABLE `fixture_only`' not in dump or b'synthetic-only' not in dump:raise RuntimeError('verified TLS dump incomplete')
        return {'synthetic_tls_and_dump':True,'untrusted_ca_refused':True,'wrong_hostname_refused':True,'explicit_system_ca_succeeded':True,'same_verified_flags_for_probe_and_dump':True,'production_source_accessed':False,'public_ports':0}
    finally:
        for kind,resource in reversed(made):
            if kind=='container':docker('rm','-f',resource)
            else:docker(kind,'rm',resource)


if __name__=='__main__':print(json.dumps(run(),sort_keys=True))
