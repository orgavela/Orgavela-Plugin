#!/usr/bin/env bash
# OrgaVela synthetic OpenPGP packaging; NOT authorized for real OrgaVela capture. Does not access production,
# configure secrets, trigger CI, contact GitHub, or expose recovery private keys.
# Inputs: database.sql.gz, wordpress-files.tar.gz (already captured).
# Outputs: encrypted *.tar.gpg, checksum sidecar, sanitized public manifest.
set -euo pipefail
set +x
umask 077

fail() { printf 'OrgaVela backup packaging: %s\n' "$1" >&2; exit 1; }

: "${BACKUP_WORK:?Private work directory required}"
: "${BACKUP_ID:?Unique synthetic backup ID required}"
: "${BACKUP_PUBLIC_KEY_FILE:?OpenPGP public key file required}"
: "${BACKUP_PUBLIC_KEY_FPR:?Expected public key fingerprint required}"
: "${BACKUP_CAPTURE_MODE:?Capture mode (synthetic/production) required}"
[[ "${ORGAVELA_PGP_SYNTHETIC_ONLY:-}" == YES ]] || fail 'synthetic-only opt-in required'
[[ -z "${GH_TOKEN:-}" && -z "${SFTP_HOST:-}" && -z "${SFTP_USER:-}" ]] || fail 'production or GitHub credentials forbidden'
[[ -z "${AGE_SECRET_KEY:-}" ]] || fail 'private key environment forbidden'

