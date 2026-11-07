"""Signed private-controller identity and independently checked public engine."""
import base64
import json
import os
from pathlib import Path
import re
import subprocess
import time
import urllib.parse
import urllib.request

from collector import ProbeError

REPOSITORY = 'orgavela/orgavela-backups'
ENGINE_REPOSITORY = 'orgavela/Orgavela-Plugin'
# GitHub immutable subjects bind numeric owner/repository IDs across renames.
OWNER_ID = '350482188'
REPOSITORY_ID = '1416889964'
SUBJECT_PREFIX = 'repo:orgavela@'+OWNER_ID+'/orgavela-backups@'+REPOSITORY_ID
WORKFLOW = REPOSITORY + '/.github/workflows/o03-private-live-backup.yml@refs/heads/main'
ISSUER = 'https://token.actions.githubusercontent.com'
ROLES = {'source':'orgavela-backup-source', 'abort':'orgavela-backup-source', 'writer':'orgavela-backup-writer', 'restore':'orgavela-backup-restore'}


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args):
        return None


def fetch_json(url, *, headers=None, limit=4*1024*1024):
    with urllib.request.build_opener(NoRedirect()).open(urllib.request.Request(url, headers=headers or {}), timeout=20) as response:
        data = response.read(limit+1)
    if len(data) > limit:
        raise ProbeError('identity response limit')
    return json.loads(data)


def decode(value):
    if not isinstance(value,str) or not re.fullmatch(r'[A-Za-z0-9_-]+',value):
        raise ProbeError('OIDC encoding refused')
    return base64.urlsafe_b64decode(value + '='*((-len(value))%4))


def verify_jwt(token, jwks, audience, *, now=None):
    from cryptography.hazmat.primitives.asymmetric import padding, rsa
    from cryptography.hazmat.primitives import hashes
    if not isinstance(token,str) or len(token)>20000 or len(token.split('.'))!=3:
        raise ProbeError('OIDC token refused')
    header64, claims64, signature64 = token.split('.')
    header=json.loads(decode(header64));claims=json.loads(decode(claims64))
    if header.get('alg')!='RS256' or header.get('typ')!='JWT' or 'crit' in header:
        raise ProbeError('OIDC algorithm refused')
    keys=[key for key in jwks.get('keys',[]) if key.get('kid')==header.get('kid') and key.get('kty')=='RSA']
    if len(keys)!=1:
        raise ProbeError('OIDC signing identity refused')
    key=keys[0]
    try:
        public=rsa.RSAPublicNumbers(int.from_bytes(decode(key['e']),'big'),int.from_bytes(decode(key['n']),'big')).public_key()
        public.verify(decode(signature64),(header64+'.'+claims64).encode('ascii'),padding.PKCS1v15(),hashes.SHA256())
    except Exception:
        raise ProbeError('OIDC signature refused') from None
    now=time.time() if now is None else now
    if (claims.get('iss')!=ISSUER or claims.get('aud')!=audience or
            any(type(claims.get(name)) is not int for name in ('iat','nbf','exp')) or
            not claims['nbf']-30<=now<claims['exp'] or not 0<claims['exp']-claims['iat']<=600 or
            now-claims['iat']>600 or claims['iat']>now+30):
        raise ProbeError('OIDC issuer/audience/lifetime refused')
    return claims


def controller_claims(claims, role):
    if role not in ROLES:
        raise ProbeError('backup role refused')
    if (claims.get('repository')!=REPOSITORY or claims.get('repository_visibility')!='private' or
            claims.get('repository_id')!=REPOSITORY_ID or claims.get('repository_owner_id')!=OWNER_ID or
            claims.get('ref')!='refs/heads/main' or claims.get('event_name')!='workflow_dispatch' or
            claims.get('workflow_ref')!=WORKFLOW or
            claims.get('sub')!=SUBJECT_PREFIX+':environment:'+ROLES[role] or
            claims.get('aud')!='orgavela-o03-'+role or
            not re.fullmatch(r'[a-f0-9]{40}',claims.get('workflow_sha','')) or
            claims.get('sha')!=claims.get('workflow_sha') or
            not all(isinstance(claims.get(name),str) and claims[name].isdecimal() for name in ('run_id','run_attempt'))):
        raise ProbeError('private controller/workflow binding refused')
    if 'job_workflow_ref' in claims and (claims['job_workflow_ref']!=WORKFLOW or claims.get('job_workflow_sha')!=claims['workflow_sha']):
        raise ProbeError('unexpected reusable workflow')
    return claims['workflow_sha']


