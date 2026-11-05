#!/usr/bin/env bash
# Explicit operator-authorized OrgaVela account from existing GitHub orgavela-ionos environment.
# Strictly bounded OrgaVela-only W0 audit. Does not deploy, change files,
# run migrations, export tables, execute arbitrary requests or print secrets.
set -euo pipefail
umask 077

mode="$1"
[[ "$mode" == 'sftp' || "$mode" == 'probe' || "$mode" == 'schema' || "$mode" == 'backup-preflight' || "$mode" == 'cron' ]] || exit 2
[[ "$GITHUB_REPOSITORY" == 'orgavela/Orgavela-Plugin' ]] || exit 2
[[ "$GITHUB_REF" == 'refs/heads/main' ]] || exit 2

host="$IONOS_AUDIT_SSH_HOST"
user="$IONOS_AUDIT_SSH_USER"
root="$IONOS_AUDIT_WP_ROOT"
[[ -n "$host" && -n "$user" && -n "$SSHPASS" && -n "$root" ]] || {
  echo '::error::Missing existing orgavela-ionos SSH secret or IONOS WordPress path. No remote access attempted.'
  exit 2
}
[[ "$host" =~ ^[a-zA-Z0-9.-]+$ && "$user" =~ ^[a-zA-Z0-9._-]+$ ]] || exit 2
[[ "$root" =~ ^/[a-zA-Z0-9._/-]+$ && "$root" != *..* && "$root" != *dreamloud* ]] || exit 2

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT
# A freshly discovered key is not implicitly trusted: pin to the ED25519
# fingerprint published at ionos.de/hilfe/hosting/ssh-zugaenge-einrichten-und-verwalten/.
ssh-keyscan -T 8 -p 22 -t ed25519 "$host" > "$tmp/known_hosts" 2>/dev/null || {
  echo '::error::SSH host key could not be fetched; no remote operation attempted.'
  exit 1
}
if ! ssh-keygen -lf "$tmp/known_hosts" | awk '{print $2}' | grep -Fxq 'SHA256:1gx2w8Rtv3wCgi7Jh8myf/KVd72cRQbow03UP8P095Q'; then
  echo '::error::SSH host fingerprint does not match official IONOS ED25519 reference.'
  exit 1
fi

# SFTP-only mode: verify the already operator-approved OrgaVela tree.
# Never download configurations/files or expose directory listings in logs.
# This proves SFTP access, NOT an interactive SSH shell or SQL access.
if [[ "$mode" == 'sftp' ]]; then
  {
    printf 'ls "%s/wp-admin"\n' "$root"
    printf 'ls "%s/wp-content/plugins/uop-core"\n' "$root"
  } > "$tmp/sftp-readonly.batch"
  if sshpass -e sftp -q -P 22 \
    -oBatchMode=no \
    -oPreferredAuthentications=password,keyboard-interactive \
    -oPubkeyAuthentication=no -oConnectTimeout=15 \
    -oStrictHostKeyChecking=yes \
    -oUserKnownHostsFile="$tmp/known_hosts" \
    -b "$tmp/sftp-readonly.batch" "$user@$host" \
    > "$tmp/sftp-out" 2> "$tmp/sftp-err"; then
    echo 'ORGAVELA_AUDIT_SFTP=verified'
    echo 'ORGAVELA_AUDIT_SHELL=not_checked'
    echo 'ORGAVELA_AUDIT_SCHEMA=not_checked'
    exit 0
  fi
  echo '::error::SFTP access or expected OrgaVela path was not verified. All raw connection output is withheld.'
  exit 1
fi

# Record BOTH streams privately and report only explicitly safe status keys.
if sshpass -e ssh -T -p 22 \
  -o StrictHostKeyChecking=yes \
  -o UserKnownHostsFile="$tmp/known_hosts" \
  -o PreferredAuthentications=password,keyboard-interactive \
  -o PubkeyAuthentication=no -o ConnectTimeout=15 \
  "$user@$host" "bash -s -- '$root' '$mode'" \
  > "$tmp/out" 2> "$tmp/err" <<'REMOTE'
