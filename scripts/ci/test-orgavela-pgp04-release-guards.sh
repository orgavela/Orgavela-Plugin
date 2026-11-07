#!/usr/bin/env bash
# PGP04 no-network, no-real-token fail-closed guard tests. Safe local/CI QA.
set -euo pipefail
set +x
root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
writer="$root/scripts/ci/orgavela-pgpony-synthetic-release-writer.sh"
count=0
reject(){
  local label="$1"
  shift
  if "$@" >/dev/null 2>&1; then
    echo "FAIL: $label" >&2
    exit 1
  fi
  count=$((count+1))
}
# None of these commands reach GitHub: fail before any network call.
reject 'no approval' env -u GH_TOKEN PGP04_OPERATOR_APPROVED=NO bash "$writer"
reject 'no synthetic attestation' env -u GH_TOKEN PGP04_OPERATOR_APPROVED=YES ORGAVELA_PGP_SYNTHETIC_ONLY=NO bash "$writer"
reject 'wrong source project' env -u GH_TOKEN PGP04_OPERATOR_APPROVED=YES ORGAVELA_PGP_SYNTHETIC_ONLY=YES CIRCLE_PROJECT_USERNAME=attacker CIRCLE_PROJECT_REPONAME=Projekt-Wordpress-Plugin bash "$writer"
reject 'wrong CI branch' env -u GH_TOKEN PGP04_OPERATOR_APPROVED=YES ORGAVELA_PGP_SYNTHETIC_ONLY=YES CIRCLE_PROJECT_USERNAME=janjrre CIRCLE_PROJECT_REPONAME=Projekt-Wordpress-Plugin CIRCLE_BRANCH=main bash "$writer"
reject 'wrong target repository' env -u GH_TOKEN PGP04_OPERATOR_APPROVED=YES ORGAVELA_PGP_SYNTHETIC_ONLY=YES CIRCLE_PROJECT_USERNAME=janjrre CIRCLE_PROJECT_REPONAME=Projekt-Wordpress-Plugin CIRCLE_BRANCH=security/pgp04-synthetic-release-v3-20261021 GITHUB_BACKUP_REPOSITORY=janjrre/dreamloud-portal bash "$writer"
reject 'no restricted token' env -u GH_TOKEN PGP04_OPERATOR_APPROVED=YES ORGAVELA_PGP_SYNTHETIC_ONLY=YES CIRCLE_PROJECT_USERNAME=janjrre CIRCLE_PROJECT_REPONAME=Projekt-Wordpress-Plugin CIRCLE_BRANCH=security/pgp04-synthetic-release-v3-20261021 GITHUB_BACKUP_REPOSITORY=janjrre/orgavela-backups bash "$writer"
echo "PGP04_OFFLINE_EARLY_REJECTION_TESTS=$count/6 PASS"
