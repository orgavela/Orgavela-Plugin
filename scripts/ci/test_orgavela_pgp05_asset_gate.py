"""Offline PGP-05 ciphertext-workspace contract regression tests; no CI or secrets."""
import hashlib
import json
import pathlib
import tempfile
import unittest

from orgavela_pgp05_asset_gate import FPR, SCHEMA, Refused, validate

ID = "orgavela-synthetic-gate-001"
ARCHIVE = ID + ".tar.gpg"
SIDE = ARCHIVE + ".sha256"


class AssetGateTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.dir = pathlib.Path(self.tmp.name)
        self.content = b"fictional ciphertext fixture - never real OrgaVela data\x00"
        self.sha = hashlib.sha256(self.content).hexdigest()
        self.manifest = {
            "schema": SCHEMA, "backup_id": ID, "synthetic": True,
            "contains_production_data": False, "archive_sha256": self.sha,
            "archive_bytes": len(self.content), "public_key_fingerprint": FPR,
            "encrypted": True, "source_components_encrypted": True,
        }
        self.write()

    def write(self):
        (self.dir / ARCHIVE).write_bytes(self.content)
        (self.dir / SIDE).write_text(self.sha + "  " + ARCHIVE + "\n", encoding="ascii")
        (self.dir / "manifest.json").write_text(json.dumps(self.manifest), encoding="utf-8")

    def denied(self):
        with self.assertRaises((Refused, OSError)):
            validate(str(self.dir))

    def test_clean_synthetic_manifest_and_sidecar(self):
        self.assertEqual(validate(str(self.dir))["sha256"], self.sha)

    def test_reject_true_production_flag(self):
        self.manifest["contains_production_data"] = True
        self.write(); self.denied()

    def test_reject_synthetic_false(self):
        self.manifest["synthetic"] = False
        self.write(); self.denied()

    def test_reject_manifest_extra_secret_field(self):
        self.manifest["sql_password"] = "test-only"
        self.write(); self.denied()

    def test_reject_manifest_missing_field(self):
        del self.manifest["encrypted"]
        self.write(); self.denied()

    def test_reject_wrong_fingerprint(self):
        self.manifest["public_key_fingerprint"] = "0" * 40
        self.write(); self.denied()

    def test_reject_modified_ciphertext(self):
        (self.dir / ARCHIVE).write_bytes(b"modified")
        self.denied()

    def test_reject_corrupt_sidecar(self):
        (self.dir / SIDE).write_text("0" * 64 + "  " + ARCHIVE + "\n")
        self.denied()

    def test_reject_sidecar_wrong_filename(self):
        (self.dir / SIDE).write_text(self.sha + "  ../bad.tar.gpg\n")
        self.denied()

    def test_reject_plaintext_addition(self):
        (self.dir / "database.sql.gz").write_bytes(b"test")
        self.denied()

    def test_reject_missing_asset(self):
        (self.dir / SIDE).unlink()
        self.denied()

    def test_reject_asset_symlink(self):
        (self.dir / ARCHIVE).unlink()
        (self.dir / ARCHIVE).symlink_to(self.dir / "manifest.json")
        self.denied()

    def test_reject_hardlinked_asset(self):
        (self.dir / "manifest.json").unlink()
        (self.dir / "manifest.json").hardlink_to(self.dir / ARCHIVE)
        self.denied()

    def test_reject_duplicate_json_keys(self):
        (self.dir / "manifest.json").write_text(json.dumps(self.manifest)[:-1] + ',"synthetic":true}')
        self.denied()

    def test_reject_wrong_manifest_data_type(self):
        self.manifest["archive_bytes"] = True
        self.write(); self.denied()

    def test_reject_non_absolute_workspace(self):
        with self.assertRaises(Refused):
            validate("relative/path")

    def test_reject_extra_dotfile(self):
        (self.dir / ".something").write_text("not permitted")
        self.denied()

    def test_reject_world_writable_workspace(self):
        self.dir.chmod(0o777)
        self.denied()

    def test_reject_parent_directory_symlink(self):
        nested = self.dir / "nested"
        nested.mkdir()
        for name in (ARCHIVE, SIDE, "manifest.json"):
            (self.dir / name).rename(nested / name)
        with tempfile.TemporaryDirectory() as other:
            alias = pathlib.Path(other) / "indirect"
            alias.symlink_to(self.dir, target_is_directory=True)
            with self.assertRaises(Refused):
                validate(str(alias / "nested"))

    def test_reject_manifest_symlink(self):
        (self.dir / "manifest.json").unlink()
        (self.dir / "manifest.json").symlink_to(self.dir / SIDE)
        self.denied()


if __name__ == "__main__":
    unittest.main()
