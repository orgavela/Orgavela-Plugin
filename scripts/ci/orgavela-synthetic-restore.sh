#!/usr/bin/env bash
# W0/O03: deterministic disposable restore drill with synthetic data only.
# This is NOT a backup or a restore of orgavela.com.
set -euo pipefail
umask 077
# This job is exclusively the disposable GitHub CI; never run against
# the real OrgaVela hosting DB or any site outside this repository.
[[ "${GITHUB_ACTIONS:-}" == "true" ]] || exit 2
[[ "${GITHUB_REPOSITORY:-}" == "orgavela/Orgavela-Plugin" ]] || exit 2
if [[ "${GITHUB_EVENT_NAME:-}" == "pull_request" ]]; then
  [[ "${GITHUB_REF:-}" =~ ^refs/pull/[0-9]+/merge$ ]] || exit 2
else
  [[ "${GITHUB_EVENT_NAME:-}" == "push" || "${GITHUB_EVENT_NAME:-}" == "workflow_dispatch" ]] || exit 2
  [[ "${GITHUB_REF:-}" == "refs/heads/main" ]] || exit 2
fi
[[ -n "${RESTORE_TEST_DATABASE_PASSWORD:-}" ]] || {
  echo '::error::Synthetic test database password missing'; exit 2;
}
export MYSQL_PWD="$RESTORE_TEST_DATABASE_PASSWORD"
database_host='127.0.0.1'
database_port='3306'
client=(mariadb --protocol=tcp --host="$database_host" --port="$database_port" --user=root --batch --skip-column-names)
dump=(mariadb-dump --protocol=tcp --host="$database_host" --port="$database_port" --user=root --single-transaction --skip-lock-tables --no-tablespaces --skip-comments)
temp="$(mktemp -d)"
trap 'rm -rf "$temp"' EXIT

# Environment-owned, completely new databases; no remote SQL source or live
# secrets can be passed to this CI job.
for n in {1..25}; do
  if "${client[@]}" --execute="SELECT 1" >/dev/null 2>&1; then
    break
  fi
  sleep 2
done
"${client[@]}" --execute="SELECT 1" >/dev/null
"${client[@]}" --execute="
  CREATE DATABASE IF NOT EXISTS uop_synthetic_source CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE DATABASE IF NOT EXISTS uop_synthetic_restore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  DROP TABLE IF EXISTS uop_synthetic_source.uop_restore_fixture;
  CREATE TABLE uop_synthetic_source.uop_restore_fixture (
    id BIGINT UNSIGNED PRIMARY KEY,
    marker VARCHAR(64) NOT NULL UNIQUE,
    data_version SMALLINT UNSIGNED NOT NULL,
    INDEX ix_version (data_version)
  ) ENGINE=InnoDB;
  INSERT INTO uop_synthetic_source.uop_restore_fixture
    (id, marker, data_version)
    VALUES (1, 'synthetic-immutable-fixture', 2),
           (2, 'no-real-participant-data', 2);
" >/dev/null

mkdir -p "$temp/source_files/wp-content/plugins/uop-core" "$temp/source_files/wp-content/uploads"
printf 'UOP synthetic restore fixture only\n' > "$temp/source_files/wp-content/plugins/uop-core/RESTORE-TEST.txt"
printf 'Synthetic uploads only\n' > "$temp/source_files/wp-content/uploads/SYNTHETIC.txt"
tar -czf "$temp/files.tar.gz" -C "$temp/source_files" .
"${dump[@]}" uop_synthetic_source | gzip -c > "$temp/database.sql.gz"
gzip -t "$temp/database.sql.gz"
tar -tzf "$temp/files.tar.gz" >/dev/null
(
  cd "$temp"
  sha256sum database.sql.gz files.tar.gz > SHA256SUMS
  sha256sum --check --status SHA256SUMS
)

# Restore ONLY into a separate synthetic database on this disposable runner.
gzip -dc "$temp/database.sql.gz" | "${client[@]}" uop_synthetic_restore >/dev/null
mkdir -p "$temp/restored_files"
tar -xzf "$temp/files.tar.gz" -C "$temp/restored_files"

source_rows=$("${client[@]}" uop_synthetic_source --execute="SELECT COUNT(*) FROM uop_restore_fixture")
restore_rows=$("${client[@]}" uop_synthetic_restore --execute="SELECT COUNT(*) FROM uop_restore_fixture")
[[ "$source_rows" == '2' && "$restore_rows" == "$source_rows" ]] || exit 1
source_hash=$("${client[@]}" uop_synthetic_source --execute="SELECT SHA2(GROUP_CONCAT(CONCAT(id, ':', marker, ':', data_version) ORDER BY id SEPARATOR '|'), 256) FROM uop_restore_fixture")
restore_hash=$("${client[@]}" uop_synthetic_restore --execute="SELECT SHA2(GROUP_CONCAT(CONCAT(id, ':', marker, ':', data_version) ORDER BY id SEPARATOR '|'), 256) FROM uop_restore_fixture")
[[ "$source_hash" == "$restore_hash" && -n "$source_hash" ]] || exit 1
cmp "$temp/source_files/wp-content/plugins/uop-core/RESTORE-TEST.txt" "$temp/restored_files/wp-content/plugins/uop-core/RESTORE-TEST.txt"
cmp "$temp/source_files/wp-content/uploads/SYNTHETIC.txt" "$temp/restored_files/wp-content/uploads/SYNTHETIC.txt"
index_count=$("${client[@]}" uop_synthetic_restore --execute="SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = 'uop_synthetic_restore' AND TABLE_NAME = 'uop_restore_fixture' AND INDEX_NAME = 'ix_version'")
[[ "$index_count" == '1' ]] || exit 1

echo 'ORGAVELA_RESTORE_DRILL=synthetic-success'
echo 'ORGAVELA_RESTORE_DRILL_DATABASE=isolated-test-container'
echo 'ORGAVELA_RESTORE_DRILL_ROWS=2-synthetic'
echo 'ORGAVELA_RESTORE_DRILL_DB_AND_FILES_SHA256=verified'
echo 'ORGAVELA_RESTORE_DRILL_INDEX=verified'
echo 'ORGAVELA_RESTORE_DRILL_LIVE_BACKUP_USED=no'
echo 'ORGAVELA_RESTORE_DRILL_LIVE_RESTORE=no'