set -euo pipefail
root="$1"
mode="$2"
[[ "$root" =~ ^/[a-zA-Z0-9._/-]+$ && "$root" != *..* && "$root" != *dreamloud* ]] || exit 2
# SFTP /public can map to a different absolute path inside an IONOS shell.
# Use a short allowlist of documented conventional IONOS web roots; never
# search other contracts or print any remote directory values.
if [[ ! -f "$root/wp-config.php" || ! -d "$root/wp-admin" || ! -d "$root/wp-content/plugins/uop-core" ]]; then
  selected=''
  for candidate in "/home/www${root}" "$HOME${root}" "/home/www" "$HOME/public" "$HOME/htdocs${root}"; do
    if [[ -f "$candidate/wp-config.php" && -d "$candidate/wp-admin" && -d "$candidate/wp-content/plugins/uop-core" ]]; then
      selected="$candidate"
      break
    fi
  done
  [[ -n "$selected" ]] || exit 21
  root="$selected"
fi
command -v wp >/dev/null || exit 22
home="$(wp --path="$root" --skip-plugins --skip-themes option get home 2>/dev/null)"
[[ "$home" == 'https://orgavela.com' || "$home" == 'https://orgavela.com/' ]] || exit 23
version="$(wp --path="$root" --skip-plugins --skip-themes plugin get uop-core --field=version 2>/dev/null)"
[[ "$version" == '0.1.0-alpha.2' ]] || exit 24

echo 'ORGAVELA_AUDIT_TARGET=verified'
echo 'ORGAVELA_AUDIT_SHELL=available'
echo 'ORGAVELA_AUDIT_PLUGIN_VERSION=alpha2'

if [[ "$mode" == 'probe' ]]; then
  echo 'ORGAVELA_AUDIT_PROBE=success'
  exit 0
fi

# O04: aggregate read-only WordPress cron and Action Scheduler inventory.
# Neither the cron option nor actions table raw rows leave the host, and
# --skip-plugins/--skip-themes prevents UOP lifecycle/migration execution.
if [[ "$mode" == 'cron' ]]; then
  if command -v crontab >/dev/null 2>&1; then
    echo 'ORGAVELA_CRON_SHELL_BINARY=available'
  else
    echo 'ORGAVELA_CRON_SHELL_BINARY=unavailable'
  fi
  php_code="$(cat <<'PHP'
global $wpdb;
$now = time();
echo 'ORGAVELA_CRON_WP_AUTO=' . (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ? 'disabled' : 'enabled') . "\n";
$events = get_option('cron', array());
if (!is_array($events)) {
    echo "ORGAVELA_CRON_WP_OPTION=invalid\n";
    echo "ORGAVELA_CRON_QUEUE_EVENT=unavailable\n";
} else {
    echo "ORGAVELA_CRON_WP_OPTION=valid\n";
    $next = null;
    $matching = 0;
    $overdue = 0;
    foreach ($events as $timestamp => $hooks) {
        if (!is_numeric($timestamp) || !is_array($hooks)) {
            continue;
        }
        $ts = (int) $timestamp;
        if ($ts <= $now - 600) {
            foreach ($hooks as $hook_name => $records) {
                if (is_array($records)) {
                    ++$overdue;
                }
            }
        }
        if (!isset($hooks['action_scheduler_run_queue']) || !is_array($hooks['action_scheduler_run_queue'])) {
            continue;
        }
        $matching += count($hooks['action_scheduler_run_queue']);
        $next = null === $next ? $ts : min($next, $ts);
    }
    $bucket = $overdue === 0 ? 'none' : ($overdue <= 2 ? '1-2' : ($overdue <= 10 ? '3-10' : 'over-10'));
    echo 'ORGAVELA_CRON_OTHER_OVERDUE_BUCKET=' . $bucket . "\n";
    $status = 'missing';
    if ($matching > 1) {
        $status = 'multiple';
    } elseif ($matching === 1 && null !== $next) {
        $age = $now - $next;
        $status = $age > 900 ? 'overdue-over-15m' : ($age > 300 ? 'overdue-5-15m' : ($age >= 0 ? 'due-within-5m' : 'scheduled-ahead'));
    }
    echo 'ORGAVELA_CRON_QUEUE_EVENT=' . $status . "\n";
}
$table = $wpdb->prefix . 'actionscheduler_actions';
if (!preg_match('/^[A-Za-z0-9_]+$/D', $table)) {
    echo "ORGAVELA_AS_STORE=unavailable\n";
} else {
    $exists = $wpdb->get_var(
        $wpdb->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
            $table
        )
    );
    if ((int) $exists !== 1) {
        echo "ORGAVELA_AS_STORE=unavailable\n";
    } else {
        echo "ORGAVELA_AS_STORE=present\n";
        $get_bucket = static function ($status, $delayed) use ($wpdb, $table) {
            $query = $delayed
                ? $wpdb->prepare(
                    'SELECT COUNT(*) FROM %i WHERE status = %s AND scheduled_date_gmt < (UTC_TIMESTAMP() - INTERVAL 600 SECOND)',
                    $table,
                    $status
                )
                : $wpdb->prepare(
                    'SELECT COUNT(*) FROM %i WHERE status = %s',
                    $table,
                    $status
                );
            $count = $wpdb->get_var($query);
            if (!is_numeric($count)) {
                return 'unknown';
            }
            $count = (int) $count;
            return $count === 0 ? 'none' : ($count <= 10 ? '1-10' : ($count <= 100 ? '11-100' : 'over-100'));
        };
        echo 'ORGAVELA_AS_PENDING_OVERDUE_BUCKET=' . $get_bucket('pending', true) . "\n";
        echo 'ORGAVELA_AS_FAILED_BUCKET=' . $get_bucket('failed', false) . "\n";
        // Last-attempt timestamps are used only for aggregate recent-failure
        // monitoring. No action IDs, hooks, arguments or timestamps leave IONOS.
        $recent_failed = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM %i WHERE status = %s AND last_attempt_gmt >= (UTC_TIMESTAMP() - INTERVAL 1 DAY)',
                $table,
                'failed'
            )
        );
        if (!is_numeric($recent_failed)) {
            $recent_bucket = 'unknown';
        } else {
            $recent = (int) $recent_failed;
            $recent_bucket = $recent === 0 ? 'none' : ($recent <= 10 ? '1-10' : ($recent <= 100 ? '11-100' : 'over-100'));
        }
        echo 'ORGAVELA_AS_FAILED_LAST24H_BUCKET=' . $recent_bucket . "\n";
    }
}
echo "ORGAVELA_CRON_EXTERNAL_TRIGGER=not-verifiable-from-wordpress\n";
echo "ORGAVELA_CRON_DISPATCH_EXECUTED=no\n";
echo "ORGAVELA_CRON_MIGRATION_EXECUTED=no\n";
PHP
)"
  wp --path="$root" --skip-plugins --skip-themes eval "$php_code" 2>/dev/null || exit 25
  exit 0
