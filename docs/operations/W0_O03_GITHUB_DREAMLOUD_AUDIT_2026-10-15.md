# OrgaVela / UOP Core – GitHub- und Dreamloud-Backup-Abgleich, 15.10.2026

**Auftrag:** Verbindliche Dateien, V1-Plan, Fortschritt, Dashboard und Arbeitsjournal prüfen; GitHub-Iststand prüfen; den **tatsächlichen** Dreamloud-Backup-Weg als Referenz bewerten; OrgaVela-Backuppfad, komplette V1-Übersicht und W0–W7-Ablauf dokumentieren. **Keine PR-Merges, Gebühren, Token-/Schlüsselanlage, CI-/Website-/Dreamloud-Mutation.**

## 1. Quellstand und Vertrauensgrenzen

- Repository: `janjrre/Projekt-Wordpress-Plugin`, öffentlich, `main` auf `0adacb427bd95dd87594e358ec9c6f2f5b38aae8`, GitHub Branch GET `protected=true`, Required Status Checks `enforcement_level=off`. Details zu Require PR, Bypass, Force Push, Delete über Connector **nicht** unabhängig lesbar.
- **Normativer Plan auf main v2.11**, Draft PR [#18](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/18) offen/nicht gemergt, enthielt v2.12 und wird mit dieser Prüfung als v2.13 **nur auf seinem Branch** fortgeschrieben.
- Stand aktiver PRs: [#1](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/1), #2, #3, #4, #5, #6, #11, [#17](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/17) (CircleCI-Pilot), [#18](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/18) (Dokumentation), [#19](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/19) (synthetischer GitHub-Release-Test); **10 offen**, nach Repository-Historie **0 gemergt**. #19 hängt auf dem Branch von #17, nicht unmittelbar auf main.
- Branch `test/orgavela-github-release-o03`: `a03b0e079ad0f8bd782574abd5b0ed581bf3c12a`, **protected=false**; Commitstatus `pending`, `total_count=0`, also **kein belegter CircleCI-Test** für OrgaVela-PR #19.
- `janjrre/orgavela-backups`: `private`, `main`, verfügbar, **GitHub Release-Liste leer**. Kein Canary und kein echtes OrgaVela-Backup als GitHub Release vorhanden.
- CircleCI-Pilot aus [PR #17](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/17) war in [Build #2](https://circleci.com/gh/janjrre/Projekt-Wordpress-Plugin/2) laut Provider-Commitstatus grün. Das ist nur ein Offline-Verbindungssmoke.
- Das Testworkflow aus #19 enthält `enable_synthetic_github_release_e2e=false` als Standardwert, einen manuellen Job mit `orgavela-backup-releases` und `GH_TOKEN`, künstliche Daten, **ephemeres GPG-Schlüsselpaar** und getrennten verschlüsselten REST-Upload/Download/Hash/Restore. **Noch nicht extern erfolgreich ausgeführt.**
- Früherer OrgaVela-Site-Nachweis laut V1_PROGRESS: aktives UOP Core `0.1.0-alpha.2`, 29 UOP-Tabellen / Migrationsversion 2/2 auf damaligem Live-Audit; **keine neue WordPress-Live-Abfrage** dieser GitHub-only Prüfung. Cron/Mail/Noindex/Restore weiter nicht formal abgenommen.

## 2. Was Dreamloud *wirklich* macht (nur lesend geprüft)

### A. Historischer realer Backup-Kern – GitHub Actions, nicht CircleCI

Die auf Dreamloud-`main` weiterhin vorhandene [`.github/workflows/create-encrypted-offsite-backup.yml`](https://github.com/janjrre/dreamloud-portal/blob/main/.github/workflows/create-encrypted-offsite-backup.yml) besitzt `cron: '23 2 * * *'`, liest WordPress-Dateien/Uploads und `wp-config.php` via SSH/SFTP, erstellt einen MariaDB-Dump, erzeugt Manifest/SHA256 und verschlüsselt mit einem **öffentlichen OpenPGP-Schlüssel**. Ziel-/Zeitplan-Konfiguration verweist noch auf S3-Secrets. Laut [Dreamloud-Produktions-Abnahmeplan](https://github.com/janjrre/dreamloud-portal/blob/main/docs/PHASE4-GITHUB-RELEASES-PRODUCTION-ACCEPTANCE-PLAN-2026-10-14.md) gab es historische echte verschlüsselte Backupartefakte und eine teilweise isolierte Restoreprobe; dies **belegt keinen aktiv geschalteten täglichen GitHub-Release-Backupbetrieb**. S3-Altworkflow bis kontrolliertem Cutover nicht als erledigt abschalten.

### B. Sicher bestätigte neue Transporttechnik – CircleCI, nur SYNTHETISCH

Privates `janjrre/dreamloud-backups`; [CircleCI Build #78](https://circleci.com/gh/janjrre/dreamloud-portal/78) und [Release `backup-dreamloud-synthetic-circleci-78`](https://github.com/janjrre/dreamloud-backups/releases/tag/backup-dreamloud-synthetic-circleci-78) nach REST **`draft=false`, `immutable=true`**, drei hochgeladene synthetische Assets (kleines GPG-verschlüsseltes TAR, SHA256, Manifest). Provider-/Skriptbelege umfassen Upload, numerische Release-/Asset-ID-Abfragen, Readback, erneuten Download, künstliche Entschlüsselung und Integritätstest. **Keine realen WordPress/MariaDB-Daten** in diesem Release. Synthetischer Test-PAT wurde laut Dreamloud-Betriebsdokumentation nach Abschluss widerrufen und Test-`GH_TOKEN` aus CircleCI-Context entfernt. **Nicht kopieren/reaktivieren.**

### C. Dreamloud-Produktionsumstellung noch nicht fertig

[Dreamloud PR #450](https://github.com/janjrre/dreamloud-portal/pull/450) weiterhin Draft; Offline-OpenPGP-Packing bestätigt, echte Collector/Writer-Integration, produktiver Capture, vollständiger Remote-only-Restore, bewachter täglicher CircleCI-Zeitplan und endgültige Betriebsfreigabe noch offen. Daher **nicht behaupten, dass Dreamloud die vollständige gewünschte CircleCI-GitHub-Produktionssicherung schon erfolgreich betreibt**. Nicht auf Dreamloud deployen oder Änderungen vornehmen.

## 3. Entscheidung für OrgaVela

**Strategie beibehalten:** IONOS-Provider-Sicherung plus ausschließlich privates `orgavela-backups` als zweiter Backup-Speicher, GitHub **Release-Assets** statt Git-Commits; vor Upload verschlüsseln. Kein Google Drive, keine zusätzliche kostenpflichtige Lösung. CircleCI nur unter nachweislich ausreichendem Free-Kontingent und effektiver geschützter Context-/Branchgrenze. Getrennter OrgaVela-Token, niemals Dreamloud-Geheimnisse.

**Technikentscheidung (bewusst noch offen):** Das vorhandene rein synthetische #19 nutzt **OpenPGP/GPG**, genau wie der bewiesene Dreamloud-Transport; ältere OrgaVela-Runbooks favorisieren **age** für echte Backups. Ein vorläufiger GPG-Test ist **keine** stille Freigabe des Produktionsformats. Vor GH-06 verbindlich im ADR/Runbook entscheiden, ob ein langlebiger OrgaVela-Recovery-Key als `age` oder OpenPGP verwendet wird; prüfen: getesteter Restore, Public-Key-Bindung/Fingerprint, sichere offline geteilte Keyverwahrung, unterstützte Runtime, Rotation und Notfallzugriff. Kein privater Recovery-Key im CI, Repo, Chat oder bei IONOS.

## 4. Präzise GH-01 bis GH-08 Roadmap / Stop-Gates

| Gate | Status 15.10.2026 | Nächster Beleg |
|---|---|---|
| GH-01 privates Zielrepo | **Bestanden** | Private Repo `orgavela-backups` bestätigt; Releases noch leer |
| GH-02 Schutz CI/Context/Free | **Blockiert/teilweise** | Testbranch nachweislich geschützt; PR/no-bypass/force-push/delete prüfen; CircleCI-Context Projekt-/Expression-Rechte passend, Free-Credits und untrusted-PR/SSH prüfen |
| GH-03 dauerhafter Betreiber-Recovery-Key | **Offen; vor echten Daten, nicht synthetischem E2E zwingend** | Zwei wirklich getrennte sichere Offline-Kopien + lokal verifizierte Entschlüsselung |
| GH-04 synthetischer CircleCI-GitHub-Release-E2E | **Code Draft #19; kein bestandener Cloud-Test** | Nur nach GH-02: kurzlebiger OrgaVela-only PAT im geschützten Context; manueller Test, privater immutable Release, Asset-IDs/Hash/Remote-Decrypt/Restore, danach Token widerrufen |
| GH-05 echte Capture-/Transportarchitektur | **Offen** | IONOS WordPress und konsistenter MariaDB-Dump, fail-closed Hostkey, Schema-/Scope- und Secret-Tests zuerst offline |
| GH-06 echte verschlüsselte private Sicherung | **Offen** | Betreiber-Key/Format freigegeben; vollständiges eigenes OrgaVela-Archiv und Remote-Hashcheck; keine Klartextartefakte |
| GH-07 Remote-only unabhängiger Full-Restore | **Offen** | Neu heruntergeladenes echtes Archiv in isoliertes WP/MariaDB-Staging, ohne Mails/Webhooks/Cron/Egress, Datenbank + Uploads + HTTPS/REST geprüft |
| GH-08 Betrieb/Schedule/Retention | **Offen** | Erst nach GH-07 echter Erfolg: Kosten-/Free-/Quoten-/AUP-/DSGVO-Prüfung, Alarmierung, dokumentierte RPO/RTO/Retention; periodische Restores, kein ungeprüftes automatisches Löschen |

**Nächster *sicherer* Benutzer-/Adminschritt:** Branch `test/orgavela-github-release-o03` vollständig schützen und GitHub `protected=true` lesen; CircleCI-Context so einschränken, dass **nur dieser** branchgebundene opt-in Test zugreifen darf, nicht zusätzlich `main`; Free-Kontingent und Release-Immutability des Backuprepos nachprüfen. **Erst danach** PAT direkt bei CircleCI und ein einzelner manueller Test. Keine kostenpflichtigen Plan-Upgrades; mögliche Trennung synthetischer und späterer produktiver Contexts berücksichtigen.

## 5. Vollständige V1-Fortsetzung W0–W7

| Welle | Fokus | Erforderlicher Exit |
|---|---|---|
| **W0 – läuft** | O01 Webspace-/Zugriff, O02 Schema, **O03 GH-01…08 und echter Restore**, O06 Testseiten/Privacy | konsistentes Backup/Remote-only Restore, überprüftes IONOS-Inventar, zugriffsgeschütztes synthetisches Testen |
| W1 | O04 Cron/Action Scheduler, O05 SMTP/Mail, O08 Monitoring/Alarme | echtes synthetisches Queue/Mail-Journey und Fehleralarm unter kontrollierten Bedingungen |
| W2 | O09 CI/PR-Review, M0–M6 auf aktuellen **gleichen** Commits prüfen, PR #11 Stabilität | grüne Pflichtmatrix und Sicherheitsreview, ohne verfrühte PR-Merges |
| W3 | P4-01 #7 Altersattest; P4-02 #8 doppelte unabhängige Actor-Einwilligung; P4-03 #9 Sorgeberechtigte; P4-04 #10 End-to-End | 4/4 akzeptiert, negative Cross-Org-/Revoke-/Actor-/Guardian-Browserfälle |
| W4 | M6-10 komplett P1–P6, O07 Browser/REST/Site-Journeys: Registrierung, Warteliste, Form, Admin, Gast/Guardian/Privacy | synthetische und IONOS-Staging-E2E inkl. Accessibility und Mail |
| W5 | geordnete Abhängigkeits-Integration **#1→#2→#3→#4→#5→#6→#11** nach separater Mergefreigabe, O10 atomarer Update/Rollback | konsistentes `main`, kontrollierte Alpha→RC-Migration, Restore + Rollback-Tests |
| W6 | M7-01…07 Last/Security/Privacy/A11y/Kompatibilität, O08/O11 UAT/Handbuch | alle Prüffälle belegt, keine High/Critical-Befunde, RC-fähiges Artefakt |
| W7 | M7-08, O12, R01–R18 Release-Signoff, Beta→RC→Stable; Veröffentlichung separat entscheiden | signiertes/prüfbares Releasepaket und ausdrückliche datierte Betreiber-Go-Entscheidung |

**Harte Bilanz unverändert:** 62/71 Code historisch belegt, **0/8** formale M-Abnahmen, **0/4** P4, **0/18** Release-Gates und keine PR-Merges. 9 ursprüngliche Coding-Pakete nicht als Restgesamtprojekt zählen. OrgaVela Alpha 0.1.0-alpha.2 ist früher nachgewiesenes Staging und **nicht** die V1.

## 6. Maßnahmen dieser Prüfrunde

- **Nur GitHub lesend auditiert**, auch Dreamloud ausschließlich gelesen.
- Dieses Audit und append-only Nachtrag im Arbeitslog auf **Draft PR #18**, Fortschritts-JSON/Status-Dashboard und normativen Plan dort aktualisieren; `main` v2.11 bleibt ohne Betreiber-Merge unangetastet.
- **Nicht erfolgt:** Merge, Freigabe, CI-/CircleCI-Run, Token, Secret, Key, Cloud-Release, IONOS-Backup, Restore, WordPress-/Dreamloud-Änderung, Rechnung oder Vertrag.
- Bei jeder nachfolgenden Arbeit GitHub-Fakten frisch lesen und im Journal belegen; neue echte Daten/Release-/Key-Schritte benötigen separat dokumentierte Freigaben und die oben genannten Nachweise.
