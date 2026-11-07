#!/usr/bin/env bash
# GH03 PGPony migration: offline synthetic proof ONLY. No owner key, site, or CI token.
set -euo pipefail
set +x
# Print only the failing source line number; no key contents or shell trace.
trap 'status=$?; printf "PGPONY_DIAGNOSTIC_FAILED script=test-orgavela-openpgp-synthetic-pack.sh line=%s exit=%s\\n" "$LINENO" "$status" >&2' ERR
umask 077
for c in gpg jq gzip tar sha256sum mktemp; do command -v "$c" >/dev/null 2>&1 || exit 69; done
root="$(cd "$(dirname "$0")/../.." && pwd)"
tmp="$(mktemp -d)"
trap 'rm -rf -- "$tmp"' EXIT
mkdir -m 700 "$tmp/owner-gnupg"
export GNUPGHOME="$tmp/owner-gnupg"
gpg --batch --quiet --passphrase '' --quick-generate-key 'OrgaVela synthetic only <synthetic@invalid.example>' rsa2048 encr 1d >/dev/null 2>&1
fpr="$(gpg --batch --with-colons --fingerprint --list-keys | awk -F: '$1=="pub"{p=1;next}p && $1=="fpr"{print toupper($10);exit}')"
[[ "$fpr" =~ ^[A-Fa-f0-9]{40}$ ]]
gpg --batch --armor --export "$fpr" > "$tmp/public.asc"
gpg --batch --armor --export-secret-keys "$fpr" > "$tmp/private.asc"
mkdir -m 700 "$tmp/work"
printf 'CREATE TABLE synthetic_only (id INTEGER);\n' | gzip -9 > "$tmp/work/database.sql.gz"
mkdir -p "$tmp/fake-wp/wp-content/uploads"
printf 'fake upload not production\n' > "$tmp/fake-wp/wp-content/uploads/test.txt"
tar -czf "$tmp/work/wordpress-files.tar.gz" -C "$tmp/fake-wp" wp-content
export BACKUP_WORK="$tmp/work"
export BACKUP_ID="orgavela-synthetic-test-001"
export BACKUP_PUBLIC_KEY_FILE="$tmp/public.asc"
export BACKUP_PUBLIC_KEY_FPR="$fpr"
export BACKUP_CAPTURE_MODE=synthetic
export ORGAVELA_PGP_SYNTHETIC_ONLY=YES
bash "$root/scripts/ci/orgavela-openpgp-synthetic-pack.sh"
(cd "$BACKUP_WORK" && sha256sum -c "$BACKUP_ID.tar.gpg.sha256" >/dev/null)
[[ "$(jq -r '.synthetic' "$BACKUP_WORK/manifest.json")" == true ]]
[[ "$(jq -r '.contains_production_data' "$BACKUP_WORK/manifest.json")" == false ]]
test "$(find "$BACKUP_WORK" -maxdepth 1 -type f | grep -E '/(orgavela-synthetic-test-001\.tar\.gpg|orgavela-synthetic-test-001\.tar\.gpg\.sha256|manifest\.json)$' | wc -l)" = 3
mkdir -m 700 "$tmp/decrypted"
gpg --batch --yes --output "$tmp/decrypted/package.tar" --decrypt "$BACKUP_WORK/$BACKUP_ID.tar.gpg" >/dev/null 2>&1
tar -xf "$tmp/decrypted/package.tar" -C "$tmp/decrypted"
test "$(sha256sum "$tmp/decrypted/database.sql.gz" | awk '{print $1}')" = "$(sha256sum "$BACKUP_WORK/database.sql.gz" | awk '{print $1}')"
test "$(jq -r '.components.database.sha256' "$tmp/decrypted/manifest.json")" = "$(sha256sum "$BACKUP_WORK/database.sql.gz" | awk '{print $1}')"
export BACKUP_ID="orgavela-synthetic-negative-002"
if BACKUP_PUBLIC_KEY_FPR=0000000000000000000000000000000000000000 bash "$root/scripts/ci/orgavela-openpgp-synthetic-pack.sh" >/dev/null 2>&1; then echo 'FAIL wrong key accepted'; exit 1; fi
if BACKUP_PUBLIC_KEY_FILE="$tmp/private.asc" bash "$root/scripts/ci/orgavela-openpgp-synthetic-pack.sh" >/dev/null 2>&1; then echo 'FAIL secret key accepted'; exit 1; fi
if BACKUP_CAPTURE_MODE=production bash "$root/scripts/ci/orgavela-openpgp-synthetic-pack.sh" >/dev/null 2>&1; then echo 'FAIL production mode accepted'; exit 1; fi
# Additional fail-closed security cases for exclusively artificial input.
if BACKUP_ID="orgavela-synthetic-test-001" bash "$root/scripts/ci/orgavela-openpgp-synthetic-pack.sh" >/dev/null 2>&1; then echo 'FAIL output overwrite accepted'; exit 1; fi
if GH_TOKEN="synthetic-redacted-nonreal-marker" bash "$root/scripts/ci/orgavela-openpgp-synthetic-pack.sh" >/dev/null 2>&1; then echo 'FAIL token context accepted'; exit 1; fi
if ORGAVELA_PGP_SYNTHETIC_ONLY=NO bash "$root/scripts/ci/orgavela-openpgp-synthetic-pack.sh" >/dev/null 2>&1; then echo 'FAIL missing opt-in accepted'; exit 1; fi
if BACKUP_ID="../outside" bash "$root/scripts/ci/orgavela-openpgp-synthetic-pack.sh" >/dev/null 2>&1; then echo 'FAIL unsafe output ID accepted'; exit 1; fi
ln -s "$tmp/public.asc" "$tmp/symlink-public.asc"
if BACKUP_PUBLIC_KEY_FILE="$tmp/symlink-public.asc" bash "$root/scripts/ci/orgavela-openpgp-synthetic-pack.sh" >/dev/null 2>&1; then echo 'FAIL symlinked public key accepted'; exit 1; fi
echo 'PASS: isolated OpenPGP synthetic pack, SHA256, recovery and 8 negative checks'
