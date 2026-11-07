# OrgaVela collector port: offline candidate

W0/O03, issue #27. Checkpoint 5 remains accepted 10/10; this candidate is the
next implementation package, not another identity-check round.

Source: `janjrre/dreamloud-portal`, `backup/production-circleci`, commit
`07d4ffbcc3c6d70f0716fda070050d6532a69df6`: capture_quiesced_circleci.py,
remote_quiesced_capture.php, backup_wp_config.php, backup_capture_safety.php,
publish_production_backup.py and encrypted packaging scripts. Reference was
read only; no Dreamloud service was used as a test environment.

The port retains private staging, static config token parsing (never includes
WordPress), InnoDB/base-table and unsupported-object checks, maintenance guards,
table read locks, two file inventories, owned cleanup/watchdog, encryption before
handoff and separate ciphertext-only publication with complete draft/final
readbacks. It binds to OrgaVela home/siteurl, 52 own-prefix tables and ten known
core/UOP tables, own public recipient and own private backup repository. It
removes the Dreamloud-specific Docket Cache exclusion and App credentials.

## Actual activation state

Collector and writer CLIs return 78 unconditionally; the assembled PHP candidate
defines its entrypoint flag false. No environment approval can enable them.
The writer token loader is disabled. Only fixture entrypoints run. The CircleCI
config has no contexts, schedules, artifact/workspace persistence or live jobs;
its one workflow defaults off. Do not attach secrets.

The PHP engine is ported source, not proven IONOS capture. Synthetic evidence
assertions are fictitious protocol data, never evidence of real locks or
maintenance. Config parsing proves literals, not arbitrary PHP runtime semantics.
Full bounded SSH snapshot transport, own credential wiring, provider client/
watchdog compatibility and live fences require later implementation/review.
Existing metadata transport is not reused as an unbounded snapshot pipe.

## Verification

Run Python unittest discovery for `test_collector_port.py`, PHP lint and
`test_capture_helpers.php`, then `offline_integration.py --synthetic-only`.
Install `requirements-test.txt` in an isolated test environment to run
`temporary_key_roundtrip.py`: a disposable private key exists only in memory,
GnuPG encrypts using its public half, PGPy decrypts and verifies all three private
component byte hashes. The owner's private key is never used. This is archive
recovery verification, not a real WordPress/MariaDB restore.

For CircleCI select this exact feature branch and set Boolean
`run_collector_port_tests=true`; no other parameter, context or credential.
It is a local/network-mocked fixture job. Dependency downloads contact package
registries; no source-server or backup-repository calls occur in the tests.
The image tag and OS package updates are intentionally not production pins;
immutable runtime/toolchain selection is required before sensitive activation.
Only use the existing free allocation; no billing/paid resource changes.

## Remaining production gates

Reviewed actual source/transport implementation and runtime pins; separately
protected least-privilege source and writer contexts; explicit first-capture and
publish decisions; consistent real files+DB capture and encrypted immutable
readback; isolated actual WordPress/MariaDB restore and owner recovery; only then
schedule, freshness alerting and retention. PR32 stays frozen and unmerged.

## Bounded transport/recovery follow-up (2026-11-06)

`transport.py`, `capture_cli.py`, `publish_cli.py`, `recovery.py`,
`isolated_restore.py`, and `restore_cli.py` add executable bounded transport and
operator-side isolated recovery. Legacy CLIs remain disabled; no live runner is
activated by importing these libraries. See
`docs/operations/O03_BOUNDED_CAPTURE_RESTORE_2026-11-06.md` for exact tests,
private-key/source/writer boundaries, integration dependencies and remaining
real-source evidence. Synthetic WP recovery is distinct from a real snapshot
restore. No Dreamloud source or service is accessed by the tests.
