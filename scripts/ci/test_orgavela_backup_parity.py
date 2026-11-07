#!/usr/bin/env python3
"""Dreamloud-parity synthetic producer/writer boundary security tests."""
from __future__ import annotations

import contextlib
import io
import json
import os
from pathlib import Path
import shutil
import sys
import tempfile
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).parent))
import orgavela_backup_parity as parity


class ParityTests(unittest.TestCase):
    def setUp(self):
        self.dir = tempfile.TemporaryDirectory()
        self.addCleanup(self.dir.cleanup)
        self.workspace = Path(self.dir.name) / "workspace"
        self.workspace.mkdir(mode=0o700)

    def fixture(self):
        # Artificial header/payload ONLY, not a claim of cryptographic validity.
        ciphertext = parity.HEADER + b"x" * 96
        manifest = {
            "schema": parity.SCHEMA,
            "backup_id": parity.BACKUP_ID,
            "synthetic": True,
            "contains_production_data": False,
            "encryption": "age",
            "archive_name": parity.ARCHIVE,
            "archive_sha256": parity.sha256(ciphertext),
            "archive_bytes": len(ciphertext),
            "private_key_in_workspace": False,
            "source": "fixed-artificial-fixture-only",
        }
        (self.workspace / parity.ARCHIVE).write_bytes(ciphertext)
        (self.workspace / parity.MANIFEST).write_text(json.dumps(manifest))
        (self.workspace / parity.SIDECAR).write_text(
            parity.sha256(ciphertext) + "  " + parity.ARCHIVE + "\n")
        for path in self.workspace.iterdir():
            path.chmod(0o600)

    def invoke(self, role="writer", extra=()):
        with contextlib.redirect_stdout(io.StringIO()), contextlib.redirect_stderr(io.StringIO()):
            return parity.main([role, "--synthetic-only", "--workspace",
                                str(self.workspace), *extra])

    def test_fixed_synthetic_contract(self):
        self.fixture()
        self.assertEqual(self.invoke(), 0)

    def test_default_off_without_explicit_flag(self):
        self.fixture()
        with contextlib.redirect_stderr(io.StringIO()):
            self.assertEqual(parity.main(["writer", "--workspace", str(self.workspace)]), 1)

    def test_reject_real_data_mode(self):
        self.fixture()
        with patch.dict(os.environ, {"ORGAVELA_GH05_ALLOW_REAL_DATA": "YES"}):
            self.assertEqual(self.invoke(), 1)

    def test_reject_context_secrets(self):
        self.fixture()
        with patch.dict(os.environ, {"GH_TOKEN": "synthetic-token-do-not-use"}):
            self.assertEqual(self.invoke(), 1)

    def test_reject_extra_plaintext(self):
        self.fixture()
        (self.workspace / "database.sql").write_text("artificial")
        self.assertEqual(self.invoke(), 1)

    def test_reject_checksum_corruption(self):
        self.fixture()
        (self.workspace / parity.ARCHIVE).write_bytes(parity.HEADER + b"z" * 96)
        self.assertEqual(self.invoke(), 1)

    def test_reject_public_manifest_production_flag(self):
        self.fixture()
        p = self.workspace / parity.MANIFEST
        value = json.loads(p.read_text())
        value["contains_production_data"] = True
        p.write_text(json.dumps(value))
        self.assertEqual(self.invoke(), 1)

    def test_reject_wrong_encryption_type(self):
        self.fixture()
        p = self.workspace / parity.MANIFEST
        value = json.loads(p.read_text())
        value["encryption"] = "OpenPGP"
        p.write_text(json.dumps(value))
        self.assertEqual(self.invoke(), 1)

    def test_reject_secret_marker(self):
        self.fixture()
        p = self.workspace / parity.ARCHIVE
        p.write_bytes(parity.HEADER + b"AGE-SECRET-KEY-" + b"z" * 128)
        self.assertEqual(self.invoke(), 1)

    def test_reject_symlink(self):
        self.fixture()
        path = self.workspace / parity.ARCHIVE
        target = self.workspace / "cipher"
        path.rename(target)
        path.symlink_to(target)
        self.assertEqual(self.invoke(), 1)

    def test_reject_unsafe_mode(self):
        self.fixture()
        (self.workspace / parity.SIDECAR).chmod(0o644)
        self.assertEqual(self.invoke(), 1)

    def test_producer_rejects_existing_files(self):
        self.fixture()
        self.assertEqual(self.invoke("producer"), 1)

    @unittest.skipUnless(shutil.which("age") and shutil.which("age-keygen"),
                         "Official age binary unavailable; separate real CLI gate open")
    def test_real_age_synthetic_producer(self):
        self.assertEqual(self.invoke("producer"), 0)
        self.assertEqual({p.name for p in self.workspace.iterdir()}, parity.ALLOWED)
        self.assertFalse(any("identity" in x.name.lower() for x in self.workspace.iterdir()))
        self.assertEqual(self.invoke("writer"), 0)


if __name__ == "__main__":
    unittest.main(verbosity=2)
