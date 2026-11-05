#!/usr/bin/env bash
# O08: Evaluate only the allowlisted aggregate SSH cron observation snapshot.
# No network, no WordPress execution, no queue retries, no sensitive payloads.
set -euo pipefail
[[ "$#" -eq 1 && -f "$1" ]] || exit 2
file="$1"

reject() {
  echo "::error::O08: Read-only OrgaVela scheduler observation failed ($1). No task or retry was executed."
  exit 1
}

# Every signal must exist exactly once. Never print caller-controlled values.
expect_one() {
  local key="$1" value="$2" count
  count="$(awk -F= -v key="$key" '$1==key {n++} END {print n+0}' "$file")"
  [[ "$count" == 1 ]] || reject 'missing-or-duplicate-status'
  grep -Fqx "$key=$value" "$file" || reject 'status-not-healthy'
}

expect_one ORGAVELA_AUDIT_TARGET verified
expect_one ORGAVELA_AUDIT_PLUGIN_VERSION alpha2
# Current approved operational mode is WordPress visit-triggered WP-Cron.
# Do not silently accept a transition back to DISABLE_WP_CRON=true until a
# separate external trigger and its execution history have been verified.
expect_one ORGAVELA_CRON_WP_AUTO enabled
expect_one ORGAVELA_CRON_WP_OPTION valid
expect_one ORGAVELA_AS_STORE present
expect_one ORGAVELA_AS_PENDING_OVERDUE_BUCKET none
expect_one ORGAVELA_AS_FAILED_LAST24H_BUCKET none
expect_one ORGAVELA_CRON_DISPATCH_EXECUTED no
expect_one ORGAVELA_CRON_MIGRATION_EXECUTED no

hook_count="$(awk -F= '$1=="ORGAVELA_CRON_QUEUE_EVENT" {n++} END {print n+0}' "$file")"
[[ "$hook_count" == 1 ]] || reject 'missing-or-duplicate-hook'
if ! grep -Eq '^ORGAVELA_CRON_QUEUE_EVENT=(scheduled-ahead|due-within-5m)$' "$file"; then
  reject 'queue-hook-unhealthy'
fi

echo 'ORGAVELA_O08_MONITOR=read-only-snapshot-ok'
