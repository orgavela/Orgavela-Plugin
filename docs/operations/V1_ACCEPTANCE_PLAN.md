# OrgaVela / UOP Core – Verbindlicher Masterplan bis zur V1-Abnahme

**Version:** 2.11 | **Bestandsaufnahme:** 12.10.2026 | **Status:** aktiv; V1 **nicht** abgenommen  
**Aktuell geltendes O03-Backupziel (Betreiberentscheid 13.10.2026):** nur **IONOS-Sicherungen** plus **separates privates GitHub-Repository mit vorverschlüsselten Release-Assets**. Die frühere Google-Drive-Entscheidung in historischen Nachträgen ist **widerrufen**; Details: [O03 GitHub-Backup-Runbook](W0_O03_GITHUB_PRIVATE_BACKUP_RUNBOOK.md).  
**Quellenhierarchie:** Phase 3 Specification Freeze > Phase 2 V1-Spezifikation > Phase 1 Produktstrategie; neuere freigegebene ADRs ergänzen den Freeze, ersetzen ihn nicht stillschweigend.  
**Projekt:** janjrre/Projekt-Wordpress-Plugin | **Technischer Name:** UOP Core | **Produktname:** OrgaVela | **Staging:** https://orgavela.com
**Zentrale GitHub-Abnahme-Issue:** [#15 – V1 Master-Abnahme](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/15) mit verlinkten Milestone- und Zusatzpaketen.  
**Begleitdokumente (ergänzend, keine zweite kanonische Wahrheit):** [Erweiterte Paket-/Abhängigkeits-Roadmap](../project/V1_MASTER_PLAN.md) · [Menschenlesbares Status-Dashboard](../project/V1_STATUS.md) · [Konkrete V1-Test- und Acceptance-Matrix](../project/V1_ACCEPTANCE_MATRIX.md). Zahlen und Gate-Freigaben kommen verbindlich aus [V1_PROGRESS.json](V1_PROGRESS.json).  
**Abgrenzung:** Dreamloud und das Repository janjrre/dreamloud-portal gehören NICHT zu diesem Projekt. Keine realen Teilnehmerdaten, kostenpflichtigen Verträge oder externen Veröffentlichungen ohne die erforderlichen Freigaben.

> **Dieses Dokument ist das verbindliche Arbeits-, Steuerungs- und Abnahmedokument.** Jedes Arbeitspaket endet erst mit nachvollziehbarem Prüfbeleg. Geplante, implementierte, im CI getestete, auf Staging geprüfte, gemergte und für V1 abgenommene Arbeit sind verschiedene Zustände. Keine automatische Gleichsetzung. Der jeweils aktuelle kompakte Maschinenstand steht in [V1_PROGRESS.json](V1_PROGRESS.json); alle Aktionen und Änderungen werden zusätzlich append-only in [ASSISTANT_WORK_LOG.md](ASSISTANT_WORK_LOG.md) dokumentiert.

## 1. Zielbild und V1-Grenze

UOP Core V1 ist ein **sicherer WordPress-Registrierungs- und Organisationskern mit Personenmodell**, kein universelles CRM. Ziel ist eine nachprüfbar lauffähige Installation, auf der autorisierte Veranstalter Personen/Organisationen, Rollen, Profile, Veranstaltungen und Versionen von Formularen verwalten; Berechtigte und ggf. verifizierte Gäste können sich anmelden; Plätze/Wartelisten und Benachrichtigungen laufen nachvollziehbar; Zustimmung, Widerruf, Datenschutz, Export, Löschung und Audit beachten zentrale Policies. Sämtliche WordPress-Admin-, REST-, Gutenberg-Block-, Portal-, Job- und Exportpfade müssen dieselben Object-/Field- und OrgScope-Regeln nutzen.

**Pflicht:** Default Organization und echte Scope-Grenze; Account ≠ Person; Beziehungen ≠ Delegationen; unveränderliche veröffentlichte Form-/Consent-Versionen; deterministische Registration/Capacity-Zustände; nach Commit zustellbare, wiederholsichere Queue; mobile, zugängliche Oberfläche; Datenschutz/Retention; reproduzierbarer Release und getesteter Restore.

**Explizit nicht V1:** Payments, Dokumenten-/Signaturmanagement, Check-in, Offline/PWA, frei konfigurierbarer Automations-Builder, SMS/WhatsApp, Rechnungen, umfassendes CRM, LMS, Marketing-Reporting, allgemeiner CSV-Import, komplexe Terminserien, Gruppenanmeldung und Netzwerk-weite Multisite-Verwaltung. Bestehende architektonische Erweiterungsschnittstellen sind erlaubt, aber keine verdeckten halbfertigen Produktversprechen.

**Abnahmeebenen:** (A) Source/CI, (B) temporäre isolierte HTTPS-Mailpit-Browserumgebung, (C) getrennte IONOS-Staginginstallation OrgaVela, (D) formale V1-RC-/Stable-Abnahme. A/B ersetzen weder C noch D. Die Alpha-Installation ist keine öffentliche V1.

## 2. Ausgangszustand und ehrliche Fortschrittszählung

| Messpunkt | Stand 12.10.2026 | Bewertung |
|---|---|---|
| Normative Pakete gemäß Specification Freeze | **71** in M0–M7 | 6+8+10+10+9+10+10+8 |
| Davon laut PR-Nachweisen technisch implementiert | **62/71** (ca. 87 % des ursprünglichen Coding-Paketkatalogs) | **Keine** Aussage über V1-Gesamtfertigstellung; M6-10, M7 und Zusatzblock P4 fehlen |
| Formal für V1 abgenommene Gesamt-Meilensteine | **0/8** | PRs #1–#6/#11 ungemergt, End-to-End-, Betriebs- und Release-Gates offen |
| M6-10 | Pakete 1–3 nach bisherigen CI-Berichten bearbeitet; Paket 4 teilweise, Gesamtpaket **offen** | P4-01 bis P4-04 separat |
| Zusatzpakete Minderjährige P4-01 bis P4-04 | **0/4 vollständig abgenommen** | #7 teilweise implementiert/PR #11 Draft; #8, #9, #10 offen |
| M7 Hardening/Release | **0/8 abgenommen** | Noch kein vollständiger V1-Release-Nachweis |
| OrgaVela Staging | WordPress-Plugin **UOP Core 0.1.0-alpha.2 aktiv** | SFTP-Upload erfolgreich, DB-/Datenversion jeweils 2 |
| Datenbankschema | Versionen 2/2 gespeichert; **physischer Schema-Health-Nachweis fehlt** | Status des verschachtelten Migrationsstatus noch nicht unabhängig abgefragt |
| Scheduler | WordPress-Cron deaktiviert; mehrere Jobs überfällig | Externer IONOS-Scheduler nicht bestätigt; Mail/Queue daher nicht abgenommen |
| Datenschutz/Staging | Suchmaschinenabschreckung aus; Datenschutztexte/Backup- und Rollback-Probe offen | Keine echten Daten, kein öffentliches Beta-Opt-in |
| Integrationsstand | #1–#6 und #11 offen; #2/#3/#6/#11 Draft | Gestapelte PR-Abhängigkeiten noch ungelöst |
| Neuester PR-#11-CI-Nachweis | Push-CI grün; PR-CI und HTTPS-Browser-CI auf HEAD 1b986587 **rot** | Nicht mit älteren grünen Läufen überschreiben |

**Zählregel:** Ein M-Paket wird in „technisch implementiert“ gezählt, wenn der PR auf dem dokumentierten Stand ausdrücklich eine Umsetzung und Tests belegt; „abgenommen“ erst nach überprüftem Definition-of-Done-Beleg, aktuellem green SHA, Sicherheits-/Betriebsgates und integrativer Freigabe. Paket P4 und Operations werden **zusätzlich** zum ursprünglichen 71er-Katalog gezählt; sie erhöhen nicht rückwirkend den scheinbaren Coding-Prozentsatz. Die tatsächliche V1-Projektfortschrittsquote wird **nicht** aus 62/71 abgeleitet.

### 2.1 Belege am Stichtag

- Source-Spezifikation: [Phase 3](../specification/phase-3-specification-freeze.md) und [Phase 2](../specification/phase-2-v1-specification.md).
- PR-Kette: [#1](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/1) → [#2](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/2) → [#3](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/3) → [#4](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/4) → [#5](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/5) → [#6](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/6) → [#11](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/11).
- Aktuelle PR-#11-Testlage: [Foundation PR 38233565475](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38233565475): 7/8 grün, PHP 8.5/MySQL 8.0 Browser-Aktivierungs-Timeout; [HTTPS/Browser PR 38233565383](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38233565383): zwei Browserfehler; [Push 38233559467](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38233559467): 8/8 grün. **Exakte SHA-Vergleiche bei jeder neuen Auswertung verpflichtend.**
- Testpaket auf OrgaVela: Source 4f2c4df800caf1e5708f05cce9536a7afb34aaa0, Paket-SHA-256 acbebdd9df25d996848bbca6ebe6726ee1a986207fc7c7b818474dc93134267a; [erfolgreicher Install-Run 38241807035](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38241807035). Aktivierung über separat verbundenen WordPress-MCP nachgewiesen. Alpha.2 ist eine **eingefrorene Einzelversion**, kein automatischer Release-Kanal.
- [Issue #12](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/12) betrifft ausschließlich die alte WordPress.com-Instanz mit latin1, **nicht** die bestätigte IONOS-utf8mb4-Instanz; für V1 als „anderes Hosting / dokumentierte Inkompatibilität“ dispositionieren.
- [Issue #13](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/13): Erstanbindung/SFTP/Install/Aktivierung erledigt; automatische Updates, sichere Rollbacks und Betriebsprüfung fehlen weiterhin.

## 3. Statusvokabular und Steuerung

**N** = noch nicht begonnen; **T** = Teilimplementierung; **I** = technisch implementiert + dokumentierter Teststand; **V** = Integrations-/Abnahmeprüfung offen; **B** = blockiert; **A** = V1-förmlich abgenommen. Ein Paket wird nur mit Datei-/PR-/Commit-/Run-ID und nachgewiesenem Testresultat auf „A“ gestellt.

Fortschrittsänderungen bekommen stets Datum, Git-HEAD, letzte Testläufe, konkret erledigte Akzeptanzkriterien, neu gefundene Fehler, Regressionen, Restaufwand und nächsten verantwortbaren Arbeitsschritt. Rückschritte sind erlaubt und werden transparent dokumentiert. Kein Prozent „aus dem Bauch“.

**Jede UOP-Arbeitsantwort enthält am Schluss den Plan-Link plus das kompakte V1-Cockpit** (technische M-Pakete, abgenommene M-Meilensteine, P4-Status, offene Blocker, aktueller Schritt). Bei wirklicher neuer Prüfung/Implementierung zuerst diesen Plan und V1_PROGRESS.json überprüfen und falls nötig aktualisieren; parallel einen neuen append-only Eintrag im Arbeitslog anlegen. Reine Antworten ohne Änderungsbedarf erhalten einen dokumentierten Kontrollvermerk, nicht zwangsläufig neue Plan-Version. Das gilt **nur für Antworten zum UOP-/OrgaVela-Projekt**, nicht für beliebige andere Chat-Themen. Außerhalb aktiv laufender Chats findet kein stilles Hintergrundmonitoring statt.

## 4. Vollständiger Work Breakdown M0–M7

**Tabellenstatus I bedeutet: technisch in bestehendem PR mit Tests ausgewiesen, aber gerade nicht automatisch gemergt oder V1-abgenommen.** Die Einzelabnahme wird entlang der nachfolgenden Matrix nachgeholt.

### M0 – Foundation (6 Pakete; I 6/6; A 0/6; PR #1)

| ID | Inhalt / bereits berichteter Stand | Offene Abnahmehandlungen |
|---|---|---|
| M0-01 | I – WordPress Plugin Bootstrap, Aktivierungsgates | Echtes Alpha-Plugin auf IONOS geprüft; Fatal-/Recovery- und Deaktivierungsfall erneut prüfen |
| M0-02 | I – Composer/npm, Locks, reproduzierbarer Build | Zwei identische Builds/Hashes, SBOM/Third-party-Notices, ZIP-Inhaltsaudit |
| M0-03 | I – WP/PHP/DB/InnoDB/utf8mb4 Compatibility Gate | Negativumgebungen vor Schreibzugriff ablehnen; aktuelle IONOS-Gatewerte protokollieren |
| M0-04 | I – Kernel, DI-/Module-Registry | Hook-/Boot-Reihenfolge, inkompatible Erweiterungen, keine globalen Nebenwirkungen |
| M0-05 | I – PHPCS/PHPStan/JS-Lint/SCA | Null offene unakzeptierte Findings; genaue Toolversion/Release-SHA |
| M0-06 | I – Testharness / WP/DB-/Browser-CI | Frische, wiederholbare Matrix auf RC-SHA; Flakes beseitigen |

**Exit M0:** Aktivieren/Deaktivieren ohne Fatal, Schutz in nicht unterstützten Umgebungen, reproducible ZIP und grüne vollständige CI; PR #1 review-/mergefähig. Bestehende Alpha-Aktivierung allein erfüllt den Exit nicht.

### M1 – Persistenz (8 Pakete; I 8/8; A 0/8; PR #1)

| ID | Inhalt / technischer Stand | Offene Abnahmehandlungen |
|---|---|---|
| M1-01 | I – Normatives V1-Manifest mit 28 Tabellen | Feld-/Index-/Collation-Ist-Soll-Audit mit Live-Schema |
| M1-02 | I – Migration Registry/Runner | Verriegelung, Retry, Versionszustand, Fail-Closed |
| M1-03 | I – Install-Migrationen | Fresh Install + Upgrade inkl. Altersattest V2 |
| M1-04 | I – UUID/Clock/Correlation | Uniqueness, UTC, keine internen IDs öffentlich |
| M1-05 | I – Transaktionen, Deadlocks und Callback-Grenzen | Mehrprozess-Rollback/Lock-Ownership/Commit-Semantik |
| M1-06 | I – Default Organization/OrgScope | Genau ein Default-Org; organisationsfremde Objekte unzugänglich |
| M1-07 | I – Scoped Repository/Query Contract | Index-, Paging-, SQL-Injection-/Scope-Architekturtests |
| M1-08 | I – Schema-/Migration-/Drift-Tests | Site-Health uop_schema good; unabhängiger Tabellen-/Indexvergleich und Restore |

**Exit M1:** Physisch belegter Schema-Stand 2/2 samt Altersattestation-Tabelle, korrekte Migration aus unterstützten Ständen und getestete Wiederherstellung. **Nächster Live-Arbeitsschritt: M1-08.**

### M2 – Identity, Authorization, Audit, Outbox (10 Pakete; I 10/10; A 0/10; PR #2 Draft)

| ID | Inhalt | Offene Abnahmehandlungen |
|---|---|---|
| M2-01 | I – Person und WP-Account-Link | Kein Matching gleicher E-Mail, Delete/Unlink, Doppelverknüpfung |
| M2-02 | I – Beziehungen/Delegationen | Nur explizit geprüfte Rechte, Ablauf und Revoke sofort |
| M2-03 | I – Actor Assignments | Organization-/Eventgrenzen, Deaktivierung, Sensitivity Ceiling |
| M2-04 | I – Rollen primitive Capabilities | Default Deny, Rollenkonflikte, Member/Staff/Admin |
| M2-05 | I – zentraler Policy Service | Cross-org/BOLA/IDOR, stale Rechte |
| M2-06 | I – Field Policy und Projection | Keine unerlaubten Felder in REST/Admin/Portal/CSV/Mail |
| M2-07 | I – minimiertes Audit | Revisionsfest, keine Tokens/Geheimnisse/unerlaubte PII |
| M2-08 | I – transaktionale Outbox | Exactly-once Wirkung trotz at-least-once Worker-Ausführung, kein Side-Effect vor Commit |
| M2-09 | I – öffentliche Post-Commit Hooks | Keine parallele dauerhafte Wahrheit und keine Secret-Exfiltration |
| M2-10 | I – Authorization Regression Matrix | Vollständiger Rollen×Objekt×Feld×Kanal-Test mit Negativfällen |

**Exit M2:** Alle Kanäle greifen nur über OrgScope, Object Policy und Projection auf Daten zu, kein Rechtegewinn durch geteilte Kontaktdaten. Draft-Review/Upstream-Merge offen.

### M3 – Profile, Events, Forms (10 Pakete; I 10/10; A 0/10; PR #3 Draft)

| ID | Inhalt | Offene Abnahmehandlungen |
|---|---|---|
| M3-01 | I – Profile Field Definitions | 11 erlaubte Feldtypen, Sensitivität, Schema-Migration |
| M3-02 | I – typisierte Werte | Multiselect, Löschung, ungültige Typänderung, Private Values |
| M3-03 | I – Condition-AST | Zyklus-, Referenz-, Consent- und Age-Fail-Closed |
| M3-04 | I – uop_event CPT/Settings | Keine unveröffentlichten Events oder unpassenden WP-Capabilities |
| M3-05 | I – manuelle Occurrences | IANA TZ/DST/UTC, Terminverschiebung, Limits |
| M3-06 | I – Entwürfe/Optimistic Lock | Konflikt statt Überschreiben, Versionswechsel |
| M3-07 | I – immutable Form-Publikation | Version/Hash/Feldbezug und spätere Historie erhalten |
| M3-08 | I – barrierearmer Form Builder | Feldpalette, Bedingung, Publikation, Tastatur-Reorder, 320px |
| M3-09 | I – Profil/Event/Form REST Reads | Scopes, Pagination, DTO, Status- und Versionskontrolle |
| M3-10 | I – Domain-/Accessibility-Tests | Reale Editor-UI, Empty/Error/Denied/Conflict, Screenreader |

**Exit M3:** Manager konfiguriert Event mit Terminen und veröffentlichter, unveränderlicher gültiger Formularversion. Draft-/Reviewstatus offen.

### M4 – Registration, Capacity, Waitlist (9 Pakete; I 9/9; A 0/9; PR #4)

| ID | Inhalt | Offene Abnahmehandlungen |
|---|---|---|
| M4-01 | I – Registration + immutable Snapshot | Wiederholung, Idempotenz, historische Referenzen |
| M4-02 | I – E-Mail-Verifizierung | Einmalig gehashte Challenge, Missbrauch/Rate/Expiry |
| M4-03 | I – Registration-State-Machine | Jede erlaubte/gesperrte Transition + Audit |
| M4-04 | I – Bucket/Capacity Claims | Kein Overbooking unter mindestens 50 parallelen Workern |
| M4-05 | I – FIFO-Warteliste/Offers | Held/Expired/Accepted, private Links, Ownership |
| M4-06 | I – Cancel/Release/Promotion | Atomar, keine doppelten Angebote oder Claims |
| M4-07 | I – Concurrency Suite | Race Cancel/Accept/Expiry, DB-Rollback, aktuelle CI |
| M4-08 | I – Event-Abbruch/Occurrence-Verschiebung | 100+ Registrations, Snapshots erhalten, Cursor |
| M4-09 | I – Service Golden Paths | Reale REST/Browser/Mail-E2E zusätzlich mit M6 verifizieren |

**Exit M4:** Erfolgreiche, sichere Anmeldung einschließlich verifizierter Gäste, deterministischer Warteliste/Angebotsannahme, Transaktions- und Race-Verträge über alle Kanäle abgenommen.

### M5 – Consent, Privacy, E-Mail, Export (10 Pakete; I 10/10; A 0/10; PR #5)

| ID | Inhalt | Offene Abnahmehandlungen |
|---|---|---|
| M5-01 | I – Consent Definition/Document Versions | Hash, immutable History, passende Zwecke |
| M5-02 | I – append-only Consent Records | Actor/Subject getrennt, Withdraw, keine Doppelwahrheit |
| M5-03 | I – DE/EN E-Mail-Vorlagen | Kontext-Escaping, private Template-Overrides |
| M5-04 | I – Queue/Delivery/Retry | Externer SMTP-Staging-Test, Scheduler, Crash/ambiguity |
| M5-05 | I – WordPress Privacy Export/Eraser | Account-Link statt Familien-E-Mail; echte Testexports |
| M5-06 | I – konfigurierbare Retention | Dry Run, Hold, Fristen fachlich/rechtlich geprüft |
| M5-07 | I – private Export-Jobs | Wiederholte Autorisierung, 24h Ablauf, private Pfade |
| M5-08 | I – CSV-Stream | Formula Injection, Größen-/Feldgrenze, Projection |
| M5-09 | I – Mail/Privacy/Export Adminzustände | Leere/fehlgeschlagene Jobs, Recovery/Reconcile |
| M5-10 | I – Failure- und Recovery-Tests | Massen-, Fehler-, Widerrufs-/Audit-Regression |

**Exit M5:** Kein ungeprüfter Consent, keine fremden Data-Exporte, stabile Outbox trotz Mailausfall und dokumentierte Lösch-/Aufbewahrungsgrenzen. **Juristische Einordnung** der Consent-Policies bleibt eine fachliche Freigabe, keine automatische Zertifizierung durch Tests.

### M6 – Admin, Portal, Blocks, REST (10 Pakete; I 9/10; M6-10 T; A 0/10; PR #6 Draft)

| ID | Inhalt | Offene Abnahmehandlungen |
|---|---|---|
| M6-01 | I – gemeinsamer REST Controller/Error Contract | API-Version, Statuscodes, Feld-/Payload-Allowlist |
| M6-02 | I – Personen-/Anmeldungs-Routen | Echt-Auth, stale PATCH, Review/Cancel, Objekt-Policies |
| M6-03 | I – Events/Forms/Capacity/Consent/Audit/Exports inkl. Gast-Mail | Standardmäßig gesperrte Gast-/Offer-Opt-ins erst nach Mail-/Cron-/HTTPS-Abnahme einschalten |
| M6-04 | I – Admin People/Registration | Screens mit Review/Audit, Filter, denied/empty/error |
| M6-05 | I – Admin Forms/Privacy/Communications/Health | Queue-Störungen, Recovery, minimal sichtbare Daten |
| M6-06 | I – fünf dynamische Gutenberg-Blocks | Editor/Frontend, CSP, Cache, veröffentlichte Inhalte |
| M6-07 | I – persönliches/delegiertes Portal | Wechsel, Revoke in offener Session, Eigentümer-Filter |
| M6-08 | I – Interactivity Adapter | Progressive Enhancement, no-JS, autoritative Serverprüfung |
| M6-09 | I – Responsive/a11y Vorprüfung | Manuelle 320px/Zoom/Tastatur/Screenreader-Abnahme fehlt |
| M6-10 | **T – gekoppelte End-to-End-Golden-Paths** | Paket 1–3 berichtete CI-Erfolge; Paket 4 Guardian/Consent erst teilweise; Pakete 5/6 und vollständige Live-Abnahme offen |

**M6-10 eigene Rest-Pakete (Projektpakete, nicht weitere Milestones):**
1. **Paket 1 – CI-Stabilität:** verbliebene intermittierende Browsertest-Timeouts auf aktuellem HEAD reproduzieren und beheben.
2. **Paket 2 – Mobile/UX:** auf 320/375/768/1280px und bei 200/400 % Zoom alle relevanten Screens/Fehlerzustände.
3. **Paket 3 – Security/Negativfälle:** invalid consent, Cross-Org, Sessionablauf, Replay, CSRF, Tokenleaks, Race. Frühere Tests sind vorhanden; auf RC-SHA wiederholen.
4. **Paket 4 – Alters-/Guardian-/Consent-Vertrauen:** **P4-01…P4-04**, siehe Abschnitt 5, vollständig zum Abschluss bringen.
5. **Paket 5 – permanente IONOS-Staging-E2E:** synthetische Erwachsene, zwei Familien-/Vertreterrollen, Admin, Gast, Warteliste, tatsächliche E-Mail-Verifikation, Frontend-/REST-/Audit-Konsistenz. Vorher Cron + Mail + Sichtbarkeit/Backup absichern. Keine Dritt-Mailadressen.
6. **Paket 6 – Abschluss M6:** vollautomatisierte CI + manuelle UX/Accessibility + Staging-Golden-Path-Testprotokolle, keine hohen Findings, PR-Review und Mergefähigkeit.

**Exit M6:** Fachliche V1-Golden-Paths von React/Gutenberg/Portal bis Datenbank/Mail/Dashboard in derselben releasefähigen Version grün; kein Umgehen von Alters-/Vertretungs-Policies; alle relevanten UI-Zustände verständlich und zugänglich.

### M7 – Hardening und Release (8 Pakete; N 8/8; A 0/8; neue Issues/PRs erforderlich)

| ID | Verbindliche Umsetzung | Messbarer Nachweis / Exit |
|---|---|---|
| M7-01 | Performance-/Large-Fixtures: 100/10.000/100.000 Persons; 500/50.000/500.000 Registrations; 10k/750k/7 Mio.+ Values, 10k/500k/3 Mio.+ Audits | p95 People/Registration List und Portal Reads ≤ 500ms; Registration Submit ≤ 1s (ohne asynchrone E-Mail); Capacity Transition ≤ 750ms ohne starke Konkurrenz, auf dokumentierter 4-vCPU-/8GB-Referenz |
| M7-02 | Sicherheit: Threat Model, statische/dynamische Checks, IDOR/BOLA, Cross-Org, CSRF/XSS/SQLi, Mass Assignment, JWT/Bearer-Replay, WAF/Abuse, CSV, Session-/Rate-Tests | Kein offener Critical/High-Fund; Ergebnisse inkl. False-Positive-Auswertung und Retest dokumentiert |
| M7-03 | Installation/Upgrade/Migration/Recovery: Alpha → Beta → RC, Schema 1→2, unterbrochene DDL/Backfills, Rollback, Backup/Restore, Drift | Reproduzierbarer Install/Upgrade; **Restore aus gesicherter IONOS-DB** erfolgreich demonstriert; keine orphan IDs |
| M7-04 | WP/PHP/DB/Theme/Browser-Matrix: WP 6.9 und Release-current, PHP 8.3/8.4/8.5, MySQL 8.0 und MariaDB 10.11+, InnoDB/utf8mb4, Min-/Current-WordPress | Alle CI-Jobs stabil grün auf dem **gleichen** Kandidaten-SHA; Konflikt-Smoketests mit verbreiteten Themes/Plugins |
| M7-05 | Manuelle WCAG 2.2 AA-Abnahme mit Keyboard, wenigstens einem Screenreader, mobilen Browsern, Fehlern/Timeouts/Fokus | Review-Protokoll pro Registrierung, Consent, Admin, Form Builder und Portal, keine bekannten AA-Blocker |
| M7-06 | Betriebs-/Datenschutz-/Developer-Dokumentation | Deutsch/Englisch Setup, Admin/Teilnehmer-Guides, Data Inventory, Zweck/Rechtsgrundlage vom Betreiber, Audit, Export/Eraser, Retention, API/Hooks/Extensions, Troubleshooting, Upgrade/Recovery, Secret-/Mailkonzept |
| M7-07 | finales Releaseartefakt und WordPress.org-Readiness | GPL/Third-party Notices/SBOM, readme.txt, Uninstall-Policy, Source/Build, reproduzierbares ZIP, externe Dienste deklariert, SHA-256 und Provenienz; keine Dev/CI-Secrets oder Test-MU-Plugins enthalten |
| M7-08 | Alpha → Beta → RC → V1 Stable nach Release-Gates | Beta-Feature-Freeze, RC nur Blocker-Fixes, Upgrade von Beta, Security-/DPA-/A11y-Abnahme, signierter Decision Record, Tag/Release Notes/Versioning |

**Exit M7:** Nur bei sämtlichen Gates grün und formellem dokumentiertem Go für V1. Ein grüner GitHub-Run auf irgendeinem älteren Commit ist kein Beleg.

## 5. Fachliches Zusatzpaket P4 – Minderjährige/Vertretung/Einwilligung

Dieses Zusatzpaket ist aus der M6-10-Härtung hervorgegangen und **zwingend für die zugesagte Nutzung mit Minderjährigen**, aber nicht in den ursprünglichen 71 Aufgaben enthalten. Die Organisations- und Rechtsprüfung muss für die tatsächlichen Zwecke gelten; technische Checkboxen sind kein amtlicher Identitäts- oder Sorgerechtsnachweis. Falls ein vollständig geprüfter Betriebsmodus für Minderjährige nicht rechtzeitig erreicht wird, **darf diese Funktion nicht als V1-tauglich freigeschaltet oder beworben werden**; der konkrete Release-Scope müsste vor Freigabe ausdrücklich angepasst und dokumentiert werden.

| Paket / Issue | Aktueller Stand | Arbeitsliste bis Abnahme |
|---|---|---|
| **P4-01 / #7; PR #11** | T – ADR 311–313, Altersattestationsmigration und Eligibility-/Admin-/REST-Teil umgesetzt; Review/CI noch offen | Code/ADR-Review, Klasse unbekannt/selbstbehauptet/geprüft/abgelaufen; je Event konfigurierbare Regel; NOT/AND/OR dreiwertig; Ablauf/Revoke; Cross-Org; datensparsame Nachweise; Backfill/Schema; aktueller HEAD grün, UX-/Rechtsprüfung |
| **P4-02 / #8** | N – echte unabhängige Consent-Entscheidung noch nicht umgesetzt | Neues ADR, State-/Schema-/Migrationsplan; pro Zweck self-only/delegate-only/beide; zwei **wirklich getrennte Actors/Sessions**, Revision/Widerruf einzeln, Version/hash/policy; UI/REST/Export/Audit, negative Browsercases |
| **P4-03 / #9** | T – Guardian-Claim/Admin-Grundstrecke existiert; externe Nachweisprüfung und Betriebsprozess offen | unabhängige Reviewer-Checkliste; keine bloße E-Mail/Code-/Checkbox-Verifikation; private Einladungen mit Rate-Limit, befristete Grants, mehrere Guardians, konkurrierende Claims, Revoke, Rechts-/Datenschutzregeln |
| **P4-04 / #10** | N – zusammenhängende Abnahme offen | echte Golden Paths für Erwachsene, Selbstentscheidung, Delegation, zwei Guardians/Actor-Entscheidungen, unklare Alterslage und alle Negativfälle; mobile/Screenreader, Privacyaudit, abgenommene Rechts-/Sicherheitsgrenzen |

**P4 Exit:** 4/4 Pakete abgeschlossen, alle gegen die zuletzt freigegebenen ADRs, mit 16/16 Release-CI-Jobs und relevanten HTTPS/Mailpit/IONOS-Stagingfällen auf demselben SHA; keine generische Zusage rechtswirksamer Unterschriften.

## 6. Eigenständige Operations-, Integration- und Go-live-Pakete

Diese Aufgaben sind **zusätzlich** zum Milestone-Katalog und aktuell Teil der V1-Freigabe; sie vermeiden ein irreführendes „62/71 bedeutet fast fertig“.

| Ops-ID | Paket / Priorität | Status | Konkrete Arbeit und Abschlussbeleg |
|---|---|---|---|
| O01 | Staging-Isolation und Zugriff – P0 | T | Domain orgavela.com, feste SFTP-Root /public, eigene GitHub-Environment-Secrets belegt. Verzeichniszugriff ist technisch weitreichend: Kontrollplan für andere Websites, eingeschränkter separater Deploy-Account soweit möglich, keine Dreamloud-Pfade, Audit der Secrets |
| O02 | Schema-/Datenintegrität – P0 | T | Aktuell DB-/Datenversion 2/2; unabhängig UOP-Site-Health „good“, 29 Tabellen inkl. Age-Attestation, Indizes, Org-Bindung, Option-Migrationsstatus und Recovery verifizieren |
| O03 | Staging Backup/Restore – P0 | N | IONOS DB+Datei-Sicherung, Zeitpunkt/Retention, praktikabler Restore; in isolierter Testinstanz zurückspielen, Integrität und Notfallkontakt dokumentieren |
| O04 | Cron/Action Scheduler – P0 | B | DISABLE_WP_CRON gesetzt; externe Cron-Route/Task und Frequenz beim Hosting klären, mindestens ein echtes Queue-Event erfolgreich ausführen, Stau-/Ausfall-/Retry-Monitoring |
| O05 | SMTP/E-Mail-Transport – P0 | N | Geschützte SMTP-Zugangsdaten und Testempfänger, SPF/DKIM/DMARC, transaktionaler UOP-Mailtransport, Mailpit-Vergleich, tatsächlich empfangene Einmallinks ohne personenbezogene Drittadressaten |
| O06 | Private Staging/Legal – P0 | B | Suchmaschinenabschreckung und ggf. Passwortschutz/Noindex; Datenschutzerklärung, Testdaten, tatsächliche Dienste, Logs/Retention, keine realen Teilnehmerdaten; vor öffentlichem Feature-Opt-in |
| O07 | Browser-/REST-E2E auf OrgaVela – P0 | N | Versionsgleiche, synthetische Tests für Admin/Erwachsene/Gast/Guardian/Consent/Warteliste/Privacy; Testdaten/Accounts nach Rücksetzen löschen; keine Logs mit Secrets |
| O08 | Security-/Betriebsüberwachung – P1 | N | Host-/Mail-/DB-/Queue-/PHP-Logs (redigiert), Alarme für Failed Jobs, Zugriffsprotokoll, Datenschutzkonforme Aufbewahrung; keine unbeobachtete öffentliche Beta |
| O09 | CI-Gate und Abhängigkeiten – P0 | B | Neueste PR-#11-Browserfehler beheben; M0–M6/P4 CI auf aktuellem SHA; in Reihenfolge PR #1→#2→#3→#4→#5→#6→#11 reviewen, rebasen/re-targeten und nach bestandenen Gates integrieren |
| O10 | Upgrade/Rollback-Pipeline – P0 | N | Bisheriger SFTP-Workflow kann nur Erstinstallation, kein Überschreiben. Versionierter atomarer Update-/Wiederherstellungsweg, freigegebene DB-Migration und prüfsummengebundene Deploys bauen und trocken testen |
| O11 | V1-Produktdokumentation/UAT – P1 | N | Verbindliches Screen-Inventar, Rollenhandbuch, verständliche UI, fehlende Texte, Abnahmeszenarien und benannte fachliche Prüfer |
| O12 | Release-/Freigabeprozess – P0 | N | Vollständige Go/No-Go-Checkliste, RC-Tag, SBOM, Security-/DPA-/A11y-Unterschriften und V1-Release-Protokoll; öffentlichen Launch gesondert entscheiden |

**Beschlossene Risikoreihenfolge:** O02 → O03 → O04/O06 → PR-#11-CI-Fix → P4 → O05/O07 → O09/O10 → M7 + O08/O11 → O12. Einige Pakete können parallel entwickelt werden, jedoch nicht ihre abhängigen Gates überspringen.

## 7. Arbeitswellen, Reihenfolge und konkrete Lieferobjekte

| Welle | Arbeit | Abhängigkeiten | Ende erst bei |
|---|---|---|---|
| **W0: Bestands- und Sicherheitsabnahme** | O01/O02/O03/O06: Schema-Health, isolierter Webspace, Backup-/Restore, Datenschutz-/Staging-Sichtbarkeit; Issue #13 neu bewerten | aktive Alpha-Site | belegte Datenbank-/Backup-/Recovery-Ausgangsbasis, keine echten Daten |
| **W1: Betriebsfähigkeit** | O04/O05/O08: externer Cron, Scheduler-Queue, E-Mail, Fehlerprotokoll, Alarmregeln | W0 | verlässlich zugestellte synthetische UOP-Mail, 0 unbehandelte überfällige UOP-Queue-Jobs |
| **W2: CI-/Branch-Gesundheit** | O09: PR #11 HEAD Browserfehler root-causen; Draft-PRs reviewen, Security-Regressions, M0–M6-Abnahmebelege | W0; CI unabhängig von Live-WP möglich | ein und derselbe HEAD für alle vorgesehenen Checks grün, keine kritischen Review-Findings |
| **W3: Minderjährige/Consent** | P4-01→P4-02→P4-03→P4-04 inkl. ADRs, Datenmigration, UX, Browser/Privacy-Legal | W2; W1 für echten Mail-E2E | vier Issues mit belastbarem Nachweis abschließbar |
| **W4: Vollständige Nutzerabläufe** | M6-10 Pakete 1–6, O07: Adult/Guardian/Guest/Waitlist/Admin/Privacy auf temporärer CI und IONOS | W1–W3 | Golden Paths und Negativmatrix auf RC-Source und Staging vollständig nachgewiesen |
| **W5: Integration und Updatefähigkeit** | PR-Kette geordnet integrieren, O10: neuer voll versionierter Update-/Rollback-Weg; Alpha-Staging kontrolliert auf RC bringen | W2–W4 | Hauptbranch repräsentiert freigegebenen V1-Code, Rollforward/Rollback real getestet |
| **W6: M7 Release Hardening** | M7-01 bis M7-07, O08/O11; Review aller Phase-2-/3-Stopconditions | W4/W5 | alle belastbaren Perf-/Security-/Accessibility-/Datenschutz-/Compatibility-Berichte grün |
| **W7: RC & Stable-Abnahme** | M7-08, O12; Freeze, Upgrade-Matrix, RC-UAT, Go/No-Go, Tag, Changelog, Produktdokumentation | W6 | schriftlicher, datierter und nach Belegen nachvollziehbarer V1-Freigabeentscheid |

**Keine Kalendertermine erfunden:** Termin-/Aufwandsschätzung erst nach W0-/W2-Triage und belastbarem Team-/Zeitbudget. Priorität ist Sicherheits- und Freigabefähigkeit, nicht das früheste denkbare Veröffentlichungsdatum.

## 8. Verbindliche Testmatrix

**A. Aktivierung/Persistenz:** WP 6.9/minimum + Release-current; PHP 8.3/8.4/8.5 × MySQL 8.0/MariaDB 10.11; utf8mb4/InnoDB; Fresh Install; Schema 1→2; Upgrade, Partial Failure, Lock-Stale, Rollback/Restore, Multisite-Subsite-Smoke; UOP-Site-Health und Migration-State exakt.

**B. Berechtigungen:** Rolle × Actor × Organization × Event × Person × Field-Sensitivity × Channel (REST, Admin, Portal, Block, Mail, Export, Cron). Tests für Gäste, geteilte E-Mail, Angehörige, explizite Delegation, abgelaufene/revoked Grants, gleiche IDs in anderem Org-Scope, stale Browser-Session, mehrstufige Einwilligungen; **kein Leck** in 403/404-Seitentiteln, Listen, Breadcrumbs, Suchergebnissen oder Response-Bodies.

**C. Registrierungen:** Adult self, manager, guest verification, delegate, guardian with and without proof, eigenes und vertretendes Consent; published form Version A bei Aktualisierung auf B, Conditions, DST, Kapazität=1, 50 parallele Worker, FIFO Offer/Expiry/Replay, Event Cancellation, E-Mail Outage und Recovery, ID-/Code-Missbrauch; korrekte Audit/Outbox-Spuren ohne Tokens.

**D. Datenschutz:** personenbezogener Export, Löschanfrage, Teil-Löschung/Retention, gesetzlich begründete Aufbewahrung nur nach Betreiberfestlegung, Legal Hold, Staff Field Visibility, CSV-Formelinjektion, 24h Export-Erlöschen, gesicherte Admin-Ansichten und tatsächliche Privacy-Notice.

**E. UX/A11y:** verbindliches Admin- und Portal-Screen-Inventar aus Phase 2 abdecken; jede Route erhält Loading, Empty, Denied, Not Found, Validation, Conflict, Background-Failure, Mobile-Reflow. 320px, 200–400 % Zoom, Fokusführung, Labels/Fieldsets/Legends, Live Errors, Screenreader, non-drag Keyboard Alternative, Mindest-Target 32px interner Standard, keine rein farbigen Statussignale. Test mit und ohne JavaScript.

**F. Performance:** Phase-2-Referenz 4 vCPU/8GB, OPcache vergleichbar, ohne zwingenden Object Cache. Data Profiles Small/Medium/Large samt Audit. People/Registration-Liste und Portal GET p95 ≤ 500ms; Registration POST p95 ≤ 1s exkl. Mail; Capacity Transition p95 ≤ 750ms ohne starke Contention. Kein N+1, keine unpaginierten Tabellen, keine CSV > 5.000 synchron, kein sync Mass-Mail/Retention.

**G. Releasequalität:** für den **identischen Release-Commit** Coding Standards/PHPStan/ESLint/unit/integration/HTTPS-/Mailpit/Browser/Migrations-/Load-/SCA-Pipeline grün; deterministischer ZIP-Hash/SBOM/Sourcenachweis; keine proprietären oder nicht deklarierten externen Dienste. Zusätzlich manuell protokollierte UAT auf OrgaVela und ein erfolgreicher realer Restore.

## 9. 18 harte Release-Gates (R01–R18)

Die Abnahme-Matrix ist die letzte Sperre, nicht eine Ergebnisbeschreibung im Nachhinein.

| Gate | Abnahmekriterium | Ausgangszustand |
|---|---|---|
| R01 Scope | Alle Muss-Funktionen, keine als „fertig“ beworbenen Out-of-Scope-Features | offen |
| R02 Source | PRs korrekt integriert, identischer freigegebener Commit in Main/Build/RC | offen |
| R03 CI | Alle verpflichtenden Checks aktuell grün, Flakes und PR-#11-Failures behoben | rot |
| R04 Schema | Fresh/Upgrade/V2/Health/Backfill/Restore nachgewiesen | offen |
| R05 Org/Auth | Keine Cross-Org-/BOLA-/Field-Policy-Umgehung | offen |
| R06 Registrations | Adult/Gast/Delegation/Admin + Consent konsistent | offen |
| R07 Capacity | FIFO, Lock, Last Seat, Offer Expiry, 50-Worker-Stress | offen |
| R08 Mail/Jobs | Sicherer Scheduler, nachweislicher Empfang, Replay/Crash/Retry | rot |
| R09 Minderjährige | P4-01…P4-04 vollständig oder ausdrücklich eingegrenzter V1-Scope | offen |
| R10 Privacy | Export/Eraser/Retention/legal hold/Notice abgenommen | offen |
| R11 Security | Keine Critical/High Findings, Threat Model und Retest | offen |
| R12 A11y | WCAG 2.2 AA Baseline, Mobile + Manual Screenreader | offen |
| R13 Performance | Large Fixture + alle festgelegten p95-Budgets | offen |
| R14 Compatibility | WordPress/PHP/MySQL/MariaDB/Browser-Matrix | offen |
| R15 Rollback | GitHub→IONOS Upgrade/Rollback + DB-Restore ausprobiert | rot |
| R16 Artefakt | Reproduzierbares GPL-/SBOM-/WordPress.org-taugliches Paket | offen |
| R17 Betriebs-/Rechtssicherheit | Staging Privacy, Mail, Datenschutz, Verantwortlichkeit, Monitoring | rot |
| R18 Releaseentscheidung | Beta→RC→Stable, Abnahmeprotokoll und begründetes GO | offen |

**Stop bei einem roten/ungeklärten zwingenden Gate**. Eine Ausnahme ist nur durch eine neue, benannte, risikobewertete und ausdrücklich genehmigte Scope-/Release-Entscheidung zulässig; keinesfalls durch stilles Ignorieren von Sicherheits-, Consent- oder Datenintegritätsfehlern.

## 10. Review-/Merge-/Deploy-Politik

1. PR #1 ist direkter Vorgänger von #2; anschließend #3, #4, #5, #6, #11. Vor jedem Merge aktuellen Diff/HEAD, Sicherheits-Review, Grüne-Checks, Migration und erforderliche Migrationskompatibilität kontrollieren. Draft-PRs erst nach Gate auf „ready“ stellen.
2. Nach Merge des Vorgängers den nächsten PR gegen den neuen Zielbranch rebasen/re-targeten; Konflikte und Tests auf dem neuen tatsächlichen Commit lösen. **Keine stillschweigenden Bulk-Merges**, kein bloßes „mergeable=true“ als Sicherheitsbeleg.
3. Keine pauschale Übernahme des jetzt aktivierten Alpha-ZIP auf zukünftige Releases: Staging enthält Source 4f2c4df, nicht zwingend PR-#11-HEAD. Für jedes Paket Diff/Provenienz/Hash festhalten, Datenmigration/Backup und Rollback bereitstellen.
4. Jede neue IONOS-Installation ist explizit eine **Staging-Freigabe**, nicht automatisch eine Produktiv-/Public-V1-Freigabe. Vor Schema-/Datenmutation Backup und Restore-Test. Mehrere Deploywege niemals parallel.
5. Issue #12 wird beim Wechsel von WordPress.com auf IONOS als separates Support-/Host-Kompatibilitätsthema behandelt, nicht per Abschwächen des utf8mb4-Gates „gelöst“; #13 bleibt für sichere Updates/Rollbacks offen.
6. Öffentliches Release/WordPress.org-Einreichung nur auf dokumentierter M7-08-/O12-Entscheidungsgrundlage; keine kostenpflichtigen Verträge ohne neue gesonderte Erlaubnis.

## 11. Verbindliche Definition of Done – pro Arbeitspaket

Ein Paket ist erst „A“, wenn **alle anwendbaren Punkte** erledigt und belegbar sind: fachlicher Domain-Invariant; OrgScope; Capability, Object- und Field-Policy/Projection; Input-Allowlist, Output-Escaping; Transaktionsgrenzen, Retry/Idempotenz; Audit/Outbox/Post-Commit; Datenschutz-Klassifikation/Retention; REST-/Admin-/Portal-/Export-/Job-Auswirkungen; Error-/Empty-/Conflict-Zustände; Migration/Abwärtskompatibilität; Unit-, Integration-, Failure- und Cross-Tenant-Tests; Browser/Mobile/Accessibility; aktuelle CI/PR-Review; Update der Dokumentation; keine offenen Critical/High Findings. Nicht anwendbare Checks müssen ausdrücklich begründet, nicht stillschweigend abgehakt werden.

Für Milestones kommt hinzu: vollständige DoD aller jeweiligen Unterpakete, definierte Exit-Kriterien, eindeutige Testlinks auf demselben Stand und dokumentierte Freigabeentscheidung. Für V1 insgesamt zusätzlich sämtliche R01–R18.

## 12. Fortschrittsroutine – verpflichtend bei jedem weiteren UOP-Auftrag

**Vor der Antwort/Arbeit:** (1) AGENTS.md und diesen Masterplan lesen, (2) aktuellen GitHub-Branch/HEAD, PR-/Issue-/CI-Lage prüfen, (3) falls gefragt/stagingrelevant OrgaVela-Plugin-/Healthstatus lesen, (4) nächsten entblockten Schritt wählen. Keine unnötigen Live-Mutationen für reine Statusfragen.

**Während der Arbeit:** Auftrag/Akzeptanzkriterien, konkrete PR/Issue-ID, Code/Tests/Logs und ungeklärte Risiken nachvollziehbar halten; Testläufe und Fehlschläge nicht verstecken. Keine Secrets/PII in öffentliches GitHub.

**Nach jedem UOP-Auftrag:** (1) neuen append-only Worklog-Eintrag schreiben; (2) diesen Plan bei geänderten Fakten/Umfang aktualisieren; (3) V1_PROGRESS.json mit Datum, Status und Beleg aktualisieren; (4) Issue/PR-Kommentare synchronisieren, soweit relevant; (5) Antwort mit **Link zum gesamten Plan und Status-Cockpit** beenden. Ein Agent ohne GitHub-Schreibzugriff meldet die Unmöglichkeit wahrheitsgemäß, statt zu behaupten, aktualisiert zu haben.

**Pflichtformat am Ende jeder UOP-Antwort:**

> **V1-Fortschritt (Prüfdatum, Commit):** Technikpakete X/71 implementiert | Meilensteine Y/8 V1-abgenommen | P4 Z/4 abgenommen | Release-Gates G/18 grün | OrgaVela-Version / Aktivierungsstatus.  
> **Seit letztem Stand:** ... | **Blocker:** ... | **Als Nächstes:** ...  
> **Vollständiger Plan:** Link zu docs/operations/V1_ACCEPTANCE_PLAN.md | **Arbeitsprotokoll:** Link zu docs/operations/ASSISTANT_WORK_LOG.md.

Kein fingierter Live-Abgleich und keine Fortschrittsänderung ohne Quellenbeleg. Bei langen Antworten dürfen Abschnitte kompakt zusammengefasst werden, doch der **vollständige Plan muss per Link jederzeit mitgesendet** werden.


## W0-Start – erster bezeugter Umsetzungsstand (12.10.2026)

**W0 läuft, wurde jedoch noch nicht abgenommen.** Verbindliches Live-/Sicherheitsprotokoll: [W0_BASELINE_AND_RECOVERY.md](W0_BASELINE_AND_RECOVERY.md), separates Tracking [Issue #16](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/16). Der [aktuelle Fortschritt](V1_PROGRESS.json) enthält den W0-Block mit belegten Prüfergebnissen, Blockern und den zuletzt gestarteten PR-#11-CI-Läufen.

- **O01:** Domain `orgavela.com`, Plugin-Ziel `/public` und SFTP erfolgreich; das bestehende SFTP-Konto hat weiterhin größere Verzeichnisrechte als gewünscht. Weitere Pfadzugriffe **nicht** als „getestet“ ausgeben.
- **O02:** UOP Core `0.1.0-alpha.2` live aktiv, `uop_db_version=2`; vollständige tatsächliche Tabellen-/Index-/Migration-State-Prüfung aus den vorhandenen WordPress-MCP-Read-Abilities nicht möglich, weiterhin kein Schema-Exit.
- **O03:** Offiziellen IONOS-Datenbank-/Datei-Backup- und sicheren **isolierten Restore**-Pfad als konkrete Checkliste erarbeitet. Keine Sicherung aus Konto geladen; **Restore nicht durchgeführt**.
- **O06:** `environment=production`, `discourage_search_engines=false`; noch keine globale Noindex-Umstellung, um die öffentliche OrgaVela-Markenwebsite nicht ohne Betreiberentscheidung aus Suchmaschinen auszuschließen. UOP-Tests weiterhin synthetisch, private Routen zu schützen.
- **Parallele O09-Diagnose:** Letzter roter PR #11 zeigte reale WP-Admin-Menüüberlagerung und 44px-CSS-Bereitstellungsproblem in Playwright. Eng begrenzte Quellkorrekturen auf Draft-Branch, Commits `710b413e` und `7892a258`; aktuelle CI-Checks müssen auf **genau** `7892a258` neu bestanden werden. PHP-8.5/MySQL-8.0-Deaktivierungs-Timeout weiterhin nicht ursächlich behoben.

**Unveränderte formale Bilanz:** 62/71 historisch implementiert, **0/8** V1-Milestones, **0/4** P4, **0/18** Release-Gates formell grün. Nächste Pflichtbelege: tatsächlicher UOP Site-Health-Schema-Status, IONOS-Backup-Inventar/privater Restore-Klon, begrenzte Testseiten-Zugriffsentscheidung und finaler neuer CI-Run auf PR #11. Bis dahin kein riskanter Live-Upgrade-/Cron-/Mail-Eingriff.

## 13. Unmittelbar nächster Arbeitsauftrag (W0)

1. **M1-08/O02:** In OrgaVela Site Health das UOP-Schema-Checkresultat ermitteln und gegen 28 Basistabellen + V2-Altersattestationsschema verifizieren. Gespeicherte Versionswerte 2/2 allein reichen nicht.
2. **O03:** In IONOS nachweisbaren DB-/Datei-Backup- und Restore-Prozess finden und ohne echte Nutzerdaten testen; andernfalls vor weiterer Migration blockieren.
3. **O04:** IONOS-externer Cron bei deaktiviertem WP-Cron, zuerst nur Scheduler-/Queue-Smoketest und überfällige UOP-Queue prüfen.
4. **O06:** Testsite gegen ungewollte öffentliche Indexierung/Nutzung schützen; Datenschutz-/Sichtbarkeit an tatsächlich verwendete Komponenten anpassen.
5. **O09:** PR #11 auf HEAD 1b986587 analysieren: PHPUnit-/Browser-Timeout PHP 8.5/MySQL 8.0 und HTTPS-Playwright-Failures; Korrektur + PR/Push/HTTPS grün **auf demselben SHA**.

**Nächstes sinnvolles zusammenhängendes Arbeitspaket:** W0 – Schema/Backups/privates Staging, danach W1 und PR-#11-CI-Härtung. Die Staging-Aktivierung wurde bereits durchgeführt; sie wird nicht wiederholt, um zusätzliche Versionswechsel vorzutäuschen.

---

**Änderungsjournal:**  
- **2026-10-12, Version 1.1:** Zentralen GitHub-Abnahmeträger Issue #15 verlinkt, ohne inhaltliche Fortschrittsänderung.
- **2026-10-12, Version 1.0:** Initiale umfassende Bestandsaufnahme aus Phase 2/3, PR #1–#6/#11, Issues #7–#10/#12/#13, CI-Status und aktivierter IONOS-Alpha-Installation; normativen 71er-Katalog, M6-10-Unterpakete, P4 und Ops, 18 Release-Gates sowie dauerhafte Fortschrittsroutine zusammengeführt.

- **2026-10-12, Version 1.2:** Nach paralleler Planerstellung auf eine einzige Kanonik konsolidiert: dieses Dokument und V1_PROGRESS.json sind maßgeblich; ergänzende /docs/project-Roadmap, Statusansicht und detaillierte Prüffallmatrix verlinkt. Doppelissue #14 wird auf #15 umgeleitet. Keine fachliche Statusänderung, weiterhin keine V1-Abnahme.

- **2026-10-12, Version 1.3:** W0 tatsächlich begonnen, [W0-Belege](W0_BASELINE_AND_RECOVERY.md) und Issue #16 verlinkt, Schema-/Backup-/Privacy-Blocker statt formaler Erfolge dokumentiert und laufende CI-Nachprüfung auf neuem PR-#11-HEAD notiert; unveränderte V1-Zählung.


### Ergänzung zur W0-Live-Verifikation vom 12.10.2026 (Version 1.4)

Ergänzende technische Nachweise und ausführbare read-only `information_schema`-SQL-Abfragen sind im [W0-Read-only-Audit](W0_ORGAVELA_READ_ONLY_AUDIT_2026-10-12.md) dokumentiert. Diese ergänzen den bereits vorhandenen [W0-Basisbericht](W0_BASELINE_AND_RECOVERY.md), ohne konkurrierende Zählung.

**Auf dem OrgaVela-MCP neu verifiziert:** `uop-core/uop-core.php` Alpha.2 aktiv, WP 7.1.3/PHP 8.3.33/MariaDB 11.8.8/utf8mb4; `cron-test` 5/19 überfällige Events einschließlich `action_scheduler_run_queue`; `discourage_search_engines=false`; zugewiesene Datenschutzseite **Entwurf, nicht öffentlich erreichbar**. Aktueller Admin-/Hosting-Zugang bietet keinen sicheren generischen SQL- oder direkten UOP Site-Health-Abfragekanal. Der Code registriert `Installer::health` und kontrolliert 28+1 physische Tabellen; **das tatsächliche Live-Ergebnis wurde nicht ausgelesen**. Bis zum Backup-/isolierten Restore-Gate keine risikobehafteten Migrationen oder Upgrade-Versuche.

**O09 aktualisiert:** Neuer aktueller PR-#11-HEAD `7892a25894c184a624ab4ebea13aab0e1a1c88dd`. HTTPS/Playwright [Run #38248040695](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38248040695) fehlgeschlagen: Sorgeberechtigten-Login `page.waitForURL` (20 s); keine bestätigte fachliche Ursache. [Foundation #38248040810](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38248040810) hatte bei erster Abfrage noch aktive Jobs. Der neue CI-Stand **überschreibt die früheren roten Fehler nicht als Erfolg**. Keine abgesenkten A11y-Grenzen und keine deaktivierten Golden-Path-Tests.

**Abnahme:** O01/O02/M1-08/O03/O06/O09 weiterhin offen oder teilweise untersucht; M0–M7 unverändert **62/71 technisch implementiert**, **0/8 formell abgenommen**, P4 **0/4**, R **0/18**. Umsetzung der Prüfabfragen in IONOS/phpMyAdmin oder Admin-Site-Health erfordert einen geeigneten **separat berechtigten, read-only Zugang**, der bislang nicht durch den WordPress-MCP angeboten wird.


### CI-Finalisierung W0/O09 – 12.10.2026 (Version 1.5)

Der tatsächliche PR-#11-HEAD bleibt `7892a25894c184a624ab4ebea13aab0e1a1c88dd` (Draft). Der zuvor noch laufende [Foundation-Run 38248040810](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38248040810) ist **abgeschlossen und fehlgeschlagen**: sieben von acht Jobs erfolgreich, **PHP 8.4 mit MariaDB 10.11** scheitert an `tests/Browser/activation.spec.js`, `page.goto` beim echten Plugin-Deaktivieren nach 30 Sekunden. Der parallel gelaufene [HTTPS-Run 38248040695](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38248040695) bleibt **fehlgeschlagen**: Sorgeberechtigten-Login mit `page.waitForURL('/wp-admin/')` nach 20 Sekunden. Beide Logs wurden auf Job-Ebene gelesen. Weder Serverauslastung noch Applikationsursache ist dadurch abschließend bewiesen; keine blind verlängerten Timeouts oder ausgeschalteten Sicherheits-/A11y-Assertions. **O09 und R03 unverändert rot**, null neue Release-Gates. Details im [ergänzenden W0-Audit](W0_ORGAVELA_READ_ONLY_AUDIT_2026-10-12.md) und im append-only Arbeitsprotokoll.


### W0/O09 – Zwei fehlerhafte Browser-Workflows am aktuellen PR-HEAD behoben (Version 1.6, 12.10.2026)

**Tatsächlich in [PR #11](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/11) umgesetzt:** `tests/Browser/activation.spec.js` Commit `d36935da376224d12f16b451e43e78a5d46d6593` prüft nun den tatsächlich committed WordPress-Redirect inkl. erfolgreichem HTTP-Status und Zielpfad; aufrufende Browser-Assertions kontrollieren weiterhin den korrekten Plugin-Status und PHP-Diagnostik. `tests/Browser/m610-members-admin.spec.js` Commit `2b8a65f753447689190d442a038b3f76472aaa71` wartet beim authentifizierten Login auf den echten Redirect-Commit, anschließend **weiterhin** auf die sichtbare WordPress-Admin-Leiste. Kein Test übersprungen, keine A11y-/Berechtigungs-Schwellen gesenkt, keine bloße Timeouterhöhung. Die zuvor lange erwarteten `DOMContentLoaded`-Signale optionaler Admin-Ressourcen entscheiden dadurch nicht allein den Browser-Testausgang. **Kausalität der früheren gelegentlichen Server-Timeouts ist damit nicht endgültig bewiesen**; Regression bleibt bei Folgeläufen zu beobachten.

**Für denselben PR-HEAD `2b8a65f753447689190d442a038b3f76472aaa71` vollständig erfolgreich:** [PR-Foundation-Run 38250446667](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38250446667) **8/8 Jobs grün**, einschließlich PHP 8.3/8.4/8.5 × MySQL 8.0/MariaDB 10.11, Quality und Artefakt-Smoke; [PR-HTTPS/Mailpit-Run 38250446526](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38250446526) **erfolgreich**, inklusive realer Browser-Sorgeberechtigtenstrecke. **O09 technisch auf diesem Draft-PR-HEAD CI-grün**, formale Reviews, Merges und V1-Release-Gates jedoch weiter offen; 0/18 formale Freigaben.

**W0 Live-Nachprüfung:** Auf OrgaVela ist das zuvor installierte **Alpha.2** aktiv, nicht der neue PR-HEAD. Dessen angegebener Paket-Quell-SHA `4f2c4df800caf1e5708f05cce9536a7afb34aaa0` enthält den registrierten `uop_schema`-Health-Check und 28+1-Schema-Verifikator; der **tatsächlich ausgeführte** Site-Health-Befund bleibt mangels passender MCP-/DB-Leseability offen. Der WordPress-MCP listet 0 veröffentlichte `uop_event`-Posts und 1 veröffentlichte Standardbeispielseite. Suchmaschinenabschreckung bleibt aus, Datenschutzseite weiterhin Entwurf. `DISABLE_WP_CRON=true`, überfällige WP-Cron-Events sanken zwischen zwei Lese-Snapshots von 5 auf 2; daraus darf **keine** verlässliche externe Cron-Konfiguration abgeleitet werden. Keine Site-, DB-, SFTP- oder IONOS-Konfiguration verändert. O01/O02/O03/O06 weiterhin **nicht abgenommen**, O09 nach PR-HEAD CI-Nachweis noch Review-/Integrationsoffen; V1-Gesamtzählung unverändert.


### W0/M1-08 – Default-Org-Datenintegrität des Site-Health-Checks als Regression abgesichert (Version 1.7, 12.10.2026)

**Neuer konkreter Quellenbefund:** Der bisherige `Installer::health()` verifizierte Versions- und Migrationszustand sowie alle 28+1 Tabellen, prüfte jedoch **nicht**, ob der gespeicherte `uop_default_organization_id`-Wert zur tatsächlichen `default`-Organisation passte und deren Status `active` blieb. Dadurch war trotz inkonsistenter Startorganisation ein formal `good`-Meldesignal möglich. **Keine Änderung an Live-Daten:** Quellkorrektur nur auf dem Draft-Branch von [PR #11](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/11).

**Codeentwicklung:** [`20062427`](https://github.com/janjrre/Projekt-Wordpress-Plugin/commit/200624274b1e6300d7ec7d080bbb5ae366369d45) ergänzt eine strikt lesende Seed-/Pointer-/Active-Prüfung nach erfolgreicher Schema- und Age-Attestations-Prüfung; bei Abweichung meldet Site Health `critical`, **keine** stille Reparatur. [`c297a829`](https://github.com/janjrre/Projekt-Wordpress-Plugin/commit/c297a829d9a5dd74378ca09ad748a7442328d6b9) ergänzt den echten PHP/MySQL/MariaDB-Regressionstest mit archivierter Standardorganisation, absichtlich falschem gespeicherten Verweis und erneuter gültiger Referenz; überprüft das ausbleibende automatische Repair. Erste CI-Quality [Run 38252371827](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38252371827) zeigte PHPCS-Dokumentationsfehler `Missing @throws tag`, behoben in [`8e5cbfc4`](https://github.com/janjrre/Projekt-Wordpress-Plugin/commit/8e5cbfc454ffe963b6c3121d73b0cd4608053fd9). **Neue CI auf diesem finalen SHA muss zuerst abgeschlossen sein**; zuvor grüne Runs auf `2b8a65f7` gelten nicht für neue Codeversion.

**V1-Abnahmegrenze:** Dieser M1-08-Fix ist **entwickelt, aber bis zur aktuellen CI nicht als validiert markiert**. Die bereits verifizierte **vorherige** PR-CI bleibt historischer Beleg. Auf OrgaVela selbst läuft weiter `0.1.0-alpha.2` aus Quell-SHA `4f2c4df`, nicht die neue Branchversion. Dort sind Site-Health-`uop_schema` und physische 29-Tabellen-/Indexprüfung weiter unzugänglich, und O03 IONOS-Backup-/Restore immer noch gesperrt. W0 und 0/18 Release-Gates bleiben offen.

**Neuer Zugangsbefund:** Über `mosmcp__rest-list-routes` wurde geprüft, dass das verknüpfte OrgaVela-WordPress-MCP lediglich genehmigte `wp/v2`-GET-Routen kennt; `site-health` und `uop/v1` werden mit 0 freigegebenen Routen geliefert. Der nur-lesende Error-Log-Aufruf zeigt `logging_enabled=false`, `log_exists=false`; **keine Fehlerdaten vorhanden** bedeutet nicht „fehlerfrei“. Der Remote-PC ist offline. Kein missbräuchlicher REST-Aufruf, keine ungeschützte PHP-Diagnose, kein Hosting-/Cron-/Datenbankeingriff.


### W0/O09 – Fehlerursache aus GitHub-Artefakt und realer CI-Härtungsfix (Version 1.8, 12.10.2026)

**Abgeschlossene M1-Fix-Prüfung auf `8e5cbfc4`:** [Foundation/Integration #38252652328](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38252652328) **8/8 SUCCESS** mit allen sechs PHP-/DB-Matrixkombinationen, Codequalität und Paket-Test. Die neue M1-08-Standardorganisationsprüfung ist damit auf diesem Entwicklungs-SHA tatsächlich durchgelaufen. **HTTPS auf demselben SHA #38252652419 war rot**, aber nicht wegen des M1-Fixes: 18 Tests bestanden, 1 A11y-Timing-Test meldete 40 statt 44 Pixel Form-Button-Höhe.

**Konkrete RCA nicht mehr geraten:** Das tatsächliche Fehlertest-Artefakt `m610-https-diagnostics` (GitHub ID `11748822534`) wurde heruntergeladen und die Playwright-Trace-Netzwerkereignisse ausgewertet. Für `/wp-content/plugins/uop-core/assets/m3-form-builder.css` zeigte die Trace einen **fehlgeschlagenen CSS-GET mit `net::ERR_TOO_MANY_RETRIES`**, `response.status=-1`. Im Source-CSS existiert bereits die unveränderte 44px-Mindesthöhe bis zur spezifischen WordPress-Admin-Mount-Regel; der 40px-Messwert stammt auf diesem Lauf aus fehlender Asset-Lieferung, nicht aus einer schwachen Zielregel. Die Trace selbst wird nicht ins öffentliche Repository hochgeladen.

**Eng begrenzte, nicht-produktive Testinfrastruktur-Korrektur in Draft-PR #11:** [`faa8e729`](https://github.com/janjrre/Projekt-Wordpress-Plugin/commit/faa8e729c27e0dc409dacb8de44c846c44247e02) lässt den ausschließlich auf `127.0.0.1:8443` betriebenen HTTPS-CI-Proxy exakt erlaubte `uop-core/assets/<basename>.css|.js` direkt aus dem Git-Checkout als statische Dateien ausliefern, statt sie hinter den PHP-Workern zu stauen. Alle anderen WordPress-Routen bleiben am lokalen PHP-Upstream; keinerlei freie Dateipfade, externen Domains oder Zugangsdaten werden geöffnet. [`a34f4195`](https://github.com/janjrre/Projekt-Wordpress-Plugin/commit/a34f4195371f9edf1fa24ea4abdef1fd479f2c19) prüft im selben isolierten GitHub-Workflow drei erfolgreiche tatsächliche HTTPS-CSS-/JS-GETs mit lokalem TLS-Zertifikat und prüft die unveränderte `min-height:44px`-Regel, **bevor** Playwright misst. Keine A11y-/Guardian-/Auth-Assertions abgeschwächt, keine generelle Timeouterhöhung, kein Live-Deploy.

**Neuester PR-#11-HEAD `a34f4195371f9edf1fa24ea4abdef1fd479f2c19`:** neue [Foundation-CI #38253835618](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38253835618) und [HTTPS-CI #38253835549](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38253835549) beim Erstellen dieses W0-Eintrags noch **pending/in progress**; erst beide grünen Ergebnisstände auf exakt diesem SHA erlauben den „CI grün“-Vermerk. M1 auf Alpha.2 in OrgaVela und IONOS-Backup/Restore bleiben live unverändert und nicht abgenommen.


### W0/O09 – Same-HEAD-CI nach M1-08- und HTTPS-Fix vollständig grün (Version 1.9, 12.10.2026)

Der aktuelle Draft-[PR #11](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/11) bleibt auf HEAD `a34f4195371f9edf1fa24ea4abdef1fd479f2c19` **offen, Draft, ungemergt**, Basiskette `feat/m6-admin-portal-rest`. Für **genau diesen SHA** ist [Foundation #38253835618](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38253835618) **8/8 SUCCESS**, inklusive Codequalität, Pakettest und aller sechs PHP-8.3/8.4/8.5–MySQL-8.0/MariaDB-10.11-Integrationen. Gleichzeitig ist [isolierter HTTPS-/Mailpit-Run #38253835549](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38253835549) **SUCCESS**, einschließlich des zuvor ausfallenden 44px-Formular-A11y-Tests. Die zuvor rote PHP-Dokumentationsprüfung wurde im Code korrigiert. **O09-CI-Gate auf diesem Entwicklungsbranch ist damit technisch grün**, aber keine formelle Release-Abnahme oder Aussage zur Langzeitstabilität und kein Produktions-Deploy.

**W0 bleibt trotz des CI-Erfolgs offen:** Auf `https://orgavela.com` ist weiterhin die getrennt gepackte und bereits aktivierte **UOP 0.1.0-alpha.2** aus Quell-SHA `4f2c4df` installiert; der jetzt getestete M1-08-Default-Org-Health-Fix ist **nicht** auf die Website ausgerollt. Für O02 fehlt das **tatsächliche** authentifizierte `uop_schema`-Site-Health-Resultat und der unabhängig bestätigte Metadaten-/Index-/Seed-Report der installierten 29 Tabellen. Für O03 fehlen IONOS-spezifisches Backup-Inventar, private Datei-/DB-Exporte und isolierter Restore. Für O01 fehlt echtes SFTP-Konto-Chroot, für O06 ein belegter privater Testseiten-Zugriffsschutz. Ohne diese Belege kein Live-Upgrade, keine echte Teilnehmerdatennutzung, keine automatische PR-Integration.

**Unveränderte V1-Baseline:** 62/71 historisch Coding-implementiert; **0/8** formale M0–M7-Abnahmen, **0/4** P4-Abnahmen, **0/18** finale Release-Gates grün, **0/7** gestapelte PRs gemergt. Die konkreten CI-Läufe sind in [V1_PROGRESS.json](V1_PROGRESS.json) und [W0-Read-only-Audit](W0_ORGAVELA_READ_ONLY_AUDIT_2026-10-12.md) nachgetragen.


### W0/O01/O02 – Betreiberautorisierter dedizierter GitHub-Secrets-/SSH-Read-Audit vorbereitet (Version 2.0, 12.10.2026)

**Betreiberauftrag:** Er möchte IONOS-Zugangsdaten in GitHub Actions hinterlegen, damit ein autorisierter Assistent weitere **eigene technische OrgaVela-Lesechecks** selbst auslösen und auswerten kann. **Kein IONOS-Hauptlogin!** Passwort und 2FA des Vertragskontos würden unnötige finanzielle/hostingweite Rechte geben; GitHub-Secrets sind keine Remote-Browser-Session und dürfen niemals IONOS-Panel-Credentials enthalten. IONOS Shared-Webhosting-Datenbanken nehmen nach offiziellen Anleitungen keine externen GitHub-Runner-MySQL-Verbindungen an, daher ist direktes Remote-SQL per GitHub Actions **nicht** der Standardweg. Ein geeigneter eigener **SSH-fähiger OrgaVela-Zugang** kann dagegen über IONOS-Webspace und `wp --skip-plugins --skip-themes eval` lokal auf WordPress-/DB-Lesemethoden zugreifen, sofern Shell und WP-CLI im konkreten Tarif verfügbar sind.

**Implementiert, noch nicht durch Zugangsdaten live getestet:** [`.github/workflows/orgavela-readonly-audit.yml`](../../.github/workflows/orgavela-readonly-audit.yml) (**eigene GitHub-Umgebung `orgavela-audit`, nur `main`, `contents:read`, `probe`/`schema`, manueller Start oder explizite begrenzte `.github/audit-requests/orgavela-readonly.json`-Commit-Anforderung), [`scripts/ci/orgavela-readonly-audit.sh`](../../scripts/ci/orgavela-readonly-audit.sh) (offizieller SHA256 ED25519-Hostkey, exakt verifizierter OrgaVela-`home` und Alpha.2-Paketstand, ausschließlich read-only PHP/SQL-Statuscodes, verschwiegenes STDERR/ungefilterte Resultate, keine Dump-/Upload-/Migration-Kommandos), [`docs/operations/ORGAVELA_AUDIT_GITHUB_SECRETS.md`](ORGAVELA_AUDIT_GITHUB_SECRETS.md) (Betreiberanleitung). **Es wurden keine Zugangsdaten gesetzt, gelesen oder entgegengenommen; das Runtime-Environment ist noch nicht eingerichtet und kein Auditlauf gestartet.** Secrets `IONOS_AUDIT_SSH_HOST`, `IONOS_AUDIT_SSH_USER`, `IONOS_AUDIT_SSH_PASSWORD` und die geprüfte Environment-Variable `IONOS_AUDIT_WP_ROOT` müssen direkt in GitHub vom Betreiber eingetragen werden. Die bestehende `orgavela-ionos`-Deployment-Umgebung wird nicht verändert oder wiederverwendet. Skript wird vor Kontakt durch `bash -n` geprüft; Remote-Ausführung nur nach bestätigter Domain, SSH-Fingerprint und installiertem Alpha.2.

**Wichtige Sperren:** Ein Webspace-Shell-Nutzer kann trotz durch den Workflow beschränkter Befehle selbst breite Rechte haben; das ist ohne IONOS-seitige Account-/Projektisolation **noch keine O01-Abnahme**. Ein `schema`-Lauf prüft den existierenden Alpha.2-`Installer::health()` plus UOP-Tabellenzahl, Versions- und Default-Org-Referenzkonsistenz (bereinigte `good/critical`-Flags), **nicht** selbstständig alle 29 Tabellen/Indexdefinitionen gegen das Manifest. O02 benötigt weiterhin echte ausführbare Live-Ergebnisse und unabhängige vollständige Metadata-Verifikation; O03 weiterhin privaten DB-/Datei-Export und isolierten Restore. Ein SFTP-only-Account kann diese SSH-/SQL-Diagnose nicht durchführen; kein öffentlich zugängliches PHP-Diagnosefile hochladen.

**Nächster Schritt mit Betreiber:** Separaten OrgaVela-nur SSH-fähigen IONOS-Nutzer prüfen, in GitHub `orgavela-audit` mit branch protection/reviewern und genannten Secrets/Variable einrichten, zunächst nur `probe` bewusst ausführen. Assistent darf nach ausdrücklichem Go und nachweislich gesetzten Secrets einen eng begrenzten Request-Commit nutzen und dessen Actions-Logs lesen. Zählung **unverändert**: 62/71 Coding, M0–M7 formal 0/8, P4 0/4, R01–R18 0/18, PRs 0/7 gemergt. Kein Live-Plugin-Deploy.


### W0/O01 – tatsächlicher SSH/SFTP-Rechteumfang `/`: Audit-Autostart vorsorglich deaktiviert (Version 2.1, 12.10.2026)

**Neue ausdrückliche Betreiberinformation:** Der **bereits in GitHub vorhandene** IONOS-SFTP/SSH-Account hat Zugriff auf den **gesamten Pfad `/` des Hosting-Webspaces**. Bei IONOS Shared Webhosting ist `/` typischerweise der chroot-begrenzte Hosting-Bereich, **kein root-Betriebssystemzugang**. Praktisch ist die Berechtigung auf alle erreichbaren Webspace-Verzeichnisse, potentiell einschließlich anderer Websites, zu bewerten. Die SFTP-/SSH-Kontoanleitung von IONOS sagt ausdrücklich, dass einem Unterverzeichnis zugewiesene SFTP-Zugänge **nur auf der SFTP-Ebene** begrenzt sind; über PHP entsteht dadurch **keine** zuverlässige Isolation. Damit ist ein auf `/public` konfigurierter Shell-Audit **nicht** automatisch von anderen Projekten getrennt. Quellen: [IONOS SFTP/SSH-Konto erstellen](https://www.ionos.de/hilfe/hosting/ftp-zugaenge-einrichten-und-verwalten/sftp/ssh-konto-erstellen/), [IONOS SSH-chroot](https://www.ionos.de/hilfe/hosting/ssh-zugaenge-einrichten-und-verwalten/was-ist-ssh/).

**Entscheidung und tatsächliche Änderung:** Der bestehende breite `orgavela-ionos`-Deployment-Zugang wird **nicht** in die neuen `orgavela-audit`-Secrets kopiert. Das vorher nur vorbereitete [Audit-Workflow-YAML](../../.github/workflows/orgavela-readonly-audit.yml) hat seinen automatischen `push`-Anstoß über `.github/audit-requests/orgavela-readonly.json` **verloren**; der verbleibende explizit manuelle `workflow_dispatch` benötigt neben `probe`/`schema` die bewusst zu aktivierende Bestätigung `confirm_dedicated_access=true`, und zwar **nur** nach unabhängig belegter, tatsächlich eingeschränkter OrgaVela-SSH-Isolation. Die Bestätigung ist bloßer Bedienerschutz, keine serverseitige Rechtebegrenzung. [Einrichtungsanleitung](ORGAVELA_AUDIT_GITHUB_SECRETS.md) korrigiert; die vorhandenen Deployment-Workflows/Secrets wurden **nicht** angefasst. **Keine** Live-SSH-/DB-/SFTP-Aktion, kein Versand/Empfang eines Secrets, keine zusätzliche Rechnung.

**Abnahmefolgen:** O01-Accountisolierung **bleibt Teilbefund/offen**, O02/M1-08 reales 29-Tabellen-/Index- und OrgHealth-Audit **offen**, O03 IONOS-Backups/isolierter Restore offen. Mit dem breiten `/`-Benutzer kann die Audit-Automation **nicht** freigegeben werden. Ohne nachweislich begrenzten SSH-Kontext ist der sicherere Zwischenschritt der **manuell betreute** IONOS phpMyAdmin-/WordPress-Admin-Readcheck. Eine neue getrennte Webspace-Hostingbuchung wäre eine weitere Isolationsoption, erfolgt aber **nie ohne gesonderte ausdrückliche Kostenfreigabe**. V1-Fortschrittszählung unverändert, kein Merge/Deploy.


### W0/O01/O02 – Betreiberfreigabe für bestehendes OrgaVela-Webspace-Konto, SSH-Audit reaktiviert (Version 2.2, 12.10.2026)

**Geänderte Voraussetzung:** Der Betreiber bestätigt ausdrücklich: **Dreamloud und OrgaVela sind in getrennten IONOS-Hosting-Verträgen.** Er akzeptiert bewusst die Reichweite des vorhandenen OrgaVela-SSH/SFTP-Benutzers auf **den gesamten Webspace `/` des OrgaVela-Vertrages**, und er autorisiert denselben bereits mit GitHub Actions verbundenen Benutzer für read-only Audits und andere fachlich freigegebene OrgaVela-IONOS-SFTP-/SSH-Arbeiten. Dies ist eine **Betreiberangabe und Risikofreigabe**, keine unabhängig verifizierte serverseitige Rechte-Isolation oder Freigabe von Dreamloud.

**Technische Umsetzung:** [`.github/workflows/orgavela-readonly-audit.yml`](../../.github/workflows/orgavela-readonly-audit.yml) verwendet nun wie die schon vorhandenen separaten SFTP- und SSH-Deployment-Workflows das **bereits eingerichtete** GitHub Environment `orgavela-ionos` sowie die existierenden Secrets `IONOS_SSH_HOST`, `IONOS_SSH_USER`, `IONOS_SSH_PASSWORD`, und die vorhandene Variable `IONOS_WP_ROOT` (optional; sonst nur für den identitätsgeprüften read-only Audit `/public`). **Keine Secrets werden kopiert, geändert oder angezeigt; keine neue Vertragsleistung.** Die frühere Ablehnung eines breit erreichbaren Webspace-Kontos wird wegen getrenntem Vertrag und ausdrücklicher Risikoübernahme aufgehoben. Der Audit hat wieder einen **engen `push`-Trigger ausschließlich für `.github/audit-requests/orgavela-readonly.json` auf `main`** sowie `workflow_dispatch`, und akzeptiert nur `probe` oder `schema` mit OrgaVela-`home`-Identitätsprüfung und hart gepinntem SSH-Hostkey. Diese Wiederfreigabe gilt **nicht** für Deploy-/Upload-/DB-Migrations-Trigger. Einzelheiten und Widerruf in [`ORGAVELA_AUDIT_GITHUB_SECRETS.md`](ORGAVELA_AUDIT_GITHUB_SECRETS.md).

**O01-Bewertung:** Die Zustimmung reduziert den organisatorischen Blocker der Account-Wiederverwendung; sie macht ein Konto mit Webspace-Schreibrechten **nicht technisch read-only**. O01 bleibt mindestens **partial**, bis Zugangsreichweite und OrgaVela-Identität durch einen wirklichen Probe-Lauf verifiziert sind. Die Nutzerangabe zur Vertragstrennung ist als solche zu protokollieren, nicht als eigene IONOS-Vertragsprüfung. **O02/M1-08 physisches Live-Schema und O03 Backup-/Restore-Abnahme bleiben offen**, bis erfolgreiche aktuelle Run-IDs und unabhängige Soll/Ist-Metadaten bzw. Restore-Belege vorliegen. Falls der vorhandene Account nur SFTP kann, bleibt SQL über SSH technisch blockiert und es ist ein OrgaVela-phpMyAdmin-/WordPress-Admin-Leseverfahren nötig. Weitere OrgaVela-SFTP/SSH-Schreiboperationen bleiben auf die bisherigen separaten Betriebs- und Release-Gates beschränkt. Dreamloud bleibt ausnahmslos unberührt.

**Nächste Tätigkeit:** Ausdrücklich beauftragten `probe`-Read-Audit als minimale `.github/audit-requests/orgavela-readonly.json`-Änderung auslösen, Logs ohne Secrets auswerten. `schema` erst nach erfolgreicher SSH/WP-CLI-/OrgaVela-Root-Probe. Stand bis zu echten Nachweisen unverändert **62/71 historisch implementiert**, **0/8** M0–M7 formal, **0/4** P4, **0/18** Release-Gates, **0/7** gemergte PRs; Live UOP Core zuletzt Alpha.2.


### W0/O01/O02/M1-08 – IONOS-Zugang vollständig wiederverwendet, erfolgreicher Live-29-Tabellen-Audit (Version 2.3, 12.10.2026)

**Betreiberfreigabe:** Vorhandener OrgaVela-SFTP/SSH-Benutzer hat webspaceweite `/`-Berechtigung, die der Betreiber nach eigener Bestätigung getrennter IONOS-Hosting-Verträge ausdrücklich akzeptiert. GitHub Workflow [`orgavela-readonly-audit.yml`](../../.github/workflows/orgavela-readonly-audit.yml) verwendet ausschließlich das bestehende `orgavela-ionos`-Environment, **keine** zusätzlichen Secrets, und hat eng begrenzte Modi `sftp` (Verzeichnislesung), `probe` (SSH-/WP-CLI-/Domain-/Plugin-Identität) und `schema` (lesende technische Schema-Health-/DB-Metadaten-Aggregate). Audit-Start durch erlaubte `main`-Request-Datei, GitHub-Token `contents:read`. Vorhandene separate Deployment-Requests und SFTP-/SSH-Deployment-Workflows **nicht** verändert.

**Tatsächlich durchgeführte aktuelle CI-/Live-Läufe, ohne Site-Mutation:**

1. [#38263245050](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38263245050) **FAILED**, obwohl GitHub Environment/Secrets vorhanden und lokales Shell-Skript valide waren: Der SSH-Shell-Pfad entsprach **nicht** dem bereits SFTP-verifizierten `/public`; zu diesem Zeitpunkt war das Fail-safe-Ergebnis nur generisch.
2. [#38263689846](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38263689846) **SUCCESS**, `ORGAVELA_AUDIT_SFTP=verified`: Verzeichnisse `wp-admin` und `wp-content/plugins/uop-core` im OrgaVela-SFTP-Webspace **gelesen, nicht heruntergeladen**.
3. [#38263987605](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38263987605) **FAILED** mit nun sauber klassifiziertem Exitcode **21 / Shell-WordPress-Root nicht gefunden**. Das beweist im Gegensatz zum ersten Sammelfehler, dass eine SSH-Shell **tatsächlich startbar** ist. IONOS-[Document-Root-Dokumentation](https://www.ionos.de/hilfe/hosting/php-fuer-web-projekt-verwenden/absoluten-pfad-document-root-ermitteln/) begründet die begrenzte Auswahl alternativer Shell-Pfade; kein unbeschränktes Dateisystem-Crawling oder öffentliche Pfadausgabe.
4. [#38264254555](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38264254555) **SUCCESS**, `ORGAVELA_AUDIT_TARGET=verified`, `ORGAVELA_AUDIT_SHELL=available`, `ORGAVELA_AUDIT_PLUGIN_VERSION=alpha2`, `ORGAVELA_AUDIT_PROBE=success` – das reale OrgaVela-WP-Root, die exakte Home-Domain `https://orgavela.com` und ein funktionsfähiges WP-CLI wurden verifiziert.
5. [#38264354774](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38264354774) **SUCCESS**, `ORGAVELA_AUDIT_SCHEMA=good`, `ORGAVELA_AUDIT_TABLE_COUNT=29`, `ORGAVELA_AUDIT_DEFAULT_ORG=good`, `ORGAVELA_AUDIT_VERSIONS=good` (DB-/Datenversion 2/2). Die installierte Alpha.2-`Installer::health()` ruft nach Source-Prüfung [`SchemaInspector::verify_all()`](../../src/Infrastructure/Database/SchemaInspector.php) (28 V1-Tabellen mit `information_schema`-Spalten-/Index-/Engine-/Kollationsvergleich und UOP-Tabellen-Set) und [`AgeAttestationSchema::verify()`](../../src/Infrastructure/Database/AgeAttestationSchema.php) (eine additive V2-Tabelle mit Spalten-/Index-/Engine-/Kollationskontrolle) auf. Ein unabhängiger Zusatz-Read im selben Audit bestätigt physische **29 Tabellen**, aktive referenzierte Default-Organisation sowie gespeicherte Migrations- und Datenversionen. **Kein** Roh-SQL-Dump, keine echten Personen- oder Teilnehmerdaten im GitHub-Output.

**Verbesserter W0-/M1-08-Beleg:** Das ehemals komplett blockierte physische OrgaVela-Schema ist jetzt **real gegen den implementierten Alpha.2-Health-Validator geprüft**, einschließlich 28+1 Tabellensatz, Spalten und Indizes. Die Datenbank-Leseprüfung selbst ist erfolgreich. Dies ist **keine** Abnahme des noch ungemergten vollständigen V1-Quellcodes oder eine unabhängige Wiederholung eines jeden einzelnen Tabellenspaltenvergleichs durch einen zweiten Inspector. O02 erhält Status **substanziell live verifiziert / formale Abnahme und restliche Kriterien offen**. M1 (0/8 formale Meilensteinabnahmen unverändert), P4 (0/4), R (0/18), 62/71 Coding, 0/7 gemergte PRs bleiben gleich. **Kein** produktives Plugin-Update, Migration, Cron-Änderung oder Datenbackup. Die bisherigen SSH-/SFTP-Schreibbefugnisse bleiben zwar nutzbar, müssen für konkrete schreibende Aufgaben weiterhin separat scope- und Gate-konform eingesetzt werden.

**Nächste W0-Priorität:** O03 wirkliches IONOS-Backupinventar, vertraulicher OrgaVela-DB+Datei-Snapshot und isoliert getesteter Restore **vor jeglicher produktiven Upgrade-/Migrationsaktion**. Parallel O01 tatsächliche Trennung/Schutz im OrgaVela-Vertrag und O06 öffentliche Zugriffsbeschränkung weiterhin prüfen. Keine automatische Abnahme aufgrund eines grünen Healthchecks; OrgaVela und Dreamloud bleiben vollständig getrennt. Alle Einzelbelege und Betreiber-Hinweise in [IONOS-Audit-Dokumentation](ORGAVELA_AUDIT_GITHUB_SECRETS.md) und [Worklog](ASSISTANT_WORK_LOG.md).


### W0 / O03 – Live-Sicherungs-Preflight und synthetischer isolierter Restore nachgewiesen (Version 2.4, 12.10.2026)

**Tatsächliche Umsetzung:** Das bestehende geschützte OrgaVela-`orgavela-ionos`-GitHub-Environment wird weiter genutzt; der [nur lesende IONOS-Audit-Workflow](../../.github/workflows/orgavela-readonly-audit.yml) verfügt jetzt zusätzlich über **`backup-preflight`**, ausschließlich über einen gezielten `.github/audit-requests/orgavela-readonly.json`-Änderungsrequest oder manuell auf `main` auslösbar. Der [Quellcode](../../scripts/ci/orgavela-readonly-audit.sh) liest nur Tool-Verfügbarkeit (`mariadb-dump`/`mysqldump`, `mysql`, `tar`, `gzip`, `sha256sum`), grobe WordPress-Dateigröße/Freiplatzkategorie und per `information_schema` die **aggregierte** Gesamt-DB-Speichergröße. Keine Datei wurde exportiert, kein Dump/Backup erstellt, keine Rohdaten/Geheimnisse/absoluten Pfade ausgegeben, keine Live-Datei geändert.

[**IONOS-Preflight Run #38267570599**](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38267570599): **SUCCESS**, verifizierte Domain `orgavela.com`, SSH/WP-CLI, UOP Alpha.2; WordPress-Filesize **100–500 MiB**, SQL-Datengröße **unter 25 MiB**, freier Webspace **über 5 GiB**. Alle fünf benötigten Kommandozeilenwerkzeuge verfügbar, DB-Speichermetadaten verfügbar. Die Daten sind **Größenklassen**, keine tatsächliche Sicherungsgröße. `ORGAVELA_BACKUP_PROVIDER_SNAPSHOTS=not-verifiable-over-shell`, `ORGAVELA_BACKUP_EXPORT_EXECUTED=no`, `ORGAVELA_BACKUP_RESTORE_EXECUTED=no` ausdrücklich belegt.

**Unabhängiger technischer Restore-Smoketest ohne reale Daten:** Neuer [synthetischer MariaDB-11.8-CI-Workflow](../../.github/workflows/orgavela-restore-rehearsal.yml) mit [synthetischem Testskript](../../scripts/ci/orgavela-synthetic-restore.sh); erstellt auf einem isolierten GitHub-Runner eine **neue synthetische Datenbank** mit zwei eigenen Platzhalterdatensätzen und Dateien, erstellt `mariadb-dump` + `tar.gz`, verifiziert SHA-256, spielt ausschließlich in einer **anderen synthetischen DB** und in einem eigenen temporären Verzeichnis zurück, prüft Zeilen-/Inhaltsvergleich und Index, entfernt temporäre Daten. **Run [#38267901371](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38267901371) SUCCESS**, `ORGAVELA_RESTORE_DRILL=synthetic-success`, `...DB_AND_FILES_SHA256=verified`, `...INDEX=verified`, `...LIVE_BACKUP_USED=no`, `...LIVE_RESTORE=no`. Keinerlei IONOS-Verbindungsdaten/Produktionsdaten wurden in diesem Test verwendet.

**Anbieterinformation und Lücken:** Die [IONOS-Webhosting-Hilfe](https://www.ionos.de/hilfe/hosting/dateien-sichern-wiederherstellen/webspace-und-datenbanken-manuell-sichern-backups-erstellen/) dokumentiert allgemein tägliche Systembackups mit **maximal 14 Tagen** Aufbewahrung und empfiehlt eigene getrennte Datenbank- und Webspace-Kopien; welche konkreten Recovery-Punkte **OrgaVela** bereitstellt, ist **nicht aus dem SSH-Hosting-Account prüfbar** und mangels entsprechendem IONOS-Kundenpanelzugriff unbekannt. Der reale **privat gespeicherte, unabhängige OrgaVela-DB+Datei-Snapshot** und der **echte Restore dieses Snapshots in einer abgeschotteten Umgebung** fehlen weiter. Deshalb O03 **TEILWEISE, KEINE formelle Abnahme**. Ausführliche Nachweismatrix/Anleitung: [`W0_O03_BACKUP_RECOVERY_RUNBOOK.md`](W0_O03_BACKUP_RECOVERY_RUNBOOK.md), zusammen mit dem bisherigen [W0-Baseline-Runbook](W0_BASELINE_AND_RECOVERY.md).

**Unveränderte Stop-Gates:** Ohne nachweisbares privates Backup plus vollständigen isolierten Restore **keine** produktive UOP-Migration, Deployment-Upgrade, Restore am Live-System, echte Teilnehmerdaten oder automatisierte Gastanmeldung; keine zusätzlichen kostenpflichtigen Verträge. O01 und O06 weiter offen; O02 ist live geprüft, formelles M1-Signoff durch PR-/Betriebskriterien gesperrt. **V1-Zählung unverändert:** 62/71 historische Implementierung, M0–M7 **0/8** formal, P4 **0/4**, Release **0/18**, PRs gemergt **0/7**. Nächste O03-Phase: echte IONOS-Vertrags-Backup-Punkte nachweisen, geschützten Offsite-Speicher und vollständige konsistente private DB+Datei-Kopie bereitstellen, erst danach eine private echte Restore-Probe ausführen.


### W0/O03 – freizugebender Echtbackup-Transfer über Public-Key-Verschlüsselung und privaten Restore (Version 2.5, 12.10.2026)

**Betreiberfrage:** „okay wie können wir das angehen?“ nach [erfolgreicher OrgaVela-Backup-Preflight #38267570599](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38267570599) und [isolierter *synthetischer* Restore-Übung #38267901371](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38267901371). Das öffentliche Repository `janjrre/Projekt-Wordpress-Plugin` ist **kein vertraulicher Backup-Speicher**; selbst GitHub-Actions-Artefakte sind von Repo-Lesenden abrufbar und haben endliche Aufbewahrung (public max. 90 Tage, [GitHub Docs](https://docs.github.com/en/repositories/managing-your-repositorys-settings-and-features/enabling-features-for-a-repository/managing-github-actions-settings-for-a-repository)). Deshalb **kein unverschlüsselter echter WordPress-/DB-Export in Repository, CI-Artefakte, Logs oder Chat**.

**Verbindliche sichere Reihenfolge:** [O03-Sicherungsrunbook Abschnitt 7](W0_O03_BACKUP_RECOVERY_RUNBOOK.md). (G0) Betreiber wählt privaten **externen dauerhaften Speicherort** (empfohlen: eigener Rechner + externe SSD; alternativ vorhandener privater Cloudspeicher) und erstellt lokales **Public-/Private-Key-Schlüsselpaar**; private Schlüssel und Wiederherstellungskopie verlassen das Betreibergerät nicht. (G1) Ein klar begrenzter, gesondert reviewter GitHub-Backup-Workflow darf die bereits bestätigten **OrgaVela-only**-IONOS-SSH-Secrets nur für einen aktuellen konsistenten Datei+DB-Read verwenden, Daten **vor** dem einzigen temporären GitHub-Actions-Artefakt mit dem **öffentlichen** Empfängerschlüssel verschlüsseln und nur `*.age` plus minimal bereinigte Statusmeldungen bereitstellen. Zuerst Verschlüsselungs-/Fehlerpfad/Artefakt testweise mit ausschließlich synthetischen Daten validieren. (G2) Betreiber speichert die verschlüsselte Sicherung unabhängig von IONOS/GitHub dauerhaft, testet die lokale Entschlüsselung und SHA-256-/Archiv-/DB-Vollständigkeit. IONOS-Vertrags-Restorepunkte zusätzlich im authentifizierten IONOS-Konto nur sichten. (G3) **Genau diesen echten Snapshott** auf **privater, netzwerkisolierter** WordPress/MariaDB-Testumgebung restaurieren, vor Import Produktsystem-Identität hart ausschließen, E-Mail/Webhooks/Cron und externen Datenverkehr unterbinden, WP/UOP-29-Tabellen-/Index-/Org-Status und Dateikonsistenz prüfen. (G4) nur nach **tatsächlich** grünem echtem Restore: O03-/B04-Abnahme und Recovery-Verantwortung signieren; später periodische Sicherungen separat planen.

**Zu diesem Zeitpunkt keine verdeckte Freigabe oder Datensicherung:** Ein echter Snapshot erfordert vorher vom Betreiber **den langfristigen privaten Speicherort und einen lokalen öffentlichen Empfängerschlüssel**. Diese nicht vertraulichen Einstellungen sind noch nicht bekannt. **Daher kein** Backup-Workflow mit echten Daten erzeugt oder gestartet; keine Speicherung im GitHub-Actions-Artefakt, keine IONOS-Datei-/DB-Schreibaktion und kein Restore. O03 bleibt `partial`, B04/R03 gesperrt, O01/O06 weitere W0-Kriterien offen. Die formal gezählten M0–M7 **0/8**, P4 **0/4**, R01–R18 **0/18**, PRs gemergt **0/7**, historische Entwicklung **62/71** sind unverändert. Nach Entscheidung G0 kann das sichere Backup-Skript für den ersten **synthetischen** Ende-zu-Ende-Verschlüsselungstest implementiert werden. Bei tatsächlichem Live-Backup getrennte Operator-/Restore-Gates beachten.



### W0/O03 – privater Google-Drive-Ordner erstellt, verschlüsselter CI-Selbsttest erfolgreich (Version 2.6, 12.10.2026)

**Betreiberentscheidung:** Google Drive ist ausdrücklich als dauerhaftes und vom Betreiber-PC unabhängiges Ziel für regelmäßige **verschlüsselte** OrgaVela-Backups gewählt. Über den bereits verbundenen Google-Drive-Connector wurde unter **Meine Ablage** der private Ordner **„OrgaVela – verschlüsselte Backups“** angelegt und **unmittelbar per API-Metadaten** geprüft: `shared=false`, Berechtigungsliste enthält nur den **Eigentümer**. Die genaue persönliche Drive-Ordner-ID ist absichtlich **nicht** im öffentlichen GitHub-Repository hinterlegt. OrgaVela bleibt von Dreamloud und anderen Hosting-Verträgen getrennt; **kein** externes neues kostenpflichtiges Abonnement.

**Separate authentifizierte Verbindungen unterscheiden:** Die Google-Drive-Verbindung dieser ChatGPT-Unterhaltung erlaubt das Erstellen/Lesen des Ordners **hier**, liefert aber **keine** wiederverwendbaren Google-OAuth-Refresh-Tokens an GitHub Actions. Der vom Betreiber dauerhaft geplante automatisierte GitHub-Upload benötigt deshalb eine **separate OAuth-Freigabe durch den Betreiber** im geschützten Environment `orgavela-backup-drive`; dieses ist **getrennt** von `orgavela-ionos`. Der aktuelle Workflow liest **keine** IONOS-Secrets und kann ausschließlich synthetische Daten auf Google Drive übertragen.

**Tatsächliche GitHub-Implementierung:** Neuer [Workflow `orgavela-google-drive-connection.yml`](../../.github/workflows/orgavela-google-drive-connection.yml), Commit `33bafd9e306c8c01e642413474dfbebefe4db2b1`: lokaler `age`-Encrypt-/Decrypt-Selbsttest mit **nur synthetischem Text**, sowie **nur manuell** auslösbarer Operation `upload-synthetic` (synthetische Testdatei → `age`-Public-Key-Verschlüsselung → `rclone` über getrennten Drive-OAuth-Konfigurationssecret in einen per `root_folder_id` fixierten privaten Ordner → verifizierter Dateiname). Secret- und Private-Key-Werte dürfen nie im öffentlichen Repo oder GitHub-Logs auftauchen. Der [Selftest-Run **#38294515636**](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38294515636) ist **SUCCESS**. Der separate echte Drive-`upload-synthetic`-Zweig ist **noch nicht ausgeführt**, weil keine betreiberautorisierte Google-GitHub-OAuth-Konfiguration und kein öffentlicher `age1...`-Empfängerschlüssel für diesen Workflow eingerichtet sind. **Es wurde kein reales OrgaVela-Backup erzeugt, kopiert oder hochgeladen.**

**Sicherheitsentscheidung / gesonderte Betreiberhandlung:** Der bereits durch den ChatGPT-Drive-Connector angelegte Ordner ist nicht von rclone erstellt; der bei Google empfohlene `drive.file`-Scope kann diesen Ordner gewöhnlich **nicht** sehen. Um ihn über `rclone` zu benutzen, benötigt der Benutzer möglicherweise eine **`drive`-Vollzugriffsfreigabe**, die im Google-OAuth-Dialog gesondert sichtbar bestätigt werden muss. `root_folder_id` beschränkt das Ziel im Skript, **nicht** Googles tatsächliche Berechtigung. Für striktes Least Privilege kann alternativ ein separater nur für Backups verwendeter Google-Account bzw. neuer über rclone-`drive.file` selbst angelegter Ordner eingerichtet werden. Betreiber entscheidet die OAuth-Berechtigung in der Google-Oberfläche und richtet seinen OAuth-Client/Token **ausschließlich** in den GitHub-Environment-Secrets ein. Wichtig: Eigene OAuth-Clients im externen Google-Testmodus erhalten typischerweise nur kurzlebige, nach sieben Tagen ablaufende Refresh-Tokens; unbeaufsichtigte Backups benötigen eine entsprechend regelkonforme Produktions-/Freigabeeinrichtung. Keine kostenpflichtige Google-Cloud-Abrechnung ohne separate Genehmigung.

**Operator-Anleitung:** [`ORGAVELA_GOOGLE_DRIVE_BACKUP_SETUP.md`](ORGAVELA_GOOGLE_DRIVE_BACKUP_SETUP.md) dokumentiert lokales `age`-Schlüsselpaar (**privater Schlüssel verbleibt offline**), Google-Desktop-OAuth-/`rclone`-Autorisierung, eigenständiges GitHub-Environment mit Secret `ORGAVELA_RCLONE_CONFIG`, nicht geheime Environment-Variable `ORGAVELA_BACKUP_AGE_RECIPIENT`, `main`-Schutz und den manuellen verschlüsselten Canary. Erst nach **erfolgreichem autorisierten Drive-Canary** darf ein von IONOS-Shell- und Google-Drive-Geheimnissen getrenntes, reviewed End-to-End-Backup und anschließend GitHub-Schedule eingerichtet werden.

**Abnahme- und Zählstand:** Neuer privater Google-Drive-Zielordner und **lokaler Kryptoselbsttest** sind verifiziert; tatsächliche Verbindung **GitHub Actions → Google Drive** und verschlüsselter Testupload **noch offen**, privater DB+Datei-Live-Snapshot und echter isolierter Restore **weiter offen**. O03 `partial`, B04/R03 nicht akzeptiert. Keine produktive OrgaVela-Datei- oder Datenbankänderung, keine E-Mail-/Cron-/Plugin-Upgrades oder Dreamloud-Aktion. Unveränderte V1-Zählung: **62/71** Coding, **0/8** Milestones, **0/4** P4, **0/18** finale Release-Gates, **0/7** PRs gemergt; aktive Live-Version **0.1.0-alpha.2**. **Nächster überprüfbarer Schritt:** Betreiber richtet in Google einen OAuth-Desktop-Client und in GitHub `orgavela-backup-drive` die nur dort sichtbaren Secrets/öffentlichen Empfängerschlüssel ein → einmaliger `upload-synthetic`-Live-Google-Test.


### W0/O03 – CircleCI Free aus Dreamloud als separater Runner bewertet; GitHub-Backups technisch möglich, aber nicht per Git-Commit (Masterplan Version 2.7, 13.10.2026)

**Neuer Betreiberbefund:** Betreiber meldet aktuell ausgeschöpftes GitHub-Actions-Laufzeit-/Budgetkontingent und verweist auf die bereits im eigenständigen Dreamloud-Portal getestete Alternative. **Nur lesende Prüfung** von `janjrre/dreamloud-portal` ergab: Der GitHub-gehostete Runner war dort durch eine nach Betreiber-Screenshot explizite 100%-Budget-Stop-Usage-Einstellung blockiert (`runner_id=0`). [Dreamloud-Status](https://github.com/janjrre/dreamloud-portal/blob/main/docs/operations/CURRENT_STATE.md#4e-github-budgetstopp-statt-duplicati-fehler-bestätigt-12102026-2118-mesz). Daraufhin wurde **CircleCI Free** für das **private** Dreamloud-Repo mit extern grün gemeldeten Offline-Jobs [#3](https://circleci.com/gh/janjrre/dreamloud-portal/3), [#4](https://circleci.com/gh/janjrre/dreamloud-portal/4), [#5](https://circleci.com/gh/janjrre/dreamloud-portal/5), [#8](https://circleci.com/gh/janjrre/dreamloud-portal/8) und [#12](https://circleci.com/gh/janjrre/dreamloud-portal/12) real erprobt; [#7](https://circleci.com/gh/janjrre/dreamloud-portal/7) testete die echte Linux-Duplicati-Binärdatei **nur mit synthetischer Offline-Sicherung**. Die GitHub-Commit-Status-API bestätigte für die Hauptbranch-SHA von #8 und die Testbranch-SHA von #12 `ci/circleci: offline-php-quality = success`. [Dreamloud-Pilot-Runbook](https://github.com/janjrre/dreamloud-portal/blob/main/docs/operations/CIRCLECI_FREE_PILOT_2026-10-12.md). **Kein Dreamloud-Context/Secret/IONOS-Account** ist für UOP freigegeben; der Dreamloud-Google-Drive-E2E aus [Draft PR #441](https://github.com/janjrre/dreamloud-portal/pull/441) ist **noch nicht als echter Cloud-Lauf bestanden**.

**Unterscheidung Ausführung vs. Speicherung:** CircleCI Free ist ein alternativer **Runner**, GitHub Releases oder Google Drive sind mögliche **Ablagen**. Für **Git-Commits von OrgaVela-DB-/Dateiarchiven** gilt das harte **100-MiB-Limit je Gitdatei**, ständiges Anwachsen der Historie und GitHubs ausdrücklicher Hinweis, Git sei kein Backuptool – **nicht zulässig** als kontinuierliche Hauptsicherung. [GitHub Docs](https://docs.github.com/de/repositories/working-with-files/managing-large-files/about-large-files-on-github). GitHub-Actions-Artefakte sind als einziges Backup wegen endlicher öffentlicher `<=90-Tage`-Retention ebenfalls ungeeignet. Dagegen erlauben GitHub **Releases Assets unter 2 GiB je Datei**, daher sind nach Vorabverschlüsselung **Backups als Assets einer eigenständigen privaten Backup-Repository-Release technisch denkbar** – auch Upload via CircleCI/GitHub API, unabhängig von GitHub Actions. [GitHub Releases Docs](https://docs.github.com/en/repositories/releasing-projects-on-github/about-releases). Backup-Repo/GitHub-Konto bleibt derselbe Anbieter und Sicherheits-/Ausfallbereich wie UOP-Source; daher **nicht** erste unabhängige Offsite-Kopie. Bestehender privater Google-Drive-Ordner bleibt **empfohlener primärer Offsite-Speicher**, optional zusätzliche verschlüsselte GitHub-Releases-Kopie, nur nach Speicher-/Retention-/Sicherheitsprüfung.

**GitHub-Actions-Billing-Nuance:** Die GitHub API bestätigt `janjrre/Projekt-Wordpress-Plugin` ist **öffentlich** (`visibility=public`); Standard-GitHub-hosted Runner sind bei öffentlichen Repos laut [GitHub Billing](https://docs.github.com/de/billing/concepts/product-billing/github-actions) grundsätzlich **kostenfrei**, anders als beim privaten Dreamloud. Allerdings kann eine produktweite [Stop-Usage-Budgetgrenze](https://docs.github.com/en/billing/how-tos/set-up-budgets) alle GitHub-hosted Runner des Kontos blockieren. Die Live-Laufbarkeit von OrgaVela wurde **nicht** durch erneuten Actions-Trigger geprüft und wird **nicht** behauptet. Auch CircleCI Free besitzt Credits-/Transferlimits; das Projekt OrgaVela ist dort **nicht** als verbunden oder freigegeben verifiziert ([CircleCI Credit-Doku](https://circleci.com/docs/guides/plans-pricing/credits/)).

**Beschlossene Priorität für W0/O03:** Neben bereits angelegtem privaten Google-Drive-Backupziel nun **zuerst eigenständiges CircleCI-Free-Projekt für OrgaVela verifizieren** → minimale Offline-/synthetische CI ohne OrgaVela-/Google-Secret testen → Betreiberseitige restriktive **OrgaVela-spezifische** CircleCI-Contexts mit Branch- und Projektbindung prüfen; keine Dreamloud- oder Github-Environment-Secrets automatisch übernehmen → Betreiber-Public-Key-Verschlüsselung und verschlüsselten synthetischen Drive-Canary mit Readback prüfen → erst nach bestandenem E2E echte OrgaVela-Datei- und DB-Sicherung (100–500 MiB WP-Bestand, DB unter 25 MiB im [Live-Preflight #38267570599](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38267570599)) verarbeiten → verschlüsselten Offsite-Snapshot vollständig downloaden/verifizieren → echtes Backup isoliert zurückspielen und auf 29 UOP-Tabellen, Integrität und Versionsgleichheit prüfen → O03/B04/R03 abnehmen. Optional ein zusätzlicher verschlüsselter GitHub-Release-Asset in **separatem privaten** Backup-Repo erst nach separater Betreiberentscheidung und eingeschränkter `contents:write`-Berechtigung, niemals als Anhang/Commit in öffentliche Quell-Repo-Historie.

**Aktuelle Umsetzung:** Nur verifizierte Anbieter-/Projektanalyse und neues [`W0_O03_CIRCLECI_GITHUB_BACKUP_ASSESSMENT_2026-10-13.md`](W0_O03_CIRCLECI_GITHUB_BACKUP_ASSESSMENT_2026-10-13.md). **Kein CircleCI-UOP-Projekt eingerichtet, kein neuer CircleCI-Job ausgeführt, kein GitHub-Actions-Runner ausgelöst, kein Live-Backup, kein Google-/GitHub-Upload oder Restore, kein Produktiv-/Dreamloud-Zugriff.** O03 bleibt `partial`; M0–M7 formell **0/8**, P4 **0/4**, Release-Gates **0/18**, historische Coding-Pakete **62/71**, PRs gemergt **0/7**. Eigener Kreis der Kosten-/Storage-/Context-Sicherheitsgates bleibt aktiv.


### W0/O03 – OrgaVela-eigener CircleCI-Free-Integrationspilot im Draft-PR #17 (Masterplan-Version 2.8, 13.10.2026)

**Neuer Auftrag:** Betreiber bestätigt „ist verbunden“ zum in [W0-CircleCI-Analyse](W0_O03_CIRCLECI_GITHUB_BACKUP_ASSESSMENT_2026-10-13.md) empfohlenen **eigenständigen** CircleCI-Free-Projekt für `janjrre/Projekt-Wordpress-Plugin`. Diese Betreiberbestätigung ist **nicht** bereits ein unabhängiger CircleCI-Jobnachweis. Ziel: kleinster verifizierbarer geheimnisfreier Pilot vor irgendeinem Google-Drive- oder IONOS-Secret.

**Tatsächlich angelegt:** Branch [`ci/circleci-free-pilot-o03`](https://github.com/janjrre/Projekt-Wordpress-Plugin/tree/ci/circleci-free-pilot-o03) mit neuer, ausschließlich branchgebundener [`.circleci/config.yml`](https://github.com/janjrre/Projekt-Wordpress-Plugin/blob/ci/circleci-free-pilot-o03/.circleci/config.yml), Commit [`d14069c8302da177ee93458950ecd82ed7726655`](https://github.com/janjrre/Projekt-Wordpress-Plugin/commit/d14069c8302da177ee93458950ecd82ed7726655); [**Draft-PR #17**](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/17) gegen main, **kein Merge**. `resource_class: small` + `cimg/python:3.12`; ein einzelner `offline-connection-smoke`-Job ohne Upload/Caches/Cron/IONOS/Google/Secrets/Deploy. Prüft nur OrgaVela-Repo und Pilotbranch, vorhandene `scripts/ci/*.sh` via `bash -n`, Pflichtdokumentation, V1-Fortschrittsinvarianten und Abwesenheit der bekannten Produktions-/Drive-Credentials. Keine Änderung des bestehenden GitHub-Release- oder Live-Deploy-Prozesses, keine Dreamloud-Aktion. Erläuterungen: [**O03-CircleCI-Pilot-Runbook**](W0_O03_CIRCLECI_FREE_PILOT_2026-10-13.md).

**Unabhängige CI-Nachweise:** Zum Prüfmoment lieferte `GET /repos/janjrre/Projekt-Wordpress-Plugin/commits/d14069c8302da177ee93458950ecd82ed7726655/status` **`state=pending`, `total_count=0`, `statuses=[]`**; über `check-runs` ebenfalls **keine** CircleCI-Checks. Daraus folgt: **CircleCI-Job bislang nicht beobachtet, nicht grün und nicht formal angenommen.** Der Provider kann noch starten, aber ohne echten Status darf dies nicht behauptet werden. Betreiber/Integration müssen bei anhaltend leerem Status in [CircleCI Projects](https://app.circleci.com/projects) und Pipeline Trigger/Config Path/Free-Credits prüfen, ob tatsächlich ein **UOP-Projekt** und ein Branch-Push-Trigger aktiv sind. Der Draft-PR bleibt zur manuellen Review bestehen, **kein** automatischer Merge nur um CircleCI zu aktivieren.

**Nächster Gate:** Positiver CircleCI-Providerstatus und idealerweise tatsächliche Step-Logs/Run-ID zum exakten Pilot-SHA `d14069c8` → erst danach Context-/Branch-/Quota-Sicherheitsprüfung → allein synthetisches verschlüsseltes Google-Drive-Readback → echter OrgaVela-DB+Datei-Offsite-Snapshot und isolierter Restore vor O03/B04/R03-Abnahme. Keine fremden Dreamloud-Secrets; GitHub-Actions-Budgetobergrenze nicht erhöhen, keine kostenpflichtige Freigabe. Bis dahin O03 **partial**, M0–M7 **0/8**, P4 **0/4**, R01–R18 **0/18**, historische Coding-Pakete **62/71**; sieben bestehende PRs plus neuer CI-Draft #17, **0** Merges.


### W0/O03 – CircleCI-Konfigurationsabruf für Pipelines #8/#9 scheitert vor Workflowstart (Masterplan v2.9, 13.10.2026)

**Neuer tatsächlicher Betreiberbeleg:** Mobile-CircleCI-Pipeline-Übersicht zeigt **#8** und **#9** mit rotem, im Bild abgeschnittenem `Could not fetch configuration: No co...` und `No workflows for this pipeline`. Damit sind Auslöser/Pipeline-Ereignisse beim OrgaVela-Projekt angekommen, aber **nicht** Konfiguration geladen oder Offlinejob erfolgreich gestartet. Der exakte Fehlerrest sowie die angesprochenen Source-/Checkout-Branches sind im Screenshot nicht lesbar; daher die mögliche Branchursache ausdrücklich als Diagnose, nicht als sicher nachgewiesen behandeln.

**Unabhängiger GitHub-Befund:** `.circleci/config.yml` auf `main` bei erneuter GitHub-API-Inspektion **404 / nicht vorhanden**; auf eigenem Testbranch `ci/circleci-free-pilot-o03` dagegen **vorhanden** (`version: 2.1`, nur ein branchgefilterter, geheimnisfreier `offline-connection-smoke`, Commit `d14069c8`, [Draft-PR #17](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/17)). CircleCI-Dokumentation verlangt eine reale Konfigurationsdatei am vom Trigger benutzten Konfigurationspfad/-Branch, Branchfilter allein vermeiden keinen Config-Fetch-Fehler. [Offizielle Pipeline-Dokumentation](https://circleci.com/docs/guides/orchestrate/pipelines/) · [Trigger/Config-Branch-Auswahl](https://github.com/circleci/circleci-docs/blob/main/docs/guides/modules/orchestrate/pages/triggers-overview.adoc) · [Betriebliches Vorgehen](W0_O03_CIRCLECI_FREE_PILOT_2026-10-13.md#6-neuer-live-befund-aus-betreiber-screenshot-pipeline-89-existieren-config-fetch-schlägt-fehl-13102026).

**Verbindlicher nächster Gate-Test ohne automatische PR-Merges:** Benutzer öffnet CircleCI-Pipeline **#9**, kontrolliert vollständigen Fehler und den tatsächlich referenzierten Branch. Anschließend genau **einen** **manuellen** `Trigger Pipeline`-Lauf mit **Config source branch `ci/circleci-free-pilot-o03`** und (bei GitHub App) demselben Checkout-Branch auslösen; Config-Pfad `.circleci/config.yml`. Vorher Free-Credits prüfen; **keine Secrets/Contexts/Live-/Cloudjobs**. Sollte CircleCI diesen Branch nicht zulassen, `Project Settings → Project Setup / Pipelines / Config source` und VCS-Berechtigungen prüfen. Erst ein echter externer `offline-connection-smoke=success` für den Testcommit erfüllt O03-CI-01; die früheren rot abgebrochenen Pipelines ändern ihren damaligen Befund nicht. Der Nutzer hat **kein** Merge von Draft-PR #17 freigegeben, daher keine eigenmächtige Übernahme der CI-Datei nach `main` und auch kein automatisches GitHub-Action-Rerun.

**Abnahme-/Sicherheitslage:** Kein neuer erfolgreicher CircleCI-Job oder Google-Drive-/IONOS-Backup und kein echter Offline-WordPress-Restore nachgewiesen; O03 bleibt `partial`, B04 und R03 offen. Zählung **62/71** Coding, **0/8** M0–M7-Abnahmen, **0/4** P4, **0/18** Release-Gates, **0** gemergte PRs (7 Alt-PRs + Draft #17). Keine neuen kostenpflichtigen Verträge, produktiven Änderungen oder Dreamloud-Aktionen.


### W0/O03 – erster OrgaVela-CircleCI-Providerjob bestanden nach Behebung des Heredoc-Parserfehlers (Masterplan v2.10, 13.10.2026)

**Betreiberbefund:** CircleCI-Detail-Screenshot aus Pipeline **#16** auf `ci/circleci-free-pilot-o03` und Commit `d14069c8` zeigt, dass CircleCI die OrgaVela-`.circleci/config.yml` inzwischen **findet**, den Job jedoch schon beim Parsen mit `Unclosed '<<' tag … python - <<'PY'` abwies. Die vorherigen #8/#9-Konfigurationsabruf-Fehler sind damit nicht mehr der aktuelle Blocker. Ursächlich war unser in YAML eingebetteter Python-Heredoc, **nicht** ein Fehlverhalten von IONOS, GitHub Actions oder Google.

**Tatsächlich auf dem separaten CI-Branch umgesetzt:** Unveränderte rein lokale Fortschrittsschutzprüfung in das neue [`scripts/ci/orgavela-circleci-offline-smoke.py`](https://github.com/janjrre/Projekt-Wordpress-Plugin/blob/ci/circleci-free-pilot-o03/scripts/ci/orgavela-circleci-offline-smoke.py) ausgelagert, Commit [`da1e195c`](https://github.com/janjrre/Projekt-Wordpress-Plugin/commit/da1e195c8ba1e76b2e9502cb61c715d9a361772f). YAML ruft jetzt nur `python scripts/ci/orgavela-circleci-offline-smoke.py` auf – **kein `<<` mehr** –, Commit [`c478b4c9e74fa8bc73db16945ff9cb2a4270d2da`](https://github.com/janjrre/Projekt-Wordpress-Plugin/commit/c478b4c9e74fa8bc73db16945ff9cb2a4270d2da). [**Draft PR #17**](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/17) bleibt explizit **unmerged** und hat den neuen Head-SHA. Scope unverändert: nur `cimg/python:3.12`-`small`-Offlinejob auf genau einem Testbranch, keine Secrets, IONOS, Drive, WordPress-Live-Zugriffe, Artefakte, Backups, Cron, GitHub-hosted Runner oder Dreamloud.

**Neuer tatsächlich verifizierter CI-Erfolg:** Externe CircleCI-Commitstatusmeldung über die **GitHub REST API** auf exakt `c478b4c9e74fa8bc73db16945ff9cb2a4270d2da` erneut geprüft: `state=success`, `total_count=1`, Providerkontext **`ci/circleci: offline-connection-smoke = success`**, Beschreibung „Your tests passed on CircleCI!“, Link [**CircleCI Build #2**](https://circleci.com/gh/janjrre/Projekt-Wordpress-Plugin/2). Kein unbestätigter Check oder bloßes „verbunden“-UI: externer Provider meldet echten Offline-Test bestanden. Separate CircleCI-Einzelschrittlogs und Creditverbrauch mangels angebundener CircleCI-Pipeline-/Logs-API noch nicht unabhängig geprüft; Provider-Gesamtstatus reicht für **O03-CI-01 (technischer unabhängiger Integrationspilot)**, **nicht** für vollständiges `O03` oder Release-/Produktionsfreigabe. Der Stand aus v2.9 `kein CircleCI-Job beobachtet` wird durch diesen neuen, konkret referenzierten grünen Commit **für die aktuelle Pilotstufe abgelöst**, bleibt als historischer früherer Befund lesbar.

**Unveränderte weitere O03-Gates:** CircleCI-Projekt-/Branchschutz und verfügbare Free-Credits kontrollieren; erst neue **OrgaVela-eigene** restriktive Contexts/OAuth-Consent und lokal/offline verwahrter privater `age`-Key; nur synthetische komplett **vorverschlüsselte** Canary-Datei im schon privaten OrgaVela-Google-Drive-Ordner hochladen, vollständig rücklesen und Hash prüfen. **Erst nach E2E-Probe** konsistente echte WordPress-Datei+MariaDB-Backups ohne Dreamloud-/GitHub-Secret-Reuse erstellen, verschlüsselt extern verwahren, genau dieses reale Backup in abgeschotteter privater Testinstanz restoren (Mail/Webhooks/Cron blockiert), anschließend O03/B04/R03 und ggf. CI-Merge separat freigeben. Noch **kein** echter OrgaVela-DB-/Dateisnapshot, verschlüsselter Drive-Upload oder echter Restore, keine Ausgabe, keine Produktionsänderung. Formale V1-Kennzahlen unverändert: historische Coding-Pakete **62/71**, M0–M7 **0/8**, P4 **0/4**, R-Gates **0/18**, PR-Merges **0**; 7 bereits offene PRs plus neuer Draft #17.

**Detailnachweis:** [`W0_O03_CIRCLECI_FREE_PILOT_2026-10-13.md` Abschnitt 7](W0_O03_CIRCLECI_FREE_PILOT_2026-10-13.md).


### W0/O03 – verbindlicher Wechsel zu ausschließlich privatem GitHub-Release-Backup neben IONOS (Masterplan v2.11, 13.10.2026)

**Expliziter neuer Betreiberauftrag:** „Ich würde die Backup Variante gerne ändern, ich möchte alle Backups neben den Ionos Sicherungen nur noch in GitHub in einem separaten Repository speichern.“ Ab diesem Datum ist die frühere Google-Drive-Storage-Entscheidung aus Versionen 2.5–2.10 **überholt**: **kein** weiterer Backup-Upload, OAuth-Connect oder CI-Test mit Google Drive; historische Einträge bleiben zur Nachvollziehbarkeit erhalten. Es gelten fortan zwei tatsächliche Sicherungsorte: (1) **IONOS-Vertragssicherungen für den eigenständigen OrgaVela-Hostingvertrag**, deren konkrete vorhandene Wiederherstellungspunkte im IONOS-Konto noch verifiziert werden müssen; (2) **eigenständiges GitHub-Repository mit zwingender Sichtbarkeit `private`**, darin ausschließlich **vorverschlüsselte** GitHub **Release-Assets**, niemals vollständige Datenbank-/WordPress-Archive als normale Git-Commits oder im **öffentlichen** `janjrre/Projekt-Wordpress-Plugin`-Quellrepository. Keine Google-Drive-Daten-Backups. **Dreamloud nicht ändern und keine Zugangsdaten übernehmen**.

**Bevorzugtes neues Repository:** `janjrre/orgavela-backups`; per GitHub `get_repo` **noch nicht vorhanden/zumindest nicht zugreifbar** (404), keine Repo-URL als bereits bestätigten Speicher ausgeben. Der aktuell verbundene GitHub-Connector bietet **keine** `create_repository`-/Admin- oder Secret-Setup-Aktion; der Betreiber muss dieses Repository einmalig unter [GitHub / new](https://github.com/new) selbst als **Private** erstellen und die Sichtbarkeit bestätigen. Kein zahlungspflichtiges Upgrade oder GitHub-Actions-Laufzeitpaket. [Vollständige Setup-/Recovery-Anleitung](W0_O03_GITHUB_PRIVATE_BACKUP_RUNBOOK.md) regelt alle tatsächlichen Abnahmen. Der frühere GitHub-Google-Drive-Workflow [`.github/workflows/orgavela-google-drive-connection.yml`](../../.github/workflows/) wurde aus `main` **gelöscht** (Commit [`58b148cb`](https://github.com/janjrre/Projekt-Wordpress-Plugin/commit/58b148cb2db1f8e9031d2e9a14a05db62b1c5c7a)); altes [Google-Drive-Setup](ORGAVELA_GOOGLE_DRIVE_BACKUP_SETUP.md) als **historisch/nicht mehr ausführen** markiert, nicht stillschweigend reaktivieren. Der früher angelegte Google-Drive-Ordner bleibt ohne weitere Änderung unbenutzt.

**Warum Release-Assets:** [GitHub Releases](https://docs.github.com/en/repositories/releasing-projects-on-github/about-releases) erlaubt bis zu **1000 Assets je Release**, jede Datei **unter 2 GiB**, ohne dokumentiertes Gesamt- oder Bandbreitenlimit; dies ist technisch eine mögliche private verschlüsselte Archivablage und kann **ohne GitHub Actions** per GitHub REST aus **CircleCI Free** bespielt werden. GitHub-Tags/Release-Metadaten sind keine Datenbank-Backups. Ein eigenes Quellrepo-Commitarchiv wird wegen 100-MiB-Git-Dateigrenze und dauerhaftem Historienwachstum **nicht** als Backupspeicher betrieben. GitHub Releases besitzen weder bestätigte WORM-Unveränderlichkeit noch eigenes Backup-SLA; die ausschließliche GitHub+IONOS-Ausfallkorrelation ist vom Betreiber bewusst statt unabhängiger Google-Cloud-Kopie gewählt und muss im Notfallkonzept sichtbar bleiben. Repository-Privatsphäre **ist kein Ersatz** für vor dem Upload angewendete `age`-Verschlüsselung und ein offline verwahrtes **privates** Recovery-Keypair. Eine offline gelagerte **Schlüsselsicherung/Runbook-Kopie** ist erforderlich, auch wenn keine dritte Datenbackupablage gewünscht ist.

**Geänderter technischer Ablauf / harte Schutz-Gates:** (GH-01) Eigenständiges tatsächlich verifiziertes **privates** GitHub-Backuprepo; (GH-02) öffentliche CircleCI-Free-Quellpipeline nur in geprüftem, projekt-/branchbeschränktem Sicherheitskontext, genügend Free-Credits, **kein** Token/Secret auf untrusted Branches; (GH-03) lokal generiertes Betreiberschlüsselpaar `age` (private Schlüssel verbleiben ausschließlich offline), synthetische Kryptoprüfung; (GH-04) Repository-exklusiver, ablaufender fine-grained **GitHub PAT `Contents: Read and write`** im geschützten OrgaVela-CircleCI-Context, *nicht* auf dem öffentlichen Repository, getrennt von IONOS-SSH-Credentials; manueller **synthetischer** Ciphertext-Release-Upload, vollständiger Rückdownload/Hashgleichheit über Release API; (GH-05) Betreiber testet private Entschlüsselung; (GH-06) erst danach echter konsistenter OrgaVela-Datei+MariaDB-Leseexport, vor jeder Veröffentlichung vollständig verschlüsselt, in GitHub Release Assets unter 2 GiB oder verschlüsselten gesplitteten Teilen gespeichert und authentisch rückgelesen; (GH-07) **genau dieses reale Backup** auf isoliertem privaten WordPress/MariaDB-Ziel vollständig wiederhergestellt, mit deaktiviertem Netzwerk-Egress/SMTP/Webhooks/Cron und geprüften 29 UOP-Tabellen/Versionen/Dateien; (GH-08) erst nach Recovery-Beweis tägliche/regelmäßige CircleCI-Free-Planung, Monitoring und **keine automatischen Löschen/Rotation**, bis Betreiber Retention ausdrücklich festlegt. Die zuvor bestandene [UOP CircleCI Free Offline-Integration #2](https://circleci.com/gh/janjrre/Projekt-Wordpress-Plugin/2) bleibt als Runnerbeleg valide, ersetzt aber kein GH-04/GH-06/GH-07-Gate. **IONOS-Vertragssicherungen müssen separat im Betreiberpanel nachgewiesen werden**; allgemeine Anbieterangaben reichen nicht.

**Tatsächlich umgesetzt in diesem Turn:** Entscheidung/Analyse und GitHub-Dokumentation, alte Google-Drive-CI-Datei deaktiviert/entfernt, alte Anleitung historisiert; private Zielrepo-Erstellung und echte CircleCI-Token/Backups **nicht** ausgeführt, weil GitHub-Verbindung Repo-Admin-/Secrets-Aktionen nicht unterstützt und der Betreiber den privaten `age`-Schlüssel erst auf seinem vertrauenswürdigen Gerät erstellen muss. **Keine** echten OrgaVela-/Datenbank-/Teilnehmerdaten exportiert, keine Zusatzkosten, keine Dreamloud- oder Produktionsänderung. **O03 bleibt `partial`, B04/R03 offen**. Unveränderte V1-Zähler: **62/71** historische Codingpakete, **0/8** M0–M7, **0/4** P4, **0/18** Release-Gates und **0** PR-Merges, aktuell live UOP Core **0.1.0-alpha.2**.

### O09/M3-08/10 – Testbereitstellung 06.11.2026

Für die ausstehende manuelle Abnahme ist alternativ eine lokale WordPress-Instanz mit dem unveränderten CI-Artefakt von `4fb50cb` bereitgestellt. Reproduzierbarer Start, SHA-256-Provenienz und Zugriffsbeschränkung: [M3-CI-Workbench](M3_CI_WORKBENCH_2026-11-06.md). Work übernimmt die manuellen Prüfungen; unabhängige Freigabe und Integration bleiben offen. Kein Update von orgavela.com, keine formale Abnahme, kein geänderter V1-Paketumfang.

### O01/O03/O10 – dauerhaftes Testziel orgavela.com, Betreiberfreigabe 06.11.2026

orgavela.com ist das verbindliche dauerhaft zugriffsgeschützte Testziel. Reihenfolge: echter konsistenter Datei+DB-Snapshot und separater Real-Restore → Hostschutz inklusive REST/Assets und Work-Zugang → synthetischer Testbetrieb ohne reale Nebenwirkungen → manueller CI-Paket-Testdeploy mit Rollback → M3-Abnahme. [Bestand, neue Main-Audits, technisch unvollständiger O03-Collector und nächste Arbeit](ORGAVELA_PERMANENT_TEST_ENVIRONMENT.md). Einrichtungsbeginn bestätigt, noch keine Websiteänderung und keine neue formale O-/M-/P4-/R-Abnahme.

### O03/PGP05 – ausführbare Capture-/Recoveryfolge 06.11.2026

[PR64](https://github.com/orgavela/Orgavela-Plugin/pull/64) auf2d031a4 implementiert bounded SSH-Transport, separate Source-/Ciphertext-Publisher-Einstiege und private Restorewerkzeuge;27 Provider-Regressionen grün und lokaler verschlüsselter synthetischer WP/MariaDB-Restore bestanden. Abhängig von PR36-Parentkette. Vor echtem Sicherungs-/Restorelauf fehlen Review/Integration, getrennte vertrauenswürdige Runneranbindung und privater Betreiber-Restorehost. O01-Zugangsschutz/O10/M3-Upload bleiben davon abhängig; keine neue formale Abnahme.

### O03 – private temporäre CI-Restorevariante,06.11.2026

Als Alternative zum lokalen Windows-/WSL-Host wird ein kurzlebiger Linuxrunner im privaten Backuprepo geprüft. [Ephemerer Testempfänger, Snapshotgleichheit und offene Kosten-/Orchestrierungsprüfungen](O03_EPHEMERAL_CI_RESTORE_2026-11-06.md). Eigentümerkey bleibt außerhalb CI; separater persönlicher Recoverynachweis unverändert erforderlich. Vorschlag, noch kein neuer produktiver Workflow/Restorebeleg oder formaler Fortschritt.
