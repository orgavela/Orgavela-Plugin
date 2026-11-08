"""Bounded private SSH transport for the quiesced capture program.

No credentials or source bytes are returned in errors or written to stdout.
The caller must authorize the maintenance window before building a live program.
"""
from dataclasses import dataclass
import os
from pathlib import Path
import re
import selectors
import signal
import subprocess
import tempfile
import time

from collector import ProbeError, accept_snapshot, build_remote_candidate

MAX_SNAPSHOT = 140 * 1024 * 1024
MAX_DIAGNOSTIC = 16384


@dataclass(frozen=True)
class Binding:
    host: str
    user: str
    root: str
    known_hosts: Path

    def validate(self):
        if not re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9.-]{0,252}', self.host):
            raise ProbeError('invalid SSH host')
        # Reuse the reviewed source-root and account grammar, without enabling it.
        build_remote_candidate(self.root, self.user)
        pin = Path(self.known_hosts)
        if not re.fullmatch(r'/[A-Za-z0-9_./-]+', str(pin.resolve())):
            raise ProbeError('invalid host-key pin path')
        if pin.is_symlink() or not pin.is_file() or pin.stat().st_nlink != 1 or not 0 < pin.stat().st_size <= 16384:
            raise ProbeError('invalid host-key pin file')
        if pin.stat().st_mode & 0o022:
            raise ProbeError('host-key pin file is writable by another user')

    def argv(self):
        self.validate()
        return ['/usr/bin/sshpass', '-e', '/usr/bin/ssh', '-T', '-p', '22',
                '-o', 'StrictHostKeyChecking=yes', '-o', 'UserKnownHostsFile=' + str(self.known_hosts.resolve()),
                '-o', 'GlobalKnownHostsFile=/dev/null', '-o', 'UpdateHostKeys=no',
                '-o', 'ConnectTimeout=10', '-o', 'ConnectionAttempts=1',
                '-o', 'ServerAliveInterval=5', '-o', 'ServerAliveCountMax=2',
                '-o', 'NumberOfPasswordPrompts=1', '-o', 'LogLevel=ERROR',
                self.user + '@' + self.host,
                'php -d display_errors=0 -d log_errors=0 -d memory_limit=512M']


def live_program(binding, *, maintenance_approved=False):
    binding.validate()
    if maintenance_approved is not True:
        raise ProbeError('explicit maintenance approval required')
    program = build_remote_candidate(binding.root, binding.user)
    from database_tls_policy import unverified_enabled, APPROVAL
    if unverified_enabled():program=program.replace(b'<?php',b'<?php\nconst ORGAVELA_DATABASE_TLS_UNVERIFIED_BACKUP_APPROVAL = '+repr(APPROVAL).encode()+b';',1)
    marker = b'const ORGAVELA_CAPTURE_ENTRYPOINT_ENABLED = false;'
    if program.count(marker) != 1:
        raise ProbeError('unexpected capture entrypoint')
    program = program.replace(marker, b'const ORGAVELA_CAPTURE_ENTRYPOINT_ENABLED = true;', 1)
    # Fail before source access/maintenance if the audited PHP runtime drifted.
    return program.replace(b'<?php', b'<?php\nif (PHP_VERSION_ID !== 80336) { fwrite(STDERR, \"ORGAVELA_CAPTURE_RUNTIME_REFUSED\\n\"); exit(78); }', 1)


