"""Disposable TLS CA/name/dump fixture; no source, source credentials or public ports."""
from datetime import datetime,timedelta,timezone
import io
import base64
import shutil
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
        docker('exec','-i',name,'sh','-c','cat >> /etc/passwd',data=b'fixture:x:33:33::/tmp:/bin/sh\n')
        lib=Path(__file__).with_name('lib')
        helper=(lib/'backup_process_safety.php').read_bytes()+b'\n'+(lib/'backup_capture_safety.php').read_bytes().removeprefix(b'<?php')
        php=helper+b'\necho json_encode(["tls"=>orgavela_database_tls_args(),"dump"=>orgavela_dump_client_args(shell_exec("mariadb-dump --no-defaults --help"),false)],JSON_THROW_ON_ERROR);'
        # Emulate provider system UID mapping; Source remains a different unprivileged UID.
        binary=docker('exec',name,'php','-r','echo realpath(PHP_BINARY);').decode()
        if not binary.startswith('/usr/') or not re.fullmatch(r'/[A-Za-z0-9_./-]+',binary):raise RuntimeError('fixture binary path')
        ca='/etc/ssl/certs/ca-certificates.crt'
        docker('exec',name,'chown','12345:12345',binary)
        docker('exec',name,'chown','23456:23456',ca)
        def arguments():return json.loads(docker('exec','-i','--user','33:33',name,'php',data=php))
        args=arguments()
        for target,operation,restore in ((ca,['chown','33:33',ca],['chown','23456:23456',ca]),(ca,['chmod','666',ca],['chmod','644',ca]),('/etc/ssl/certs',['chmod','777','/etc/ssl/certs'],['chmod','755','/etc/ssl/certs'])):
            docker('exec',name,*operation)
            try:
                try:arguments()
                except RuntimeError:pass
                else:raise RuntimeError('unsafe fixture trust location accepted')
            finally:docker('exec',name,*restore)
        args=arguments()
        def client(command,*,target=host):
            config=('[client]\nuser=fixture\npassword='+password+'\nhost='+target+'\n').encode()
            return docker('exec','-i',name,'sh','-c','exec 3<&0; exec "$@"','fixture-client',*command,data=config)
        query=['mariadb','--defaults-file=/proc/self/fd/3',*args['tls'],'--connect-timeout=5','--batch','--skip-column-names','--execute',"SHOW SESSION STATUS LIKE 'Ssl_cipher'",'--','fixture']
        try:client(query)
        except RuntimeError:pass
        else:raise RuntimeError('untrusted fixture CA accepted')
        # Approved mode still requires encrypted per-session continuity; the peer is unauthenticated.
        from database_tls_policy import APPROVAL,MODE,EXTRA
        fp=x509.load_pem_x509_certificate(certs['server.pem']).fingerprint(hashes.SHA1()).hex().upper()
        unverified_php=php.replace(b'<?php',b"<?php\nconst ORGAVELA_DATABASE_TLS_UNVERIFIED_BACKUP_APPROVAL = '"+APPROVAL.encode()+b"';",1).replace(b'orgavela_database_tls_args()',('orgavela_database_tls_args("'+fp+'")').encode()).replace(b'--help"),false)',('--help"),false,"'+fp+'")').encode())
        for denied in (unverified_php.replace(("const ORGAVELA_DATABASE_TLS_UNVERIFIED_BACKUP_APPROVAL = '"+APPROVAL+"';").encode(),b"const ORGAVELA_DATABASE_TLS_UNVERIFIED_BACKUP_APPROVAL = '"+b'0'*40+b"';"),unverified_php.replace(('orgavela_database_tls_args("'+fp+'")').encode(),b'orgavela_database_tls_args()')):
            try:docker('exec','-i','--user','33:33',name,'php',data=denied)
            except RuntimeError:pass
            else:raise RuntimeError('unapproved or unbound exception accepted')
        unverified=json.loads(docker('exec','-i','--user','33:33',name,'php',data=unverified_php))
        uq=['mariadb','--defaults-file=/proc/self/fd/3',*unverified['tls'],'--batch','--skip-column-names','--execute',"SHOW SESSION STATUS LIKE 'Ssl_cipher'",'--','fixture']
        if not re.fullmatch(rb'Ssl_cipher\t[A-Za-z0-9_-]+\n',client(uq)):raise RuntimeError('unverified TLS missing cipher')
        if not re.fullmatch(rb'Ssl_cipher\t[A-Za-z0-9_-]+\n',client(uq,target=wrong)):raise RuntimeError('exception unexpectedly authenticated hostname')
        wrong_fp=[('--ssl-fp='+'0'*40) if a.startswith('--ssl-fp=') else a for a in uq]
        try:client(wrong_fp)
        except RuntimeError:pass
        else:raise RuntimeError('peer continuity not enforced')
        # Full actual assembled resource branch: no production source and no simulated TLS flags.
        from collector import build_remote_candidate
        from unittest.mock import patch
        import transport
        names=['options','posts','users','usermeta','comments','uop_organizations','uop_persons','uop_consent_records','uop_registrations','uop_waitlist_entries','only']
        names+=['synthetic_'+str(i) for i in range(len(names),52)]
        sql=''.join('CREATE TABLE fixture_'+n+'(id INT PRIMARY KEY,option_name VARCHAR(40),option_value VARCHAR(100)) ENGINE=InnoDB;' for n in names)
        sql+="INSERT INTO fixture_options VALUES(1,'siteurl','https://orgavela.com'),(2,'home','https://orgavela.com');"
        client(['mariadb','--defaults-file=/proc/self/fd/3',*unverified['tls'],'--execute',sql,'--','fixture'])
        setup=("<?php mkdir('/tmp/o03-tls-source',0700);mkdir('/tmp/o03-tls-source/wp',0700);mkdir('/tmp/o03-tls-source/wp/wp-content',0700);mkdir('/tmp/o03-tls-source/wp/wp-content/uploads',0700);"
               "file_put_contents('/tmp/o03-tls-source/wp/wp-config.php',"+"base64_decode('"+base64.b64encode(("<?php define('DB_NAME','fixture');define('DB_USER','fixture');define('DB_PASSWORD',"+repr(password)+");define('DB_HOST',"+repr(host)+");$table_prefix='fixture_';").encode()).decode()+"')"+");"
               "foreach(['index.php','wp-load.php']as$f)file_put_contents('/tmp/o03-tls-source/wp/'.$f,'<?php // inert');")
        docker('exec','-i','--user','33:33',name,'php',data=setup.encode())
        program=build_remote_candidate('/tmp/o03-tls-source/wp','fixture').replace(b'ENTRYPOINT_ENABLED = false;',b'ENTRYPOINT_ENABLED = true;',1)
        program=program.replace(b'<?php',b"<?php\nconst ORGAVELA_DATABASE_TLS_UNVERIFIED_BACKUP_APPROVAL = '"+APPROVAL.encode()+b"';",1)
        class Binding:
            def argv(self):return [shutil.which('docker'),'--host=unix:///var/run/docker.sock','exec','-i','--user','33:33',name,'php']
        with patch.object(transport,'live_program',return_value=program),patch.dict('os.environ',{'ORGAVELA_DATABASE_TLS_MODE':MODE}):
            phases=[]
            try:receipt=transport.resource_probe(Binding(),'database',password='fixture',maintenance_approved=True,phase_callback=phases.append)
            except ValueError:raise RuntimeError('synthetic unverified resource refused: '+str(phases)) from None
        if receipt['server_certificate_verified'] is not False or receipt['database_tls_exception_authorization']!=APPROVAL:raise RuntimeError('false verification evidence')
        plain=host+'-plain'
        docker('run','-d','--name',plain,'--network',name,'-e','MARIADB_DATABASE=fixture','-e','MARIADB_USER=fixture','-e','MARIADB_PASSWORD='+password,'-e','MARIADB_RANDOM_ROOT_PASSWORD=1','--tmpfs','/var/lib/mysql:rw,nosuid,nodev,size=256m',DB_IMAGE,'--skip-ssl');made.append(('container',plain))
        for _ in range(60):
            try:docker('exec',plain,'healthcheck.sh','--connect','--innodb_initialized');break
            except RuntimeError:time.sleep(1)
        else:raise RuntimeError('plaintext fixture unavailable')
        php_plain=("<?php mysqli_report(MYSQLI_REPORT_OFF);$db=mysqli_init();$db->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT,false);$db->ssl_set(null,null,null,null,null);$ok=@$db->real_connect("+repr(plain)+",'fixture',"+repr(password)+",'fixture',3306,null,MYSQLI_CLIENT_SSL|MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT);echo json_encode(['connected'=>$ok]);")
        if json.loads(docker('exec','-i',name,'php',data=php_plain.encode()))['connected']:raise RuntimeError('control client plaintext fallback accepted')
        for command in (uq,[*unverified['dump'],'--','fixture']):
            try:client(command,target=plain)
            except RuntimeError:pass
            else:raise RuntimeError('plaintext fallback accepted')
        # Only this disposable client container trust store is extended; never host/Source.
        docker('exec','-i',name,'sh','-c','cat >> /etc/ssl/certs/ca-certificates.crt',data=certs['ca.pem'])
        cipher=client(query)
        if not re.fullmatch(rb'Ssl_cipher\t[A-Za-z0-9_-]+\n',cipher):raise RuntimeError('fixture TLS not negotiated')
        try:client(query,target=wrong)
        except RuntimeError:pass
        else:raise RuntimeError('wrong TLS hostname accepted')
        client(['mariadb','--defaults-file=/proc/self/fd/3',*args['tls'],'--execute',"INSERT INTO fixture_only VALUES(1,'marker','synthetic-only');",'--','fixture'])
        dump=client([*args['dump'],'--','fixture'])
        if b'CREATE TABLE `fixture_only`' not in dump or b'synthetic-only' not in dump:raise RuntimeError('verified TLS dump incomplete')
        return {'synthetic_tls_and_dump':True,'approved_unverified_tls_and_actual_dump':True,'assembled_unverified_evidence_false':True,'unverified_plaintext_client_and_dump_refused':True,'unverified_wrong_peer_refused':True,'nonzero_system_owner_succeeded':True,'separate_protected_system_owners_succeeded':True,'source_owned_or_writable_or_replaceable_trust_refused':True,'untrusted_ca_refused':True,'wrong_hostname_refused':True,'explicit_system_ca_succeeded':True,'same_verified_flags_for_probe_and_dump':True,'production_source_accessed':False,'public_ports':0}
    finally:
        for kind,resource in reversed(made):
            if kind=='container':docker('rm','-f',resource)
            else:docker(kind,'rm',resource)


if __name__=='__main__':print(json.dumps(run(),sort_keys=True))
