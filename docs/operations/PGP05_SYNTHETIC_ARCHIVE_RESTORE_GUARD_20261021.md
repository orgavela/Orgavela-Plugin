# PGP-05 Checkpoint 4 – rein synthetische Archiv-Wiederherstellungsprüfung

Stand 21.10.2026 · **nur Offline-/Entwicklungsnachweis, kein Live-Backup und kein echter Systemrestore**

## Motivation und Implementierung

Im bisherigen Entwicklungsstapel prüfen Draft PR #28 (Ciphertext-Manifest/SHA), #29 (synthetischer Snapshot plus OpenPGP-Public-Key-Verschlüsselung / separater Writer-Preflight) und #30 (simulierte Source-Consistency) verschiedene Schutzgrenzen. Es fehlte eine strikte Prüfung der **inneren Archivstruktur nach einer bereits andernorts erfolgten Entschlüsselung**, ohne daraus die erfolgreiche Wiederherstellung einer echten Website abzuleiten.

Neuer, separat gestapelter Draft PR: `feat/pgp05-synthetic-archive-restore-guard-20261021` auf Basis PR #30. Neu:
- `scripts/ci/orgavela_pgp05_synthetic_archive_verify.py` arbeitet **ausschließlich offline** auf einer bereits entschlüsselten kleinen **synthetischen** TAR-Datei. Es entschlüsselt nicht, liest keine privaten Schlüssel, schreibt keine Dateien aus dem Archiv heraus und verbindet sich mit keinem Provider.
- Drei **exakt vorgeschriebene** innere Mitglieder: `database.sql.gz`, `wordpress-files.tar.gz`, `manifest.json`; Verbot von doppelten Namen, zusätzlichen Dateien, absoluten oder relativen Traversal-Pfaden, nicht-regulären Dateien, Symbolic Links und Hard Links.
- Begrenzte Eingabe-/Datei-/Dekompressionsgrößen. `gzip` wird mit begrenztem Ergebnis und expliziter EOF-/Trailing-Data-Prüfung verarbeitet, damit ein scheinbar kleines Archiv nicht unbegrenzt expandieren kann.
- Nur exakt `CREATE TABLE orgavela_synthetic_only (id INTEGER);` und `wp-content/uploads/synthetic.txt` mit dem festen Marker `SYNTHETIC TEST ONLY - NO REAL SITE DATA` erlaubt. **Jede** reale WordPress-/MariaDB-Information muss verweigert werden.
- Strikte Schema-/Modus-/Backup-ID-/Empfängerfingerprint-/Hash-/Quellinventar-Prüfung des **internen** Manifests. Ein synthetischer Archivinhalt mit nachträglich neu berechneten, aber fachlich falschen Komponentendigest-Werten bleibt verboten.
- CLI muss explizit `--synthetic-only --decrypted-archive /ABSOLUTER/TESTPFAD.tar` erhalten; es protokolliert nur `PGP05_SYNTHETIC_ARCHIVE=PASS / NO REAL RESTORE` oder `...=REFUSED`. Es darf **niemals** mit echten entschlüsselten Backups verwendet werden.

## Nachweise (lokal, keine CI-/GitHub-Release-Ausführung)

Testsuite `scripts/ci/test_orgavela_pgp05_synthetic_archive_verify.py`: **28/28 lokal PASS** auf Python 3.13.5. Enthält künstliches Positiv-TAR sowie fehlende/zusätzliche/duplizierte Pfade, Traversal, SQL/WordPress-Markerfälschung bei passend neu berechneten Hashes, Komponentendigest-/Manifestfehler, falschen Empfänger, doppelte JSON-Keys, GZIP-Trailing-Junk, potenzielle Dekompressionsbombe, TAR-Symlinks auf beide Ebenen und CLI-Default-Off/Symlink/Relativpfad.

**Gesamtes erneut lokal getestetes synthetisches Entwicklungspaket: 86/86 PASS** aus 54 Tests für den originalen PR29-Snapshot plus neuem Archive-Gate und 32 Tests im PR30-Quellvertrag. Die beiden neuen Dateien stimmen bytegenau mit den lokal getesteten Git-Blobs überein: `18be7f07e1ac048367e1de3cc758e975f98e04fe` und `bd174b576f879afdbf1b1b393d99957a268e158d`. Das sind lokale Python-Tests, **keine Provider- oder End-to-End-Owner-Abnahme**.

Lokal:
`python3 -m unittest discover -v -s scripts/ci -p 'test_orgavela_pgp05_synthetic_*.py'`
`python3 -m unittest discover -v -s scripts/ci -p 'test_orgavela_pgp05_source_boundary.py'`

## Nicht dadurch bewiesen

