#!/usr/bin/env bash
# W0/O03 PGP04: one-off, owner-approved, synthetic-only OpenPGP Release writer.
# Never invoke on production datasets. No real WordPress/DB/IONOS access.
set -euo pipefail
set +x
umask 077
fail(){ printf 'PGP04_REFUSED: %s\n' "$1" >&2; exit 1; }
[[ "${PGP04_OPERATOR_APPROVED:-}" == "YES" ]] || fail 'manual owner approval absent'
[[ "${ORGAVELA_PGP_SYNTHETIC_ONLY:-}" == "YES" ]] || fail 'synthetic-only permission absent'
[[ "${CIRCLE_PROJECT_USERNAME:-}" == "janjrre" && "${CIRCLE_PROJECT_REPONAME:-}" == "Projekt-Wordpress-Plugin" ]] || fail 'unexpected source project'
[[ "${CIRCLE_BRANCH:-}" == "security/pgp04-synthetic-release-v3-20261021" ]] || fail 'unexpected branch'
[[ "${GITHUB_BACKUP_REPOSITORY:-}" == "janjrre/orgavela-backups" ]] || fail 'unexpected target'
[[ -n "${GH_TOKEN:-}" ]] || fail 'missing one-time scoped token'
for key in GITHUB_TOKEN BACKUP_GITHUB_TOKEN AGE_SECRET_KEY BACKUP_RECOVERY_PRIVATE_KEY BACKUP_RECOVERY_PASSPHRASE ORGAVELA_BACKUP_PRIVATE_KEY SFTP_HOST SFTP_PASSWORD SSH_PRIVATE_KEY IONOS_SSH_HOST IONOS_SSH_PASSWORD GOOGLE_DRIVE_TOKEN_JSON; do
  [[ -z "${!key:-}" ]] || fail "unexpected credential: $key"