def git(root, *args):
    result=subprocess.run(['/usr/bin/git','-C',str(Path(root).resolve()),*args],capture_output=True,
                          timeout=10,env={'PATH':'/usr/bin:/bin','GIT_CONFIG_NOSYSTEM':'1','GIT_CONFIG_GLOBAL':'/dev/null'})
    if result.returncode or len(result.stdout)>1024*1024:
        raise ProbeError('checkout identity unavailable')
    return result.stdout.decode().strip()


def checkout(root, repository, expected_sha):
    if not re.fullmatch(r'[a-f0-9]{40}',expected_sha):
        raise ProbeError('approved checkout SHA required')
    origin=git(root,'config','--get','remote.origin.url').removesuffix('.git')
    if origin!='https://github.com/'+repository or git(root,'rev-parse','HEAD')!=expected_sha:
        raise ProbeError('checkout origin/SHA drift')
    if git(root,'status','--porcelain','--untracked-files=normal'):
        raise ProbeError('checkout contains unreviewed changes')


def integrated_engine(expected_sha, comparison):
    if (comparison.get('status') not in ('ahead','identical') or
            comparison.get('base_commit',{}).get('sha')!=expected_sha or
            comparison.get('merge_base_commit',{}).get('sha')!=expected_sha):
        raise ProbeError('engine is not integrated into public main')


def authorize(env, role, *, engine_root=None):
    if role not in ROLES:
        raise ProbeError('backup role refused')
    url=env.get('ACTIONS_ID_TOKEN_REQUEST_URL','');parsed=urllib.parse.urlsplit(url)
    if (parsed.scheme!='https' or not parsed.hostname or
            not parsed.hostname.endswith('.actions.githubusercontent.com') or parsed.username or parsed.password):
        raise ProbeError('GitHub OIDC request endpoint refused')
    request_token=env.get('ACTIONS_ID_TOKEN_REQUEST_TOKEN','')
    if not request_token:
        raise ProbeError('GitHub identity capability required')
    audience='orgavela-o03-'+role
    token=fetch_json(url+('&' if parsed.query else '?')+'audience='+audience,
                     headers={'Authorization':'Bearer '+request_token},limit=32768).get('value')
    claims=verify_jwt(token,fetch_json(ISSUER+'/.well-known/jwks',limit=65536),audience)
    sha=controller_claims(claims,role)
    if (env.get('GITHUB_SHA'),env.get('GITHUB_RUN_ID'),env.get('GITHUB_RUN_ATTEMPT'))!=(sha,claims['run_id'],claims['run_attempt']):raise ProbeError('signed runtime context drift')
    # Signed controller identity and physical checkout are distinct from engine identity.
    checkout(Path(env.get('GITHUB_WORKSPACE',''))/'controller',REPOSITORY,sha)
    engine_root=Path(__file__).resolve().parents[3] if engine_root is None else Path(engine_root)
    engine_sha=env.get('ORGAVELA_APPROVED_ENGINE_SHA','')
    checkout(engine_root,ENGINE_REPOSITORY,engine_sha)
    comparison=fetch_json('https://api.github.com/repos/'+ENGINE_REPOSITORY+'/compare/'+engine_sha+'...main?per_page=1',
                          headers={'Accept':'application/vnd.github+json','User-Agent':'orgavela-backup-identity'})
    integrated_engine(engine_sha,comparison)
    return {'controller_sha':sha,'engine_sha':engine_sha,'run_id':claims['run_id'],'run_attempt':claims['run_attempt'],'role':role}