fi

# O03: Preflight only. No dump, archive, backup file, cron, restore or remote
# mutation; no secrets, filenames, paths, usernames or DB identifiers printed.
if [[ "$mode" == 'backup-preflight' ]]; then
  tool_state() {
    if command -v "$1" >/dev/null 2>&1; then
      printf 'available'
    else
      printf 'missing'
    fi
  }
  bucket_kib() {
    local amount="${1:-}"
    if [[ ! "$amount" =~ ^[0-9]+$ ]]; then
      printf 'unknown'
    elif (( amount < 25600 )); then
      printf 'under-25MiB'
    elif (( amount < 102400 )); then
      printf '25-100MiB'
    elif (( amount < 512000 )); then
      printf '100-500MiB'
    elif (( amount < 1048576 )); then
      printf '500MiB-1GiB'
    elif (( amount < 5242880 )); then
      printf '1-5GiB'
    else
      printf 'over-5GiB'
    fi
  }
  count_kib="$(du -sk "$root" 2>/dev/null | awk 'NR==1 {print $1}')" || count_kib=''
  free_kib="$(df -Pk "$root" 2>/dev/null | awk 'NR==2 {print $4}')" || free_kib=''
  echo "ORGAVELA_BACKUP_WP_FILES_SIZE=$(bucket_kib "$count_kib")"
  echo "ORGAVELA_BACKUP_HOST_FREE_SPACE=$(bucket_kib "$free_kib")"
  echo "ORGAVELA_BACKUP_MYSQLDUMP=$(tool_state mysqldump)"
  echo "ORGAVELA_BACKUP_MARIADBDUMP=$(tool_state mariadb-dump)"
  echo "ORGAVELA_BACKUP_TAR=$(tool_state tar)"
  echo "ORGAVELA_BACKUP_GZIP=$(tool_state gzip)"
  echo "ORGAVELA_BACKUP_SHA256SUM=$(tool_state sha256sum)"
  echo "ORGAVELA_BACKUP_MYSQLCLI=$(tool_state mysql)"
  # WordPress already loaded through WP-CLI, using only aggregate metadata.
  php_code="$(cat <<'PHP'