done
for cmd in gh jq sha256sum stat gpg awk grep find cmp mktemp curl; do command -v "$cmd" >/dev/null || fail "missing $cmd"; done
work="${1:-}"
[[ "$work" == /* && "$work" != / && -d "$work" && ! -L "$work" ]] || fail 'invalid ciphertext workspace'
id='orgavela-synthetic-pgpony-handoff-001'
archive="$id.tar.gpg"
sidecar="$archive.sha256"
[[ "$(find "$work" -mindepth 1 -maxdepth 1 | wc -l | tr -d ' ')" == 3 ]] || fail 'workspace must contain exactly three objects'
for name in "$archive" "$sidecar" manifest.json; do
  [[ -f "$work/$name" && -s "$work/$name" && ! -L "$work/$name" ]] || fail "invalid asset $name"
done
(cd "$work" && sha256sum -c --status "$sidecar") || fail 'ciphertext checksum mismatch'
sha="$(sha256sum "$work/$archive" | awk '{print $1}')"
bytes="$(stat -c %s "$work/$archive")"
(( bytes > 0 && bytes < 2147483648 )) || fail 'invalid archive size'
jq -e --arg id "$id" --arg sha "$sha" --argjson bytes "$bytes" '
  (. | keys | sort) == (["schema","backup_id","synthetic","contains_production_data","archive_sha256","archive_bytes","public_key_fingerprint","encrypted","source_components_encrypted"] | sort) and
  .schema == "orgavela-github-release-backup-public-manifest/v1" and
  .backup_id == $id and .synthetic == true and .contains_production_data == false and
  .encrypted == true and .source_components_encrypted == true and
  .archive_sha256 == $sha and .archive_bytes == $bytes and
  .public_key_fingerprint == "7C9DC60D8E1B293E514696EB333D15A4D8BA625C"
' "$work/manifest.json" >/dev/null || fail 'manifest does not attest approved synthetic data'
# Read-only packet inspection: no decryption key in this job.
[[ -z "$(gpg --batch --with-colons --list-secret-keys 2>/dev/null | grep '^sec' || true)" ]] || fail 'private key present in writer'
packets="$(gpg --batch --list-packets "$work/$archive" 2>/dev/null || true)"
grep -q '^:pubkey enc packet:' <<<"$packets" || fail 'no public-key encryption'
grep -q '^:encrypted data packet:' <<<"$packets" || fail 'no encrypted payload'
grep -q 'keyid D9E58CC6484AF1DE' <<<"$packets" || fail 'wrong encryption subkey'
# Pin the branch revision that was reviewed and locked. Only explicit commit value is permitted.
[[ "${PGP04_APPROVED_SHA:-}" =~ ^[[:xdigit:]]{40}$ ]] || fail 'approved commit SHA absent'
[[ "${CIRCLE_SHA1:-}" == "$PGP04_APPROVED_SHA" ]] || fail 'running source SHA differs from reviewed code'
meta="$(gh api "repos/$GITHUB_BACKUP_REPOSITORY")" || fail 'cannot read target repository'
jq -e '.full_name == "janjrre/orgavela-backups" and .private == true and .archived == false' <<<"$meta" >/dev/null || fail 'target must be the approved private repo'
# Server-side context + locked-branch proofs are operator preconditions:
# client checks cannot replace CircleCI context restriction or GitHub branch rules.
tag="pgp04-synthetic-${CIRCLE_BUILD_NUM:-}"
[[ "$tag" =~ ^pgp04-synthetic-[1-9][0-9]*$ ]] || fail 'unique numeric CircleCI build number required'
# Errors other than HTTP 404 MUST fail closed (e.g. 403, 5xx, network failure).
assert_absent() {
  local endpoint="$1" status
  status="$(gh api -i "$endpoint" 2>/dev/null | head -n 1 || true)"
  [[ "$status" == *" 404 "* ]] || fail "cannot prove release/tag absence; HTTP 404 required"
}
assert_absent "repos/$GITHUB_BACKUP_REPOSITORY/releases/tags/$tag"
# Check tag object independently: releases/tags alone does not detect orphan refs.
assert_absent "repos/$GITHUB_BACKUP_REPOSITORY/git/ref/tags/$tag"
# Create as draft; upload only the three checked files.
release="$(gh api -X POST "repos/$GITHUB_BACKUP_REPOSITORY/releases" -f tag_name="$tag" -f name="PGP04 SYNTHETIC OpenPGP ciphertext $tag" -f body="SYNTHETIC TEST ONLY. Never production data. Owner PGPony recovery required." -F draft=true -F prerelease=true)" || fail 'cannot create synthetic draft release'
release_id="$(jq -er '.id | select(type == "number" and . > 0)' <<<"$release")" || fail 'numeric release ID absent'
upload_url="$(jq -er '.upload_url | split("{")[0]' <<<"$release")"
[[ "$upload_url" == "https://uploads.github.com/repos/janjrre/orgavela-backups/releases/$release_id/assets" ]] || fail 'unexpected upload host'
# Use GitHub's strict upload_url on uploads.github.com, not gh api --hostname.
# The latter can resolve to api.uploads.github.com, which is not the right host.
# Token sent ONLY over curl stdin via --header @-, never process argv/disk/logs.
# Require HTTP 201, disable redirects, bound time and response schema.
[[ "$GH_TOKEN" =~ ^[A-Za-z0-9_]+$ ]] || fail 'invalid token characters'
for asset in "$archive" "$sidecar" manifest.json; do
  upload_response="$(mktemp)"
  upload_status="$(
    printf 'Authorization: Bearer %s\nAccept: application/vnd.github+json\nContent-Type: application/octet-stream\n' "$GH_TOKEN" |
      curl --silent --show-error --fail --proto '=https' \
        --connect-timeout 10 --max-time 90 --retry 0 \
        --header @- --request POST --data-binary "@$work/$asset" \
        --output "$upload_response" --write-out '%{http_code}' \
        "$upload_url?name=$asset"
  )" || fail "asset upload failed: $asset"
  [[ "$upload_status" == 201 ]] || fail "unexpected upload status for $asset"
  jq -e --arg name "$asset" '.name == $name and .state == "uploaded" and (.id | type == "number")' "$upload_response" >/dev/null || fail "invalid upload response for $asset"
  rm -f "$upload_response"
done
assets="$(gh api "repos/$GITHUB_BACKUP_REPOSITORY/releases/$release_id/assets?per_page=100")" || fail 'asset listing failed'
jq -e 'type=="array" and length==3 and all(.[]; .state=="uploaded")' <<<"$assets" >/dev/null || fail 'expected exactly three uploaded assets'
verify_remote_asset_bytes() {
  local stage="$1" listing asset aid temp
  listing="$(gh api "repos/$GITHUB_BACKUP_REPOSITORY/releases/$release_id/assets?per_page=100")" || fail "remote listing unavailable at $stage"
  jq -e 'type=="array" and length==3 and all(.[]; .state=="uploaded")' <<<"$listing" >/dev/null || fail "incorrect remote asset count at $stage"
  for asset in "$archive" "$sidecar" manifest.json; do
    aid="$(jq -er --arg n "$asset" '[.[] | select(.name==$n)] | if length==1 then .[0].id else empty end' <<<"$listing")" || fail "missing/duplicate remote asset $asset"
  temp="$(mktemp)"
  # Ensure raw binary response, not a JSON description mistaken for a download.
  gh api -H 'Accept: application/octet-stream' "repos/$GITHUB_BACKUP_REPOSITORY/releases/assets/$aid" >"$temp" || fail 'remote binary readback failed'
  cmp -s "$work/$asset" "$temp" || fail "remote asset mismatch: $asset"
    rm -f "$temp"
  done
}
verify_remote_asset_bytes before_publish
published="$(gh api -X PATCH "repos/$GITHUB_BACKUP_REPOSITORY/releases/$release_id" -F draft=false)" || fail 'could not publish test release'
jq -e --arg tag "$tag" '.tag_name == $tag and .draft==false and .immutable==true' <<<"$published" >/dev/null || fail 'published release immutability not confirmed'
confirmed="$(gh api "repos/$GITHUB_BACKUP_REPOSITORY/releases/$release_id")" || fail 'published release readback missing'
jq -e --arg tag "$tag" --argjson rid "$release_id" '.id==$rid and .tag_name==$tag and .immutable==true and .draft==false' <<<"$confirmed" >/dev/null || fail 'final immutable release readback failed'
verify_remote_asset_bytes after_publish
printf 'PGP04_SYNTHETIC_RELEASE_METADATA_VERIFIED=PASS\nPGP04_RELEASE_ID=%s\nPGP04_TAG=%s\nPGP04_OPERATOR_RESTORE=PENDING\n' "$release_id" "$tag"