def receive(argv, program, destination, *, env, timeout=150,
            max_bytes=MAX_SNAPSHOT, diagnostic_limit=MAX_DIAGNOSTIC,phase_callback=None):
    """Private exclusive file, both pipes drained, deadline and group cleanup.

    stdout is a binary TAR, stderr is discarded after counting. A rejected or
    interrupted transfer never leaves a partial file and never logs diagnostics.
    """
    destination = Path(destination)
    parent = destination.parent
    if parent.is_symlink() or not parent.is_dir() or parent.stat().st_mode & 0o077:
        raise ProbeError('private destination directory required')
    if not 0 < timeout <= 180 or not 0 < len(program) <= 131072 or not 0 < max_bytes <= MAX_SNAPSHOT or not 0 < diagnostic_limit <= MAX_DIAGNOSTIC:
        raise ProbeError('invalid transport limits')
    # Exclusive creation precedes launch; existing files are never deleted.
    fd = os.open(destination, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600)
    child = None
    accepted = False
    try:
        with os.fdopen(fd, 'wb') as output, tempfile.TemporaryFile() as source:
            source.write(program)
            source.seek(0)
            child = subprocess.Popen(argv, stdin=source, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                                     env=env, start_new_session=True, close_fds=True)
            deadline = time.monotonic() + timeout
            received = diagnostics = 0
            diagnostic_buffer=b''
            with selectors.DefaultSelector() as poll:
                poll.register(child.stdout, selectors.EVENT_READ, 'data')
                poll.register(child.stderr, selectors.EVENT_READ, 'diagnostic')
                while poll.get_map():
                    left = deadline - time.monotonic()
                    if left <= 0:
                        raise ProbeError('capture deadline exceeded')
                    for key, _ in poll.select(min(left, 0.2)):
                        block = os.read(key.fileobj.fileno(), 65536)
                        if not block:
                            poll.unregister(key.fileobj)
                            continue
                        if key.data == 'data':
                            received += len(block)
                            if received > max_bytes:
                                raise ProbeError('capture size exceeded')
                            output.write(block)
                        else:
                            diagnostics += len(block)
                            if diagnostics > diagnostic_limit:
                                raise ProbeError('capture diagnostic limit exceeded')
                            if phase_callback is not None:
                                diagnostic_buffer+=block
                                while b'\n' in diagnostic_buffer:
                                    line,diagnostic_buffer=diagnostic_buffer.split(b'\n',1)
                                    match=re.fullmatch(rb'ORGAVELA_CAPTURE_PHASE=([a-z_]+)',line)
                                    if match and match[1].decode() in SAFE_PHASES:phase_callback(match[1].decode())
                left = deadline - time.monotonic()
                if left <= 0 or child.wait(timeout=left) != 0 or received == 0:
                    raise ProbeError('capture process refused or empty')
            output.flush()
            os.fsync(output.fileno())
        accepted = True
    except (OSError, subprocess.SubprocessError):
        # Never expose argv, stderr, credentials or private paths in CI output.
        raise ProbeError('capture transport failed') from None
    finally:
        if child is not None:
            try:
                os.killpg(child.pid, signal.SIGKILL)
            except ProcessLookupError:
                pass
            child.wait()
            child.stdout.close()
            child.stderr.close()
        if not accepted:
            destination.unlink(missing_ok=True)


def capture(binding, components, *, password, maintenance_approved=False,phase_callback=None):
    """Capture and validate before exposing any component to encryption."""
    program = live_program(binding, maintenance_approved=maintenance_approved)
    components = Path(components)
    if components.is_symlink() or not components.is_dir() or any(components.iterdir()) or components.stat().st_mode & 0o077:
        raise ProbeError('private empty component directory required')
    if not isinstance(password, str) or not password or '\0' in password:
        raise ProbeError('source credential required')
    with tempfile.TemporaryDirectory(prefix='orgavela-source-', dir=components.parent) as temporary:
        snapshot = Path(temporary) / 'snapshot.tar'
        receive(binding.argv(), program, snapshot,
                env={'PATH': '/usr/bin:/bin', 'LC_ALL': 'C', 'SSHPASS': password},phase_callback=phase_callback)
        return accept_snapshot(snapshot, components)


def controlled_abort(binding, *, password, maintenance_approved=False):
    """No dump/archive: abort after locks, verify cleanup before any real capture."""
    import json
    program=live_program(binding,maintenance_approved=maintenance_approved)
    program=program.replace(b'<?php',b'<?php\nconst ORGAVELA_CAPTURE_ABORT_AFTER_LOCK = true;',1)
    if not password or '\0' in password:
        raise ProbeError('source credential required')
    with tempfile.TemporaryDirectory(prefix='orgavela-abort-') as folder:
        output=Path(folder)/'abort.json'
        receive(binding.argv(),program,output,env={'PATH':'/usr/bin:/bin','LC_ALL':'C','SSHPASS':password},max_bytes=4096)
        evidence=json.loads(output.read_bytes())
    expected={'schema':'orgavela-controlled-abort/v2','own_processes_ended':True,'aborted_after_table_locks':True,
              'fresh_connection_write_locks':True,'maintenance_removed':True,'origin_recovered':True,'snapshot_created':False}
    if evidence!=expected:
        raise ProbeError('controlled abort cleanup unverified')
    return evidence


