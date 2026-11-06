"""Exercise shared watchdog/locks on disposable MariaDB and local HTTP fixture."""
import json
import os
import http.server
from pathlib import Path
import secrets
import tempfile
import threading
import time
import urllib.error
import urllib.request
from isolated_restore import docker, WP_IMAGE, DB_IMAGE


def run():
    name='orgavela-abort-'+secrets.token_hex(6)
    resources=[]
    results={}
    with tempfile.TemporaryDirectory(prefix='orgavela-abort-fixture-') as directory:
        root=Path(directory)
        password=secrets.token_urlsafe(32)
        env=root/'db.env'
        env.write_text('MARIADB_DATABASE=fixture\nMARIADB_USER=fixture\nMARIADB_PASSWORD='+password+'\nMARIADB_RANDOM_ROOT_PASSWORD=1\n')
        env.chmod(0o600)
        state=root/'state';state.mkdir(mode=0o700)
        class Handler(http.server.BaseHTTPRequestHandler):
            def do_GET(self):
                self.send_response(503 if (state/'.maintenance').exists() else 200)
                self.end_headers()
            def log_message(self,*args):pass
        server=http.server.ThreadingHTTPServer(('127.0.0.1',0),Handler)
        thread=threading.Thread(target=server.serve_forever,daemon=True);thread.start()
        opener=urllib.request.build_opener(urllib.request.ProxyHandler({}))
        def status():
            try:
                with opener.open('http://127.0.0.1:'+str(server.server_port),timeout=2) as response:return response.status
            except urllib.error.HTTPError as error:return error.code
        try:
            docker('network','create','--internal',name);resources.append(('network',name))
            docker('run','-d','--name',name+'-db','--network',name,'--env-file',str(env),'--tmpfs','/var/lib/mysql:rw,nosuid,nodev,size=256m',DB_IMAGE)
            resources.append(('container',name+'-db'))
            for _ in range(60):
                try:docker('exec',name+'-db','healthcheck.sh','--connect','--innodb_initialized');break
                except RuntimeError:time.sleep(1)
            else:raise RuntimeError('abort fixture database unavailable')
            for mode in ('exception','timeout','disconnected','foreign-marker','tar-exception','tar-timeout','tar-disconnected','dump-exception','dump-timeout','dump-disconnected'):
                for path in state.iterdir():
                    if path.is_file():path.unlink()
                runner=name+'-'+mode
                docker('run','-d','--name',runner,'--network',name,'--user',str(os.getuid())+':'+str(os.getgid()),
                    '-e','FIXTURE_DB_HOST='+name+'-db','-e','FIXTURE_PASSWORD='+password,
                    '-v',str(state)+':/state','-v',str(Path(__file__).parent.resolve())+':/checks:ro',
                    '--entrypoint','sh',WP_IMAGE,'-c','sleep 90')
                resources.append(('container',runner))
                docker('exec','-d',runner,'php','/checks/synthetic_abort_worker.php',mode)
                deadline=time.monotonic()+12
                while not (state/'locked').exists() and time.monotonic()<deadline:time.sleep(.05)
                if not (state/'locked').exists():raise RuntimeError('abort fixture lock setup failed')
                if not mode.endswith('exception'):
                    if status()!=503:raise RuntimeError('synthetic HTTP maintenance missing')
                    # The conflicting lock probe must fail before cleanup.
                    try:docker('exec',runner,'php','/checks/synthetic_abort_worker.php','probe')
                    except RuntimeError:pass
                    else:raise RuntimeError('synthetic read lock was ineffective')
                if mode.endswith('disconnected'):
                    pid=(state/'pid').read_text()
                    if not pid.isdecimal():raise RuntimeError('invalid synthetic pid')
                    docker('exec',runner,'/bin/kill','-KILL',pid)
                if mode=='foreign-marker':(state/'.maintenance').write_text('foreign-owner')
                deadline=time.monotonic()+12
                while (state/'watch').exists() and time.monotonic()<deadline:time.sleep(.05)
                if (state/'watch').exists():raise RuntimeError('watchdog fixture cleanup timeout: '+mode+' '+(state/'watchdog.log').read_text()[-1000:])
                docker('exec',runner,'php','/checks/synthetic_abort_worker.php','probe')
                if mode=='foreign-marker':
                    if (state/'.maintenance').read_text()!='foreign-owner':raise RuntimeError('foreign maintenance removed')
                    (state/'.maintenance').unlink()
                if status()!=200:raise RuntimeError('synthetic HTTP recovery failed')
                if (state/'children.json').exists():
                    children=json.loads((state/'children.json').read_text())
                    for member in children['members']:
                        code='$p=orgavela_process_current('+str(member['pid'])+');exit($p && $p["state"]!=="Z" && $p["start"]==="'+member['start']+'"?1:0);'
                        docker('exec',runner,'php','-r','require "/checks/lib/backup_process_safety.php";'+code)
                    # Fresh full session scan catches descendants born after the first observation.
                    owned=next(m for m in children['members'] if m['pid']==m['session'])
                    code='exit(orgavela_process_members(json_decode('+json.dumps(json.dumps(owned))+',true))?1:0);'
                    docker('exec',runner,'php','-r','require "/checks/lib/backup_process_safety.php";'+code)
                    docker('exec',runner,'/bin/kill','-0',str(children['sentinel']))
                    docker('exec',runner,'/bin/kill','-KILL',str(children['sentinel']))
                results[mode]=True
                docker('rm','-f',runner);resources.pop()
        finally:
            server.shutdown();server.server_close();thread.join()
            failures=[]
            for kind,resource in reversed(resources):
                try:docker('rm','-f',resource) if kind=='container' else docker(kind,'rm',resource)
                except Exception:failures.append(kind)
            if failures:raise RuntimeError('abort fixture cleanup incomplete')
    return {'synthetic_abort_cases':results,'fresh_connection_write_locks':True,'http_fixture_recovered':True,
            'running_tar_and_dump_children_terminated':True,'unrelated_process_preserved':True,'production_source_accessed':False,'production_abort_proven':False}


if __name__=='__main__':print(json.dumps(run(),sort_keys=True))
