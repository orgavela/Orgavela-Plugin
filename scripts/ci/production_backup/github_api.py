"""Fixed-target HTTP primitives adapted from Dreamloud; no credential entry point."""
import json
import urllib.request
import urllib.error
import re

REPOSITORY='orgavela/orgavela-backups'

class WriterError(ValueError):
    def __init__(self,message,status=None): super().__init__(message);self.status=status

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,req,fp,code,msg,headers,newurl): return None

def request_json(path,token,*,method='GET',data=None):
    # Fixed repository only, including before URL parsers normalize dot paths.
    if not isinstance(path,str) or not re.fullmatch(r'/[A-Za-z0-9_./-]+',path) or any(p in ('.','..','') for p in path[1:].split('/')):
        raise WriterError('target path refused')
    if not path.startswith('/repos/'+REPOSITORY+'/') and path!='/repos/'+REPOSITORY:
        raise WriterError('target path refused')
    request=urllib.request.Request('https://api.github.com'+path,method=method,
        data=None if data is None else json.dumps(data).encode(),headers={
            'Authorization':'Bearer '+token,'Accept':'application/vnd.github+json',
            'Content-Type':'application/json','X-GitHub-Api-Version':'2022-11-28','User-Agent':'orgavela-backup-writer'})
    try:
        with urllib.request.build_opener(urllib.request.ProxyHandler({}),NoRedirect()).open(request,timeout=60) as response:
            data=response.read(1024*1024+1)
            if len(data)>1024*1024: raise WriterError('oversized API response')
            return json.loads(data)
    except urllib.error.HTTPError as error: raise WriterError('API refused',error.code) from None
    except (OSError,ValueError): raise WriterError('API unavailable') from None

def installation_token(env):
    # Do not copy Dreamloud App IDs/private keys or invent OrgaVela credentials.
    # A later separately reviewed activation will wire the established own PAT path.
    raise WriterError('production writer disabled in offline candidate')

def verify_repository(token):
    result=request_json('/repos/'+REPOSITORY,token)
    if result.get('full_name')!=REPOSITORY or result.get('private') is not True or result.get('archived') is not False:
        raise WriterError('private backup repository required')
    return result

def reject_mixed_credentials(env,role):
    import re
    if role not in ('source','writer'): raise WriterError('credential role refused')
    for name,value in env.items():
        if not value: continue
        private=re.search(r'(PRIVATE|RECOVERY|PASSPHRASE|AGE_SECRET)',name)
        source=name.startswith(('PGP05_SSH_','BACKUP_SSH_','IONOS_','SFTP_')) or name=='PGP05_WP_ROOT'
        writer=name in ('GH_TOKEN','GITHUB_TOKEN','BACKUP_GITHUB_TOKEN') or name.startswith('BACKUP_GITHUB_')
        raw_database=name in ('DB_PASSWORD','MYSQL_PWD','WP_DB_PASSWORD','SSHPASS') or name.startswith(('PGPASSWORD','DATABASE_URL'))
        if private or raw_database or (role=='writer' and source) or (role=='source' and writer):
            raise WriterError('credential boundary refused')