def disconnect_when_armed(argv, program, *, env, timeout=45):
    """Kill the actual local SSH process group only after remote lock acknowledgement."""
    child = None
    try:
        with tempfile.TemporaryFile() as source:
            source.write(program); source.seek(0)
            child = subprocess.Popen(argv, stdin=source, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                                     env=env, start_new_session=True, close_fds=True)
            deadline=time.monotonic()+timeout
            data=b''; diagnostics=0
            with selectors.DefaultSelector() as poll:
                poll.register(child.stdout,selectors.EVENT_READ,'data')
                poll.register(child.stderr,selectors.EVENT_READ,'diagnostic')
                while poll.get_map() and time.monotonic()<deadline:
                    for key,_ in poll.select(.2):
                        block=os.read(key.fileobj.fileno(),4096)
                        if not block:
                            poll.unregister(key.fileobj);continue
                        if key.data=='data':
                            data+=block
                            if data==b'ORGAVELA_ABORT_ARMED\n':
                                os.killpg(child.pid,signal.SIGKILL)
                                child.wait(timeout=5)
                                return
                            if len(data)>64:raise ProbeError('abort acknowledgement refused')
                        else:
                            diagnostics+=len(block)
                            if diagnostics>MAX_DIAGNOSTIC:raise ProbeError('abort diagnostic limit')
                raise ProbeError('abort was not armed')
    except (OSError,subprocess.SubprocessError):
        raise ProbeError('abort channel operation failed') from None
    finally:
        if child is not None:
            try:os.killpg(child.pid,signal.SIGKILL)
            except ProcessLookupError:pass
            child.wait()
            child.stdout.close();child.stderr.close()


def hard_disconnect_abort(binding, *, password, maintenance_approved=False):
    """Separate SSH sessions for interruption and independently rechecked cleanup."""
    import json
    import secrets
    if not password or '\0' in password:raise ProbeError('source credential required')
    token=secrets.token_hex(16)
    base=live_program(binding,maintenance_approved=maintenance_approved)
    declarations=('\nconst ORGAVELA_CAPTURE_ABORT_AFTER_LOCK = true;\nconst ORGAVELA_ABORT_TOKEN = "'+token+'";').encode()
    program=base.replace(b'<?php',b'<?php'+declarations,1)
    env={'PATH':'/usr/bin:/bin','LC_ALL':'C','SSHPASS':password}
    disconnect_when_armed(binding.argv(),program,env=env)
    # The independent watchdog has a 15-second deadline; this wait does not prove cleanup.
    time.sleep(18)
    verify=program.replace(b'<?php',b'<?php\nconst ORGAVELA_ABORT_VERIFY = true;',1)
    with tempfile.TemporaryDirectory(prefix='orgavela-abort-observer-') as folder:
        output=Path(folder)/'evidence.json'
        receive(binding.argv(),verify,output,env=env,max_bytes=4096,timeout=30)
        evidence=json.loads(output.read_bytes())
    expected={'schema':'orgavela-hard-disconnect/v1','watchdog_completed':True,'ssh_channel_killed':True,'own_processes_ended':True,
              'fresh_connection_write_locks':True,'maintenance_removed':True,'origin_recovered':True,'snapshot_created':False}
    if evidence!=expected:raise ProbeError('independent abort recovery unverified')
    return evidence


