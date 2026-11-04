# OV-00 – OrgaVela technische Bestandsaufnahme (04.11.2026)

**Auftrag:** Den V1-Arbeitsplan mit einer ausschließlich lesenden technischen Prüfung der GitHub- und dokumentierten Betriebsnachweise beginnen. **Umsetzung:** Nur öffentliche, redigierte Projektdokumentation in einem separaten GitHub-Dokumentationszweig; kein Merge, kein Workflow-Dispatch, kein IONOS-/WordPress-/Datenbankzugriff, kein Backup-/Restore- oder Dreamloud-Zugriff, keine Secrets und keine Kostenfreigabe.

## Quellen und Evidenzgrenzen

- Kanonisch: [V1_ACCEPTANCE_PLAN.md](V1_ACCEPTANCE_PLAN.md), [V1_PROGRESS.json](V1_PROGRESS.json), [Issue #15](https://github.com/orgavela/Orgavela-Plugin/issues/15), [AGENTS.md](../../AGENTS.md), [V1_ACCEPTANCE_MATRIX.md](../project/V1_ACCEPTANCE_MATRIX.md).
- Connector-Read am 04.11.2026: GitHub `main` = [0adacb427bd95dd87594e358ec9c6f2f5b38aae8](https://github.com/orgavela/Orgavela-Plugin/commit/0adacb427bd95dd87594e358ec9c6f2f5b38aae8).
- GitHub-PR-Aktionen liefern PR-Metadaten und **PR-getriggerte** Workflowläufe für ein Commit (nur erste Seite); das ist **kein vollständiger Nachweis sämtlicher Push-/CircleCI-/Staging-Checks**. Statusabfragen und die Issue-Notizen sind deshalb getrennt bewertet.
- **Keine frische Live-Verifikation:** Zuletzt in [Issue #16](https://github.com/orgavela/Orgavela-Plugin/issues/16) belegte OrgaVela-Installation: UOP Core 0.1.0-alpha.2, WP 7.1.3, PHP 8.3.33, MariaDB 11.8.8, DB-/Datenoption 2/2. Aktuelle Installation/Schema-Integrität unbekannt.

## 1. Entwicklungs-PR-Stack (Stand der Connectorabfrage)

| PR | Aufgabengruppe | HEAD | aktueller PR-Zustand |
| --- | --- | --- | --- |
| [#1](https://github.com/orgavela/Orgavela-Plugin/pull/1) | M0/M1 | `115b82768d12f249caa047019e69bdb99b50bf59` | offen, nicht gemergt |
| [#2](https://github.com/orgavela/Orgavela-Plugin/pull/2) | M2 | `32ea1088b15567ed34a24b56e5f8d99ccc8dde91` | Draft, nicht gemergt, Basis #1 |
| [#3](https://github.com/orgavela/Orgavela-Plugin/pull/3) | M3 | `e70597ef8f4d7e77df6fa32ba818e3bff48f9cf1` | Draft, nicht gemergt, Basis #2 |
| [#4](https://github.com/orgavela/Orgavela-Plugin/pull/4) | M4 | `59ec51110d428d047849baafa61a0e7598363610` | offen, nicht gemergt, Basis #3 |
| [#5](https://github.com/orgavela/Orgavela-Plugin/pull/5) | M5 | `ea8d37743cfb622d393b408517a474e2223ffadb` | offen, nicht gemergt, Basis #4 |
| [#6](https://github.com/orgavela/Orgavela-Plugin/pull/6) | M6 | `027af05041123e59ff2802edcd1d0ac41b14e44c` | Draft, nicht gemergt, Basis #5 |
| [#11](https://github.com/orgavela/Orgavela-Plugin/pull/11) | P4-01 | `a34f4195371f9edf1fa24ea4abdef1fd479f2c19` | Draft, nicht gemergt, Basis #6 |

**Ergebnis:** Kein V1-Code-Stack auf `main` integriert. Die Merge-Abhängigkeiten #1 → #2 → #3 → #4 → #5 → #6 → #11 respektieren. Nicht direkt nach `main` retargeten oder PRs ohne Abnahme mergen.

## 2. CI-Nachweise, exakt zum abgefragten PR-HEAD

- PR **#6** `027af050...`: PR-Läufe [38180355859](https://github.com/orgavela/Orgavela-Plugin/actions/runs/38180355859) (Foundation: **8/8 erfolgreiche Jobs**) und [38180355844](https://github.com/orgavela/Orgavela-Plugin/actions/runs/38180355844) (HTTPS/Mailpit-E2E: **1/1 erfolgreich**). [38180355863](https://github.com/orgavela/Orgavela-Plugin/actions/runs/38180355863) enthält **static-check erfolgreich**, eigentlicher **staging-Job übersprungen**. Das ist kein Live-Staging-Signoff.
- PR **#11** `a34f419...`: PR-Läufe [38253835618](https://github.com/orgavela/Orgavela-Plugin/actions/runs/38253835618) (Foundation: **8/8 erfolgreiche Jobs**) und [38253835549](https://github.com/orgavela/Orgavela-Plugin/actions/runs/38253835549) (HTTPS/Mailpit-E2E: **1/1 erfolgreich**). Der frühere Hinweis in Issue #15 auf fehlgeschlagene Checks ist **nicht als neuer HEAD-PR-Fehler reproduziert**, jedoch sind Push-/andere Checks nicht vollständig über diesen Connector abgedeckt; nicht als endgültig gelöst markieren.
- `main` `0adacb42...`: Der abgerufene GitHub-Commit-Status hat **CircleCI Pipeline = error**, mit Ziel [historische Pipeline 526](https://app.circleci.com/pipelines/gh/janjrre/Projekt-Wordpress-Plugin/526). Ursache, Zeitpunkt und Aussagekraft nach Repository-Migration **noch nicht geklärt**. Dies ist **kein** Nachweis, dass heutige OrgaVela-Codeprüfungen fehlschlagen.
- **Keine neuen CI-Jobs gestartet**. Vor Integration fehlende Push-/Check-Run-Nachweise auf exakt dem jeweiligen HEAD einschließlich Branchschutz und Kostenlage überprüfen.

## 3. PGP-05 / Backup

- [Issue #27](https://github.com/orgavela/Orgavela-Plugin/issues/27) weiterhin offen. [PR #32](https://github.com/orgavela/Orgavela-Plugin/pull/32) auf `8c4b445ea5dced969f63b0f30e0e371342f64680`: drei CircleCI-Statusnachweise `success` (Offline-Security #54, manuelle Metadatenfreigabe, begrenzte SSH-Metadatenprüfung #56); **CP5 10/10** laut Projektunterlagen, kein Full-Snapshot-Nachweis.
- [PR #33](https://github.com/orgavela/Orgavela-Plugin/pull/33) auf `0d419c991d75999dd264aba2a912fedf663a1cf0`: gestapelter **offline-only** Collector-Port mit lokal dokumentierten Tests, aber **keine PR-getriggerten Workflowläufe/Commitstatus** gefunden. Provider-CI weiterhin **nicht belegt**. Produktive Entry-Points und Tokenloader absichtlich deaktiviert (Exit 78).
- Produktionsbackup, tatsächliches WordPress+MariaDB-Quellinventar, sichere Snapshot-Konsistenz, separater Writer und **isolierter Real-Restore nicht nachgewiesen**. Nur der Betreiber darf ein gesondertes, genau abgegrenztes IONOS-Quell- oder Real-Backup-Gate freigeben. Keine eigenmächtige Aktivierung, keine Secrets oder kostenpflichtigen Jobs.

## 4. Priorisierte Arbeitsaufträge nach OV-00

1. **P0 – O02/O03/W0 (ohne Produktiveingriff):** Quellidentität, Schema-/Migrationsintegrität, IONOS-Backupinventar und mögliche Restore-/Rollback-Grenzen lesen/klären. Bis zum nachgewiesenen Restore **keine produktive Migration**. PGP-05 Provider-CI für PR #33 erst nach sicherer Branch-/Kontext-/Kostenprüfung, nur synthetisch.
2. **P0 – P4-02/P4-03 (Entwicklungsbranch):** Vor einem Feature-/Live-Go getrennten consent-Actor-/Subject-State, Vertreterprüfungen, Revoke/Expiry, Missbrauchsschutz und eigenständige ADR/Migrationsverträglichkeit prüfen; negative und browserbasierte Tests zuerst auf synthetischen Daten.
3. **P1 – M6-10 P4–P6:** Auf einem gemeinsam geprüften Code-Stand reale Admin-/Mitglieder-/Guardian-/Gast-/Wartelisten-Golden-Paths in abgeschirmter Umgebung nachweisen; der `staging`-Job aus PR #6 ist übersprungen und kein Ersatz dafür.
4. **P1 – CI-/PR-Gates:** Push-/PR-/CircleCI-Status für die exakten HEADs und historischen `main`-Fehler kategorisieren, keine alten roten Meldungen als neue Befunde ausgeben; nach nötigen Korrekturen erneute gegenwartsbezogene Läufe im Free-Limit.
5. **P2 – Review/Integration:** gestapelte PRs in Reihenfolge mit Quell-/SHA-Prüfung, Datenschutz- und Security-Abnahme und Rollbacknachweis integrieren; M7 und R01–R18 danach auf exakt einem Release-Kandidaten.

**Definition of Done OV-00:** aktueller GitHub-Stand, vorhandene CI-Belege, tatsächliche Evidenzgrenzen, Blocker und nächste risikobasierte Reihenfolge dokumentiert. **Nicht erreicht:** Live/DB-Verifikation, vollständige Push-CI-Abnahme, Backup-/Restore-/P4-/M6-/V1-Abnahme.

## 5. Unverändertes V1-Cockpit

- Technisch laut historischer PR-Baseline implementiert: **62/71** normative Pakete (keine Gesamtreifequote).
- Formal V1-abgenommen: **M0–M7 0/8**, P4 **0/4**, R01–R18 **0/18**.
- Letzte bestätigte aktive Version: **0.1.0-alpha.2**, ohne Live-Neuprüfung am 04.11.2026.
- V1-Status: **OFFEN / NO-GO**.
- Dieser Bericht enthält keine Produktivfreigabe und ersetzt weder Sicherheitsreview noch den normativen [Masterplan](V1_ACCEPTANCE_PLAN.md).
