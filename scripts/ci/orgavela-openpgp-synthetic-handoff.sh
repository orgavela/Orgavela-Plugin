#!/usr/bin/env bash
# W0/O03 PGP-03: synthetic-only ciphertext handoff, no real site or remote API.
# Producer packages FAKE components with the pinned PUBLIC PGPony key.
# Writer validates exactly three ciphertext-only files; has NO decrypt key.
set -euo pipefail
set +x
umask 077
fail() { printf 'PGPONY_HANDOFF_FAIL: %s\n' "$1" >&2; exit 1; }
[[ "${ORGAVELA_PGP_SYNTHETIC_ONLY:-}" == YES ]] || fail 'explicit synthetic-only opt-in required'
for name in GH_TOKEN GITHUB_TOKEN BACKUP_GITHUB_TOKEN SFTP_HOST SFTP_USER SFTP_PASSWORD IONOS_SSH_HOST IONOS_SSH_PASSWORD SSH_PRIVATE_KEY BACKUP_RECOVERY_PRIVATE_KEY BACKUP_RECOVERY_PASSPHRASE ORGAVELA_BACKUP_PRIVATE_KEY AGE_SECRET_KEY GOOGLE_DRIVE_TOKEN_JSON; do
  [[ -z "${!name:-}" ]] || fail "credential environment forbidden: $name"
done
for cmd in gpg jq sha256sum gzip tar stat awk grep find mktemp; do
  command -v "$cmd" >/dev/null 2>&1 || fail "missing command: $cmd"
done
root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
mode="${1:-}"
workspace="${2:-}"
[[ "$workspace" == /* && "$workspace" != / && "$workspace" != */.. && "$workspace" != */. ]] ||
  fail 'workspace must be an explicit safe absolute directory'
id='orgavela-synthetic-pgpony-handoff-001'
public_key="$root/.github/backup/orgavela-backup-candidate-public.asc"
primary_fpr='7C9DC60D8E1B293E514696EB333D15A4D8BA625C'
subkey_fpr='CF5D8A8498F65C6D894ECE2FD9E58CC6484AF1DE'
encrypted="$id.tar.gpg"
checksum="$id.tar.gpg.sha256"

validate_ciphertext_workspace() {
  [[ -d "$workspace" && ! -L "$workspace" ]] || fail 'ciphertext workspace absent or symlinked'
  local names_count
  names_count="$(find "$workspace" -mindepth 1 -maxdepth 1 -print | wc -l | tr -d ' ')"
  [[ "$names_count" == 3 ]] || fail 'workspace must contain exactly 3 objects'
  for f in "$encrypted" "$checksum" manifest.json; do
    [[ -f "$workspace/$f" && -s "$workspace/$f" && ! -L "$workspace/$f" ]] ||
      fail "missing, empty, symlinked or non-regular asset: $f"
  done
  (cd "$workspace" && sha256sum --status -c "$checksum") || fail 'ciphertext sha256 mismatch'
  local digest size
  digest="$(sha256sum "$workspace/$encrypted" | awk '{print $1}')"
  size="$(stat -c %s "$workspace/$encrypted")"
  jq -e \
    --arg id "$id" --arg digest "$digest" --argjson size "$size" --arg fpr "$primary_fpr" '
    (. | keys | sort) == (["schema","backup_id","synthetic","contains_production_data","archive_sha256","archive_bytes","public_key_fingerprint","encrypted","source_components_encrypted"] | sort)
    and .schema=="orgavela-github-release-backup-public-manifest/v1"
    and .backup_id==$id
    and .synthetic==true and .contains_production_data==false
    and .encrypted==true and .source_components_encrypted==true
    and .archive_sha256==$digest and .archive_bytes==$size
    and .public_key_fingerprint==$fpr' \
    "$workspace/manifest.json" >/dev/null || fail 'public manifest metadata mismatch'
  # Writer may inspect only public encrypted packet headers, never private keys.
  # Both CircleCI jobs run without contexts, and importing owner secrets is denied.
  if gpg --batch --with-colons --list-secret-keys 2>/dev/null | grep -q '^sec'; then
    fail 'private OpenPGP key present in handoff verifier'
  fi
  local packets kid
  packets="$(gpg --batch --list-packets "$workspace/$encrypted" 2>/dev/null || true)"
  grep -q '^:pubkey enc packet:' <<< "$packets" ||
    fail 'missing public-key-encrypted packet'
  grep -q '^:encrypted data packet:' <<< "$packets" ||
    fail 'missing encrypted data packet'
  kid="${subkey_fpr: -16}"
  grep -q "keyid $kid" <<< "$packets" ||
    fail 'recipient encryption-subkey key ID differs from pinned OrgaVela key'
}

case "$mode" in
  producer)
    [[ ! -e "$workspace" && ! -L "$workspace" ]] ||
      fail 'producer refuses to overwrite an existing workspace'
    [[ -s "$public_key" && ! -L "$public_key" ]] || fail 'pinned public key unavailable'
    tmp="$(mktemp -d)"
    trap 'rm -rf -- "$tmp"' EXIT
    mkdir -m 700 "$tmp/pack"
    mkdir -p -m 700 "$tmp/fake-wp/wp-content/uploads"
    printf '%s\n' 'CREATE TABLE orgavela_synthetic_only (id INTEGER);' |
      gzip -n -9 > "$tmp/pack/database.sql.gz"
    printf '%s\n' 'SYNTHETIC TEST ONLY - NO REAL SITE DATA' \
      > "$tmp/fake-wp/wp-content/uploads/synthetic.txt"
    tar -czf "$tmp/pack/wordpress-files.tar.gz" \
      -C "$tmp/fake-wp" wp-content
    BACKUP_WORK="$tmp/pack" BACKUP_ID="$id" \
      BACKUP_PUBLIC_KEY_FILE="$public_key" BACKUP_PUBLIC_KEY_FPR="$primary_fpr" \
      BACKUP_CAPTURE_MODE=synthetic \
      bash "$root/scripts/ci/orgavela-openpgp-synthetic-pack.sh"
    mkdir -m 700 "$workspace"
    # Crucial: CircleCI workspace contains CIPHERTEXT ONLY, never fake plaintext.
    cp -- "$tmp/pack/$encrypted" "$tmp/pack/$checksum" \
      "$tmp/pack/manifest.json" "$workspace/"
    chmod 600 "$workspace"/*
    validate_ciphertext_workspace
    echo 'PGPONY_HANDOFF_PRODUCER=PASS'
    echo 'PGPONY_WORKSPACE_PLAINTEXT=NONE'
    ;;
  writer)
    validate_ciphertext_workspace
    echo 'PGPONY_HANDOFF_WRITER=PASS'
    echo 'PGPONY_RELEASE_UPLOAD=DISABLED'
    ;;
  *)
    fail 'choose producer or writer mode'
    ;;
esac
