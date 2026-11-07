"""Execute assembled abort branches on disposable DB/files; Docker channel, simulated origin."""
import json
import os
from pathlib import Path
import secrets
import shutil
import tempfile
import time
from unittest.mock import patch

from collector import build_remote_candidate
from isolated_restore import docker, WP_IMAGE, DB_IMAGE
import transport


def run():
    name='orgavela-protocol-'+secrets.token_hex(6)
    resources=[]
    with tempfile.TemporaryDirectory(prefix='orgavela-protocol-') as directory:
        root=Path(directory);state=root/'state';state.mkdir(mode=0o700)
        wp=state/'wp';wp.mkdir(mode=0o700);(wp/'wp-content/uploads').mkdir(parents=True)
        password=secrets.token_urlsafe(24)
        env=root/'db.env';env.write_text('MARIADB_DATABASE=fixture\nMARIADB_USER=fixture\nMARIADB_PASSWORD='+password+'\nMARIADB_RANDOM_ROOT_PASSWORD=1\n');env.chmod(0o600)
        (wp/'wp-config.php').write_text("<?php define('DB_NAME','fixture'); define('DB_USER','fixture'); define('DB_PASSWORD',"+repr(password)+"); define('DB_HOST','"+name+"-db'); $table_prefix='fixture_';")
        for filename in ('index.php','wp-load.php'):(wp/filename).write_text('<?php // inert fixture')
        try:
            docker('network','create','--internal',name);resources.append(('network',name))
            docker('run','-d','--name',name+'-db','--network',name,'--env-file',str(env),'--tmpfs','/var/lib/mysql:rw,nosuid,nodev,size=256m',DB_IMAGE);resources.append(('container',name+'-db'))
            for _ in range(60):
                try:docker('exec',name+'-db','healthcheck.sh','--connect','--innodb_initialized');break
                except RuntimeError:time.sleep(1)
            else:raise RuntimeError('fixture DB unavailable')
            docker('run','-d','--name',name,'--network',name,'--user',str(os.getuid())+':'+str(os.getgid()),'-v',str(state)+':/state','--entrypoint','sh',WP_IMAGE,'-c','sleep 120');resources.append(('container',name))
            # Disposable container passwd only: exercise the real POSIX account gate.
            docker('exec','-i','--user','0',name,'sh','-c','cat >> /etc/passwd',data=('fixture:x:'+str(os.getuid())+':'+str(os.getgid())+'::/tmp:/bin/sh\n').encode())
            names=['options','posts','users','usermeta','comments','uop_organizations','uop_persons','uop_consent_records','uop_registrations','uop_waitlist_entries']
            names+=['synthetic_'+str(i) for i in range(len(names),52)]
            setup="<?php $db=new mysqli('"+name+"-db','fixture',"+repr(password)+",'fixture');"
            for table in names:setup+="$db->query('CREATE TABLE fixture_"+table+"(id INT PRIMARY KEY,option_name VARCHAR(40),option_value VARCHAR(100)) ENGINE=InnoDB')||exit(2);"
            setup+="$db->query(\"INSERT INTO fixture_options VALUES(1,'siteurl','https://orgavela.com'),(2,'home','https://orgavela.com')\")||exit(3);"
            docker('exec','-i',name,'php',data=setup.encode())
            program=build_remote_candidate('/state/wp','fixture').replace(b'ENTRYPOINT_ENABLED = false;',b'ENTRYPOINT_ENABLED = true;',1)
            start=program.index(b'function orgavela_capture_origin_status(): int {')
            end=program.index(b'function orgavela_validate_tables',start)
            # Explicit simulated origin only; no outbound HTTP or production source.
            program=program[:start]+b"function orgavela_capture_origin_status(): int {return is_file('/state/wp/.maintenance')?503:200;}\n"+program[end:]
            # Poison data-export helpers: any fallthrough fails the rehearsal.
            program=program.replace(b'function orgavela_capture_tar_args(string $root): array {',b'function orgavela_capture_tar_args(string $root): array { throw new RuntimeException("fixture export forbidden");')
            signature=b'function orgavela_process_open(array $args,array $spec,array &$pipes,string $work) {'
            if program.count(signature)!=1:raise RuntimeError('fixture process seam drift')
            program=program.replace(signature,signature+b' if($args[0]!=="/bin/sleep")throw new RuntimeException("fixture export forbidden");',1)
            class Binding:
                def argv(self):return [shutil.which('docker'),'--host=unix:///var/run/docker.sock','exec','-i',name,'php']
            binding=Binding()
            with patch.object(transport,'live_program',return_value=program):
                preflight=transport.abort_preflight(binding,password='fixture',maintenance_approved=True)
                exception=transport.controlled_abort(binding,password='fixture',maintenance_approved=True)
                hard=transport.hard_disconnect_abort(binding,password='fixture',maintenance_approved=True)
                phases=[]
                try:transport.resource_probe(binding,'database',password='fixture',maintenance_approved=True,phase_callback=phases.append)
                except ValueError:pass
                else:raise RuntimeError('plaintext database must fail strict TLS probe')
                if 'database_tls_probe_started' not in phases:raise RuntimeError('TLS negative fixture did not reach negotiation')

            if (wp/'.maintenance').exists() or list(state.glob('.orgavela-*')):raise RuntimeError('protocol artifacts remain')
            return {'synthetic_remote_protocol':True,'plaintext_database_tls_refused':True,'capabilities':preflight,'exception':exception,
                    'hard_channel_observer':hard,'channel':'docker-exec-not-SSH','origin':'simulated',
                    'production_source_accessed':False}
        finally:
            for kind,resource in reversed(resources):
                if kind=='container':docker('rm','-f',resource)
                else:docker('network','rm',resource)


if __name__=='__main__':print(json.dumps(run(),sort_keys=True))
