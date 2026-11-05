#!/usr/bin/env bash
# Pure offline O08 monitoring fixtures. No SSH, network, WP-CLI, database or credentials.
set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
evaluator="$here/orgavela-cron-status-evaluate.sh"
[[ -r "$evaluator" ]] || { echo "::error::Missing cron evaluator"; exit 2; }

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

cat > "$tmp/healthy" <<'STATUS'
ORGAVELA_AUDIT_TARGET=verified
ORGAVELA_AUDIT_PLUGIN_VERSION=alpha2
ORGAVELA_CRON_WP_AUTO=enabled
ORGAVELA_CRON_WP_OPTION=valid
ORGAVELA_CRON_OTHER_OVERDUE_BUCKET=none
ORGAVELA_AS_STORE=present
ORGAVELA_AS_PENDING_OVERDUE_BUCKET=none
ORGAVELA_AS_FAILED_BUCKET=1-10
ORGAVELA_AS_FAILED_LAST24H_BUCKET=none
ORGAVELA_CRON_QUEUE_EVENT=scheduled-ahead
ORGAVELA_CRON_DISPATCH_EXECUTED=no
ORGAVELA_CRON_MIGRATION_EXECUTED=no
STATUS

# Historic failed actions are intentionally informational; only recent ones alert.
bash "$evaluator" "$tmp/healthy" > "$tmp/healthy-result"
grep -Fqx 'ORGAVELA_O08_MONITOR=read-only-snapshot-ok' "$tmp/healthy-result"

sed 's/ORGAVELA_CRON_QUEUE_EVENT=scheduled-ahead/ORGAVELA_CRON_QUEUE_EVENT=due-within-5m/' "$tmp/healthy" > "$tmp/healthy-due"
bash "$evaluator" "$tmp/healthy-due" > /dev/null

count=0
fail_if_accepted() {
  local label="$1"
  if bash "$evaluator" "$tmp/test" > /dev/null 2>&1; then
    echo "::error::O08 evaluator accepted forbidden offline case: $label"
    exit 1
  fi
  count=$((count + 1))
}

for case_name in \
  wpcron-disabled wpcron-missing wpcron-duplicate \
  other-overdue other-unknown other-missing \
  action-pending-overdue recent-failed recent-failed-unknown \
  queue-overdue queue-missing queue-duplicate \
  target-forged plugin-wrong cron-option-invalid store-missing \
  cron-dispatched cron-migrated; do

  cp "$tmp/healthy" "$tmp/test"
  case "$case_name" in
    wpcron-disabled) sed -i 's/ORGAVELA_CRON_WP_AUTO=enabled/ORGAVELA_CRON_WP_AUTO=disabled/' "$tmp/test" ;;
    wpcron-missing) sed -i '/^ORGAVELA_CRON_WP_AUTO=/d' "$tmp/test" ;;
    wpcron-duplicate) echo 'ORGAVELA_CRON_WP_AUTO=enabled' >> "$tmp/test" ;;
    other-overdue) sed -i 's/ORGAVELA_CRON_OTHER_OVERDUE_BUCKET=none/ORGAVELA_CRON_OTHER_OVERDUE_BUCKET=1-2/' "$tmp/test" ;;
    other-unknown) sed -i 's/ORGAVELA_CRON_OTHER_OVERDUE_BUCKET=none/ORGAVELA_CRON_OTHER_OVERDUE_BUCKET=unknown/' "$tmp/test" ;;
    other-missing) sed -i '/^ORGAVELA_CRON_OTHER_OVERDUE_BUCKET=/d' "$tmp/test" ;;
    action-pending-overdue) sed -i 's/ORGAVELA_AS_PENDING_OVERDUE_BUCKET=none/ORGAVELA_AS_PENDING_OVERDUE_BUCKET=1-10/' "$tmp/test" ;;
    recent-failed) sed -i 's/ORGAVELA_AS_FAILED_LAST24H_BUCKET=none/ORGAVELA_AS_FAILED_LAST24H_BUCKET=1-10/' "$tmp/test" ;;
    recent-failed-unknown) sed -i 's/ORGAVELA_AS_FAILED_LAST24H_BUCKET=none/ORGAVELA_AS_FAILED_LAST24H_BUCKET=unknown/' "$tmp/test" ;;
    queue-overdue) sed -i 's/ORGAVELA_CRON_QUEUE_EVENT=scheduled-ahead/ORGAVELA_CRON_QUEUE_EVENT=overdue-5-15m/' "$tmp/test" ;;
    queue-missing) sed -i '/^ORGAVELA_CRON_QUEUE_EVENT=/d' "$tmp/test" ;;
    queue-duplicate) echo 'ORGAVELA_CRON_QUEUE_EVENT=scheduled-ahead' >> "$tmp/test" ;;
    target-forged) sed -i 's/ORGAVELA_AUDIT_TARGET=verified/ORGAVELA_AUDIT_TARGET=foreign/' "$tmp/test" ;;
    plugin-wrong) sed -i 's/ORGAVELA_AUDIT_PLUGIN_VERSION=alpha2/ORGAVELA_AUDIT_PLUGIN_VERSION=unknown/' "$tmp/test" ;;
    cron-option-invalid) sed -i 's/ORGAVELA_CRON_WP_OPTION=valid/ORGAVELA_CRON_WP_OPTION=invalid/' "$tmp/test" ;;
    store-missing) sed -i '/^ORGAVELA_AS_STORE=/d' "$tmp/test" ;;
    cron-dispatched) sed -i 's/ORGAVELA_CRON_DISPATCH_EXECUTED=no/ORGAVELA_CRON_DISPATCH_EXECUTED=yes/' "$tmp/test" ;;
    cron-migrated) sed -i 's/ORGAVELA_CRON_MIGRATION_EXECUTED=no/ORGAVELA_CRON_MIGRATION_EXECUTED=yes/' "$tmp/test" ;;
  esac
  fail_if_accepted "$case_name"
done

[[ "$count" == 18 ]] || { echo "::error::Expected 18 independent negative cases"; exit 1; }
echo "O08_OFFLINE_POSITIVE_TESTS=2"
echo "O08_OFFLINE_NEGATIVE_TESTS=$count"
echo "O08_OFFLINE_NETWORK_ACCESS=none"
