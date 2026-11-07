"""One explicitly authorized OrgaVela backup TLS exception; never a global TLS setting."""
import os

MODE='orgavela-backup-encrypted-unverified-20261107'
APPROVAL='bd622f9fa9e926d7af866676af1a0224cb9d92c0'
EXTRA={'database_tls_policy':MODE,'database_tls_exception_authorization':APPROVAL,
       'database_control_connection_tls_verified':True,'database_peer_continuity_enforced':True}
TRANSPORT={'tls_encrypted':True,'server_certificate_verified':False,'policy':MODE,
           'exception_authorization':APPROVAL,'peer_continuity_enforced':True}


def unverified_enabled(env=None):
    mode=(os.environ if env is None else env).get('ORGAVELA_DATABASE_TLS_MODE','verified')
    if mode not in ('verified',MODE):raise ValueError('unknown database TLS mode')
    return mode==MODE


def validate_transport(value):
    if not isinstance(value,dict) or value!=TRANSPORT or any(type(value.get(k)) is not type(v) for k,v in TRANSPORT.items()):raise ValueError('database TLS exception evidence refused')
    return dict(TRANSPORT)


def transport_summary(evidence):
    if evidence.get('schema')=='orgavela-ssh-capture-evidence/v4':return dict(TRANSPORT)
    return None
