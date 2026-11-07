#!/usr/bin/env python3
"""Purely local GH-05 fixture/negative tests. No network, secrets or site."""
import io
from pathlib import Path
import shutil
import sys
import tarfile
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).parent))
import orgavela_gh05_synthetic_age as gh05


def assemble(members):
    buffer = io.BytesIO()
    with tarfile.open(fileobj=buffer, mode="w") as archive:
        for name, content, kind in members:
            info = tarfile.TarInfo(name)
            info.type = kind
            if kind == tarfile.REGTYPE:
                info.size = len(content)
                archive.addfile(info, io.BytesIO(content))
            else:
                info.linkname = "../../sensitive"
                archive.addfile(info)
    return buffer.getvalue()


def unpack_for_modification():
    archive = gh05.payload()
    with tarfile.open(fileobj=io.BytesIO(archive), mode="r:") as source:
        return [(member.name, source.extractfile(member).read(), tarfile.REGTYPE)
                for member in source.getmembers()]


class GH05SyntheticTests(unittest.TestCase):
    def test_manifest_sha256_valid(self):
        gh05.verify_payload(gh05.payload())

    def test_reject_non_archive(self):
        with self.assertRaises(ValueError):
            gh05.verify_payload(b"not a TAR")

    def test_reject_corrupt_file(self):
        archive = gh05.payload()
        self.assertIn(gh05.MARKER, archive)
        with self.assertRaises(ValueError):
            gh05.verify_payload(archive.replace(gh05.MARKER, b"CORRUPTED-FIXTURE\n", 1))

    def test_reject_duplicate_entry(self):
        members = unpack_for_modification()
        with self.assertRaises(ValueError):
            gh05.verify_payload(assemble(members + [members[-1]]))

    def test_reject_extra_path_traversal(self):
        members = unpack_for_modification()
        with self.assertRaises(ValueError):
            gh05.verify_payload(assemble(members + [("../unsafe.txt", b"x", tarfile.REGTYPE)]))

    def test_reject_symlink(self):
        members = unpack_for_modification()
        name, _, _ = members[0]
        with self.assertRaises(ValueError):
            gh05.verify_payload(assemble([(name, b"", tarfile.SYMTYPE)] + members[1:]))

    def test_only_explicit_synthetic_flag(self):
        for args in ([], ["--production"], ["--synthetic-only", "--path=/public"]):
            self.assertEqual(gh05.main(args), 2)

    def test_reject_real_data_mode(self):
        with patch.dict("os.environ", {"ORGAVELA_GH05_ALLOW_REAL_DATA": "1"}):
            with self.assertRaises(RuntimeError):
                gh05.run_synthetic()

    @unittest.skipUnless(shutil.which("age") and shutil.which("age-keygen"),
                         "Official age/age-keygen not installed: real CLI test unverified")
    def test_real_cli_artificial_only(self):
        self.assertEqual(gh05.main(["--synthetic-only"]), 0)


if __name__ == "__main__":
    unittest.main(verbosity=2)
