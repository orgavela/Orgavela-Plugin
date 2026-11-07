#!/usr/bin/env python3
"""OrgaVela GH-05: Dreamloud-inspired split producer/writer, synthetic ONLY.

No live capture, release API, network, persistent secrets, or scheduled backup.
The writer is a separate invocation and only receives age ciphertext + public
integrity metadata. Never use this module to back up a real installation.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import stat
import subprocess
import sys
import tempfile

import orgavela_gh05_synthetic_age as synthetic

BACKUP_ID = "orgavela-synthetic-gh05-parity-v1"
ARCHIVE = BACKUP_ID + ".tar.age"
MANIFEST = "manifest.json"
SIDECAR = ARCHIVE + ".sha256"
ALLOWED = frozenset((ARCHIVE, MANIFEST, SIDECAR))
SCHEMA = "orgavela-backup-public-manifest/v1"
HEADER = b"age-encryption.org/v1\n"
SECRET_NAMES = (
    "GH_TOKEN", "BACKUP_GITHUB_TOKEN", "BACKUP_RECOVERY_PRIVATE_KEY",
    "BACKUP_RECOVERY_PASSPHRASE", "IONOS_SSH_PASSWORD", "SFTP_PASSWORD",
    "SFTP_HOST", "SSH_PRIVATE_KEY", "AGE_SECRET_KEY",
)


class Refusal(Exception):
    pass


def require(condition: bool, message: str) -> None:
    if not condition:
        raise Refusal(message)


def sha256(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def safe_workspace(directory: str, *, empty: bool = False) -> Path:
    # Never resolve links in the original path before checking them.
    workspace = Path(directory)
    require(workspace.is_absolute() and workspace.is_dir()
            and not workspace.is_symlink(), "private absolute workspace required")
    require(stat.S_IMODE(workspace.stat().st_mode) == 0o700,
            "workspace must be 0700")
    require(all(not parent.is_symlink() for parent in workspace.parents),
            "workspace must not have symlink parents")
    if empty:
        require(not any(workspace.iterdir()), "producer refuses nonempty workspace")
    return workspace


def assert_no_secrets() -> None:
    require(not any(os.environ.get(name) for name in SECRET_NAMES),
            "unexpected credential in synthetic-only job")
    for name in ("CIRCLE_PROJECT_REPONAME", "CIRCLE_PROJECT_USERNAME"):
        expected = {"CIRCLE_PROJECT_REPONAME": "Projekt-Wordpress-Plugin",
                    "CIRCLE_PROJECT_USERNAME": "janjrre"}[name]
        require(not os.environ.get(name) or os.environ[name] == expected,
                "wrong CI project")


def producer(workspace: Path) -> None:
    require(not any(workspace.iterdir()), "producer refuses existing output")
    with tempfile.TemporaryDirectory(prefix="orgavela-gh05-disposable-") as root:
        temp = Path(root)
        identity = temp / "SYNTHETIC-IDENTITY-DELETE.txt"
        original = temp / "fixed-artificial.tar"
        ciphertext = temp / ARCHIVE

        original_bytes = synthetic.payload()
        synthetic.verify_payload(original_bytes)
        original.write_bytes(original_bytes)

        synthetic.cli("age-keygen", "-o", str(identity))
        recipient = synthetic.cli("age-keygen", "-y", str(identity)).stdout.decode("ascii").strip()
        require(re.fullmatch(r"age1[0-9a-z]+", recipient) is not None,
                "invalid ephemeral test recipient")

        synthetic.cli("age", "-r", recipient, "-o", str(ciphertext), str(original))
        encrypted = ciphertext.read_bytes()
        require(len(encrypted) > 64 and encrypted.startswith(HEADER)
                and encrypted != original_bytes, "invalid age ciphertext")
        restored = temp / "restored-artificial.tar"
        synthetic.cli("age", "-d", "-i", str(identity), "-o", str(restored), str(ciphertext))
        require(restored.read_bytes() == original_bytes, "synthetic recovery mismatch")
        synthetic.verify_payload(restored.read_bytes())

        # Publish ONLY ciphertext + public integrity metadata into the
        # downstream workspace; disposable private test key never crosses.
        manifest = {
            "schema": SCHEMA, "backup_id": BACKUP_ID, "synthetic": True,
            "contains_production_data": False, "encryption": "age",
            "archive_name": ARCHIVE, "archive_sha256": sha256(encrypted),
            "archive_bytes": len(encrypted), "private_key_in_workspace": False,
            "source": "fixed-artificial-fixture-only",
        }
        (workspace / ARCHIVE).write_bytes(encrypted)
        (workspace / MANIFEST).write_text(
            json.dumps(manifest, sort_keys=True, separators=(",", ":")) + "\n",
            encoding="utf-8",
        )
        (workspace / SIDECAR).write_text(
            sha256(encrypted) + "  " + ARCHIVE + "\n", encoding="ascii")
        for file in workspace.iterdir():
            file.chmod(0o600)
    writer(workspace)
    print("GH05_PARITY_PRODUCER=synthetic-age-pass")
    print("GH05_PARITY_PLAINTEXT_IN_WRITER=no")


def writer(workspace: Path) -> None:
    entries = list(workspace.iterdir())
    require(len(entries) == 3 and {p.name for p in entries} == ALLOWED,
            "writer accepts exactly three synthetic assets")
    for file in entries:
        require(file.is_file() and not file.is_symlink()
                and stat.S_IMODE(file.stat().st_mode) == 0o600,
                "writer rejects symlink, nonfile or unsafe mode")

    public = workspace / MANIFEST
    require(0 < public.stat().st_size < 4096, "manifest size invalid")
    try:
        manifest = json.loads(public.read_text(encoding="utf-8"))
    except (ValueError, UnicodeError) as exc:
        raise Refusal("manifest invalid") from exc

    require(isinstance(manifest, dict) and
            set(manifest) == {"schema", "backup_id", "synthetic",
                             "contains_production_data", "encryption",
                             "archive_name", "archive_sha256",
                             "archive_bytes", "private_key_in_workspace", "source"},
            "public manifest fields invalid")
    require(manifest["schema"] == SCHEMA and manifest["backup_id"] == BACKUP_ID
            and manifest["synthetic"] is True
            and manifest["contains_production_data"] is False
            and manifest["encryption"] == "age"
            and manifest["archive_name"] == ARCHIVE
            and manifest["private_key_in_workspace"] is False
            and manifest["source"] == "fixed-artificial-fixture-only",
            "not an approved synthetic age manifest")

    cipher = workspace / ARCHIVE
    require(64 < cipher.stat().st_size < 8_000_000,
            "ciphertext size outside artificial test limit")
    content = cipher.read_bytes()
    require(content.startswith(HEADER), "not standard age ciphertext")
    require(b"AGE-SECRET-KEY-" not in content
            and b"CREATE TABLE" not in content
            and synthetic.MARKER not in content,
            "plaintext or private key leaked into writer")
    require(type(manifest["archive_bytes"]) is int
            and manifest["archive_bytes"] == len(content)
            and manifest["archive_sha256"] == sha256(content),
            "ciphertext integrity mismatch")
    require((workspace / SIDECAR).read_text(encoding="ascii")
            == sha256(content) + "  " + ARCHIVE + "\n",
            "sidecar integrity mismatch")
    print("GH05_PARITY_WRITER=ciphertext-only-verified")
    print("GH05_PARITY_GITHUB_RELEASE_PUBLISHED=no")
    print("GH05_PARITY_REAL_WORDPRESS_BACKUP=no")


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("role", choices=("producer", "writer"))
    parser.add_argument("--synthetic-only", action="store_true", default=False)
    parser.add_argument("--workspace", required=True)
    args = parser.parse_args(argv)
    try:
        require(args.synthetic_only and not os.environ.get("ORGAVELA_GH05_ALLOW_REAL_DATA"),
                "real-data mode prohibited")
        assert_no_secrets()
        workspace = safe_workspace(args.workspace, empty=args.role == "producer")
        if args.role == "producer":
            producer(workspace)
        else:
            writer(workspace)
        return 0
    except (Refusal, ValueError, OSError, subprocess.CalledProcessError,
            RuntimeError, UnicodeError) as exc:
        print("GH05_PARITY_REFUSED=" + type(exc).__name__, file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
