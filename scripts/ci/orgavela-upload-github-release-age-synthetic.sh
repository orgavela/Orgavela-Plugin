#!/usr/bin/env bash
# GH-06 native age-only GitHub Release proof, synthetic ONLY. No WordPress/IONOS/retention.
set -euo pipefail
set +x
umask 077

fail() { printf 'GitHub Releases transport: %s\n' "$1" >&2; exit 1; }
[[ "${ORGAVELA_SYNTHETIC_ONLY:-}" == YES ]] || fail 'synthetic-only approval missing'
[[ "${ORGAVELA_GH06_AGE_RELEASE_APPROVED:-}" == YES ]] || fail 'GH06 explicit synthetic-only approval missing'
[[ -z "${SFTP_HOST:-}" && -z "${SFTP_PASSWORD:-}" && -z "${GOOGLE_DRIVE_TOKEN_JSON:-}" ]] || fail 'unexpected production/Drive credentials'
: "${GH_TOKEN:?Restricted GitHub token missing}"
: "${GITHUB_BACKUP_REPOSITORY:?Separate private backup repository missing}"
: "${BACKUP_WORK:?Temporary work directory missing}"
: "${BACKUP_ID:?Synthetic backup id missing}"
: "${ENCRYPTED_BACKUP_FILE:?Source archive missing}"
: "${EXPECTED_ENCRYPTED_SHA256:?Expected SHA-256 missing}"
[[ "$GITHUB_BACKUP_REPOSITORY" =~ ^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$ ]] || fail 'invalid repository identifier'
[[ "$GITHUB_BACKUP_REPOSITORY" == janjrre/orgavela-backups ]] || fail 'only the explicitly designated private backup repository is permitted'
[[ "$BACKUP_ID" =~ ^orgavela-age-synthetic-[a-zA-Z0-9_-]+$ ]] || fail 'only unique synthetic backup ids are allowed'
[[ "$EXPECTED_ENCRYPTED_SHA256" =~ ^[a-f0-9]{64}$ ]] || fail 'invalid checksum'
[[ -s "$ENCRYPTED_BACKUP_FILE" && "$ENCRYPTED_BACKUP_FILE" == *.tar.age ]] || fail 'missing pre-encrypted archive'
for command in gh jq sha256sum stat mktemp python3; do
  command -v "$command" >/dev/null 2>&1 || fail "required tool missing: $command"
done
[[ "$(sha256sum "$ENCRYPTED_BACKUP_FILE" | cut -d' ' -f1)" == "$EXPECTED_ENCRYPTED_SHA256" ]] || fail 'local archive SHA-256 mismatch'
# A .age suffix alone is not evidence. Check the standard age envelope header.
# Full decryption is checked by the synthetic remote-only recovery job, not by
# this token-bearing writer (which must not receive ANY private identity).
python3 - "$ENCRYPTED_BACKUP_FILE" <<'PY'
import pathlib, sys
p=pathlib.Path(sys.argv[1])
with p.open('rb') as stream:
    header=stream.read(22)
if header != b'age-encryption.org/v1\n':
    raise SystemExit('Not a native age v1 ciphertext envelope')
PY
# Reject unexpected recovery secrets on the writer boundary.
for key in BACKUP_RECOVERY_PRIVATE_KEY BACKUP_RECOVERY_PASSPHRASE AGE_SECRET_KEY ORGAVELA_BACKUP_PRIVATE_KEY SFTP_HOST SFTP_PASSWORD SSH_PRIVATE_KEY GOOGLE_DRIVE_TOKEN_JSON; do
  [[ -z "${!key:-}" ]] || fail "unsafe secret injected into age release writer: $key"
done
# Never trust a caller-supplied repository name: enforce one private target.
repo_metadata="$(gh repo view "$GITHUB_BACKUP_REPOSITORY" --json nameWithOwner,isPrivate,isArchived)" || fail 'GitHub backup repository inaccessible'
[[ "$(jq -r '.nameWithOwner' <<< "$repo_metadata")" == "$GITHUB_BACKUP_REPOSITORY" ]] || fail 'wrong GitHub repository'
[[ "$(jq -r '.isPrivate' <<< "$repo_metadata")" == true ]] || fail 'backup repository must be private'
[[ "$(jq -r '.isArchived' <<< "$repo_metadata")" == false ]] || fail 'backup repository must not be archived'
size="$(stat -c '%s' "$ENCRYPTED_BACKUP_FILE")"
(( size > 0 && size < 2147483648 )) || fail 'synthetic archive must be below 2 GiB; chunking is not yet implemented'

export GH_TOKEN
tag="backup-${BACKUP_ID}"
dir="$(mktemp -d "$BACKUP_WORK/github-release-test.XXXXXXXX")"
trap 'rm -rf -- "$dir"' EXIT
archive_name="$(basename "$ENCRYPTED_BACKUP_FILE")"
[[ "$archive_name" =~ ^[A-Za-z0-9_.-]+\.tar\.age$ ]] || fail 'invalid archive name'
status="$BACKUP_WORK/github-release-transport-status.json"
jq -n --arg id "$BACKUP_ID" '{schema:"orgavela-age-github-release-synthetic/v1",backup_id:$id,provider:"github_releases",synthetic:true,uploaded:false,readback_verified:false,published:false,immutable_verified:false,independent_restore_tested:false}' > "$status"

manifest="$dir/manifest.json"
checksum_file="$dir/${archive_name}.sha256"
printf '%s  %s\n' "$EXPECTED_ENCRYPTED_SHA256" "$archive_name" > "$checksum_file"
jq -n --arg id "$BACKUP_ID" --arg sha "$EXPECTED_ENCRYPTED_SHA256" --argjson bytes "$size" --arg tag "$tag" '{schema:"orgavela-age-github-release-synthetic-manifest/v1",synthetic:true,encryption:"age",backup_id:$id,tag:$tag,archive_sha256:$sha,archive_bytes:$bytes,contains_production_data:false}' > "$manifest"