global $wpdb;
$bytes = $wpdb->get_var(
    'SELECT COALESCE(SUM(DATA_LENGTH + INDEX_LENGTH), 0) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
);
$status = is_numeric($bytes) && (int) $bytes >= 0 ? 'available' : 'unavailable';
echo 'ORGAVELA_BACKUP_DB_METADATA=' . $status . "\n";
if ($status === 'available') {
    $kb = (int) ceil(((float) $bytes) / 1024);
    $bucket = $kb < 25600 ? 'under-25MiB'
        : ($kb < 102400 ? '25-100MiB'
        : ($kb < 512000 ? '100-500MiB'
        : ($kb < 1048576 ? '500MiB-1GiB'
        : ($kb < 5242880 ? '1-5GiB' : 'over-5GiB'))));
    echo 'ORGAVELA_BACKUP_DB_SIZE=' . $bucket . "\n";
}
PHP
)"
  wp --path="$root" --skip-plugins --skip-themes eval "$php_code" 2>/dev/null || exit 25
  echo 'ORGAVELA_BACKUP_PROVIDER_SNAPSHOTS=not-verifiable-over-shell'
  echo 'ORGAVELA_BACKUP_EXPORT_EXECUTED=no'
  echo 'ORGAVELA_BACKUP_RESTORE_EXECUTED=no'
  exit 0
fi

# WordPress CLI boot is restricted to --skip-plugins --skip-themes. It reads
# the installed UOP classes without registering their plugin lifecycle or
# calling migration runners. Never reveal DB name, table names, IDs, rows,
# WordPress configuration, participant fields or exception details.
php_code="$(cat <<'PHP'
require_once WP_PLUGIN_DIR . '/uop-core/vendor/autoload.php';
global $wpdb;
$prefix = $wpdb->prefix . 'uop_';
if (!preg_match('/^[a-zA-Z0-9_]+$/D', $prefix)) {
    throw new RuntimeException('Unsafe site table prefix');
}
$health = \UOP\Infrastructure\Database\Installer::health();
$status = ($health['status'] ?? '') === 'good' ? 'good' : 'critical';
$physical_tables = (int) $wpdb->get_var(
    $wpdb->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND LEFT(TABLE_NAME, %d) = %s',
        strlen($prefix), $prefix
    )
);
$rows = $wpdb->get_results(
    $wpdb->prepare(
        'SELECT id, status FROM %i WHERE slug = %s',
        $prefix . 'organizations', 'default'
    ),
    ARRAY_A
);
$stored_id = (int) get_option('uop_default_organization_id', 0);
$org_good = count($rows) === 1
    && $stored_id > 0
    && (int) $rows[0]['id'] === $stored_id
    && $rows[0]['status'] === 'active';
$state = get_option('uop_migration_status', array());
$versions_good = (int) get_option('uop_db_version', 0) === 2
    && (int) get_option('uop_data_version', 0) === 2
    && is_array($state) && ($state['status'] ?? '') === 'complete';
echo 'ORGAVELA_AUDIT_SCHEMA=' . $status . "\n";
echo 'ORGAVELA_AUDIT_TABLE_COUNT=' . $physical_tables . "\n";
echo 'ORGAVELA_AUDIT_DEFAULT_ORG=' . ($org_good ? 'good' : 'critical') . "\n";
echo 'ORGAVELA_AUDIT_VERSIONS=' . ($versions_good ? 'good' : 'critical') . "\n";
PHP
)"
wp --path="$root" --skip-plugins --skip-themes eval "$php_code" 2>/dev/null
REMOTE
then
  : # Successful remote read. Output is still allowlist-filtered below.
else
  # This branch is only entered on failure. Print safe categories, not any
  # raw SSH/php/WP-CLI output or server paths (all remain in temp files).
  code="$?"
  case "$code" in
    21) reason='OrgaVela WordPress root was not found at the verified SSH path.' ;;
    22) reason='SSH connected, but WP-CLI is unavailable on this hosting shell.' ;;
    23) reason='WordPress home did not match exactly orgavela.com; refused.' ;;
    24) reason='Installed UOP Core version differs from the audited Alpha.2 baseline; refused.' ;;
    25) reason='Read-only WordPress metadata could not be read; no action, archive or restore was attempted.' ;;
    255) reason='SSH handshake/authentication failed; SFTP-only access is still possible.' ;;
    *) reason='Remote shell/WordPress read-only command failed; SSH shell or WP-CLI capability remains unverified.' ;;
  esac
  echo "::error::$reason No raw server output or credentials are published."
  exit 1
fi

if ! grep -Fqx 'ORGAVELA_AUDIT_TARGET=verified' "$tmp/out"; then
  echo '::error::OrgaVela target identity not verified; report withheld.'
  exit 1
