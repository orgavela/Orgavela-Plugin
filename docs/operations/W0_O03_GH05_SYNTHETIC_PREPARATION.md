# W0/O03 / GH-05 – Synthetic-only age archive harness (19.10.2026)

**Status:** PREPARATION ONLY, **not GH-05 acceptance**. This branch is stacked on GH-03 Draft PR #18. No CircleCI/GitHub Actions workflow added or triggered by this change. No IONOS, WordPress, MariaDB, GitHub backup release or Dreamloud access.

## Goal and hard limits

Validate archive/integrity handling with only fixed synthetic fixtures before any real capture. GH-04's OpenPGP transport test is independent, and GH-03's successful AgePony note test is not an offline copy-2 recovery proof.

Source: scripts/ci/orgavela_gh05_synthetic_age.py
Tests: scripts/ci/test_orgavela_gh05_synthetic_age.py

- No real-data option; the only CLI argument supported is --synthetic-only.
- No input path, WordPress root, SSH credentials, GitHub token, hostname, cloud/release API, deployment, schedule, database or real participant data.
- Builds fictional fixture/database.sql and two fictional fixture/wp-content/ files in memory. No WordPress files are read.
- Validates exact TAR inventory; rejects duplicate/extra members, path traversals, symbolic links and altered checksums. Does not extract archive contents onto a filesystem.
- With official age/age-keygen CLI installed, creates a disposable TEST-ONLY identity in a temporary directory, encrypts a .tar.age fixture, decrypts, checks SHA-256 and rejects deliberately damaged ciphertext. No owner AgePony identity is ever involved.
- No upload, persistent plaintext, or private key output. The only byte-specific output is the synthetic ciphertext SHA256.
- Without age CLI, the true crypto integration test is SKIPPED, not faked green; the direct runner fails closed.

## Local isolated validation

