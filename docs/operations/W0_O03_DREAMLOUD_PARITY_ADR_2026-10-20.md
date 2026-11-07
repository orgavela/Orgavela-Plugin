# W0/O03 – Dreamloud-Backupverfahren als OrgaVela-Vorlage (20.10.2026)

**Entscheidung:** Bewährte zweistufige PC-unabhängige Backup-Architektur übernehmen, jedoch niemals Dreamloud-Produktionscode, -Schlüssel, -Kontexte, -Daten oder -Berechtigungen unverändert kopieren. Diese ADR ist ein Entwurf auf [Draft-PR #20](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/20), kein produktiver Scharfstellungsbeschluss.

## Tatsächlich geprüfte Dreamloud-Referenz

- Repository: janjrre/dreamloud-portal (read only), **Branch backup/production-circleci**, Commit da44f1a6d76ac25bce5cfed3122224c82c5da2fa. Die kleine CircleCI-Konfiguration auf main ist **nicht** die Produktionsbackup-Vorlage.
- .circleci/config.yml: default-false-Parameter und Branchfilter; getrennte production-quiesced-capture / production-immutable-writer Jobs. Nur Ciphertext, SHA256 und öffentliches Manifest zwischen Jobs; isolierte Recovery als separater Job. Kein automatischer täglicher Trigger ohne UI-Konfiguration.
- scripts/capture_quiesced_circleci.py, scripts/pack-encrypted-backup.sh, scripts/publish_production_backup.py, scripts/restore_from_github_circleci.py: Source-Konsistenz unter Wartungsfenster, asymmetrische Verschlüsselung VOR Upload/Workspace, Checksums/Manifest, privater unveränderlicher GitHub Release und getrennte Voll-Recovery.
- Produktions- und Restorebelege: [Dreamloud Backup-Handoff](https://github.com/janjrre/dreamloud-portal/blob/backup/production-circleci/docs/operations/PHASE4_BACKUP_HANDOFF_LATEST.md), CircleCI #348 (Capture + Writer), #353 (isolierter Recovery), Immutable Release ID 415872499. Das belegt noch **keinen täglich funktionierenden Zeitplan**.
- Dreamloud nutzt produktiv OpenPGP/GPG und einen extern gesicherten privaten Recovery-Key. OrgaVela nutzt gemäß GH-03-ADR weiterhin **age X25519** und verfügt derzeit nur über eine noch nicht unabhängig gesicherte lokale AgePony-Recovery-PDF.

## Adaption OrgaVela – überprüfbare Reihenfolge

1. **GH05-SYN:** Isolierter Producer nur mit fest codierten künstlichen SQL-/WordPress-Dateien und ephemerem age-Testkey. Der neue Writer akzeptiert exakt drei Assets (orgavela-synthetic-gh05-parity-v1.tar.age, gleichnamige .sha256, manifest.json). Kein privater Schlüssel oder Klartext im Workspace; keine GitHub-Release-API.
2. **Manueller CircleCI-Smoke noch nicht gestartet:** Auf Draft PR #20 Branch feat/gh05-synthetic-age-20261019 stellt .circleci/config.yml zwei Jobs gh05-synthetic-source und gh05-synthetic-writer bereit. Nur Boolean enable_gh05_synthetic_parity=true erlaubt Ausführung, default=false. Kein Secret-Context; nur nach erneuter Bestätigung des tatsächlich kostenlosen Kontingents starten. **PR #17 hat eine abweichende CircleCI-Konfiguration: vor Merge konfliktfrei zusammenführen**, nicht überschreiben.
3. **GH-03 Besitzer-Recovery vor echten Daten:** zwei unabhängig geschützte Wiederherstellungskopien, Copy-2-/Geräteverlusttest mit künstlichem Ciphertext/Hash. Der erfolgreiche lokale AgePony-Original-Vault-Roundtrip genügt NICHT. Bis dahin kein echter Snapshot, kein Upload und kein Cron.
4. **GH-05 Echtquelle nach Abnahme:** nur OrgaVela-Domain/-Vertrag, minimaler IONOS-Source-Context, verifiziertes WordPress-Root, DB+Dateien in konsistentem Snapshot, Wartungs-Fail-Closed-Evidenz, sicher bereinigte Logs. Kein Dreamloud-Pfad.
5. **GH-06 Ciphertext-Writer nach Abnahme:** separater auf privates janjrre/orgavela-backups begrenzter GitHub-App-/Kurzzeit-Token-Context. Ausschließlich zuvor mit öffentlichem age1-Empfänger verschlüsselte age-Archive, Hash und Manifest; immutable GitHub Release, binärer Readback vor/nach Publish, kein privater Key auf Writer.
6. **GH-07 Recovery und GH-08 Betrieb:** unabhängig vom Producer heruntergeladenes echtes Backup in isolierter MariaDB/WordPress-Umgebung mit blockiertem Mail/Cron/Internet wiederherstellen. Erst dann Free-Plan-, Quota- und Sicherheitsevidenz, bewusst manuell freigegebener täglicher Trigger, Alarme und Retention; keine neuen Kosten.

## Neue Artefakte auf Draft PR #20

- scripts/ci/orgavela_backup_parity.py: synthetischer age-Producer und ciphertext-only Writer.
- scripts/ci/test_orgavela_backup_parity.py: separate Negativ-Sicherheitsregressionen.
- .circleci/config.yml: default-off, zwei abhängige Jobs, branch-gefiltert, ohne Contexts.
- docs/operations/W0_O03_GH05_SYNTHETIC_PREPARATION.md: Runbook/Gates.

**Status:** Dateien auf GitHub angelegt, **noch kein tatsächlicher CircleCI-Lauf oder neuer nativer age-CLI-Lauf**. Die älteren 8 PASS/1 SKIP betreffen die VORHERIGE GH-05-Testreihe, nicht die neuen Parity-Tests. Statische Prüfung ist kein Provider-PASS. V1 unverändert: 62/71 Coding-Pakete historisch implementiert; M0–M7 0/8, P4 0/4, Release R01–R18 0/18 abgenommen; PR-Integration ausstehend.

**Stop-Regeln:** Keine Dreamloud-/OrgaVela-Produktivdaten, keine privaten Schlüssel/PDF/Passphrases im öffentlichen Repository, keine produktiven CircleCI-Kontexte oder Tokens, keine automatische Zeitplanung oder Uploads, keine kostenpflichtigen Runner, kein Merge ohne getrennte Sicherheitsgates.
