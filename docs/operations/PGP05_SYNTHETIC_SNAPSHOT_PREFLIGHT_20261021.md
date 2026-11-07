> **Aktueller Nachtrag 21.10.2026:** Der reduzierte lokale Validierungsadapter wurde nach dem ersten Test **durch den vollständigen unveränderten Prüfer aus Draft PR #28 ersetzt**. Das Modul wurde aus GitHub read-only geladen und seine identische Git-Blob-SHA `56dc9960dfdb946e7286d9a9044946e6cd3545e5` im lokalen Python-Arbeitsbereich nachgewiesen. Danach wurden die **20/20 PGP05-Snapshot-Tests erneut lokal erfolgreich ausgeführt**, einschließlich echter GnuPG-OpenPGP-Public-Key-Verschlüsselung und Validierung durch das **echte** Modul. Das ist nun ein reproduzierbarer lokaler **PR28+PR29-Integrations-PASS**, weiterhin **kein** CI-Provider-/GitHub-Release-/Live-WordPress-Restore-PASS. Die ursprüngliche Testadapter-Evidenz unten ist der vorherige historische Zwischenstand.

# PGP-05 – Checkpoint 2: synthetic snapshot consistency and encrypted handoff

**Date:** 2026-10-21. **Scope:** offline, synthetic-only, no live or CI authorization.