Python 3.10+ stdlib; official age and age-keygen in PATH only for cryptographic integration (https://github.com/FiloSottile/age).

    python3 -m unittest discover -v -s scripts/ci -p 'test_orgavela_gh05_synthetic_age.py'
    python3 scripts/ci/orgavela_gh05_synthetic_age.py --synthetic-only

Without --synthetic-only the runner exits 2. Without age executables, the second command exits 1.

### Preparation evidence (19.10.2026)

On a disposable assistant-owned local container (not PCjan/OrgaVela/CircleCI), 9 unit tests were run using the equivalent staged source: **8 PASS / 1 SKIP** because age executable was unavailable. Verified: synthetic TAR inventory and SHA256 manifest, corrupted TAR/file, duplicate member, traversal, symlink, prohibited flags and prohibited real-data env. **NOT executed:** age CLI encryption, decrypt, tamper proof; MariaDB or real WP consistency; IONOS access; GitHub Releases; independent real restore; CircleCI/GitHub Actions run. No claimed green provider check.

After commit: read back both scripts and perform static review. A future isolated environment with age installed can make the age integration test pass; it may not claim production readiness.

## Real GH-05 still blocked

Before any real capture, design and review OrgaVela-only source identity and narrow permissions, consistent database snapshot + matching WordPress file cutoff, exclusion of WP configs/PII in test output, secure local staging/free space/large file failures, symlink/path boundaries, plaintext cleanup, inventory integrity, transactional restore and evidence chain. GH-06 must use only the operator-verified PUBLIC age recipient and encrypt before remote transfer. GH-07 requires a full independent isolated restore. GH-08 schedules and retention only after gates.

**STOP:** Current owner AgePony paper backup PDF is only locally stored on the same iPhone/iPad; local note crypto success is not independent recovery. GH-03 is OPEN; GH-05 synthetic preparation does not authorize real WordPress/DB backups, uploads or schedules.

Main tracking: https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/15 and https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/16
Canonical Masterplan: docs/operations/V1_ACCEPTANCE_PLAN.md
Progress: docs/operations/V1_PROGRESS.json


## 20.10.2026 – versuchter Original-age-CLI-Test: technisch blockiert

**Auftrag:** Die vorher noch ausstehende reale age-CLI-Verschlüsselung innerhalb **rein künstlicher** GH-05-Archivdaten tatsächlich ausführen, ohne CI-Kosten, produktive Credentials oder Serversysteme.

**Quelle für offizielles age Binary:** [FiloSottile/age Releases](https://github.com/FiloSottile/age/releases), Version **v1.3.2** mit Asset `age-v1.3.2-linux-amd64.tar.gz`, öffentlich vom Hersteller ausgewiesene SHA256-Prüfsumme `cbe24006683f8eb669266162894b9a522a1af52f2665fbc63a4bb032ed26ac10`. Eine Abweichung würde Ausführung verhindern. Das Archiv wurde **nicht heruntergeladen**, daher auch kein Hash verifiziert und kein fremdes Binary ausgeführt.

**Tatsächliche Versuche und Ergebnisse:** In der isolierten Assistenzumgebung sind `age` und `age-keygen` nicht vorhanden; `git ls-remote` gegen das öffentliche Quellrepository scheiterte bereits an der Netzwerk-/Namensauflösung. Downloadversuche für das offizielle GitHub-Linux-Release und einen alternativen SourceForge-Spiegel waren über die verfügbare Download-Funktion nicht möglich. Remote Desktop listete `PCjan` als **offline**; es erfolgte kein Zugriff. Ein Ersatzbinary aus unbekannter Quelle wurde nicht akzeptiert.

Die lokal vorhandenen äquivalenten synthetischen Vorbereitungsdateien in einem reinen Testverzeichnis wurden erneut ausgeführt: `python3 -m unittest -v test_orgavela_gh05_synthetic_age.py` → **9 Fälle, 8 PASS, 1 SKIP** (`age`-Integration mangels CLI). Direkter `python3 orgavela_gh05_synthetic_age.py --synthetic-only` → erwartetes **Exit 1 / FAIL CLOSED**, nicht `PASS`, da `age` fehlt. Diese lokale Testkopie ist nicht als byteidentische Ausführung des letzten GitHub-PR-HEAD nachgewiesen; der Committed-Python-Testcode wurde separat per GitHub-Connector lesend inspiziert. Kein GitHub-Actions- oder CircleCI-Run und keine externe Kostenentstehung.

**Abnahmeentscheidung:** `GH05-SYN-D` **BLOCKED (binary unavailable)** und `gh05_real_age_cli_encryption_e2e_verified=false`; nicht `accepted` und auch nicht als echter Provider-CI-PASS markieren. GH03 unabhängiges Offline-Key-Recovery weiterhin offen. GH-05 eigentlicher OrgaVela/MariaDB-Capture, GH-06 echter age-Upload, GH-07 isolierter echter Restore und GH-08 Zeitplanung bleiben vollständig gesperrt.

**Sichere Fortsetzung:** Auf einer zukünftig verfügbaren isolierten Laufzeit oder Betreiber-PC mit offiziellem age/age-keygen v1.3.2 und geprüftem Release-SHA ausschließlich die unveränderten synthetischen Tests aus PR #20 ausführen, nur **temporäre Test-Identity** verwenden, keine AgePony-Identity oder Recovery-PDF, kein GitHub-Release und kein IONOS-Zugriff. Erst dann nach tatsächlichen Ergebnissen die Testmatrix ergänzen.


## 20.10.2026 – Dreamloud-Producer/Writer-Parität ohne PCjan

**Source audit (read-only):** Dreamloud `janjrre/dreamloud-portal`, actual production branch `backup/production-circleci` at commit `da44f1a6d76ac25bce5cfed3122224c82c5da2fa`, not the simplified main CircleCI config. Evidence: [Dreamloud backup handoff](https://github.com/janjrre/dreamloud-portal/blob/backup/production-circleci/docs/operations/PHASE4_BACKUP_HANDOFF_LATEST.md), production snapshot #348, isolated full restore #353, private immutable Release 415872499. **Schedule code is prepared, but daily clock operation not independently proved**. Dreamloud uses OpenPGP and different source secrets/schema; neither keys nor IONOS credentials nor even implementation literals should be copied to OrgaVela.

**Adaptation on OrgaVela Draft PR #20:**
- `scripts/ci/orgavela_backup_parity.py`: the new **fixed synthetic-only** producer starts from existing hardcoded fake SQL/WordPress fixture, creates an ephemeral disposable age key, encrypts `.tar.age`, verifies it locally, then removes the temporary private identity. The writer runs separately and sees precisely **three** permitted artifacts: ciphertext, SHA-256 sidecar and sanitized public manifest. It checks exact inventory, age envelope, metadata, file modes, checksum and artificial-only constraints. There is **no GitHub release API** in this adapter; nothing is uploaded and the writer cannot decrypt. This is not a real backup exporter.
- `scripts/ci/test_orgavela_backup_parity.py`: negative tests for wrong mode, injected secrets, unexpected files, corrupted checksums, wrong encryption, accidental production fields, private-key/plaintext markers, symlinks and permissions. Real age CLI test is conditional and must not be marked green if binaries are absent.
- `.circleci/config.yml`: **one manual pipeline parameter `enable_gh05_synthetic_parity`, default `false`**. Only this explicit flag on `feat/gh05-synthetic-age-20261019` selects the two separated jobs `gh05-synthetic-source` and `gh05-synthetic-writer`, with writer dependent on source. Image `cimg/python:3.12`, `small`; the synthetic source would install the distribution-signed `age` CLI using apt (only in CircleCI), then test and publish **ciphertext-only workspace**. Job inputs use **no CircleCI context**, no CI-recovery key, SSH, WordPress login, GitHub PAT, remote writer or upload.
- This `.circleci/config.yml` is a **draft branch-local test proposal**, not a replacement for the separate OrgaVela CircleCI pilot on Draft PR #17. Prior to integrating these branches, their configs MUST be reconciled rather than overwriting one another. CI logic, provider settings and package availability require runtime proof and security review; **no CircleCI run has been launched**.
- The real Dreamloud production pattern would later require OrgaVela-specific implementation/review of independent source/ciphertext-only writer Contexts, owner-verified age recipient, immutable private GitHub release upload with two remote downloads and checksums, live consistency control, isolated WordPress/DB restore, and optional daily scheduling only after explicit operator gates. These are **NOT** supplied by this synthetic adapter.
- The existing GH-04 OpenPGP/GPG synthetic GitHub release is useful provider evidence but not a successful age publication or owner recovery.

**Hard stops:** Owner's AgePony recovery PDF is currently only on their original iOS device. GH-03 remains incomplete; no real OrgaVela/MariaDB files or production keys are allowed in runner/workspace until independently protected recoverability is verified and the live snapshot/restore controls pass. **No source/capture secrets, no writer token, no deploy, no real GitHub releases, no schedules, and no paid CI jobs were activated in this task.**

**Validation state:** New files created and pending independent provider execution. Keep `GH05-SYN-D` **OPEN** until an actual official age CLI run returns success; static Python review or a staged artificial fixture cannot replace runtime evidence. New source/writer parity tests also require runtime execution for formal PASS. Evidence is only code+docs presence, not a completed production backup.


## 20.10.2026 – echte synthetische CircleCI-Parität #188 erfolgreich

- **Anbieter-Run:** CircleCI Projekt-Wordpress-Plugin **Pipeline #188**, Workflow gh05-synthetic-parity-manual. Source/Checkout-Branch: feat/gh05-synthetic-age-20261019, **tatsächlich ausgeführter GitHub-Commit 590d530686356d641774a27ff4004aa27fd063aa**.
- **Unabhängige Providerbelege:** GitHub Commit Combined Status für diesen SHA hat ci/circleci: gh05-synthetic-source = **success** mit [Job #4](https://circleci.com/gh/janjrre/Projekt-Wordpress-Plugin/4) und ci/circleci: gh05-synthetic-writer = **success** mit [Job #5](https://circleci.com/gh/janjrre/Projekt-Wordpress-Plugin/5). Betreiber-Screenshot zeigt denselben Branch/Kurz-SHA, grüne Pipeline, source 11 s, writer 3 s, Gesamtlaufzeit 20 s; source persist_to_workspace 0 s SUCCESS, hochgeladener Workspace 11 KiB.
- **Commit-spezifisch überprüfte Run-Pfade:** .circleci/config.yml bei diesem SHA führt (ohne Skip-Condition und unter set -euo pipefail) im ersten Job apt-Installation offizielles Debian/Ubuntu-age-Paket + age/age-keygen-Version, beide Python-Testsuiten, künstliche Verschlüsselung/Entschlüsselung mit wegwerfbarem age-Key, danach lediglich drei vorverschlüsselte Workspace-Artefakte aus. Der nachgelagerte Writer prüft exact 3 Assets, Hash, öffentliches Manifest, Dateirechte und verweigert produktive Quellen. Kein GitHub-Upload, kein Source-/Backup-Context, kein Cron.
- **Evidenzgrenze:** Direkte stdout-Rohlogs mit exakten unittest-Gesamtzahlen und age-Versionsstrings wurden nicht separat durch den Assistenten abgerufen. Der positive Nachweis beruht auf tatsächlich authentifizierten SUCCESS-Job-Statuses am gepinnten Quell-SHA und zwingenden Workflowsteps; keine erfundene Rohlogzahl. Ein weiterer Rerun von #188 ist für diese Synthetik-Abnahme nicht nötig.
- **Abnahme:** GH05-SYN-D/DP-01–04 gelten für **synthetische CLI- und Producer/Writer-Parität als bestanden**. GH-05 im Sinne echten OrgaVela-/MariaDB-Captures, GH-03 Owner-Offline-Recovery, GH-06 immutable Github age-Release, GH-07 reale Full-Restore und GH-08 täglicher Schedule bleiben **OPEN**. Ein 11-KiB-Workspace ist **kein offsite GitHub Backup** und nicht dauerhaft für Disaster Recovery geeignet.
- **Nächster sinnvoller Technikschritt:** GH-06: age-basierte GitHub-Release-Transportvariante mit rein künstlichen Dateien und separater eingeschränkter Writer-Autorisierung konzipieren und in neuem Draft sicher testen; erst danach schrittweise echten Producer und Restaurierung bei erfüllten GH-03-Gates vorbereiten. Keine Betreiber-Privatekeys in CI.