fi
awk -F= '
$1 == "ORGAVELA_AUDIT_TARGET" && $2 == "verified" { print; next }
$1 == "ORGAVELA_AUDIT_SHELL" && $2 == "available" { print; next }
$1 == "ORGAVELA_AUDIT_PLUGIN_VERSION" && $2 == "alpha2" { print; next }
$1 == "ORGAVELA_AUDIT_PROBE" && $2 == "success" { print; next }
$1 == "ORGAVELA_CRON_SHELL_BINARY" && $2 ~ /^(available|unavailable)$/ { print; next }
$1 == "ORGAVELA_CRON_WP_AUTO" && $2 ~ /^(disabled|enabled)$/ { print; next }
$1 == "ORGAVELA_CRON_WP_OPTION" && $2 ~ /^(valid|invalid)$/ { print; next }
$1 == "ORGAVELA_CRON_QUEUE_EVENT" && $2 ~ /^(unavailable|missing|multiple|scheduled-ahead|due-within-5m|overdue-5-15m|overdue-over-15m)$/ { print; next }
$1 == "ORGAVELA_CRON_OTHER_OVERDUE_BUCKET" && $2 ~ /^(none|1-2|3-10|over-10)$/ { print; next }
$1 == "ORGAVELA_AS_STORE" && $2 ~ /^(present|unavailable)$/ { print; next }
$1 ~ /^ORGAVELA_AS_(PENDING_OVERDUE_BUCKET|FAILED_BUCKET|FAILED_LAST24H_BUCKET)$/ && $2 ~ /^(none|1-10|11-100|over-100|unknown)$/ { print; next }
$1 == "ORGAVELA_CRON_EXTERNAL_TRIGGER" && $2 == "not-verifiable-from-wordpress" { print; next }
$1 == "ORGAVELA_CRON_DISPATCH_EXECUTED" && $2 == "no" { print; next }
$1 == "ORGAVELA_CRON_MIGRATION_EXECUTED" && $2 == "no" { print; next }
$1 == "ORGAVELA_AUDIT_SCHEMA" && $2 ~ /^(good|critical)$/ { print; next }
$1 == "ORGAVELA_AUDIT_TABLE_COUNT" && $2 ~ /^[0-9]+$/ { print; next }
$1 == "ORGAVELA_AUDIT_DEFAULT_ORG" && $2 ~ /^(good|critical)$/ { print; next }
$1 == "ORGAVELA_AUDIT_VERSIONS" && $2 ~ /^(good|critical)$/ { print; next }
$1 == "ORGAVELA_BACKUP_WP_FILES_SIZE" && $2 ~ /^(under-25MiB|25-100MiB|100-500MiB|500MiB-1GiB|1-5GiB|over-5GiB|unknown)$/ { print; next }
$1 == "ORGAVELA_BACKUP_HOST_FREE_SPACE" && $2 ~ /^(under-25MiB|25-100MiB|100-500MiB|500MiB-1GiB|1-5GiB|over-5GiB|unknown)$/ { print; next }
$1 == "ORGAVELA_BACKUP_DB_METADATA" && $2 ~ /^(available|unavailable)$/ { print; next }
$1 == "ORGAVELA_BACKUP_DB_SIZE" && $2 ~ /^(under-25MiB|25-100MiB|100-500MiB|500MiB-1GiB|1-5GiB|over-5GiB)$/ { print; next }
$1 ~ /^ORGAVELA_BACKUP_(MYSQLDUMP|MARIADBDUMP|TAR|GZIP|SHA256SUM|MYSQLCLI)$/ && $2 ~ /^(available|missing)$/ { print; next }
$1 == "ORGAVELA_BACKUP_PROVIDER_SNAPSHOTS" && $2 == "not-verifiable-over-shell" { print; next }
$1 == "ORGAVELA_BACKUP_EXPORT_EXECUTED" && $2 == "no" { print; next }
$1 == "ORGAVELA_BACKUP_RESTORE_EXECUTED" && $2 == "no" { print; next }
' "$tmp/out"

# O08: a scheduled run evaluates only aggregate, allowlisted status keys.
# The separate evaluator is covered by data-free negative tests in PR CI.
if [[ "${ORGAVELA_MONITOR_STRICT:-0}" == '1' && "$mode" == 'cron' ]]; then
  bash scripts/ci/orgavela-cron-status-evaluate.sh "$tmp/out"
fi
