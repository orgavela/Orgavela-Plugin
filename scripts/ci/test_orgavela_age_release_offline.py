#!/usr/bin/env python3
"""GH-06 native-age GitHub Release publisher: offline-only fail-closed tests.

ALL subprocesses use an explicitly substituted local fake 'gh'.  No network,
real GitHub token, IONOS, or real WordPress data. The test-only age header is
NOT cryptographically valid and must never be published; remote publication
requires a separate actual age E2E with a disposable CLI test identity.
"""
from __future__ import annotations

import hashlib
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
WRITER = ROOT / "scripts/ci/orgavela-upload-github-release-age-synthetic.sh"
REMOTE_E2E = ROOT / "scripts/ci/orgavela-github-release-age-remote-e2e.sh"


class AgeReleaseOfflineTests(unittest.TestCase):
    def setUp(self) -> None:
        self.tmp = tempfile.TemporaryDirectory(prefix="orgavela-gh06-artificial-")
        self.addCleanup(self.tmp.cleanup)
        base = Path(self.tmp.name)
        self.work = base / "private-work"
        self.work.mkdir(mode=0o700)
        tools = base / "fake-tools"
        tools.mkdir(mode=0o700)
        self.calls = base / "fake-gh-called"
        gh = tools / "gh"
        gh.write_text(
            '#!/bin/sh\n'
            'echo "FAKE_GITHUB_CALL" >> "$GH06_FAKE_CALL_FILE"\n'
            'exit 78\n', encoding="utf-8")
        gh.chmod(0o700)
        self.synthetic_bytes = b"age-encryption.org/v1\n" + b"x" * 512
        self.archive = self.work / "orgavela-age-synthetic-ci-1.tar.age"
        self.archive.write_bytes(self.synthetic_bytes)
        self.env = dict(os.environ)
        for name in ("BACKUP_RECOVERY_PRIVATE_KEY", "BACKUP_RECOVERY_PASSPHRASE",
                     "AGE_SECRET_KEY", "ORGAVELA_BACKUP_PRIVATE_KEY",
                     "SFTP_HOST", "SFTP_PASSWORD", "SSH_PRIVATE_KEY",
                     "GOOGLE_DRIVE_TOKEN_JSON"):
            self.env.pop(name, None)
        self.env.update({
            "PATH": str(tools) + os.pathsep + os.environ.get("PATH", ""),
            "GH06_FAKE_CALL_FILE": str(self.calls),
            "GH_TOKEN": "FAKE-NONWORKING-TEST-VALUE",
            "ORGAVELA_SYNTHETIC_ONLY": "YES",
            "ORGAVELA_GH06_AGE_RELEASE_APPROVED": "YES",
            "BACKUP_WORK": str(self.work),
            "BACKUP_ID": "orgavela-age-synthetic-ci-1",
            "ENCRYPTED_BACKUP_FILE": str(self.archive),
            "EXPECTED_ENCRYPTED_SHA256":
                hashlib.sha256(self.synthetic_bytes).hexdigest(),
            "GITHUB_BACKUP_REPOSITORY": "janjrre/orgavela-backups",
        })

    def writer(self, **changes) -> subprocess.CompletedProcess[str]:
        env = dict(self.env)
        env.update(changes)
        return subprocess.run(["bash", str(WRITER)], env=env, cwd=ROOT,
                              capture_output=True, text=True, timeout=12)

    def assert_refused_before_gh(self, **changes) -> None:
        result = self.writer(**changes)
        self.assertNotEqual(result.returncode, 0, result.stdout)
        self.assertFalse(self.calls.exists(), "Unexpected GitHub invocation!")

    def test_scripts_parse_as_bash(self):
        for path in (WRITER, REMOTE_E2E):
            with self.subTest(path=str(path)):
                p = subprocess.run(["bash", "-n", str(path)], capture_output=True,
                                   text=True, timeout=10)
                self.assertEqual(p.returncode, 0, p.stderr)

    def test_missing_approval(self):
        self.assert_refused_before_gh(ORGAVELA_GH06_AGE_RELEASE_APPROVED="")

    def test_missing_synthetic_gate(self):
        self.assert_refused_before_gh(ORGAVELA_SYNTHETIC_ONLY="")

    def test_wrong_repository(self):
        self.assert_refused_before_gh(GITHUB_BACKUP_REPOSITORY="janjrre/dreamloud-backups")

    def test_missing_writer_token(self):
        self.assert_refused_before_gh(GH_TOKEN="")

    def test_reject_unsupported_archive_extension(self):
        other = self.work / "orgavela-age-synthetic-ci-1.tar.gpg"
        other.write_bytes(self.synthetic_bytes)
        self.assert_refused_before_gh(ENCRYPTED_BACKUP_FILE=str(other))

    def test_reject_bad_hash(self):
        self.assert_refused_before_gh(EXPECTED_ENCRYPTED_SHA256="0" * 64)

    def test_reject_private_recovery_key(self):
        self.assert_refused_before_gh(BACKUP_RECOVERY_PRIVATE_KEY="DO-NOT-USE-FAKE")

    def test_reject_live_ssh_secret(self):
        self.assert_refused_before_gh(SFTP_PASSWORD="DO-NOT-USE-FAKE")

    def test_reject_wrong_backup_id(self):
        self.assert_refused_before_gh(BACKUP_ID="orgavela-wordpress-production-01")

    def test_reject_invalid_age_header(self):
        corrupted = b"FAKE-OPENPGP-PAYLOAD" + b"x" * 512
        self.archive.write_bytes(corrupted)
        self.assert_refused_before_gh(
            EXPECTED_ENCRYPTED_SHA256=hashlib.sha256(corrupted).hexdigest())

    def test_valid_preflight_must_hit_fake_only(self):
        # This does not pass the publication test: our fake gh deliberately
        # returns 78. But it proves no real gh/network call occurs.
        p = self.writer()
        self.assertNotEqual(p.returncode, 0)
        self.assertTrue(self.calls.exists())
        self.assertIn("FAKE_GITHUB_CALL", self.calls.read_text())

    def test_source_contains_mandatory_immutability_and_remote_checks(self):
        publisher = WRITER.read_text(encoding="utf-8")
        e2e = REMOTE_E2E.read_text(encoding="utf-8")
        for token in (
            "verify_download before_publish", "verify_download after_publish",
            "releases/$release_id/assets", "releases/assets/$asset_id",
            "repo_metadata", "isPrivate", "preexisting", "readback_verified",
            "ORGAVELA_GH06_AGE_RELEASE_APPROVED",
        ):
            self.assertIn(token, publisher)
        for token in ("immutable == true", "gh release download",
                      "age -d -i", "independent_restore_tested"):
            self.assertIn(token, e2e)
        self.assertNotIn("gpg --", publisher + e2e)
        self.assertNotIn("dreamloud-portal", publisher + e2e)


if __name__ == "__main__":
    unittest.main(verbosity=2)