# Reject existing drafts AS WELL AS published releases before creating anything.
# gh release view <tag> may fail on drafts because the Git tag only exists once
# the release is published. The authenticated List Releases REST API includes
# drafts when the account has push access. Never overwrite or delete a draft.
preexisting="$(gh api "repos/$GITHUB_BACKUP_REPOSITORY/releases?per_page=100")" \
  || fail 'cannot inventory existing releases safely'
jq -e 'type == "array"' <<< "$preexisting" >/dev/null || fail 'invalid GitHub release inventory'
if jq -e --arg tag "$tag" '.[] | select(.tag_name == $tag)' <<< "$preexisting" >/dev/null; then
  fail 'existing draft/release tag detected; will not overwrite any backup'
fi
if gh release view "$tag" --repo "$GITHUB_BACKUP_REPOSITORY" >/dev/null 2>&1; then
  fail 'release tag already exists; existing backups are never overwritten'
fi
# Asset upload is part of the original release CREATE operation; never call
# gh release upload <tag> against a just-created draft (gh/cli#6599).
gh release create "$tag" "$ENCRYPTED_BACKUP_FILE" "$manifest" "$checksum_file" \
  --repo "$GITHUB_BACKUP_REPOSITORY" --draft --title "$tag" \
  --notes 'Synthetic AGE X25519 encrypted-archive transport test; not a production backup' >/dev/null

# Resolve the fresh, uniquely tagged DRAFT through the authenticated API.
# Drafts can be listed for a push-authorized token even before there is a Git
# tag. From this point onward, use numeric release/asset IDs, not tag lookups.
release_id=''
for attempt in 1 2 3 4 5 6 7 8; do
  response="$(gh api "repos/$GITHUB_BACKUP_REPOSITORY/releases?per_page=100" 2>/dev/null)" || response=''
  if [[ -n "$response" ]]; then
    release_id="$(jq -er --arg tag "$tag" \
      '[.[] | select(.tag_name == $tag and .draft == true)] |
       if length == 1 then .[0].id else empty end' <<< "$response" 2>/dev/null)" || release_id=''
  fi
  if [[ "$release_id" =~ ^[1-9][0-9]*$ ]]; then break; fi
  if (( attempt < 8 )); then sleep 2; fi
done
[[ "$release_id" =~ ^[1-9][0-9]*$ ]] || fail 'draft numeric release ID unavailable; refusing to publish'

# Download all THREE remote assets via release/asset ID, even BEFORE publish.
# GitHub documents binary access with Accept: application/octet-stream.
# Binary output is never trusted without SHA-256 verification against LOCAL data.
verify_download() {
  local stage="$1" local_file name expected actual asset_id assets
  local target="$dir/$stage"
  mkdir -m 700 "$target"
  assets="$(gh api "repos/$GITHUB_BACKUP_REPOSITORY/releases/$release_id/assets?per_page=100")" \
    || fail "cannot list remote assets by release ID: $stage"
  jq -e 'type == "array" and length == 3' <<< "$assets" >/dev/null \
    || fail "incorrect number of remote release assets: $stage"
  for local_file in "$ENCRYPTED_BACKUP_FILE" "$manifest" "$checksum_file"; do
    name="$(basename "$local_file")"
    asset_id="$(jq -er --arg name "$name" \
      '[.[] | select(.name == $name and .state == "uploaded")] |
       if length == 1 then .[0].id else empty end' <<< "$assets")" \
      || fail "missing or duplicate remote asset: $name"
    [[ "$asset_id" =~ ^[1-9][0-9]*$ ]] || fail "invalid asset ID: $name"
    gh api -H "Accept: application/octet-stream" \
      "repos/$GITHUB_BACKUP_REPOSITORY/releases/assets/$asset_id" > "$target/$name" \
      || fail "binary readback via asset ID failed: $name"
    [[ -s "$target/$name" ]] || fail "remote asset empty: $name"
    expected="$(sha256sum "$local_file" | cut -d' ' -f1)"
    actual="$(sha256sum "$target/$name" | cut -d' ' -f1)"
    [[ "$actual" == "$expected" ]] || fail "SHA-256 mismatch after remote readback: $name"
  done
}
verify_download before_publish

# Publish ONLY the already verified numeric draft. gh release edit by TAG is
# subject to the same draft lookup problem as gh release download.
publication="$(gh api -X PATCH -F draft=false \
  "repos/$GITHUB_BACKUP_REPOSITORY/releases/$release_id")" \
  || fail 'publishing verified synthetic draft by numeric ID failed'
jq -e --arg tag "$tag" --argjson id "$release_id" \
  '.id == $id and .tag_name == $tag and .draft == false' \
  <<< "$publication" >/dev/null || fail 'publication REST response inconsistent'
verify_download after_publish

jq -n --arg id "$BACKUP_ID" --arg tag "$tag" --arg repo "$GITHUB_BACKUP_REPOSITORY" --arg sha "$EXPECTED_ENCRYPTED_SHA256" --argjson rid "$release_id" \
  '{schema:"orgavela-age-github-release-synthetic/v1",backup_id:$id,tag:$tag,repository:$repo,release_id:$rid,provider:"github_releases",synthetic:true,uploaded:true,readback_verified:true,published:true,encrypted_sha256:$sha,immutable_verified:false,independent_restore_tested:false}' > "$status"
printf 'PASS: synthetic GitHub release ID-based pre/post-publication remote binary SHA-256 readback verified.\n'
