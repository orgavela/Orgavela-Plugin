#!/usr/bin/env bash
# PGP-03 synthetic-only ciphertext transport security regressions.
set -euo pipefail
set +x
umask 077
root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
tool="$root/scripts/ci/orgavela-openpgp-synthetic-handoff.sh"
tmp="$(mktemp -d)"
trap 'rm -rf -- "$tmp"' EXIT
export ORGAVELA_PGP_SYNTHETIC_ONLY=YES
good="$tmp/good"
bash "$tool" producer "$good"
bash "$tool" writer "$good"
expect_reject() {
  local reason="$1"
  shift
  if "$@" >/dev/null 2>&1; then
    printf 'FAIL: unsafe handoff accepted: %s\n' "$reason" >&2
    exit 1
  fi
}
copy_fixture() {
  mkdir -m 700 "$tmp/$1"
  cp -a -- "$good/." "$tmp/$1/"
}
encrypted='orgavela-synthetic-pgpony-handoff-001.tar.gpg'
expect_reject 'producer must not overwrite existing transport' bash "$tool" producer "$good"
copy_fixture extra
printf 'fake unencrypted SQL\n' > "$tmp/extra/database.sql.gz"
expect_reject 'reject plaintext/unexpected fourth file' bash "$tool" writer "$tmp/extra"
copy_fixture tamper
printf 'X' >> "$tmp/tamper/$encrypted"
expect_reject 'reject changed ciphertext hash' bash "$tool" writer "$tmp/tamper"
copy_fixture manifest
jq '.synthetic=false' "$tmp/manifest/manifest.json" > "$tmp/manifest.new"
mv "$tmp/manifest.new" "$tmp/manifest/manifest.json"
expect_reject 'reject falsified public manifest' bash "$tool" writer "$tmp/manifest"
copy_fixture missing
rm -- "$tmp/missing/manifest.json"
expect_reject 'reject missing manifest' bash "$tool" writer "$tmp/missing"
copy_fixture symlink
rm -- "$tmp/symlink/manifest.json"
ln -s "$good/manifest.json" "$tmp/symlink/manifest.json"
expect_reject 'reject symlinked metadata' bash "$tool" writer "$tmp/symlink"
expect_reject 'reject injected token environment' env GH_TOKEN=synthetic-nonreal-marker bash "$tool" writer "$good"
expect_reject 'reject missing synthetic-only consent' env ORGAVELA_PGP_SYNTHETIC_ONLY=NO bash "$tool" writer "$good"
copy_fixture fingerprint
jq '.public_key_fingerprint="0000000000000000000000000000000000000000"' "$tmp/fingerprint/manifest.json" > "$tmp/manifest.new"
mv "$tmp/manifest.new" "$tmp/fingerprint/manifest.json"
expect_reject 'reject wrong recipient fingerprint metadata' bash "$tool" writer "$tmp/fingerprint"
echo 'PASS: OpenPGP ciphertext-only producer/writer and 9 fail-closed regressions'