def abort_preflight(binding, *, password, maintenance_approved=False):
    import json
    if not password or '\0' in password:raise ProbeError('source credential required')
    program=live_program(binding,maintenance_approved=maintenance_approved).replace(b'<?php',b'<?php\nconst ORGAVELA_CAPTURE_ABORT_AFTER_LOCK = true;\nconst ORGAVELA_ABORT_PREFLIGHT = true;',1)
    with tempfile.TemporaryDirectory(prefix='orgavela-abort-preflight-') as folder:
        output=Path(folder)/'preflight.json'
        receive(binding.argv(),program,output,env={'PATH':'/usr/bin:/bin','LC_ALL':'C','SSHPASS':password},max_bytes=4096,timeout=45)
        evidence=json.loads(output.read_bytes())
    expected={'schema':'orgavela-abort-preflight/v1','runtime':'8.3.36','posix_sessions':True,'proc_identity':True,
              'hardlink':True,'independent_watchdog':True,'table_scope':True,'table_locks':True,'origin_reachable':True,
              'maintenance_created':False,'snapshot_created':False}
    if evidence!=expected:raise ProbeError('abort capabilities unverified')
    return evidence


SAFE_PHASES={'filesystem_owner_ids_exceed_ustar','filesystem_archive_owner_ids_normalized','filesystem_tar_owner_range','filesystem_tar_changed','filesystem_tar_permission','filesystem_tar_option_unsupported','filesystem_tar_gzip_unavailable','filesystem_tar_path_unavailable','filesystem_tar_path_range','filesystem_tar_failed','database_tls_bridge_input','database_tls_bridge_listen','database_tls_bridge_client','database_tls_bridge_accept','database_tls_bridge_connect','database_tls_bridge_greeting','database_tls_bridge_capabilities','database_tls_bridge_tls_required','database_tls_bridge_auth','database_tls_bridge_tls','database_tls_bridge_peer','database_tls_bridge_cipher','database_tls_bridge_auth_deadline','database_tls_bridge_deadline','database_tls_bridge_relay','database_tls_bridge_read','database_tls_bridge_write','database_tls_bridge_internal','database_client_profile_verified','database_tls_bridge_refused','database_tls_peer_fingerprint_invalid','dump_tls_peer_option_unsupported','database_tls_peer_reader_unavailable','database_tls_peer_extension_unavailable','database_tls_peer_read_failed','database_tls_peer_read_complete','database_tls_peer_certificate_missing','database_tls_peer_fingerprint_read','database_tls_exception_refused','database_tls_peer_unbound','database_tls_peer_started','database_control_tls_verified','source_validating','source_root_rejected','source_core_files_rejected','source_uploads_rejected','source_disk_space_rejected','source_maintenance_rejected','source_inventory_rejected','inventory_invalid_name','inventory_entry_limit','inventory_symlink','inventory_total_size','inventory_unreadable_file','inventory_unsupported_entry','inventory_ustar_path','scope_validated','dump_client_validated','database_validated','database_tls_probe_started','database_tls_verified','database_dump_started','filesystem_tar_started','database_dump_deadline','database_dump_stream_deadline','database_dump_no_output_deadline','filesystem_tar_deadline','dump_process_failed','dump_auth_failed','dump_database_missing','dump_lock_timeout','dump_statement_timeout','dump_connection_failed','dump_connection_lost','system_trust_runtime_unverified','system_trust_store_unavailable','system_trust_store_owner_mismatch','system_trust_store_writable','system_trust_ancestor_unsafe','dump_tls_error','dump_tls_unavailable','dump_tls_hostname_mismatch','dump_tls_certificate_dates','dump_tls_certificate_untrusted','dump_permission_denied','dump_option_unsupported','watchdog_starting','watchdog_ready','maintenance_active','maintenance_origin_verified','tables_locked','dump_complete','files_complete','archive_inventory_verified','files_verified','maintenance_removed','origin_recovered','stream_complete'}


