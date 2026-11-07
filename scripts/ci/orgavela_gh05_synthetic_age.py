#!/usr/bin/env python3
"""GH-05 synthetic-only age archive and integrity exercise.

This is NOT a WordPress/IONOS snapshotter. It cannot connect to a host,
database, API or user-selected file path. No upload or schedule.
All data and cryptographic identities are temporary, artificial test fixtures.
"""
from __future__ import annotations

import hashlib
import io
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import tarfile
import tempfile

SCHEMA = "orgavela-gh05-synthetic/v1"
MARKER = b"ORGAVELA-GH05-ARTIFICIAL-ONLY\n"
FILES = {
    "fixture/database.sql": b"-- Artificial data only, no real site\nCREATE TABLE synthetic_probe (id INTEGER);\nINSERT INTO synthetic_probe VALUES (42);\n",
    "fixture/wp-content/plugins/uop-core/SYNTHETIC.txt": MARKER,
    "fixture/wp-content/uploads/SYNTHETIC.txt": b"Example upload placeholder, no user content\n",
}
ALLOWED_NAMES = frozenset({"manifest.json", *FILES})


def digest(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def payload() -> bytes:
    """Create a deterministic artificial TAR, with in-archive checksums."""
    manifest = {
        "schema": SCHEMA,
        "synthetic_only": True,
        "checksums": {name: digest(content) for name, content in FILES.items()},
    }
    members = {
        "manifest.json": (json.dumps(manifest, sort_keys=True, separators=(",", ":")) + "\n").encode(),
        **FILES,
    }
    output = io.BytesIO()
    with tarfile.open(fileobj=output, mode="w", format=tarfile.PAX_FORMAT) as archive:
        for name, content in sorted(members.items()):
            info = tarfile.TarInfo(name)
            info.size = len(content)
            info.mode = 0o600
            info.mtime = 0
            archive.addfile(info, io.BytesIO(content))
    return output.getvalue()


def verify_payload(data: bytes) -> None:
    """Fail closed on extra/duplicate/missing members, links and hash mismatches."""
    try:
        with tarfile.open(fileobj=io.BytesIO(data), mode="r:") as archive:
            members = archive.getmembers()
            names = [member.name for member in members]
            if len(names) != len(ALLOWED_NAMES) or set(names) != ALLOWED_NAMES:
                raise ValueError("Unexpected, missing or duplicate archive members")
            for member in members:
                if not member.isfile() or member.size > 1024 * 1024:
                    raise ValueError("Non-file or oversized archive member")
            read = {}
            for member in members:
                stream = archive.extractfile(member)
                if stream is None:
                    raise ValueError("Unreadable archive member")
                read[member.name] = stream.read()
    except (tarfile.TarError, OSError, EOFError) as exc:
        raise ValueError("Invalid tar payload") from exc

    try:
        manifest = json.loads(read.pop("manifest.json"))
    except (UnicodeError, json.JSONDecodeError) as exc:
        raise ValueError("Invalid embedded manifest") from exc
    if not isinstance(manifest, dict) or set(manifest) != {"schema", "synthetic_only", "checksums"}:
        raise ValueError("Invalid manifest fields")
    if manifest["schema"] != SCHEMA or manifest["synthetic_only"] is not True:
        raise ValueError("Not the expected synthetic fixture")
    expected = {name: digest(content) for name, content in FILES.items()}
    if manifest["checksums"] != expected:
        raise ValueError("Manifest checksums do not match")
    for name, content in FILES.items():
        if read[name] != content or digest(read[name]) != expected[name]:
            raise ValueError("Artificial fixture mismatch")


def cli(program: str, *args: str) -> subprocess.CompletedProcess:
    """Invoke local official age tools; never use the shell or arbitrary commands."""
    executable = shutil.which(program)
    if not executable:
        raise RuntimeError(f"Required official CLI {program} is not installed")
    return subprocess.run(
        [executable, *args], check=True, stdout=subprocess.PIPE,
        stderr=subprocess.PIPE, timeout=25,
    )


def run_synthetic() -> None:
    if os.environ.get("ORGAVELA_GH05_ALLOW_REAL_DATA"):
        raise RuntimeError("Real data mode is prohibited")
    with tempfile.TemporaryDirectory(prefix="orgavela-gh05-artificial-") as directory:
        base = Path(directory)
        identity = base / "DISPOSABLE-synthetic-identity.txt"
        plain = base / "fixture.tar"
        encrypted = base / "fixture.tar.age"
        recovered = base / "recovered.tar"
        tampered = base / "tampered.tar.age"
        rejected = base / "rejected.tar"

        archive = payload()
        verify_payload(archive)
        plain.write_bytes(archive)
        cli("age-keygen", "-o", str(identity))
        recipient = cli("age-keygen", "-y", str(identity)).stdout.decode("ascii").strip()
        if re.fullmatch(r"age1[0-9a-z]+", recipient) is None:
            raise RuntimeError("Unexpected age recipient format")
        cli("age", "-r", recipient, "-o", str(encrypted), str(plain))
        if not encrypted.is_file() or encrypted.stat().st_size < 40:
            raise RuntimeError("No age ciphertext")
        ciphertext = encrypted.read_bytes()
        ciphertext_hash = digest(ciphertext)
        if ciphertext == archive:
            raise RuntimeError("Ciphertext equals plaintext")
        cli("age", "-d", "-i", str(identity), "-o", str(recovered), str(encrypted))
        recovered_bytes = recovered.read_bytes()
        if digest(archive) != digest(recovered_bytes):
            raise RuntimeError("Decrypted archive digest mismatch")
        verify_payload(recovered_bytes)

        corrupted = bytearray(ciphertext)
        corrupted[-1] ^= 1
        tampered.write_bytes(corrupted)
        try:
            cli("age", "-d", "-i", str(identity), "-o", str(rejected), str(tampered))
        except subprocess.CalledProcessError:
            pass
        else:
            raise RuntimeError("Tampered age ciphertext was accepted")

        print("GH05_SYNTHETIC_ONLY=PASS")
        print("GH05_AGE_ENCRYPT_DECRYPT=PASS")
        print("GH05_INNER_MANIFEST_AND_SHA256=PASS")
        print("GH05_TAMPER_REJECTED=PASS")
        print("GH05_CIPHERTEXT_SHA256=" + ciphertext_hash)
        print("GH05_REAL_SITE_OR_DATABASE_ACCESSED=no")
        print("GH05_NETWORK_OR_CLOUD_UPLOAD=no")
        print("GH05_PRODUCTION_RECOVERY_KEY_USED=no")


def main(argv: list[str]) -> int:
    if argv != ["--synthetic-only"]:
        print("Refused: only --synthetic-only is supported", file=sys.stderr)
        return 2
    try:
        run_synthetic()
        return 0
    except (RuntimeError, subprocess.CalledProcessError, OSError, ValueError, UnicodeError):
        print("GH05_SYNTHETIC_ONLY=FAIL (local prerequisite or integrity error)", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main(sys.argv[1:]))