- Kein **PGPony-Decrypt dieses neuen Pakets** durch den Betreiber, keine echten WordPress-Daten, keine echte MariaDB-Dump-Konsistenz, kein isolierter WordPress-/Datenbank-Neustart und keine echte Wiederherstellung von Nutzerdaten.
- Kein CI-Job, keine produktive Quell-/Writer-Kontextfreigabe, kein neuer GitHub-Release oder PAT, kein automatischer Backup-/Restore-/Scheduler-Lauf.
- Die Prüfung der **synthetischen inneren Archivstruktur** ersetzt weder digitale Quell-Authentizität noch Schutz vor einem Angreifer, der Ciphertext und Metadaten zugleich ersetzt. Produktion erfordert getrennte Vertrauen-/Freigabekontrollen.
- Die bestehenden Quell- und Konsistenz-Ereignisse in PR30 sind ausschließlich **selbstberichtende Simulationen**, keine Beweisführung über reale Providermaßnahmen.

## Nächste echte Betreiber-Entscheidungen (weiterhin offen)
1. Gesondertes GO für ausschließlich datensparsame, verifizierte **read-only IONOS-Quellmetadaten**; vorher unabhängiges Host-/Contract-/WordPress-/DB-Mapping und Secret-Rechte festlegen.
2. Getrenntes GO erst **später** für echte Capture + geeignete DB/Filesystem-Quiescence; weder Wartungsmodus noch Locks automatisch einschalten.
3. Nochmals getrennte Freigabe für echtes verschlüsseltes Offsite-Release. Anschließend isolierter WordPress+MariaDB-System-Restore, dann und nur dann Scheduler/Monitoring/Retention.
4. Historische PGPony-PUBLIC-Key-Policy mit bereits später vom Betreiber bestätigten Offline-Recovery-Kopien konsolidieren; bisherige `false`-Kandidatenflags nicht als aktuellen Abnahmestand verwenden.

**Ausdrücklich NICHT freigegeben:** echte Sicherung, IONOS-Site-Zugriff, CircleCI-Neulauf, neue Geheimnisse, PR-Merge, Scheduler, bezahlte Verträge. Formale V1-Zähler unverändert: 62/71 historische Codingpakete, M0–M7 0/8, P4 0/4, R01–R18 0/18.

Referenzen: [O03/PGP-05 Sicherheitsgates #27](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/27), [Draft PR #30](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/30), [OpenPGP-ADR](W0_O03_PGPONY_OPENPGP_MIGRATION_ADR_2026-10-20.md).

## Sicherheitsreview-Nachtrag: Original-Producer und TAR-Ende (21.10.2026)

Zusätzlich zur Datei-/Manifestprüfung verlangt die TAR-Validierung nun ausschließlich zulässige Nullbytes nach dem letzten Mitglied einschließlich der TAR-Endmarkierung, um ansonsten vom Parser stillschweigend ignorierte angehängte Bytes abzuweisen. Ein weiterer Integrationstest erfasst direkt im Speicher das von **dem tatsächlichen synthetischen PR29-Producer vor der Verschlüsselung** gebaute innere TAR und validiert es gegen den vollständigen neuen Archivprüfer. Für diesen *einen* Test wurde die Verschlüsselungsfunktion mit einem rein lokalen künstlichen Rückgabebyte-Stand-in versehen, um das **nicht verschlüsselte innere TAR ausschließlich vor dem Encrypt-Schritt** überprüfen zu können. Dies ist **kein** Test einer neuen OpenPGP-Entschlüsselung oder des Owner-Keys. Der separate PR29-GnuPG-Test bleibt dafür die kryptografische synthetische Referenz.

**Endstand: 28/28 Archivprüfungen PASS, gesamte lokal erneut ausgeführte Prüfgruppen 86/86 PASS** (PR29 Snapshot/Writer 26, PR30 simulierte Source-Policy 32, Archivprüfer 28). Bitgenau geprüfte neue SHA-Blobs Source `18be7f07e1ac048367e1de3cc758e975f98e04fe`, Test `bd174b576f879afdbf1b1b393d99957a268e158d`. Kein CI-Run, realer Snapshot, privater Key oder System-Restore.


## Public-Key-Kandidatenpolicy: Betreiber-Recovery-Evidenz aktualisiert

Die ältere `.github/backup/orgavela-pgpony-candidate-policy.json` meldete die zwei physischen Offline-Recovery-Kopien und die unabhängige private Besitzer-Wiederherstellung historisch mit `false`. Nach den eindeutigen Betreiberangaben vom 20.–21.10.2026 sind **diese konkreten Betreiberattestationen** nun separat mit Herkunft und `owner_recovery_evidence_verified_by_assistant=false` dokumentiert. Nicht geändert wurden die fehlende ausdrückliche Produktionsfreigabe, die fehlende Code-/Klartextbestätigung im ganz frühen externen Test und alle produktiven Sperren `production_backup_enabled=false`, `remote_github_release_enabled=false`, `automatic_schedule_enabled=false`. Kein privater Schlüssel, Passwort oder sonstige sensitive Information ist Bestandteil dieser GitHub-Metadaten. Diese Korrektur ist ein **Dokumentationsabgleich**, keine zusätzliche technische Schlüsselwiederherstellungsprüfung.