[[ "$BACKUP_WORK" = /* && -d "$BACKUP_WORK" && ! -L "$BACKUP_WORK" ]] ||
  fail 'BACKUP_WORK must be an existing private absolute directory, not a symlink'
[[ "$(stat -c %a "$BACKUP_WORK")" =~ ^(700|750)$ ]] ||
  fail 'BACKUP_WORK permissions must be 0700 or 0750'
[[ "$BACKUP_ID" =~ ^orgavela-synthetic-[A-Za-z0-9_-]{3,100}$ ]] ||
  fail 'invalid backup ID format'
[[ "$BACKUP_PUBLIC_KEY_FPR" =~ ^[A-Fa-f0-9]{40}$ ]] ||
  fail 'expected public key fingerprint must have 40 hex characters'
case "$BACKUP_CAPTURE_MODE" in
  synthetic) ;;
  production) fail 'production mode forbidden in synthetic GH03 migration test' ;;
  *) fail 'unknown capture mode' ;;
esac
for cmd in gpg jq gzip tar sha256sum stat mktemp awk grep; do
  command -v "$cmd" >/dev/null 2>&1 || fail "required command missing: $cmd"
done
[[ -f "$BACKUP_PUBLIC_KEY_FILE" && -s "$BACKUP_PUBLIC_KEY_FILE" ]] ||
  fail 'public key file unavailable'
[[ ! -L "$BACKUP_PUBLIC_KEY_FILE" ]] || fail 'public key file must not be a symlink'
db="$BACKUP_WORK/database.sql.gz"
files="$BACKUP_WORK/wordpress-files.tar.gz"
[[ -s "$db" && -s "$files" && ! -L "$db" && ! -L "$files" ]] ||
  fail 'both captured source archives must be nonempty regular files'
gzip -t "$db" || fail 'invalid compressed MariaDB dump'
gzip -t "$files" || fail 'invalid compressed WordPress archive'
tar -tzf "$files" >/dev/null || fail 'WordPress archive tar integrity failed'

encrypted="$BACKUP_WORK/$BACKUP_ID.tar.gpg"
sidecar="$encrypted.sha256"
public_manifest="$BACKUP_WORK/manifest.json"
for dest in "$encrypted" "$sidecar" "$public_manifest"; do
  [[ ! -e "$dest" && ! -L "$dest" ]] ||
    fail 'backup output already exists; never overwrite an artifact'
done

stage="$(mktemp -d "$BACKUP_WORK/.packaging.XXXXXXXX")"
trap 'rm -rf -- "$stage"' EXIT
chmod 700 "$stage"
export GNUPGHOME="$stage/gnupg"
mkdir -m 700 "$GNUPGHOME"
# Imports must contain public material only. The producer may not possess a
# private recovery key. Reject imports accidentally including secret keys.
gpg --batch --no-tty --import "$BACKUP_PUBLIC_KEY_FILE" >/dev/null 2>&1 ||
  fail 'public OpenPGP key import failed'
if gpg --batch --with-colons --list-secret-keys 2>/dev/null | grep -q '^sec'; then
  fail 'PRIVATE recovery key detected in producer input; refusing encryption'
fi
actual="$(gpg --batch --with-colons --fingerprint --list-keys |
  awk -F: '$1 == "fpr" {print toupper($10); exit}')"
expected="$(printf '%s' "$BACKUP_PUBLIC_KEY_FPR" | tr '[:lower:]' '[:upper:]')"
[[ "$actual" == "$expected" ]] || fail 'recipient public key fingerprint mismatch'

db_sha="$(sha256sum "$db" | awk '{print $1}')"
files_sha="$(sha256sum "$files" | awk '{print $1}')"
db_bytes="$(stat -c %s "$db")"
files_bytes="$(stat -c %s "$files")"
[[ "$db_bytes" -gt 0 && "$files_bytes" -gt 0 ]] || fail 'empty source components'

# Internal manifest contains component hashes. It is included ONLY inside the
# encrypted archive; only the sanitized public manifest is exported separately.
jq -n --arg id "$BACKUP_ID" \
  --arg captured_at_utc "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
  --arg dbsha "$db_sha" --arg filessha "$files_sha" \
  --argjson dbbytes "$db_bytes" --argjson filesbytes "$files_bytes" \
  --arg fpr "$expected" --arg mode "$BACKUP_CAPTURE_MODE" \
  '{schema:"orgavela-backup-package/v1",backup_id:$id,captured_at_utc:$captured_at_utc,
    mode:$mode,scope:{database:true,wordpress_filesystem:true,uploads:true,wp_config:true},
    components:{database:{name:"database.sql.gz",sha256:$dbsha,bytes:$dbbytes},
                wordpress:{name:"wordpress-files.tar.gz",sha256:$filessha,bytes:$filesbytes}},
    encryption:{type:"OpenPGP-public-key",public_key_fingerprint:$fpr,private_key_in_producer:false}}' \
  > "$stage/internal-manifest.json"
capture_members=(database.sql.gz wordpress-files.tar.gz)
if [[ "$BACKUP_CAPTURE_MODE" == production ]]; then
  [[ -f "$BACKUP_WORK/capture-evidence.json" && ! -L "$BACKUP_WORK/capture-evidence.json" ]] ||
    fail 'production quiescence evidence missing'
  jq -e --arg dbsha "$db_sha" --arg fsha "$files_sha" '
    .schema=="orgavela-ssh-capture-evidence/v1" and .capture_mode=="production" and
    .filesystem_consistency=="quiesced" and .database_consistency=="single-transaction" and
    .quiescence.wordpress_maintenance==true and .quiescence.all_table_read_locks==true and
    .quiescence.stable_file_hashes==true and .quiescence.maintenance_removed==true and
    .quiescence.maintenance_origin_verified==true and .quiescence.source_origin_recovered==true and
    .components.database.sha256==$dbsha and .components.wordpress.sha256==$fsha' \
    "$BACKUP_WORK/capture-evidence.json" >/dev/null || fail 'invalid production capture evidence'
  capture_members+=(capture-evidence.json)
fi
tar -cf "$stage/$BACKUP_ID.tar" \
  -C "$stage" --transform='s|^internal-manifest.json$|manifest.json|' internal-manifest.json \
  -C "$BACKUP_WORK" "${capture_members[@]}"

gpg --batch --no-tty --yes --trust-model always --recipient "$actual" \
  --output "$stage/$BACKUP_ID.tar.gpg" --encrypt "$stage/$BACKUP_ID.tar" ||
  fail 'public-key OpenPGP encryption failed'
[[ -s "$stage/$BACKUP_ID.tar.gpg" ]] || fail 'encrypted archive empty'
gpg --batch --no-tty --list-packets "$stage/$BACKUP_ID.tar.gpg" \
  >"$stage/packets.txt" 2>/dev/null || true
grep -q '^:pubkey enc packet:' "$stage/packets.txt" ||
  fail 'encrypted archive has no OpenPGP public-key packet'
grep -q '^:encrypted data packet:' "$stage/packets.txt" ||
  fail 'encrypted archive has no OpenPGP encrypted-data packet'

encrypted_sha="$(sha256sum "$stage/$BACKUP_ID.tar.gpg" | awk '{print $1}')"
encrypted_bytes="$(stat -c %s "$stage/$BACKUP_ID.tar.gpg")"
printf '%s  %s\n' "$encrypted_sha" "$BACKUP_ID.tar.gpg" > "$stage/checksum.sha256"
jq -n --arg id "$BACKUP_ID" --arg sha "$encrypted_sha" \
  --argjson bytes "$encrypted_bytes" --arg fpr "$expected" \
  --arg mode "$BACKUP_CAPTURE_MODE" \
  '{schema:"orgavela-github-release-backup-public-manifest/v1",backup_id:$id,
    synthetic:($mode == "synthetic"),contains_production_data:($mode == "production"),
    archive_sha256:$sha,archive_bytes:$bytes,public_key_fingerprint:$fpr,
    encrypted:true,source_components_encrypted:true}' > "$stage/release-manifest.json"

# Publish to local ephemeral workdir only AFTER all checks succeeded. Never
# write plaintext to GitHub artifacts or any remote transport from this module.
mv -- "$stage/$BACKUP_ID.tar.gpg" "$encrypted"
mv -- "$stage/checksum.sha256" "$sidecar"
mv -- "$stage/release-manifest.json" "$public_manifest"
printf 'PASS: OpenPGP public-key encrypted backup package and SHA-256 sidecar generated.\n'
