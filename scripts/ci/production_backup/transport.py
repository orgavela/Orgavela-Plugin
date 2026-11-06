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
    marker = b'const ORGAVELA_CAPTURE_ENTRYPOINT_ENABLED = false;'
    if program.count(marker) != 1:
        raise ProbeError('unexpected capture entrypoint')
    program = program.replace(marker, b'const ORGAVELA_CAPTURE_ENTRYPOINT_ENABLED = true;', 1)
    # Fail before source access/maintenance if the audited PHP runtime drifted.
    return program.replace(b'<?php', b'<?php\nif (PHP_VERSION_ID !== 80336) { fwrite(STDERR, \"ORGAVELA_CAPTURE_RUNTIME_REFUSED\\n\"); exit(78); }', 1)


def receive(argv, program, destination, *, env, timeout=150,
            max_bytes=MAX_SNAPSHOT, diagnostic_limit=MAX_DIAGNOSTIC):
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


def capture(binding, components, *, password, maintenance_approved=False):
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
                env={'PATH': '/usr/bin:/bin', 'LC_ALL': 'C', 'SSHPASS': password})
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
    expected={'schema':'orgavela-controlled-abort/v1','aborted_after_table_locks':True,
              'fresh_connection_write_locks':True,'maintenance_removed':True,'origin_recovered':True,'snapshot_created':False}
    if evidence!=expected:
        raise ProbeError('controlled abort cleanup unverified')
    return evidence
