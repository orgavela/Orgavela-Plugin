# O04 – OrgaVela live IONOS baseline and cron decision record

**Inspected 2026-11-04. Read-only site evidence; no production mutation.** This is **orgavela.com**, not the unrelated WordPress.com sites or any Dreamloud host.

## Observed facts

Read via the explicitly linked **OrgaVela** WordPress MCP, never via the Dreamloud connection:

| Observation | Verified state |
| --- | --- |
| Site | https://orgavela.com, WordPress environment type production, isolated OrgaVela project |
| WordPress / runtime | WordPress 7.1.4, PHP 8.3.36, MariaDB 11.8.8 |
| Database charset | utf8mb4, collation utf8mb4_unicode_520_ci |
| UOP | UOP Core **0.1.0-alpha.2 active**; this is the existing older plugin, **not** Draft #43/#44 functionality |
| Plugin updates | No updates reported by the site connector |
| Browser/loopback | HTTPS true; WordPress loopback returns HTTP 200 |
| WordPress Cron | DISABLE_WP_CRON true; alternate cron false in WordPress site configuration. This **does not** prove the absence of an independent IONOS server job. |
| Repeated scheduled-task reads | WordPress hook action_scheduler_run_queue reported the same next-run timestamp 2026-11-04 14:27:39 GMT, initially 33 seconds late and later 394 seconds late; the site subsequently reported two overdue WordPress events. |
| Full-site backup / restore | Not verified by the available WordPress connection. The absence of a theme draft/backup is **not** evidence of missing IONOS backups. |

The **old WordPress.com Atomic staging latin1/utf8mb4 blocker** belongs to another host and **does not describe the current orgavela.com database**, which returned utf8mb4. The separate simple WordPress.com site orgavela.wordpress.com is also not the IONOS project host. Preserve these distinctions in issues, release gates and future handovers.

## Two operational Cron paths at IONOS — do not confuse them

1. **IONOS Control Center / Hosting / Cronjobs:** This service executes **HTTP GET URLs only**. A WordPress job would normally GET https://orgavela.com/wp-cron.php. Such a call processes **all due registered WordPress cron work**, which can in turn dispatch unrelated Action Scheduler actions; it does **not** run the hook-and-group-filtered WP-CLI command from Draft #43. IONOS documents a 60-second invocation limit and recommends intervals of at least five minutes. **Do not activate this on orgavela.com until existing jobs, queued side effects (especially notifications), cron ownership and backup/restore are verified.**
2. **Shell/server-side cron (requires verified SSH/CLI availability on the actual contract):** Can run the tested exact CLI command for the Guardian hook and group, but does **not** run all other WordPress core/outbox/email housekeeping. Before using Action Scheduler's --group filter, check its real storage implementation read-only; **HybridStore is a STOP**, not a license to migrate a live database. Any future migration requires an independent restore-tested backup and operational signoff.

Official hosting references (verified 2026-11-04):
- https://www.ionos.de/hilfe/hosting/cronjobs/cronjobs-erstellen-und-verwalten-im-ionos-konto/
- https://www.ionos.de/hilfe/hosting/cronjobs/best-practices-fuer-die-einrichtung-von-cronjobs/

## Present operational decision

- **Do not silently turn off or bypass DISABLE_WP_CRON.** It is intentional on installations using host-side scheduling; first determine whether such a job already exists.
- **Do not GET the production wp-cron.php or force action_scheduler_run_queue from ChatGPT.** Both execute production work, potentially emails or other side effects unrelated to the Guardian test. Existing queue contents and a complete restore mechanism are not yet confirmed.
- **Do not deploy Draft #43/#44 to orgavela.com** merely because its temporary GitHub tests passed. P4, external Guardian verification, Privacy/Legal, O03/W0 full-site recovery and actual host Cron need acceptance first.
- **Do not change wp-config.php, trigger a production Action Scheduler store migration, or modify IONOS hosting contracts** without a verified rollback and explicit conditions.
- Prepare a **read-only** inventory of the actual IONOS Hosting > Cronjobs entries (name, timing, destination URL only, **no secrets**), plus any separate SSH crontab. Compare them to WP's rising-overdue event timestamps. If a pre-existing host Cron exists, investigate its execution logs/overlap before making any new entry.
- Obtain a verifiable **full-site/database backup and restoration proof**; after this, a **synthetic test** on an authorized separate staging environment can validate scheduled execution and logging, failed/retry/overlap cases without touching real participants.
- Operator must document minimum viable alarm routing, responsible person, expected 5+-minute triggering cadence, failure threshold and recovery. A manual dashboard status is not an alert service.

## GitHub CI proof scope

[Draft #43](https://github.com/orgavela/Orgavela-Plugin/pull/43) already proves an isolated **hook/group-scoped WP-CLI** job and two untouched decoys in GitHub only. A separate CI fixture, tests/Fixtures/o04-ionos-http-cron-probe.php, and a dedicated pipeline step now verify whether a **direct HTTP GET to wp-cron.php** on **127.0.0.1** processes a due synthetic Guardian action with DISABLE_WP_CRON=true. The two proofs are intentionally different: the HTTP variant provides no selective hook/group isolation. Passing them cannot be treated as proof of configured recurring jobs on the real IONOS host.

**Release gate status:** O04 external operation **OPEN**, W0/O03 recovery **OPEN**, P4 Privacy/Legal and guardian identity **OPEN**. No full V1 release approval.