def resource_probe(binding, kind, *, password, maintenance_approved=False,phase_callback=None):
    import json
    flags={'database':'ORGAVELA_CAPTURE_DATABASE_ONLY','filesystem':'ORGAVELA_CAPTURE_FILESYSTEM_ONLY'}
    if kind not in flags or not password or '\0' in password:raise ProbeError('invalid resource probe')
    program=live_program(binding,maintenance_approved=maintenance_approved).replace(b'<?php',b'<?php\nconst '+flags[kind].encode()+b' = true;',1)
    with tempfile.TemporaryDirectory(prefix='orgavela-resource-probe-') as folder:
        output=Path(folder)/'result.json'
        receive(binding.argv(),program,output,env={'PATH':'/usr/bin:/bin','LC_ALL':'C','SSHPASS':password},timeout=110,max_bytes=4096,phase_callback=phase_callback)
        evidence=json.loads(output.read_bytes())
    common={'completed':True,'maintenance_created':False,'table_locks_used':False,'data_discarded':True,'backup_saved':False}
    expected={'schema':'orgavela-database-dump-probe/v2','tls_verified':True,'server_certificate_verified':True,'table_count':52,'row_data_logged':False} if kind=='database' else {'schema':'orgavela-filesystem-memory-probe/v2','ustar_paths_verified':True,'file_data_logged':False}
    from database_tls_policy import unverified_enabled, EXTRA
    if unverified_enabled():
        expected={**expected,**EXTRA,'schema':expected['schema'].replace('/v2','/v3'),'tls_verified':True,'server_certificate_verified':False}
    if set(evidence)!=set(common)|set(expected)|{'compressed_bytes','seconds'}|({'filesystem_entries'} if kind=='filesystem' else set()):raise ProbeError('resource evidence shape')
    for k,v in {**common,**expected}.items():
        if evidence[k]!=v or type(evidence[k]) is not type(v):raise ProbeError('resource probe incomplete')
    if type(evidence['compressed_bytes']) is not int or not 0<evidence['compressed_bytes']<=100663296 or type(evidence['seconds']) not in (int,float) or not 0<evidence['seconds']<=(25 if kind=='database' else 60):raise ProbeError('resource limits exceeded')
    if kind=='filesystem' and (type(evidence['filesystem_entries']) is not int or not 0<evidence['filesystem_entries']<=50000):raise ProbeError('filesystem scope')
    return evidence


def client_tls_negative_program(binding):
    """Separate disabled-by-default entrypoint; never append the capture entrypoint."""
    folder=Path(__file__).parent
    candidate=build_remote_candidate(binding.root,binding.user)
    tail=(folder/'remote_capture.php').read_bytes().removeprefix(b'<?php')
    if not candidate.endswith(tail):raise ProbeError('negative entrypoint assembly')
    program=candidate[:-len(tail)]+(folder/'remote_tls_negative.php').read_bytes().removeprefix(b'<?php')
    program=program.replace(b'<?php',b'<?php\nconst ORGAVELA_TLS_NEGATIVE_ONLY = true;',1)
    return program


def client_tls_negative(binding, *, password):
    import json
    program=client_tls_negative_program(binding)
    with tempfile.TemporaryDirectory(prefix='orgavela-tls-negative-') as directory:
        output=Path(directory)/'receipt.json'
        receive(binding.argv(),program,output,env={'PATH':'/usr/bin:/bin','LC_ALL':'C','SSHPASS':password},timeout=55,max_bytes=4096)
        receipt=json.loads(output.read_bytes())
    expected={'schema':'orgavela-exact-client-tls-negative/v1','database_login':False,'maintenance_created':False,'source_database_accessed':False,'snapshot_created':False,'own_processes_cleaned':True,'synthetic_loopback_only':True}
    if set(receipt)!=set(expected)|{'results'} or any(receipt.get(k)!=v or type(receipt[k]) is not type(v) for k,v in expected.items()) or len(receipt['results'])!=2:raise ProbeError('negative receipt')
    for row,path,sha in zip(receipt['results'],('/usr/bin/mariadb','/usr/bin/mariadb-dump'),('e375617e0bad12f0880e85a4f1125a68480bd7b9b4160d86e48d0ef4210c5a76','a61d09007f25c5df6bcbdf02ff34fe13bcd86426cff4bb40efc5d216cbd74dea')):
        if any(type(row.get(k)) is not bool for k in ('client_started','plaintext_refused')) or any(type(row.get(k)) is not int for k in ('remote_authentication_bytes','forwarded_output_bytes')):raise ProbeError('negative receipt types')
        if row!={'kind':'dump' if path.endswith('-dump') else 'client','path':path,'sha256':sha,'version':'10.11.19','client_started':True,'plaintext_refused':True,'remote_authentication_bytes':0,'forwarded_output_bytes':0}:raise ProbeError('exact client negative identity')
    return receipt