This stacked implementation follows the already-unmerged [PGP-05 asset validator PR #28](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/28), using that branch as base for the new snapshot producer and tests. The prior PGP-04 V3 frozen branch and the audit branch remain separate.

## Actual implementation

- **scripts/ci/orgavela_pgp05_synthetic_snapshot.py** generates only its own artificial WordPress upload and fixed artificial SQL fixture. It has no IONOS API, SSH, MariaDB driver, network, GitHub API, production flag, credentials, or scheduler.
- A strict fixture allowlist admits only wp-content/uploads/synthetic.txt containing SYNTHETIC TEST ONLY - NO REAL SITE DATA; two-pass inventory checks compare size, digest, modification and change metadata. Reject unexpected extra files, symlinks, hardlinks, non-regular files and source drift.
- Synthetic database.sql.gz and wordpress-files.tar.gz are assembled with an internal component-hash/consistency manifest; all are encrypted together using the reviewed OrgaVela PGPony OpenPGP PUBLIC key. GnuPG uses a private temporary GNUPGHOME and refuses any secret key presence.
- Before handing off the workspace, the producer verifies the primary fingerprint 7C9DC60D8E1B293E514696EB333D15A4D8BA625C and OpenPGP encryption packet subkey D9E58CC6484AF1DE. Only ciphertext, a SHA-256 sidecar and a sanitized public manifest are atomically exposed, using the original validator from Draft PR #28. No plaintext files appear in the writer handoff.
- Default-off CLI requires --synthetic-only and an absent absolute output path. Wrong flags, unsafe paths, present credential variables and encryption errors fail closed and remove the temporary work directory.

## Executed tests and evidence limitations

Local Python 3.13.5 and GnuPG 2.4.7: **20/20 tests passed** via:
python3 -m unittest discover -v -s scripts/ci -p 'test_orgavela_pgp05_synthetic_snapshot.py'

Tests include a public-only GnuPG cipher fixture, exact three-asset handoff, no plaintext or private key in handoff, source drift rejection, wrong source data, added directories, symlink/parent-symlink and hardlink, insecure output, missing opt-in, synthetic fake credentials, forced encryption failure and cleanup.

**Important limitation:** for the local test, the original PR #28 asset-validator import was represented by a minimal *local-only* adapter (NOT committed). On GitHub, the new module and its test import the actual validator from PR #28. That full combined execution has **not been run yet** and must not be recorded as PASS. Previous 21/21 PR #28 tests are separate evidence. The artifact is real synthetic OpenPGP ciphertext, but no owner-side decryption of this new PGP-05 package was performed.

## Production source / writer architecture pending separate authorization

1. Confirm IONOS contract, OrgaVela WordPress root, MariaDB identity, permissions and SSH host key. Do not trust a self-reported environment flag for the correct installation.
2. Decide transactional DB+filesystem consistency and DDL/mixed-table locks; a single-transaction dump alone cannot prove a consistent WordPress-files+MariaDB recovery point. Establish safe read-only or minimally privileged acquisition and bounded cleanup.
3. Isolate source credentials from release-writer context. Source encrypts locally before handing off persistent workspace; writer receives only validated ciphertext and a repository-scoped backup token, never IONOS credentials or owner private key.
4. Prove a synthetic combined CI boundary if warranted by review, then request new explicit owner approval before first real source access and a separate approval before first real GitHub Release. Real restoration must occur in a completely isolated WordPress+MariaDB environment.
5. Scheduler, monitoring, retention, costs, key rotation, IONOS recovery options and backup dependency risks are independent later gates.

**No production capture, no real backup, no workflow modification, no CircleCI run, no PATs, no merge or paid services.** Historical coding 62/71; formal V1 M0–M7 0/8, P4 0/4 and Release R01–R18 0/18 unchanged.

See [PGP-05 issue #27](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/27) and the approved [PGPony/OpenPGP migration ADR](W0_O03_PGPONY_OPENPGP_MIGRATION_ADR_2026-10-20.md).


## Aktueller lokaler Sicherheitsnachtrag: separater Writer-Prozess (21.10.2026)

Nach erfolgreichem Cross-PR-Test mit dem **byteidentischen PR28-Validator** wurden drei zusätzliche unabhängige Offline-Testfälle eingebaut:
1. Source-Producer erzeugt echte fiktive GnuPG-verschlüsselte drei Dateien; ein **separater Python-Prozess** ruft das unveränderte ursprüngliche PR28-CLI-Programm über dessen Dateipfad auf und meldet **PGP05_ASSET_CONTRACT_SYNTHETIC_ONLY=PASS**.
2. Der separat gestartete Writer verweigert einen nach Übergabe gezielt manipulierten Ciphertext (**PGP05_ASSET_CONTRACT=REFUSED**, exit 1), selbst wenn der vorherige Source-Producer bestanden hat.
3. Das öffentliche Manifest darf weder den synthetischen SQL-Inhalt noch WordPress-Dateipfade oder den künstlichen Upload-Marker offenlegen.

**Lokales Endergebnis: 23/23 PASS** (Python 3.13.5, GnuPG 2.4.7). Die neu zu GitHub hochgeladene Testdatei hat exakt die lokal geprüfte Git-Blob-SHA **42e0bc1cf9b444cc3a1c5382591346d8ad611550**; der komplette PR28-Gate-Blob ist weiterhin identisch zu GitHub **56dc9960dfdb946e7286d9a9044946e6cd3545e5**. Keine Secrets, keine IONOS-Verbindung, keine CI-Pipeline, kein GitHub-Release, kein Real-Backup oder produktiver Scheduler. Der Test beweist **getrennte Offline-Prozesse**, noch **nicht** getrennte CircleCI-Contexts oder tatsächliche echte WordPress-/MariaDB-Konsistenz.


## Finaler Offline-Nachtrag: Writer prüft auch OpenPGP-Empfänger unabhängig (21.10.2026)

Ein neu hinzugefügter **synthetic-only Writer-Preflight** unter scripts/ci/orgavela_pgp05_offline_writer.py ist **kein** GitHub Release-Uploader. Er läuft eigenständig ohne Tokens/Netzzugriff, prüft erst den vollständigen PR28-Manifest-/SHA-Vertrag und danach in einem frischen, privaten GnuPG-Home die tatsächlichen Public-Key-Encrypted-Packets und genau die freigegebene OrgaVela-Encryption-Subkey-ID D9E58CC6484AF1DE. Er verweigert zusätzliche/mangelhafte Pakete oder das Fehlen des ausdrücklichen --synthetic-only-Flags.

Zusätzliche echte Offline-Regressionstests beweisen (1) korrektes OpenPGP-Paket im getrennten Writer-Prozess PASS, (2) künstlich gefälschte nicht verschlüsselte Nutzdaten mit **nachträglich korrekt angepassten Manifest- und SHA-256-Werten** werden durch den neuen Writer trotzdem REFUSED und (3) fehlendes synthetisches Opt-in wird verweigert. Der reine PR28-SHA-Validator akzeptiert das Hash-konsistente Fälschungsfixture erwartungsgemäß, der strengere GnuPG-Writer weist es zurück.

**Final lokaler Stand: 26/26 PASS** mit exakt dem GitHub-Original des PR28-Validators, Python3.13.5/GnuPG2.4.7. Lokale Git-Blobs entsprechen exakt den hochgeladenen Quelltexten: neuer Writer **79eaca5b60a76136403aa6a93b6282c0d7b66b8c** und Testmodul **33483719a3f2588c504427959e773fe10c234240**. Es erfolgte keine echte Datenbank-/WordPress-Sicherung, keine CI-Runner-Ausführung, kein GitHub-Release und keine Freigabe von Produktivbackup oder Scheduler. OpenPGP Packet Header ohne Private Key beweisen nicht die Entschlüsselbarkeit oder System-Restore-Fähigkeit.
