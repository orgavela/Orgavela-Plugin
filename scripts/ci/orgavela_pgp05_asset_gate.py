#!/usr/bin/env python3
"""PGP-05: offline fail-closed contract gate for synthetic OpenPGP release assets.

This module never reads site/DB content, decrypts, publishes, or accepts production.
OpenPGP packet/recipient validation remains a separate GnuPG guard.
"""
import argparse
import hashlib
import json
import os
import re
import stat
import sys
from pathlib import Path

FPR = "7C9DC60D8E1B293E514696EB333D15A4D8BA625C"
SCHEMA = "orgavela-github-release-backup-public-manifest/v1"
FIELDS = frozenset({"schema", "backup_id", "synthetic", "contains_production_data",
                    "archive_sha256", "archive_bytes", "public_key_fingerprint",
                    "encrypted", "source_components_encrypted"})
ID = re.compile(r"orgavela-synthetic-[A-Za-z0-9_-]{3,100}\Z", re.ASCII)
SHA = re.compile(r"[a-f0-9]{64}\Z", re.ASCII)
MAX_ARCHIVE = 2_147_483_647  # GitHub release asset upper bound is below 2 GiB.


class Refused(ValueError):
    """Contract failed; the caller must stop before release/upload."""


def pairs_no_duplicates(pairs):
    obj = {}
    for k, v in pairs:
        if k in obj:
            raise Refused("duplicate JSON key")
        obj[k] = v
    return obj


def safe_workspace(folder):
    path = Path(folder)
    if not path.is_absolute() or path == Path("/") or ".." in path.parts:
        raise Refused("workspace must be safe absolute directory")
    if any(part.is_symlink() for part in (path, *path.parents)) or not path.is_dir():
        raise Refused("workspace and its ancestors must not be symlinks")
    st = path.stat()
    if st.st_mode & stat.S_IWOTH:
        raise Refused("world-writable workspace")
    return path


def _open_regular(directory_fd, filename, maximum):
    flags = os.O_RDONLY | getattr(os, "O_NOFOLLOW", 0) | getattr(os, "O_NONBLOCK", 0)
    fd = os.open(filename, flags, dir_fd=directory_fd)
    try:
        info = os.fstat(fd)
        if not stat.S_ISREG(info.st_mode) or info.st_nlink != 1:
            raise Refused("asset must be regular, single-link file")
        if info.st_size <= 0 or info.st_size > maximum:
            raise Refused("invalid asset size")
        return fd, info
    except BaseException:
        os.close(fd)
        raise


def _read_small(dfd, filename, maximum):
    fd, before = _open_regular(dfd, filename, maximum)
    try:
        data = b""
        while True:
            piece = os.read(fd, min(8192, maximum + 1 - len(data)))
            if not piece:
                break
            data += piece
            if len(data) > maximum:
                raise Refused("oversized metadata")
        after = os.fstat(fd)
        if len(data) != before.st_size or (before.st_size, before.st_mtime_ns) != (after.st_size, after.st_mtime_ns):
            raise Refused("asset changed during read")
        return data
    finally:
        os.close(fd)


def validate(workspace):
    path = safe_workspace(workspace)
    dfd = os.open(path, os.O_RDONLY | getattr(os, "O_DIRECTORY", 0) | getattr(os, "O_NOFOLLOW", 0))
    try:
        # There must be exactly one ciphertext file, one sidecar and one sanitized manifest.
        names = os.listdir(dfd)
        if len(names) != 3 or "manifest.json" not in names:
            raise Refused("workspace must contain exactly three expected assets")
        manifest_bytes = _read_small(dfd, "manifest.json", 8192)
        try:
            manifest = json.loads(manifest_bytes.decode("utf-8"), object_pairs_hook=pairs_no_duplicates)
        except (UnicodeError, json.JSONDecodeError, TypeError) as exc:
            raise Refused("invalid manifest JSON") from exc
        if type(manifest) is not dict or set(manifest) != FIELDS:
            raise Refused("unexpected manifest schema/keys")
        backup_id = manifest["backup_id"]
        if type(backup_id) is not str or ID.fullmatch(backup_id) is None:
            raise Refused("invalid synthetic backup ID")
        archive_name = backup_id + ".tar.gpg"
        sidecar_name = archive_name + ".sha256"
        if set(names) != {archive_name, sidecar_name, "manifest.json"}:
            raise Refused("unexpected asset names or unencrypted payload")
        if (manifest["schema"] != SCHEMA or
                manifest["synthetic"] is not True or
                manifest["contains_production_data"] is not False or
                manifest["encrypted"] is not True or
                manifest["source_components_encrypted"] is not True or
                manifest["public_key_fingerprint"] != FPR):
            raise Refused("non-synthetic or incorrect encryption policy")
        claimed_sha = manifest["archive_sha256"]
        claimed_size = manifest["archive_bytes"]
        if type(claimed_sha) is not str or SHA.fullmatch(claimed_sha) is None:
            raise Refused("invalid checksum")
        if type(claimed_size) is not int or not (0 < claimed_size <= MAX_ARCHIVE):
            raise Refused("invalid archive bytes")
        expected = (claimed_sha + "  " + archive_name + "\n").encode("ascii")
        if _read_small(dfd, sidecar_name, 256) != expected:
            raise Refused("checksum sidecar mismatch")
        fd, info = _open_regular(dfd, archive_name, MAX_ARCHIVE)
        try:
            if info.st_size != claimed_size:
                raise Refused("archive length mismatch")
            sha = hashlib.sha256()
            total = 0
            while True:
                chunk = os.read(fd, 1024 * 1024)
                if not chunk:
                    break
                sha.update(chunk)
                total += len(chunk)
                if total > MAX_ARCHIVE:
                    raise Refused("archive too large")
            after = os.fstat(fd)
            if total != info.st_size or (info.st_size, info.st_mtime_ns) != (after.st_size, after.st_mtime_ns):
                raise Refused("archive changed while hashing")
            if sha.hexdigest() != claimed_sha:
                raise Refused("archive hash mismatch")
        finally:
            os.close(fd)
        return {"id": backup_id, "sha256": claimed_sha, "bytes": claimed_size}
    finally:
        os.close(dfd)


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--workspace", required=True, help="Absolute local ciphertext-only fixture path")
    args = parser.parse_args(argv)
    try:
        validate(args.workspace)
    except (Refused, OSError, UnicodeError, ValueError) as exc:
        print("PGP05_ASSET_CONTRACT=REFUSED: " + str(exc), file=sys.stderr)
        return 1
    print("PGP05_ASSET_CONTRACT_SYNTHETIC_ONLY=PASS")
    return 0


if __name__ == "__main__":
    sys.exit(main())
