#!/usr/bin/env bash
# PGPony public-only GnuPG preflight for OrgaVela: no secrets, network or releases.
set -euo pipefail
set +x
# Print only the failing source line number; no key contents or shell trace.
trap 'status=$?; printf "PGPONY_DIAGNOSTIC_FAILED script=test-orgavela-pgpony-public-key.sh line=%s exit=%s\\n" "$LINENO" "$status" >&2' ERR
umask 077
for cmd in gpg awk grep sha256sum mktemp; do command -v "$cmd" >/dev/null 2>&1 || exit 69; done
[[ -z "${GH_TOKEN:-}" && -z "${SFTP_HOST:-}" && -z "${SFTP_PASSWORD:-}" ]] || { echo "Refusing secret contexts" >&2; exit 1; }
root="$(cd "$(dirname "$0")/../.." && pwd)"
public_key="$root/.github/backup/orgavela-backup-candidate-public.asc"
expected_primary="7C9DC60D8E1B293E514696EB333D15A4D8BA625C"
expected_sub="CF5D8A8498F65C6D894ECE2FD9E58CC6484AF1DE"
[[ -s "$public_key" ]] || exit 1
grep -q '^-----BEGIN PGP PUBLIC KEY BLOCK-----$' "$public_key"
if grep -q '^-----BEGIN PGP PRIVATE KEY BLOCK-----$' "$public_key"; then echo "Private key forbidden" >&2; exit 1; fi
work="$(mktemp -d)"
trap 'rm -rf -- "$work"' EXIT
export GNUPGHOME="$work/gnupg"
mkdir -m 700 "$GNUPGHOME"
gpg --batch --import "$public_key" >/dev/null 2>&1
[[ "$(gpg --batch --with-colons --list-secret-keys 2>/dev/null | grep -c '^sec' || true)" == 0 ]]
keydetails="$(gpg --batch --with-colons --with-fingerprint --with-subkey-fingerprint --list-keys)"
primary="$(printf '%s\n' "$keydetails" | awk -F: '$1=="fpr"{print $10; exit}')"
sub="$(printf '%s\n' "$keydetails" | awk -F: '$1=="sub"{pending=1; next} pending && $1=="fpr"{print $10; exit}')"
[[ "$primary" == "$expected_primary" && "$sub" == "$expected_sub" ]]
# Key-length field is not stable across GnuPG versions (255 vs 256 for 25519).
# Validate OpenPGP algorithm identifiers and an encryption-capable subkey,
# separately from the already pinned full fingerprints.
check_algorithms() {
  printf '%s\n' "$1" | awk -F: '
    $1 == "pub" && $4 == "22" { primary_ed25519 = 1 }
    $1 == "sub" && $4 == "18" && $12 ~ /[eE]/ { sub_cv25519_encryption = 1 }
    END { if (!primary_ed25519 || !sub_cv25519_encryption) exit 1 }
  '
}
check_algorithms "$keydetails"
# Regression probe against the equally valid 256-bit display used by
# some GnuPG builds. This modifies *only* the non-authoritative length label.
keydetails_256="$(printf '%s\n' "$keydetails" | awk -F: '
  BEGIN { OFS = ":" }
  ($1 == "pub" && $4 == "22") || ($1 == "sub" && $4 == "18") { $3 = "256" }
  { print }
')"
check_algorithms "$keydetails_256"
gpg --batch --check-sigs "$expected_primary" > "$work/self-sig-check.log" 2>&1
grep -q 'sig!' "$work/self-sig-check.log"
printf 'OrgaVela PUBLIC-only synthetic fixture. No real WordPress data.\n' > "$work/fixture.txt"
gpg --batch --yes --trust-model always --recipient "$expected_primary" --output "$work/fixture.gpg" --encrypt "$work/fixture.txt"
packets="$(gpg --batch --list-packets "$work/fixture.gpg" 2>&1 || true)"
printf '%s\n' "$packets" | grep -q '^:pubkey enc packet:'
printf '%s\n' "$packets" | grep -q '^:encrypted data packet:'
if gpg --batch --yes --output "$work/restore.txt" --decrypt "$work/fixture.gpg" >/dev/null 2>&1; then
  echo "Unexpected private key access" >&2
  exit 1
fi
echo "PASS: correct OrgaVela PGPony public fingerprint, cv25519 encryption subkey, self-signature and synthetic encryption; no secret key present"
