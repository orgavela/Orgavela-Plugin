# UOP Core / OrgaVela – aktueller V1-Fortschrittsbericht

**Stand:** 12.10.2026, initiale V1-Baseline nach Liveprüfung. **V1-Status: NICHT ABGENOMMEN.**  
**Normativer Masterplan:** [V1_ACCEPTANCE_PLAN.md](../operations/V1_ACCEPTANCE_PLAN.md) · **Verbindliche Fortschrittsdaten:** [V1_PROGRESS.json](../operations/V1_PROGRESS.json) · **Zentrales Abnahme-Issue:** [#15](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/15) · **Ergänzende Roadmap:** [V1_MASTER_PLAN.md](V1_MASTER_PLAN.md) · **Abnahmematrix:** [V1_ACCEPTANCE_MATRIX.md](V1_ACCEPTANCE_MATRIX.md) · **Arbeitsjournal:** [ASSISTANT_WORK_LOG.md](../operations/ASSISTANT_WORK_LOG.md).

## Aktuelle Welle: W0 – Bestands- und Sicherheitsabnahme

**Stand 12.10.2026: IN ARBEIT, noch nicht abgenommen.** [W0-Live-/Restore-Bericht](../operations/W0_BASELINE_AND_RECOVERY.md) · [Issue #16](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/16) · [verbindliche maschinenlesbare Messwerte](../operations/V1_PROGRESS.json).

| Paket | Stand nach aktuellem Live-Abgleich | Nächster belastbarer Nachweis |
| --- | --- | --- |
| **O01 – OrgaVela-Isolation** | HTTPS/SFTP `/public` erfolgreich, Konto mit breiterem Webspace-Zugriff | engere IONOS-Rechte und begrenzter Negativtest |
| **O02 – M1-08 SQL-Schemahealth** | Plugin aktiv; DB-/Datenversion 2/2; keine physische Tabellen-/Indexkontrolle | bereinigter WordPress-Site-Health-/SQL-Strukturbeleg |
| **O03 – Backup/Restore** | Sicherungsverfahren anhand offizieller IONOS-Anleitungen spezifiziert; kein echtes Backup verifiziert | IONOS-Backupstände, privater DB-/Dateien-Export, isolierter Restore |
| **O06 – Staging-Datenschutz** | WordPress-Marker `production`; `discourage_search_engines=false`; keine Live-Änderung | öffentliche Produktseite vs. geschützter UOP-Testbereich entscheiden |
| **Parallel O09 – CI-Fehler** | PR #11 HEAD `7892a258`; Push Foundation **8/8 grün**, PR Foundation **7/8 rot** (PHP8.4/MariaDB 10.11 Aktivierungsnavigation), HTTPS-Playwright **rot** (Caregiver-Login-Timeout) | [Push](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38248031834) / [PR Foundation](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38248040810) / [HTTPS Browser](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38248040695) – weiterhin Releaseblocker |

**Gesperrt bis W0-Belegen:** keine neue IONOS-DB-Migration, keine echten Teilnehmerdaten, kein ungeprüfter WP-Cron-/Mailer-Effekt, kein produktives UOP-Beta-Opt-in. Das aktuelle Alpha bleibt unverändert.

## 1. Übersicht auf einen Blick

| Zählbare Achse | Ist / Soll | Interpretation |
| --- | --- | --- |
| **Technisch implementierte normative Pakete** | **62 / 71** M0–M7-Pakete, gemäß PR-Belegen | Das ist nur ein Code-Umsetzungsindikator, **kein** V1-Gesamtfortschritt; M6-10 und M7-Gates fehlen. |
| **Formale V1-Meilensteinabnahmen** | **0 / 8** (M0–M7) | Code ist teilweise/weitgehend vorhanden, aber kein Meilenstein final gegen integrierte V1 akzeptiert. |
| **Formale Release-Gates R01–R18** | **0 / 18 grün** | Kein finaler RC-/Stable-Nachweis, trotz erfolgreicher Alpha-Aktivierung. |
| **Offene gestapelte PRs** | **7 / 7 offen**, **0 / 7 gemergt** | [#1](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/1) bis [#6](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/6) und [#11](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/11). Draft: #2, #3, #6, #11. |
| **M6-10-Funktions-/Betriebspakete** | **P1–P3 technisch historisch bearbeitet**, P4 teilimplementiert, P5–P6 offen | Eine durchgehende IONOS-Betriebsabnahme auf V1-Kandidat fehlt. |
| **Minderjährigen-Pakete P4-01…04** | **0 / 4 formal abgeschlossen** | [#7](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/7), [#8](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/8), [#9](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/9), [#10](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/10) offen. |
| **M7-Releasepakete** | **0 / 8 formal abgeschlossen** | Frühere Teilprüfungen sind Ausgangspunkt, nicht abschließende M7-Abnahme. |
| **OrgaVela-Testbetrieb** | **Installation + Aktivierung + DB-Versionen 2/2 bestätigt** | Site Health physische Tabellen, Cron, Mail, Backup/Restore, Noindex weiter offen. |
| **Jüngste CI PR #11** | **Älterer Stand mit roten PR-Checks, neuer HEAD `7892a258` in Prüfung** | Noch keine vollständig grüne aktuelle Pflichtmatrix. |
| **Formale Stable-Freigabe** | **0 / 1** | V1 bleibt gesperrt, bis R01–R08 vollständig nachgewiesen. |

**Keine Gesamt-Prozentangabe:** Implementierung, Merge, Betrieb und Abnahme haben unterschiedliche Nenner. 0/8 formale Meilensteinabnahmen bedeutet **nicht**, dass 0 % Code geschrieben wurden. Umgekehrt bedeutet das aktive Alpha-Plugin **nicht**, dass V1 bereits fertig ist.

## 2. Jede Milestone-Stufe: Ist, Rest, nächste Prüfung

| Meilenstein | Code-Stand | V1-Abnahme | Wichtigster offener Punkt / Beleg |
| --- | --- | --- | --- |
| **M0 Foundation** | implementiert, historische 8/8 CI | **offen** | [PR #1](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/1) Review/main-Integration, neuer Source-/Build-Nachweis |
| **M1 Persistence** | 28 Basis- + V2-Altersschema; Live-Options 2/2 | **offen** | physisches Schema / Site-Health / Restore / [PR #1](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/1) |
| **M2 Identitäten/Policy** | Code auf gestapeltem Branch | **offen** | [PR #2](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/2) Draft/Review, Cross-Channel-Gate |
| **M3 Profile/Events/Forms** | M3-01…10 in Draft-PR | **offen** | [PR #3](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/3), Form-/Schema-/A11y-Abnahme |
| **M4 Registration/Capacity** | Service-Code 01…09, historisch grüne CI | **offen** | [PR #4](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/4), Mail/REST/Browser-Livepath |
| **M5 Consent/Privacy/Mail** | 01…10 als Code bereit | **offen** | [PR #5](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/5), Cron/Mail/Legal/Privacy |
| **M6 UI/REST/M6-10** | M6-01…09 weitgehend, M6-10 P4–P6 nicht abgenommen | **offen** | [PR #6](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/6), [PR #11](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/11) + #7–#10 |
| **M7 Hardening/Release** | noch kein eigener finaler Review/Release-Kandidat | **offen** | Performance, Security, Upgrades, A11y, Packaging, RC/Stable |

### Detailstatus der M6-10- und P4-Pakete

| Paket | Tatsächlicher Stand | Rest |
| --- | --- | --- |
| **M6-10/P1 CI** | historische 16/16 und HTTPS-Baselines, aber jüngstes PR-#11-CI rot | aktuellen Browser-/E2E-Defekt auf neuem Head ursächlich beheben |
| **M6-10/P2 Mobile** | automatische 320px-/Keyboard-Cases vorhanden | reale manuelle WCAG-/Screenreader-Abnahme auf V1-Kandidat |
| **M6-10/P3 Negative Security** | Session-/Nonces-/Revoke-Tests historisch vorhanden | wiederholen + Cross-Org und Pen-Test-Review |
| **M6-10/P4 Guardian/Consent** | Delegation/Einzelbestätigung technisch gehärtet; P4-01 teils | alle #7–#10 Abnahmekriterien, zwei unabhängige Entscheidungen, echte Nachweise |
| **M6-10/P5 Realbetrieb** | GitHub-HTTPS/Mailpit synthetische Gast-/Wartelisten-Journeys vorhanden | IONOS-Cron, externe Test-E-Mail, vollständiger End-to-End-Lauf |
| **M6-10/P6 Final** | noch nicht gestartet | zusammenhängende Browser- und fachliche Freigabe |
| **P4-01 #7** | Teilimplementation in PR #11 | Source-CI rot, externe Alters-Nachweis-/Retention-Regeln offen |
| **P4-02 #8** | kein belegter abgeschlossener zwei-Actor-Workflow | ADR, Schema, eigenständige Consent-Entscheidungen und Widerrufe |
| **P4-03 #9** | begrenzte Claim/Adminprüfung | verifizierbare Guardian- und Invite-Prozesse, Mehrfachvertretung |
| **P4-04 #10** | nicht formell begonnen | konsolidierte Minderjährigen-Rechts-/Security-/A11y-Golden-Paths |

## 3. Aktuelle, überprüfte technische Beweise (nur zugelassene Referenzen)

- **OrgaVela Hosting:** https://orgavela.com, WordPress 7.1.3, PHP 8.3.33, MariaDB 11.8.8, DB utf8mb4 und SSL.
- **SFTP Verbindung/Root:** [Run #38239184433](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38239184433) bestätigte /public/wp-admin, /public/wp-includes und /public/wp-content/plugins.
- **Installiertes Plugin:** [Installationslauf #38241807035](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38241807035) mit SHA256 acbebdd9df25d996848bbca6ebe6726ee1a986207fc7c7b818474dc93134267a. WordPress-MCP bestätigte UOP Core 0.1.0-alpha.2 und Plugin-Datei.
- **Aktivierung:** via separatem OrgaVela-WordPress-MCP erfolgreich, unabhängiger Plugin-Get meldete active. WordPress-Options-Checks uop_db_version=2 und uop_data_version=2. **Kein SQL-Schema-Vergleich oder geprüfter DB-Restore.**
- **Zeitsteuerung:** cron_disabled=true; UOP Action Scheduler registriert, zu Prüfbeginn fünf fällige WP-Aufgaben insgesamt. [Issue #13](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/13) mit Deploymenthistorie.
- **Sichtbarkeit:** discourage_search_engines=false. Noch kein Nachweis eines separaten externen Cron oder eines gesicherten nichtöffentlichen Testbetriebs.
- **CI Paket-Source 4f2c4df:** [Foundation 8/8](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38225097092) und [HTTPS/Mailpit](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38225097051) grün. **Dieser SHA entspricht dem auf OrgaVela installierten Paket**, nicht dem aktuellsten PR #11.
- **Aktueller PR-#11-HEAD 1b986587:** [Push Foundation #38233559467](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38233559467) 8/8 erfolgreich; [PR Foundation #38233565475](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38233565475) 7/8 mit Playwright-Timeout in PHP 8.5/MySQL 8.0; [PR HTTPS/Mailpit #38233565383](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38233565383) mit 2 fehlgeschlagenen Chromium-Szenarien. Keine Deaktivierung dieser Tests als „Fix“.
- **Alter WordPress.com-Stagingweg:** [Issue #12](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/12), latin1-Konflikt. Für OrgaVela mit bestätigtem utf8mb4 kein Blocker mehr; Issue als historische Hostingentscheidung bereinigen, nicht die alte Site in V1 verwenden.

## 4. Offene Release-Stoppunkte (Risikoregister)

| ID | Schwere | Konkreter Befund | Besitzer / Maßnahme | Freigabe |
| --- | --- | --- | --- | --- |
| **B01** | P0 | neuesten PR #11 CI rot | Entwicklung: Fehler-RCA, stabile Repro und neue grüne Runs | nein |
| **B02** | P0 | #7–#10 / Zwei-Personen-Consent/Guardian-Verification ungeklärt | Entwicklung + Betreiber-Rechts-/Datenschutzprüfung | nein |
| **B03** | P0 | sieben gestapelte PRs ungemergt, Alpha-Source nicht GitHub-main | Entwicklung: Review & sequenzielle Integration | nein |
| **B04** | P0 | kein echter IONOS-DB-Restore-/Upgrade-Rollbackbeweis | Betreiber/Entwicklung: Backup und isolierter Restore | nein |
| **B05** | P0 | WordPress-Cron deaktiviert, überfällige Tasks | Betreiber/Entwicklung: externer Scheduler + Worker-Health | nein |
| **B06** | P0 | keine reale synthetische externe E-Mail-Zustellungsabnahme | Betreiber/Entwicklung: Mailkonfiguration/SMTP-Test | nein |
| **B07** | P1 | Suchmaschinenabschreckung aus, Datenschutz-/Zugriffsgrenze unvollständig | Betreiber: nichtöffentliches Staging/Noindex/Consent | nein |
| **B08** | P0 | UOP-Schema-Health vs. tatsächliche DB nicht nachgewiesen | Entwicklung: direkte Site-Health-/SQL-Strukturprüfung | nein |
| **B09** | P1 | SFTP-Account hat zu breite Verzeichnisrechte | Betreiber/Entwicklung: dedizierter enger Zugriff | nein |
| **B10** | P0 | M7 Performance/Security/A11y/Upgrade/RC-Abnahme offen | Entwicklung + Reviewer | nein |
| **B11** | P1 | privater authentifizierter Actions-Dispatch aus Chat nicht verfügbar | Betreiber: autorisierter Connector oder bewusst manuelle Runs | nein |

**Rechts-/Finanzstopps:** keine realen Minderjährigen-/Teilnehmerdaten; keine echten Zustimmungs-/Vertretungsbeweise ohne unabhängige Prüfung; kein Vertrag mit Gebühren; kein Dreamloud-Zugriff.

## 5. Nächste konkrete Aufgaben (Backlog in verbindlicher Reihenfolge)

1. **W0-01 – Aktueller PR #11 CI rot:** aus Run #38233565475 die Browser-Deaktivierungs-Timeoutursache und aus #38233565383 die HTTPS-/Chromium-Szenarien eingrenzen, Ursache beheben, CI auf **exaktem aktuellen SHA** komplett grün.
2. **W0-02 – Schema und Site Health:** UOP Site-Health-Diagnose auf OrgaVela abnehmen: Migrationsstatus complete, 29 erwartete Tabellen inkl. Attest, Indizes und Stringencoding; keine Secrets/privaten Tabelleninhalte veröffentlichen. 2/2-Optionswerte sind lediglich Zwischenbeleg.
3. **W0-03 – Datenschutz/Betrieb:** Staging-Sichtbarkeit, IONOS-Backup und echte Restore-Probe in isolierter Kopie sowie Cron/Action Scheduler verbindlich einrichten; keine Versand-/Gast-Testoptins bis danach.
4. **W1-01 – P4-01/PR #11:** vollständige #7-Abnahmekriterien, Nachweisarten, Retention, Consent-/Guardian-Gates und Migrationen.
5. **W1-02 – P4-02 bis P4-04:** ADR, getrennte Entscheider, externe Guardian-Verifikation und gesamte Minderjährigen-/A11y-/Abnahmematrix.
6. **W1-03 – M6-10/P5–P6:** sichere Mail-/Waitlist-/Member-/Admin-End-to-End-Teststrecken plus manuelles Review.
7. **W2 – M0…M6 integrieren:** PR #1 → #2 → #3 → #4 → #5 → #6 → #11 / Folgelösungen zu #8–#10 mit frischer CI.
8. **W3/W4 – M7 + Release:** Performance/Security/Compatibility/Migrations-Recovery/WCAG/Privacy, Beta/RC/Stable, schriftliche R-Abnahme.

**Nächster ausführbarer Arbeitsschritt:** W0-01 oder W0-02; ersteres ist reine GitHub-Entwicklung, letzteres betrifft die bereits autorisierte OrgaVela-Testwebsite, ohne Zugriff auf Dreamloud.

## 6. Updatepflicht und Historie

- Jede UOP-Folgeantwort verifiziert den berührten Ist-Zustand; wenn eine Veränderung belegt wird, diese Datei mit Datum, Evidence-Links, Paketstatus, Risiken und nächsten Schritten aktualisieren.
- Der vollständige Plan wird bei Scope-/Prioritäts-/Abhängigkeitsänderungen über einen versionierten GitHub-Commit aktualisiert; **nicht** für jede unbelegte Vermutung.
- Den jeweiligen Arbeitsauftrag, Entscheidungen, Commits, Fehlversuche, Testergebnisse und Übergabe verpflichtend in [ASSISTANT_WORK_LOG.md](../operations/ASSISTANT_WORK_LOG.md) fortführen.
- **Jede UOP-Antwort endet mit einem V1-Fortschrittsblock** (M0–M7, P4, PR-Integrationen, wesentliche Blocker, nächstes Paket) und **Direktlinks zu diesem Status sowie vollständigem Masterplan**. Ohne Fortschritt explizit „Stand unverändert“.
- Diese Regeln gelten bei neuen Chats aufgrund [AGENTS.md](../../AGENTS.md) für Coding-Arbeiten, solange der Assistent das Repository öffnet; sie sind **keine Hintergrundüberwachung**.


### W0 – ergänzender authentifizierter Livebefund (12.10.2026)

- [Technischer W0-Audit und sichere SQL-Readchecks](../operations/W0_ORGAVELA_READ_ONLY_AUDIT_2026-10-12.md); vollständige physische DB-Struktur **noch nicht** bestätigt, `uop_schema`-Ergebnis nicht über MCP lesbar. `Installer::health` ist im Plugin-Quellcode registriert, aber das ist kein Live-Resultat.
- WP-/Plugin-Konfiguration bestätigt: Alpha.2 aktiv; 5/19 Cron-Ereignisse überfällig, einschließlich Action Scheduler; Indexierungsabschreckung aus; zugewiesene Datenschutzseite noch **unveröffentlicht**. Keine echten Nutzer-/Minderjährigendaten.
- PR #11 aktueller HEAD `7892a25894c184a624ab4ebea13aab0e1a1c88dd`: HTTPS-Run #38248040695 **rot** durch caregiver Login-Timeout; Foundation-Run #38248040810 zunächst noch im Lauf. Sieben gestapelte PRs weiterhin nicht in main; **0/18** grüne Release-Gates.
- **O03:** offizielle IONOS-Datei-/DB-Backup-Möglichkeiten untersucht, aber kein tatsächlicher eigener Export oder isolierter Restore; keine risikobehafteten Updates. Masterplan v1.4; Statusmetriken unverändert.


**CI-Statuskorrektur 12.10.2026, Run-Abschluss:** PR #11 auf `7892a25894c184a624ab4ebea13aab0e1a1c88dd`: Foundation [38248040810](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38248040810) **7/8 grün**, PHP 8.4/MariaDB 10.11 activation/deactivation-Browsertest Timeout 30 s; HTTPS [38248040695](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38248040695) **rot**, Sorgeberechtigtenlogin Timeout 20 s. **Keine Abnahme, O09 offen**, v1.5 Masterplan.


### W0 / O09 – neuer grüner CI-Beleg auf PR #11 (12.10.2026)

- **Tatsächlich geänderte Browser-Tests:** Commits [`d36935da`](https://github.com/janjrre/Projekt-Wordpress-Plugin/commit/d36935da376224d12f16b451e43e78a5d46d6593) und [`2b8a65f7`](https://github.com/janjrre/Projekt-Wordpress-Plugin/commit/2b8a65f753447689190d442a038b3f76472aaa71), reale Redirect-Commits und sichtbaren authentifizierten/Pluginzustand geprüft statt allein auf `DOMContentLoaded` zu warten. **Keine** Prüfung übersprungen.
- **Aktueller PR #11 HEAD:** `2b8a65f753447689190d442a038b3f76472aaa71`; [Foundation #38250446667](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38250446667) **SUCCESS 8/8**, [HTTPS/Mailpit #38250446526](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38250446526) **SUCCESS**. PR #11 bleibt Draft/unmerged; CI-Grün eines Entwicklungsbranchs ersetzt keine M-, W0- oder Release-Abnahme.
- **OrgaVela auf installiertem Alpha.2:** `uop_schema`-Prüfcode am Paketquell-SHA vorhanden, jedoch **kein** echtes per Datenbank/Site Health bestätigtes Live-Ergebnis; 0 veröffentlichte UOP-Events, 1 veröffentlichte WordPress-Beispielseite. `DISABLE_WP_CRON` unverändert; überfällige Cron-Ereignisse zuletzt 2/19 statt zuvor 5/19, externer Zeitplan weiter ungeprüft. Datenschutzseite Entwurf, Indexierungsabschreckung aus, kein geprüfter Restore.
- **Bilanz unverändert:** 62/71 technisch implementiert, M0–M7 0/8 formell abgenommen, P4 0/4, R01–R18 0/18, 0/7 gestapelte PRs gemergt. W0-Schema/Backup/Privatheit (O01/O02/O03/O06) weiterhin blockiert.


### W0 M1-08 – neuer Schema-Check für Default-Organisation (12.10.2026)

- Der bisherige Check konnte **trotz fehlendem oder falschem Default-Org-Verweis beziehungsweise deaktivierter Standardorganisation** `good` melden. Dieser reale Prüflückenbefund ist im Draft-Branch von PR #11 über [`20062427`](https://github.com/janjrre/Projekt-Wordpress-Plugin/commit/200624274b1e6300d7ec7d080bbb5ae366369d45) **read-only fail-closed** gefixt; [`c297a829`](https://github.com/janjrre/Projekt-Wordpress-Plugin/commit/c297a829d9a5dd74378ca09ad748a7442328d6b9) ergänzt Integration-Regressionen. PHPCS-Kommentarfehler aus erster CI über [`8e5cbfc4`](https://github.com/janjrre/Projekt-Wordpress-Plugin/commit/8e5cbfc454ffe963b6c3121d73b0cd4608053fd9) behoben.
- **Aktueller PR #11 HEAD `8e5cbfc4`:** [Foundation #38252652328](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38252652328) und [HTTPS #38252652419](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38252652419) **noch ohne abschließende gleiche-SHA-Green-Bestätigung**. Die historisch vorherigen grünen Läufe auf `2b8a65f7` sind keine Freigabe des neuen Fixes.
- OrgaVela-WordPress-MCP bietet keine freigegebene UOP- oder Site-Health-REST-Route; sein Debuglog ist deaktiviert/nicht vorhanden. Live-Alpha.2 und die physischen Schema-/Backupsicherungs-Sperren bleiben unverändert.
- Formelle Bilanz weiterhin **62/71** Coding-Pakete, M0–M7 **0/8**, P4 **0/4**, Release-Gates **0/18**; PR #11 Draft, sieben PRs ungemergt. [Kanonischer Fortschritt](../operations/V1_PROGRESS.json) ist maßgeblich.


### W0/O09 – CSS-Netzwerkursache im Playwright-Trace bewiesen (12.10.2026)

- M1-08 Default-Org-Prüfung bestand [Foundation-CI #38252652328](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38252652328) **8/8** (inklusive aller PHP-/DB-Kombinationen). HTTPS [#38252652419](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38252652419) hatte **18 bestandene, 1 fehlgeschlagenen** Test: UOP-Formularbutton 40px statt 44px.
- **Die CI-Trace zeigt konkret `GET m3-form-builder.css → net::ERR_TOO_MANY_RETRIES` (HTTP-Status -1)**, nicht einfach eine vermutete CSS-Spezifitätsstörung. Das reale Source-CSS hält bereits 44px fest.
- In PR #11 behebt [`faa8e729`](https://github.com/janjrre/Projekt-Wordpress-Plugin/commit/faa8e729c27e0dc409dacb8de44c846c44247e02) die Auslieferung der eigenen statischen UOP-Dateien im **ausschließlich isolierten HTTPS-CI-Proxy**; [`a34f4195`](https://github.com/janjrre/Projekt-Wordpress-Plugin/commit/a34f4195371f9edf1fa24ea4abdef1fd479f2c19) belegt sie mit echtem HTTPS-CSS-/JS-Smoketest. Die 44px-Grenze bleibt unverändert. PR #11 neuer HEAD `a34f4195` und dessen Foundation/HTTPS [38253835618](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38253835618)/[38253835549](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38253835549) benötigen neue Abschlussnachweise.
- Keine Live-Veränderung; W0-Schema-/Backup-/Indexierungs-Sperren und **0/8 M-, 0/4 P4-, 0/18 formelle Release-Abnahmen** unverändert. [Fortschritt](../operations/V1_PROGRESS.json).


### W0 – bestätigter neuer PR-#11-CI-Stand (12.10.2026)

- **Draft PR #11 HEAD `a34f4195371f9edf1fa24ea4abdef1fd479f2c19`**: [Foundation #38253835618](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38253835618) **SUCCESS 8/8** inklusive M1-08-Default-Org-Health-Regression, [HTTPS/Mailpit #38253835549](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38253835549) **SUCCESS** inklusive echter responsive 44px-Verifikation nach Trace-basierter CSS-Auslieferungskorrektur. Der PR ist weiterhin ungemergt, kein Produktionsdeploy.
- **W0-Realbetrieb nicht abgenommen:** UOP Alpha.2 auf OrgaVela unverändert; live Schema `uop_schema`, unabhängige 29-Tabellen-/Index- und Default-Org-Prüfung, IONOS-Backup/isolierter Restore, SFTP-Kontorechte und Testseiten-Zugriffsschutz fehlen. **Nächstes:** O02/M1-08 direkte Lesebelege, dann O03 Wiederherstellungstest.
- **Keine Änderung der Zählung:** 62/71 Coding, 0/8 Milestones, 0/4 P4, 0/18 finale Release-Gates, 0/7 PRs gemergt. Normativer Masterplan [v1.9](../operations/V1_ACCEPTANCE_PLAN.md) und [kanonischer JSON-Status](../operations/V1_PROGRESS.json).


### W0/O01/O02 – eigenständigen IONOS-Audit-Zugang vorbereitet (12.10.2026)

- Auf konkreten Betreiberwunsch steht jetzt ein **neuer, getrennter, nur lesender GitHub-Actions-Prüfworkflow** bereit: [`orgavela-readonly-audit.yml`](../../.github/workflows/orgavela-readonly-audit.yml), [Quellskript](../../scripts/ci/orgavela-readonly-audit.sh), [sichere Schritt-für-Schritt-Einrichtung](../operations/ORGAVELA_AUDIT_GITHUB_SECRETS.md).
- Neue, separat gesicherte Umgebung `orgavela-audit` soll vom Betreiber eingerichtet werden. **Keine Secrets erhalten oder gesetzt, kein SSH-Aufruf, kein Live-SQL-Nachweis.** Die bestehenden SFTP-Deployment-Zugangsdaten bzw. das normale IONOS-Kundenlogin werden bewusst **nicht** für die neue Umgebung übernommen.
- Die neue `probe`-Action verifiziert IONOS-SSH-Fingerprint, echtes OrgaVela-WordPress-Root, WP-CLI und feststehenden Alpha.2-Paketstand; `schema` liest nur bereinigte technische Health-Flags und 29-Tabellen-Zählung. **Keine unabhängige vollständige Index-Abnahme**, kein DB-Dump, kein Restore, keine produktiven Änderungen. IONOS-Shared-DB ist regelmäßig nur vom eigenen Webspace, nicht direkt vom GitHub-Runner aus erreichbar.
- Offen bleibt Betreiber-Gate für separaten SSH-fähigen OrgaVela-Nutzer, Benutzerisolation, korrektes GitHub-Environment/Secrets, erste read-only Actions-Ergebnisse, vollständige O02-Metadaten und O03-Restore. Formelle Zählung unverändert: **62/71** Coding, **0/8** M0–M7-Abnahmen, **0/4** P4, **0/18** Release-Gates, **0/7** gestapelte PRs gemergt. [Masterplan v2.0](../operations/V1_ACCEPTANCE_PLAN.md) · [Maschinenstatus](../operations/V1_PROGRESS.json).


### W0/O01 – SSH-Account mit Hosting-Webspace-Zugriff `/`: Autoprüfung gestoppt (12.10.2026)

Der Betreiber hat ausdrücklich bestätigt, dass der bestehende IONOS-SFTP/SSH-Account den gesamten Webspace-`/` erreichen kann. IONOS Shared Hosting ist dabei zwar eine vom Betriebssystem-Root getrennte chroot-Umgebung, aber im Hosting-Bereich dürfen potenziell **weitere Websites** betroffen sein; reine SFTP-Verzeichnisbeschränkung genügt laut IONOS für PHP/SSH-Isolation **nicht**. **Der vorhandene breit berechtigte Account wird nicht in die neue Audit-Umgebung `orgavela-audit` kopiert.**

Zur Risikominderung wurde der automatische `push`-/Request-Trigger des neuen Audit-Workflows auf `main` **entfernt**. Der verbleibende manuelle Aufruf erfordert `confirm_dedicated_access=true`, nur nach echter unabhängiger OrgaVela-Rechteisolation; das Kontrollkästchen ist kein technischer Ersatz für Serverberechtigungen. [Workflow](../../.github/workflows/orgavela-readonly-audit.yml) · [Sicherheitsanleitung](../operations/ORGAVELA_AUDIT_GITHUB_SECRETS.md). **Kein** Auditlauf, kein Zugriff auf Secret-Werte und keine Veränderung an bestehenden Deployment-Secrets/OrgaVela/Dreamloud. Alternativ nur **betreiberbegleitete** phpMyAdmin-/WordPress-Admin-Leseprüfung. Formelle V1-Werte bleiben 62/71, M0–M7 0/8, P4 0/4, Release 0/18, sieben ungemergte PRs; [Masterplan 2.1](../operations/V1_ACCEPTANCE_PLAN.md) und [Fortschritt](../operations/V1_PROGRESS.json) maßgeblich.


### W0/O01/O02 – vorhandenen OrgaVela-SFTP/SSH-Benutzer für Audit wiederfreigegeben (12.10.2026)

**Neue Betreiberklärung:** OrgaVela und Dreamloud laufen in **getrennten IONOS-Hosting-Verträgen**; der Betreiber akzeptiert die webspace-weiten `/`-Rechte des bereits hinterlegten **OrgaVela**-Benutzers ausdrücklich und autorisiert dessen GitHub-Weiterverwendung. Trennung beruht auf Betreiberangabe, IONOS-Vertragsunterlagen wurden nicht erneut unabhängig geprüft. **Dreamloud bleibt ausgeschlossen.**

**GitHub-Code geändert:** Der [Audit-Workflow](../../.github/workflows/orgavela-readonly-audit.yml) verwendet nun direkt die im bestehenden Environment **`orgavela-ionos`** verwendeten SSH-SFTP-Secrets `IONOS_SSH_HOST`/`IONOS_SSH_USER`/`IONOS_SSH_PASSWORD` und `IONOS_WP_ROOT` (sonst den Identitäts- und Dateistruktur-geprüften Fallback `/public`); **keine Secret-Werte gelesen, dupliziert oder verändert**. Audit-`push` wieder ausschließlich über `.github/audit-requests/orgavela-readonly.json` auf `main`, nur lesende Modi `probe`/`schema`. Die bestehenden SFTP-/SSH-Deployment-Workflows sind unverändert. [Dokumentation und Risikogrenzen](../operations/ORGAVELA_AUDIT_GITHUB_SECRETS.md) aktualisiert. Nächstes: tatsächlichen GitHub-Auditlauf prüfen.

**Keine formale Abnahme durch Risikofreigabe:** O01 bleibt teilweise offen (technische Identität/Shell-Kontrolle), O02/M1-08 29-Tabellen-/Index-Abgleich offen, O03 Backup-/Restore-Probe offen. Status [Masterplan v2.2](../operations/V1_ACCEPTANCE_PLAN.md) · [Progress](../operations/V1_PROGRESS.json); V1 nach wie vor **62/71 Coding, 0/8 M, P4 0/4, R 0/18, 0/7 gemergte PRs**, Alpha.2 letzte verifizierte Live-Version.


### W0/O01/O02/M1-08 – OrgaVela-Ionoszugang und Live-Datenbankschema verifiziert (12.10.2026)

**Berechtigung:** Der Betreiber bestätigt, dass Dreamloud und OrgaVela **getrennte Hosting-Verträge** haben; er autorisiert ausdrücklich den bereits bestehenden, webspaceweit (`/`) berechtigten OrgaVela-SSH/SFTP-Nutzer zur GitHub-Audit-Wiederverwendung. Zugriff erfolgt unter dem **bereits bestehenden** geschützten Environment `orgavela-ionos`. **Keine** Secrets kopiert oder offengelegt; bestehende Deployment-Workflows und Dreamloud unverändert.

**Beleg- und Fehlerversuchsprotokoll:**

| Live-Run | Ergebnis | Tatsächlicher Nachweis |
|---|---|---|
| [#38263245050](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38263245050) | Rot | Erster SSH-Probeversuch mit SFTP-Root `/public`, abweichender Shell-Root |
| [#38263689846](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38263689846) | **Grün** | `sftp`: Verzeichnisse von OrgaVela lesbar, nichts heruntergeladen |
| [#38263987605](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38263987605) | Rot | SSH-Shell **läuft**, aber der SFTP-WordPress-Pfad existiert nicht als Shell-Absolutpfad |
| [#38264254555](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38264254555) | **Grün** | `probe`: sichere IONOS-Pfadauflösung, WP-CLI vorhanden, exakte Homepage `orgavela.com`, UOP Core Alpha.2 |
| [#38264354774](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38264354774) | **Grün** | `schema`: physischer 28+1-Tabellen-Schemahealth `good`, SQL-Tabellecount **29**, Default-Org-Referenz `good`, DB-/Datenversionen **2/2** |

Der installierte [`SchemaInspector::verify_all()`](../../src/Infrastructure/Database/SchemaInspector.php) prüft über `information_schema` Spalten, Indexdefinitionen, Collation und Engine der eingefrorenen 28 Tabellen; das installierte [`AgeAttestationSchema::verify()`](../../src/Infrastructure/Database/AgeAttestationSchema.php) prüft die zusätzliche 29. Tabelle. Ein separater lesender SQL-Query im Workflow bestätigt die Tabellenanzahl; Default-Org und gespeicherte Versionen sind weitere unabhängige Aggregatprüfungen. **Keine personenbezogenen Daten und keine geheimen Dateipfade veröffentlicht.**

**Fortschritt:** O02/M1-08 ist **live technisch sehr viel besser belegt**; die formale M1-/V1-Abnahme, Versionsintegration aller PRs und Backup-/Restore-Prüfung bleiben offen. **Nächste priorisierte Arbeit O03:** vertrauliches Backupinventar, DB+Datei-Sicherung und isolierter Restore **ohne vorherige Live-Upgrade-/Migrationsoperation**. O01 organisatorische Trennung laut Betreiber, Schutz/Gates noch weiter prüfen. **Zähler unverändert:** 62/71 historisch implementierte M-Pakete, **0/8** formal abgenommene M0–M7, **0/4** P4, **0/18** Release-Gates, **0/7** gemergte PRs. [Normativer Masterplan v2.3](../operations/V1_ACCEPTANCE_PLAN.md) · [Kanonischer Fortschritt](../operations/V1_PROGRESS.json) · [Audit-Sicherheitsdokumentation](../operations/ORGAVELA_AUDIT_GITHUB_SECRETS.md).


### W0/O03 – Sicherungsfähigkeit live geprüft, synthetische Wiederherstellung erfolgreich (12.10.2026)

- [**OrgaVela read-only Backup-Preflight #38267570599**](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38267570599) **SUCCESS**: Berechtigter SSH-/WP-CLI-Zugriff auf die richtige Alpha.2-Website. Datenbank-Umfang **unter 25 MiB**, WordPress-Dateibestand **100–500 MiB**, über **5 GiB** freier Webspace. `mysqldump`, `mariadb-dump`, `mysql`, `tar`, `gzip`, `sha256sum` verfügbar. Nur gerundete technische Metadaten, keine Sicherungs-/Webspace-Datei und kein DB-Dump erzeugt.
- [**Isolierter synthetischer Restore-Test #38267901371**](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38267901371) **SUCCESS**: MariaDB 11.8 auf neuem CI-Container, ausschließlich **synthetische** SQL-/Datei-Fixures; Dump + gzip + tar + SHA-256 erstellt, in getrennte Ziel-DB und -Dateistruktur importiert, 2 Testdatensätze, Index und Prüfsummen kontrolliert. **Kein** echter OrgaVela-Datentransfer, kein Produktionsbackup- oder Produktions-Restore.
- Offiziell [IONOS-Webhosting](https://www.ionos.de/hilfe/hosting/dateien-sichern-wiederherstellen/webspace-und-datenbanken-manuell-sichern-backups-erstellen/): allgemeiner Systembackup-Zeitraum maximal **14 Tage**. **Tatsächliche Backup-Punkte im konkreten OrgaVela-IONOS-Konto bleiben unbelegt** und können nicht über diesen SSH-Zugang abgerufen werden.
- **O03 TEILWEISE, NICHT ABGENOMMEN:** Private unabhängige echte DB+Datei-Sicherung einschließlich sicherem externem Ablageort und isolierter Restore genau **dieses** Snapshots stehen aus. [Ausführliches Runbook](../operations/W0_O03_BACKUP_RECOVERY_RUNBOOK.md), [Masterplan v2.4](../operations/V1_ACCEPTANCE_PLAN.md), [Fortschritt](../operations/V1_PROGRESS.json). Keine Produktivmigration, keine vertragsverursachenden Kosten und keine Dreamloud-Aktion.
- Projektzahlen **unverändert:** 62/71 historisch implementierte M0–M7-Pakete, **0/8** formelle Meilensteinabnahmen, **0/4** P4, **0/18** Release-Gates, **0/7** PR-Merges; Live-Plugin Alpha.2. **Nächste priorisierte W0-Tätigkeit:** providerseitiges Backup-Inventar/privater vollständiger OrgaVela-Snapshot, danach isolierter echter Restore.


### W0/O03 – Entscheidung zum echten Backup-/Restore-Aufbau: verschlüsselt und extern aufbewahrt (12.10.2026)

Der nächste **noch nicht ausgeführte** Schritt ist jetzt nachprüfbar festgelegt: ein vom Betreiber kontrolliertes Public-/Private-Key-Schlüsselpaar (privat **nur** beim Betreiber), ein dauerhaft **außerhalb des IONOS-Webspaces und öffentlichen GitHub-Repositories** verwahrter Sicherungssatz (empfohlen: eigener Rechner + externe SSD) und ein in GitHub Actions **vor Artefakt-Upload verschlüsselnder** OrgaVela-Datei-/DB-Leseexport. Public-Repository-Artefakte sind grundsätzlich für Repository-Lesende herunterladbar und werden je nach Retention nach spätestens 90 Tagen gelöscht; deshalb nur verschlüsselt und temporär. [Konkrete Arbeitsphasen und Stop-Gates](../operations/W0_O03_BACKUP_RECOVERY_RUNBOOK.md#7-konkreter-nächster-durchlauf--minimaler-betreiberaufwand-keine-vertragskosten) · [Normativer Masterplan v2.5](../operations/V1_ACCEPTANCE_PLAN.md).

**Weiterhin keine echten Daten gesichert oder restauriert:** Betreiberentscheidung über lokalen/externalen Speicherort und öffentlicher Empfängerschlüssel liegen noch nicht vor. GitHub-Backup-/Verschlüsselungsworkflow für Realinhalte noch **nicht** implementiert, keine Aktion mit privatem Schlüssel. Bereits grün: OrgaVela-Sicherungsvoraussetzungen [#38267570599](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38267570599); synthetischer isolierter Restore [#38267901371](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38267901371). Betreiber muss IONOS-Vertrags-Restorepunkte noch im eigenen Konto kontrollieren.

O03 `partial`, B04 und R03 bleiben offen. Formal unverändert: historisch 62/71 Coding-Pakete, M0–M7 abgenommen 0/8, P4 0/4, R01–R18 0/18, PRs gemergt 0/7; OrgaVela Alpha.2 aktiv. Genau nächstes Paket: **O03-G0 privaten Speicher und Public Key wählen → O03-G1 nur synthetischen Test des verschlüsselten Exports bauen.** Keine Produktionsmigration/Live-Restore vor O03-G4.


### W0/O03 – Google-Drive-Backupziel vorbereitet (12.10.2026)

Betreiber hat **Google Drive** als künftigen PC-unabhängigen Speicherort für verschlüsselte OrgaVela-Backups gewählt. Ein neuer Ordner **„OrgaVela – verschlüsselte Backups“** wurde über den bereits verbundenen Google-Drive-Connector unter „Meine Ablage“ angelegt. Die Drive-Metadaten bestätigen `shared=false` und nur eine Berechtigung für den **Eigentümer**. Ordner-ID und etwaige spätere Google-Credentials werden **nicht** im öffentlichen GitHub-Repository hinterlegt.

[Neuer GitHub-Workflow](../../.github/workflows/orgavela-google-drive-connection.yml): ausschließlich synthetische `age`-Kryptoselbsttests (`selftest`) und manueller Upload einer **verschlüsselten Testdatei** (`upload-synthetic`) unter einem später gesondert geschützten OAuth-Environment `orgavela-backup-drive`. Die [**Krypto-Selbstprüfung #38294515636**](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38294515636) ist **grün**. **Kein** Drive-Upload aus GitHub und **kein** echtes OrgaVela-Backup hat stattgefunden; der Benutzer muss die separate Google-OAuth-/rclone-Genehmigung und öffentliche Age-Empfängeradresse einmalig im GitHub Environment hinterlegen. [Einrichtungsanleitung](../operations/ORGAVELA_GOOGLE_DRIVE_BACKUP_SETUP.md) und [Masterplan v2.6](../operations/V1_ACCEPTANCE_PLAN.md).

**Wichtig:** Der mit ChatGPT verbundene Drive-Zugriff ist **nicht** automatisch ein GitHub-Secret. Für den über den Connector angelegten Ordner erfordert rclone üblicherweise einen OAuth-`drive`-Scope mit weitergehenden Google-Berechtigungen als nur dieser Ordner; der Benutzer entscheidet darüber im Google-Anmeldedialog, alternativ getrenntes Backupkonto oder neuer `drive.file`-rclone-Ordner. Secrets dürfen nicht in Issues/Chat eingefügt werden. **O03 bleibt teilweise/offen**, ebenso der tatsächliche isolierte Real-Restore/B04 und Release-Gate R03. V1-Zähler unverändert: historische Implementierung **62/71**, formal M0–M7 **0/8**, P4 **0/4**, Release **0/18**, gemergte gestapelte PRs **0/7**. Live UOP **0.1.0-alpha.2**.


### W0/O03 – CircleCI Free aus Dreamloud und GitHub-Releases-Backup bewertet (13.10.2026)

**Wichtig: Nur Analyse, kein Backup ausgeführt.** Der Betreiber meldet eine ausgeschöpfte GitHub-Actions-Budgetgrenze. Im getrennten Dreamloud-Repository wurde ein GitHub-Actions-Budgetstopp bei 100 % bestätigt. Die dort eingerichtete **CircleCI-Free-Alternative** lieferte echte, extern gemeldete grüne Offline-/synthetische CI-Statusmeldungen ([Builds #8](https://circleci.com/gh/janjrre/dreamloud-portal/8) und [#12](https://circleci.com/gh/janjrre/dreamloud-portal/12) zusätzlich via GitHub-Commitstatus überprüft). Der [Duplicati-Linux-Restore-Test #7](https://circleci.com/gh/janjrre/dreamloud-portal/7) verwendete nur künstliche Daten; ein vollständiger echter Google-Drive-/WordPress-Restore wurde **nicht** durchgeführt. Dreamloud wurde hier nicht geändert.

**Für das öffentliche OrgaVela-Repo** sind gewöhnliche GitHub-Actions-Standardrunner laut [GitHub-Abrechnung](https://docs.github.com/de/billing/concepts/product-billing/github-actions) grundsätzlich kostenlos, eine globale Account-Budgetobergrenze kann die Runner aber dennoch blockieren; kein neuer Workflow wurde gestartet und sein Lauf ist unbestätigt. CircleCI Free ist als **separater Ausführungsanbieter** geeignet, dessen **OrgaVela-Projekt und Free-Credits** aber noch geprüft werden müssen. Als Backup-Ablage sind normale **Git-Commits** (100-MiB-Dateigrenze, ständig wachsende Historie) **nicht geeignet**; GitHub-Releases-Assets **unter 2 GiB** wären vorverschlüsselt technisch möglich, vorzugsweise in einem eigenständigen privaten Backup-Repo. Bestehender privater Google-Drive-OrgaVela-Ordner bleibt die **empfohlene unabhängige primäre Offsite-Ablage**. [Quellen und Entscheidungsmatrix](../operations/W0_O03_CIRCLECI_GITHUB_BACKUP_ASSESSMENT_2026-10-13.md) · [Masterplan v2.7](../operations/V1_ACCEPTANCE_PLAN.md).

**Nächster Schritt:** Unabhängiges CircleCI-OrgaVela-Projekt und Free-Kontingent prüfen, dann rein synthetischen, geheimnisfreien Test; Geheimnisse/echte Backups erst nach kontrollierter projekt-/branchgebundener Berechtigung und vorüberprüfter Verschlüsselung, vollständigem Remote-Readback und isoliertem Restore. Keine Übertragung von Dreamloud-Zugängen. Keine Zahlung/Produktion. O03 weiter `partial`; V1-Metriken weiterhin historisch **62/71** Coding, **0/8** M0–M7, **0/4** P4, **0/18** Release-Gates, **0/7** Merges. **Hinweis:** Aktualisierung von `V1_PROGRESS.json` in dieser Antwort wurde vom GitHub-Tool aus Sicherheits-/Ausführungsgründen blockiert; zuletzt bestätigter JSON-Stand daher noch Masterplan v2.6, Zähler unverändert.


### W0/O03 – eigenes CircleCI-Free-Projekt von Betreiber verbunden; Pilot-Draft #17 (13.10.2026)

Der Betreiber meldet, dass er `janjrre/Projekt-Wordpress-Plugin` mit **CircleCI** verbunden hat. Statt sofortiger Live-Backups wurde ein eigener UOP-Testbranch [`ci/circleci-free-pilot-o03`](https://github.com/janjrre/Projekt-Wordpress-Plugin/tree/ci/circleci-free-pilot-o03) mit der neuen [`.circleci/config.yml`](https://github.com/janjrre/Projekt-Wordpress-Plugin/blob/ci/circleci-free-pilot-o03/.circleci/config.yml) angelegt, Commit `d14069c8302da177ee93458950ecd82ed7726655`, [Draft-PR **#17**](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/17), **nicht gemergt**. Ein einziger sehr kleiner, branchbeschränkter CircleCI-Offline-Smoketest prüft ausschließlich UOP-Repo-Identität, Shell-Syntax, dokumentierte Projektdateien und JSON-Fortschrittsinvarianten; er fordert **keine** IONOS-, Google-, CircleCI-Context- oder Backup-Credentials an.

**Externes Testresultat beim letzten Abruf:** GitHub-Commitstatus für den genauen Pilot-SHA `d14069c8` `pending`, `statuses=[]`, `total_count=0`, keine CircleCI-Check-Runs sichtbar. **Deshalb noch kein bestätigter OrgaVela-CircleCI-Lauf**, keine Run-ID, keine Free-Credit-Abnahme. Die erfolgreiche CircleCI-Funktion in **Dreamloud** ist hierfür nur isolierte Referenz und kein UOP-Nachweis. Der Betreiber sollte [CircleCI → Projects](https://app.circleci.com/projects) / Pipelines für `janjrre/Projekt-Wordpress-Plugin`, Free-Plan und Branch-Push-Trigger kontrollieren, sofern der externe Status leer bleibt. [Ausführliches Pilot-Runbook](../operations/W0_O03_CIRCLECI_FREE_PILOT_2026-10-13.md) · [Masterplan v2.8](../operations/V1_ACCEPTANCE_PLAN.md).

**Keine** WordPress-/Datenbank-/Google-Drive-Aktion, kein Live-Backup, kein Restore, keine Kosten- oder Dreamloud-Aktion. O03 weiter `partial`. Maschinenstatus `V1_PROGRESS.json` wurde auf **v2.8** mit erledigtem GitHub-Branch/PR, aber **nicht** bestandenem CircleCI-Provider-Gate synchronisiert. Formelle V1-Zähler unverändert: historische Coding-Pakete 62/71, M0–M7 0/8, P4 0/4, R 0/18, neue Draft-PR #17 zusätzlich zu 7 ursprünglichen ungemergten PRs, **0** gemergt.


### W0/O03 – CircleCI-Pipeline-Fehler #8/#9 beim Konfigurationsabruf (13.10.2026)

**Betreiber-Screenshot:** Zwei CircleCI-Pipelines (**#8**, **#9**) für `Projekt-Wordpres...` zeigen die abgeschnittene Fehlermeldung `Could not fetch configuration: No co...` und `No workflows for this pipeline`. Es gibt deshalb jetzt einen **UI-Nachweis, dass Pipeline-Trigger ankommen**, aber **noch keinen** ausgeführten CircleCI-Job. Der vollständige Fehler und die CircleCI-Config-Source-Branchwerte sind nicht im Screenshot sichtbar; Ursache nicht als endgültig diagnostiziert darstellen.

**GitHub-API-Gegenprüfung:** `.circleci/config.yml` **auf `main` fehlt** (404), auf dem separaten Branch `ci/circleci-free-pilot-o03` ist sie vorhanden; [Draft-PR #17](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/17) weiterhin ungemergt. Dies erklärt einen Config-Fetch-Fehler, **wenn** CircleCI die Konfiguration von `main` oder einem anderen Branch ohne Datei holen wollte. CircleCI verlangt korrekten Config-Path/Source-Branch. [Betriebsanleitung: konkreter einmaliger Pilotbranch-Test](../operations/W0_O03_CIRCLECI_FREE_PILOT_2026-10-13.md#6-neuer-live-befund-aus-betreiber-screenshot-pipeline-89-existieren-config-fetch-schlägt-fehl-13102026).

**Nächster Schritt:** CircleCI-Pipeline #9 öffnen und den ganzen Fehler plus Config-Source-Branch prüfen; dann **einmal** manuell mit `Config source branch=ci/circleci-free-pilot-o03` und ggf. identischem `Checkout branch` auslösen, keine Secrets oder produktiven Aktionen. Bei ausbleibendem Start CircleCI Project Setup/Trigger-Konfiguration prüfen. Draft-PR #17 **nicht** eigenmächtig mergen. Bis echter Providerjob grün: O03 `partial`, kein Datenbank-/Datei-/Drive-Backup und keine Restore-Abnahme. [Masterplan v2.9](../operations/V1_ACCEPTANCE_PLAN.md) und [maschinenlesbarer V1-Status v2.9](../operations/V1_PROGRESS.json) wurden synchronisiert. Formelle Zähler unverändert: **62/71** historische Entwicklung, M0–M7 **0/8**, P4 **0/4**, Release **0/18**, Merges **0**.


### W0/O03 – OrgaVela-CircleCI-Pilot nach echtem Parserfix grün (13.10.2026)

Ein neuer Betreiber-Screenshot zu CircleCI-Pipeline **#16** belegt: Die branchgebundene OrgaVela-Konfiguration wird von CircleCI **geladen**, aber der ursprüngliche Python-Heredoc `python - <<'PY'` in YAML wurde als ungültiger CircleCI-`<<`-Parameter abgewiesen. Der alte Config-Fetch-Fehler aus #8/#9 ist daher kein aktueller Branch-Blocker mehr.

**Korrektur ausschließlich in [Draft PR #17](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/17) (unmerged):** Python-Offline-Checks in [eigenes, rein lokales Skript](https://github.com/janjrre/Projekt-Wordpress-Plugin/blob/ci/circleci-free-pilot-o03/scripts/ci/orgavela-circleci-offline-smoke.py) verschoben (Commit `da1e195c`), YAML ruft es jetzt direkt auf ohne `<<` (Commit [`c478b4c9`](https://github.com/janjrre/Projekt-Wordpress-Plugin/commit/c478b4c9e74fa8bc73db16945ff9cb2a4270d2da)). Die GitHub-Status-API bestätigte **externen CircleCI-`success`-Status** für denselben SHA, `ci/circleci: offline-connection-smoke = success`, Link [**OrgaVela CircleCI Build #2**](https://circleci.com/gh/janjrre/Projekt-Wordpress-Plugin/2). **Somit ist der erste OrgaVela-CircleCI-Free-Offline-Pilot technisch bestanden.** Provider-Einzelschrittlogs und Creditverbrauch bisher nicht zusätzlich unabhängig eingesehen. [Details/Fehlerbelege](../operations/W0_O03_CIRCLECI_FREE_PILOT_2026-10-13.md#7-pipeline-16-yaml-heredoc-parserfehler-behoben-orgavela-provider-pilot-grün-13102026).

**Keine Verwechslung mit dem Backupziel:** O03-CI-01 bestanden, aber **O03 Backup/Recovery selbst weiter `partial`**, keinerlei echte WP-Datei-/MariaDB-Sicherung, Google-Drive-Upload oder isolierter Real-Restore. Google-Drive-Ordner existiert und ist privat; Betreiber muss noch Free-Credits/Context-/Branchschutz prüfen und einen eigenen privaten `age`-Recovery-Key plus restriktive Google-OAuth-Verbindung für synthetischen Canary einrichten. Keine IONOS-, Dreamloud-, Secret- oder Produktivänderung; Draft PR #17 nicht gemergt.

Masterplan [**v2.10**](../operations/V1_ACCEPTANCE_PLAN.md) und maschinenlesbare [**v2.10**](../operations/V1_PROGRESS.json) synchronisiert. Formal: historische Entwicklung **62/71** Codingpakete, Milestones M0–M7 **0/8**, P4 **0/4**, Release-Gates **0/18**, gemergte PRs **0** (7 vorhandene + neuer Draft #17).


### W0/O03 – verbindlicher Betreiberentscheid: Backups nur auf IONOS und privatem GitHub (13.10.2026)

**Neue Strategie:** Betreiber hat Google Drive als Backupziel ausdrücklich **abgewählt**. Fortan sind für die Wiederherstellung **IONOS-Vertragssicherungen** (tatsächliche vorhandene Restorepunkte noch im konkreten IONOS-Konto nachzuweisen) und **verschlüsselte Backup-Release-Assets in einem separaten privaten GitHub-Repository** vorgesehen, z. B. `janjrre/orgavela-backups`. Dies ist **nicht** das öffentliche WordPress-Plugin-Quellrepository. Kein tägliches Committen großer .zip/.sql/.tar-Files, kein normales Git-LFS- oder GitHub-Actions-Artefakt als einzige Dauerablage. GitHub Releases hat [Assets unter 2 GiB pro Datei](https://docs.github.com/en/repositories/releasing-projects-on-github/about-releases). **Gleiches GitHub-Konto für Source/Backup** bleibt ein korrelierter Ausfallbereich, den der Betreiber statt der früheren unabhängigen Drive-Ablage bewusst wünscht.

**Tatsächlich erledigt:** [Verbindliches GitHub-Private-Backup-Runbook](../operations/W0_O03_GITHUB_PRIVATE_BACKUP_RUNBOOK.md) erstellt, inkl. privatem Repo, offline verwahrtem `age`-Schlüssel, eingeschränktem GitHub-CircleCI-Context/PAT, synthetischem verschlüsseltem Release-Upload mit vollständigem Remote-Readback und echtem separatem offline Restore. Der frühere Google-Drive-Actions-Workflow `.github/workflows/orgavela-google-drive-connection.yml` wurde aus `main` entfernt, Commit [`58b148cb`](https://github.com/janjrre/Projekt-Wordpress-Plugin/commit/58b148cb2db1f8e9031d2e9a14a05db62b1c5c7a); [altes Google-Drive-Setup](../operations/ORGAVELA_GOOGLE_DRIVE_BACKUP_SETUP.md) sowie [altes Recovery-Runbook](../operations/W0_O03_BACKUP_RECOVERY_RUNBOOK.md) als durch neue Destination überholt gekennzeichnet. Der dort zuvor angelegte private Drive-Ordner bleibt ohne Backupaktion unbenutzt, aber nicht gelöscht.

**Echte Einrichtung noch offen:** Die verbundene GitHub-Integration kann **keine Repositorys anlegen**. `janjrre/orgavela-backups` ist bislang **nicht durch GitHub API als vorhanden und privat bestätigt** (der Owner muss einmalig über [github.com/new](https://github.com/new) als **Private** anlegen). Noch kein geschützter CircleCI-GitHub-Token/Betreiberschlüssel, kein synthetischer GitHub-Release-Upload, **kein echtes OrgaVela-Datei-/SQL-Backup und kein isolierter Real-Restore**; keine CircleCI-/GitHub-Actions-Laufzeit oder zahlungspflichtigen Dienste in diesem Schritt gestartet. Der zuvor [erfolgreiche UOP CircleCI-Offline-Build #2](https://circleci.com/gh/janjrre/Projekt-Wordpress-Plugin/2) bleibt nachgewiesen, ersetzt aber nicht den tatsächlichen Backup-Nachweis. Kein WordPress-Produktionsdeploy, keine Dreamloud-Mutation.

[Masterplan **v2.11**](../operations/V1_ACCEPTANCE_PLAN.md) und [Fortschritt **v2.11**](../operations/V1_PROGRESS.json) enthalten die jetzt maßgebliche Entscheidung. Status weiterhin `O03 partial`, B04/R03 offen; historisch 62/71 Codepakete, M0–M7 abgenommen 0/8, P4 0/4, Release-Gates 0/18, Pull Requests gemergt 0, Live UOP Core 0.1.0-alpha.2. **Als Nächstes Betreiberaktion:** neues privates GitHub-Backuprepo erstellen, ohne Upgrade/Bezahlvertrag; anschließend private Sichtbarkeit direkt verifizieren und nur synthetischen Verschlüsselungs-/Uploadtest entwickeln.


### O03 GH-01 – privates GitHub-Backup-Repository jetzt verifiziert (13.10.2026)

Der Betreiber hat das neue Repo erstellt; **GitHub API `get_repo` bestätigt** [`janjrre/orgavela-backups`](https://github.com/janjrre/orgavela-backups) mit `visibility=private`, Standardbranch `main`, Schreib-/Adminzugriff. **GH-01 bestanden**. Diese Bestätigung ersetzt die ältere Aussage „noch nicht erstellt“ in den historischen Abschnitten. Keine Releases/Backupdaten hochgeladen, keine Secrets angelegt. **GH-02…GH-08 bleiben offen**, insbesondere geschützter CircleCI-Context, Betreiber-`age`-Key, synthetischer Ciphertext-Release, vollständiger realer Snapshot/Readback, privater isolierter Restore und verifizierte IONOS-Recoverypunkte. Masterplan weiterhin **v2.11** (Architektur unverändert), O03 `partial`, B04/R03 offen; Gesamtzähler unverändert 62/71, 0/8, 0/4, 0/18, 0 PR-Merges.


### 13.10.2026 – GH-02 CircleCI-Ausdruck bestätigt, GitHub-`main` noch ungeschützt

Betreiber hat das Speichern der CircleCI-Expression im Context `orgavela-backup-releases` bestätigt; **keine CircleCI-API-Verifikation** möglich. Frühere Betreiber-Screenshot-Evidenz belegt nur die projektweite Restriction auf `Projekt-Wordpress-Plugin`. Unabhängig durch GitHub **neu belegt**: [Source-Repo `main` Branch API](https://api.github.com/repos/janjrre/Projekt-Wordpress-Plugin/branches/main) meldet `protected=false`, [Repository-Rulesets](https://api.github.com/repos/janjrre/Projekt-Wordpress-Plugin/rulesets) enthalten `[]`; detailierte `/protection`-Abfrage verweigert der verbundene GitHub-Connector mit `403`. **Daher GH-02 weiterhin blockiert, kein GitHub-Token, kein IONOS-Secret** in CircleCI eintragen. **Nächster Betreiber-Schritt:** Öffentliches GitHub-Source-Repo [Settings → Branches](https://github.com/janjrre/Projekt-Wordpress-Plugin/settings/branches), Branchschutz `main` mit Pflicht-PR, keinem Admin-Bypass, Force-Push/Löschen aus erstellen; wenn keine zweite prüfberechtigte Person vorhanden, vorerst keine Reviewpflicht mit mindestens 1 Fremdreviewer erzwingen. Anschließend REST-`protected=true` und echte Einstellungen prüfen und PR-/Fork-Risiken bewerten. Durch verpflichtende PRs werden weitere Dokumentationsänderungen von `main` auf separate Branches/PRs verlegt (kein Merge ohne Freigabe).

Der [Masterplan v2.11](../operations/V1_ACCEPTANCE_PLAN.md) bleibt normativ unverändert, [Maschinenstatus](../operations/V1_PROGRESS.json) und [Backup-Runbook](../operations/W0_O03_GITHUB_PRIVATE_BACKUP_RUNBOOK.md) enthalten neue Befunde. O03 `partial`, Release-/B04-/R03-Gates offen, kein realer Backup-/Restore-Nachweis, 62/71 Coding historisch, Milestones 0/8, P4 0/4, Release 0/18, Merges 0. Kein neuen Workflow oder Zahlung ausgelöst.


## 22.10.2026 — W0/O01/O03: PGP05 transport readiness, unchanged acceptance

[Execution readiness and independent review packet](../operations/PGP05_CLOUD_SSH_READINESS_AND_REVIEW_PACKET_20261022.md); tracking #27 / Draft PR32. Code SHA `80eda724f2339afe1804fff91c3c10c27d694852`: 93/93 exact-blob local synthetic tests OK; existing CircleCI #40 success reverified. Documentation-only continuation; no code/CI/deploy change.

Cloud runtime has SSH tools but DNS fails and no configured private access is available; PCjan is offline. Official IONOS fingerprint rechecked, actual endpoint key/SSH namespace/domain-root **not checked**. Review handoff prepared, independent signoff absent. **Checkpoint 5 = 7/10**, open #6 review, #8 authenticated identity/domain binding, #10 live metadata run/acceptance. Next: existing authorized PCjan online, private transport readiness; then complete reviewed live design. No passwords/keys in chat or GitHub. V1 acceptance unchanged: 62/71 historical coding, M0–M7 0/8, P4 0/4, R 0/18; PR32 Draft/unmerged, last evidenced live alpha.2 (not re-queried this session).


## 22.10.2026 — PGP05 CircleCI SSH-Prüfjob erstellt, Live weiterhin offen

[Anleitung](../operations/PGP05_CIRCLECI_SSH_PROBE_OPERATOR_GUIDE_20261022.md) · #27 / Draft PR32. Standard off führt nur kontextfreie Offline-Tests aus; hostkey und metadata sind explizite separate, manuell freizugebende Abläufe mit getrennten exakt SHA-beschränkten Contexts. **116/116 lokale synthetische Tests PASS**, YAML-Struktur und Shellsyntax PASS; keine neue Provider-CI-/Live-/Review-Abnahme. Contexts/Secrets nicht angelegt, keine Siteänderung.

Nächster Betreiber-Schritt: **orgavela-pgp05-hostkey-only** zuerst auf Projekt und exakte geprüfte Revision beschränken, danach nur Host/SHA/Zero-cost-Bestätigung eintragen, **kein Passwort**. Authentisierter Metadatenmodus bleibt vor unabhängigem Startup-/Codereview gesperrt. PCjan nicht erforderlich. Checkpoint5 **7/10**, #6/#8/#10 offen; V1 unverändert 62/71 historisch, M0–M7 0/8, P4 0/4, R 0/18; alpha.2 zuletzt belegt, PR32 Draft/unmerged.


## 2026-10-22 — PGP05 Hostkeykandidat tatsächlich PASS (Pipeline #537 / CircleCI #43)

Betreiber-Screenshots zeigen genehmigten Hostkey-Approval und erfolgreichen Job pgp05-hostkey-candidate (3s). Sichtbare Ausgabe: PGP05_CIRCLECI_SSH_PROBE=PASS; mode=hostkey, hostkey_candidate_matches_provider=true, authenticated_ssh=false, production_capture_allowed=false. source_revision im Log durch CircleCI-Secretmaskierung verdeckt; kein eigenständiger vollständiger SHA-Readback aus dem Screenshot behauptet. GitHub-Commitstatus bestätigt unabhängig success für [Candidate #43](https://circleci.com/gh/janjrre/Projekt-Wordpress-Plugin/43) auf **392053bb0e3f24ef79cc66a5142a6558b3fa5ee5**, ebenso [Offline #41](https://circleci.com/gh/janjrre/Projekt-Wordpress-Plugin/41) und [Approval](https://circleci.com/workflow-run/9e8eb44c-16a1-413b-9713-1c19b9383ac5). Exact source + provider status + redacted receipt are combined evidence, not a direct assistant read of raw SSH packets.

**Akzeptierter Teilnachweis:** Der vom festgelegten Endpunkt erhaltene ED25519-Keyscan-Kandidat stimmt gemäß geprüftem Code mit dem offiziell veröffentlichten IONOS-Pin überein. Provider-only Keyscan **beweist keine abgeschlossene authentisierte SSH-Sitzung oder Schlüsselbesitzprüfung im angemeldeten Transport**, kein bestimmtes Kundenkonto, Shell-Namespace oder aktive Domain/vHost-Zuordnung. Diese Grenzen sind im Receipt korrekt erhalten. Keine Login-Credentials, Site-Dateien, PHP/WordPress/SQL, Backup/Deploy/Restore/Release/Merge durch diesen Hostkeymodus.

**Nächste Betreiberaktion:** Die drei temporären Variablen im ausschließlich für diesen Versuch eingerichteten Context orgavela-pgp05-hostkey-only entfernen (PGP05_SSH_HOST, PGP05_APPROVED_SHA, PGP05_ZERO_COST_CONFIRMED); Projekt-/Expression-Restrictions stehen lassen. Nur diesen Context bearbeiten, keine Backup-/Deploy-Zugänge. Kein Rerun. Entfernen sperrt die weitere Nutzung durch fehlende Pflichtfelder; vorherige Logs werden dadurch nicht gelöscht. Ausführung der Bereinigung noch nicht bestätigt. Vor Metadata-Context/Passworteingabe bleibt unabhängige Code-/Transport-/Startup-Sicherheitsabnahme zwingend. Neue autorenseitige Tests wären kein Ersatz.

**Checkpoint5 unverändert 7/10 (70 %):** #8 hat jetzt zusätzlich den echten Hostkey-Kandidatenteil bestanden; vollständige authentisierte Konto/Namespace/vHost-Bindung bleibt offen, ebenso #6 unabhängiges Live-Review und #10 akzeptierter echter Metadatenlauf. Keine künstliche Erhöhung auf 8/10. Aktueller Fortschritt zeigt abgeschlossene Vorprüfung, nicht vollständige PGP05-/V1-Abnahme. Historische Abschnitte mit 'Hostkey nicht ausgeführt' sind durch diesen datierten Nachtrag im Kandidatenumfang überholt.

V1-Zähler unverändert: 62/71 historische Codingpakete, M0–M7 0/8, P4 0/4, R01–R18 0/18. Dieser Dokumentationsnachtrag verändert den gepinnten PR32-Codebranch nicht.


## 2026-10-22 — PGP05 unabhängige KI-Zweitprüfung und Fehlerbehebung
Der ausdrücklich beauftragte separate Prüfagent prüfte PR32 auf 392053bb0e3f24ef79cc66a5142a6558b3fa5ee5.
Zwei P2-Befunde: unerwartetes SSH-stderr konnte PASS ergeben; veränderliches
cimg/python:3.12 ist vor Passwortinjektion nicht ausreichend festgeschrieben.
Stderr-Fehler und widersprüchliche historische Credential-Dokumentation auf
**4d7b257ae398d663cf35668777e7b5bbea95b17c** korrigiert; Zweitprüfer bestätigte
beide Korrekturen und unabhängig **117/117 lokale synthetische Tests OK**.
Keine menschliche Audit-/GitHub-Approval-Abnahme behauptet.
[Reviewbericht und Nachprüfung](../operations/PGP05_INDEPENDENT_SECURITY_REVIEW_20261022.md).

**Live-Urteil weiterhin BLOCKED**, #6 nicht erfüllt: immutable Runtime-Identität
und tatsächlicher Provider-/Account-Startup-Nachweis fehlen. #8 benötigt zusätzlich
authentisierte Namespace-/Domainbindung, #10 den akzeptierten echten Metadatenlauf.
**Checkpoint5 7/10 (70%)**, #6/#8/#10 offen. Produktionscapture bleibt false.
Keine Secrets/Kontexte eingerichtet, kein neuer Runner, SSH-Login, PHP/WordPress/SQL,
Backup/Deploy/Merge oder Kauf. Alte CircleCI-Erfolge #41/#43 gelten ausschließlich
für 392053..., neue Code-SHA hat noch keinen Provider-Testbeleg.
Nächste tatsächliche Benutzeraktion: bestehendes CircleCI #41 öffnen, Abschnitt
Spin-up environment mit vollständigem Image-Repository und Registry-Manifestdigest
zeigen (keine Secrets, kein neuer Lauf). Danach Provenienzprüfung, beide Jobs
immutable pinnen, genaue SHA erneut reviewen und kontextfreie Provider-CI.
Hostkey-only-Variablen sind bereits entfernt; alte Restrictions nicht erweitert.
V1 unverändert offen: 62/71 historisch, M0–M7 0/8, P4 0/4, R 0/18; PR32 Draft/
ungemergt, zuletzt belegte Live-alpha.2 nicht erneut abgefragt.


## 2026-10-22 — PGP05 Executor immutable gepinnt, kontextfreier Providerlauf als nächster Schritt

Das bereitgestellte Build41-Spin-up-Log enthält keinen vollständigen Manifestdigest;
kurze Layer-IDs beweisen keine historische Imageidentität. Direkter HTTPS-Abgleich
mit DockerHub cimg/python und Registry zeigte einen neuen legitimen linux/amd64-
Kandidaten; Index- und Manifestbytes wurden SHA256-verifiziert. Beide Jobs sind
auf **docker.io/cimg/python@sha256:793ca7ea5a2fc27fa5b379fd077bf571c456d103992c7b3098cfc885f46cfa08**
festgeschrieben, Code-SHA **c952645000b2954c04f1c0446c333392048065e6**.
Kein Bezug auf den exakt historischen Job41-Digest behauptet.
[Provenienz](../operations/PGP05_IMMUTABLE_EXECUTOR_PROVENANCE_20261022.md).

Runtimecheck ist lokal/begrenzt, keine Installation/Verbindung: x86_64, benötigte
Binaries, SSH-/System-Pythonversionen und trusted-binary-Prüfung. YAML-/Shellsyntax
PASS, **117/117 lokale synthetische Tests PASS**, vom separaten Reviewer erneut
ausgeführt; exakte publizierte Config ebenfalls überprüft. Default-off bleibt
ohne Context/Secrets und ist für den nächsten Provider-Test geeignet.

**Nächste Betreiberaktion:** Featurebranch feat/pgp05-readonly-ssh-metadata-check-20261021
auf exakter SHA c952645000b2954c04f1c0446c333392048065e6 mit pgp05_ssh_probe=off
starten, übrige Modi aus. Spin-up-Image, Runtime-Step und Offline-Ergebnis zeigen.
Kein Approval-/SSH-Job erwartet; keine Contextpins/Secrets anpassen.
Provider-Pull und Runtime auf dieser SHA bleiben bis zu diesem Lauf unbestätigt.

Live-Metadata weiter BLOCKED: Provider-Runtime und tatsächlich geprüftes SSH-
Startupverhalten fehlen; Domainbindung/authentisierter Lauf ebenfalls offen.
**CP5 7/10 (70%), #6/#8/#10 offen**; production_capture_allowed=false.
V1 unverändert offen62/71 historisch, M0–M7 0/8, P4 0/4, R0/18; PR32 Draft/
ungemergt; alpha.2 zuletzt belegt, kein neuer Livezugriff oder Kauf.


## 2026-10-22 — PGP05 Build44: gepinnte Runtime und 117 Tests beim Provider bestanden

[CircleCI #44](https://circleci.com/gh/janjrre/Projekt-Wordpress-Plugin/44) auf exakter
SHA **c952645000b2954c04f1c0446c333392048065e6**: GitHub-Commitstatus
ci/circleci: pgp05-offline-security=success. Sechs Betreiberlogs zeigen Quelle/
Checkout passend, Isolation PASS, Start des exakt gepinnten amd64-Images aus
Providercache, Runtime PASS, **117/117 Tests OK**. Kein neuer Registrydownload
behauptet. OpenSSH8.9p1 Ubuntu-3ubuntu0.17/OpenSSL3.0.2, /usr/bin/python3=3.10.12;
dies ist der geprüfte System-Helper, nicht die pyenv-Anwendungs-Pythonversion.
Separater Reviewer las Logs und Status unabhängig und akzeptiert damit den
Immutable-Executor-/tatsächlichen Runtime-Teilnachweis für genau diese SHA.

**Noch keine SSH-Livefreigabe.** Es fehlt das tatsächliche IONOS-/Konto-
Startverhalten vor dem übergebenen Kommando. Clientcode/env-i kann frühere
SSH-/Shell-Hooks nicht ausschließen. Öffentliche Hilfe/SSH-Dokumentation ergibt
keine konkrete IONOS-Kontokonfiguration. Providerbestätigung der tatsächlichen
Shell/Home/Startupkette, danach private lesende Prüfung nur der relevanten
Kontodateien über Providerdateimanager ist der nächste sichere Nachweisweg.
[Vorbereitete, nicht versandte IONOS-Anfrage](../operations/PGP05_IONOS_STARTUP_EVIDENCE_REQUEST_20261022.md).
Keine Startup-Dateien/SSH/SFTP testweise ausführen; keine Secrets im Chat/GitHub.

**CP5 weiter 7/10 (70%)**: #6 Startup-/Live-Sicherheitsabnahme, #8 authentisierte
Konto/Namespace/Domainzuordnung, #10 echter akzeptierter Metadatenlauf offen.
Keine Contextpins/Secrets geändert; keine neue CI/Livesitzung/WordPress/PHP/SQL/
Backup/Deploy/Merge/Kauf oder Supportnachricht durch den Assistenten.
V1 unverändert offen62/71 historisch, M0–M7 0/8,P4 0/4,R0/18; DraftPR32/
ungemergt; zuletzt belegte Live-alpha.2 nicht erneut abgefragt.


## 2026-10-22 — verbindliche Neufassung PGP05-SSH-20261022.2

**Überholt die frühere pauschale Pflicht zur IONOS-Supportauskunft/Prüfung aller
globalen Startup-Hooks.** Diese Pflicht war für den begrenzten Metadatencheck
unverhältnismäßig. Die Revidierung ist keine Behauptung einer Hookinspektion.

Der separat beauftragte unabhängige Prüfagent erteilt **APPROVED** für genau
c952645000b2954c04f1c0446c333392048065e6, festen Remote-Skript-SHA256
7d8e383ca0e09738b11a31a20d79c88b38a540539763f0091f0c69f692882e99,
unter normalem Vertrauen in die IONOS-gemanagte SSH-/Serverinfrastruktur und
deren übliches Kontostartverhalten. Dieses Infrastrukturverhalten ist nicht
unabhängig beobachtet. Kein absolutes Null-Nebenwirkungsversprechen.

Scope: eine gepinnte Verbindung zum privat gebundenen bestehenden OrgaVela-Konto,
exakt festes stdin-Shellskript für Account/Root/Pfad/Typ/Owner/Mode/Dateinamen.
Kein angefordertes PHP, WordPress-Bootstrap, SQL, Inhaltslesen, Schreiben,
Backup oder Deployment. 117/117 aktuelle CircleCI44-Tests, stderr-Fix und
unabhängig geprüfte immutable Runtime bleiben gültige Nachweise.

Unverändert erforderlich: exakte Projekt/Repo/Branch/SHA-Contextrestriction
vor Secretinjektion, getrennte private Credentialablage, feste Hostpin-Prüfung,
Ausgabe-/Zeitlimits, exaktes stdout/leeres stderr, eigene manuelle Freigabe.
Der Providertrust überstimmt keine bekannte ungewöhnliche Konfiguration,
Customhooks mit Sitecode/Schreibwirkung, Warnungen oder abweichende Ausgabe:
bei konkretem Gegenbeleg stoppen und erneut prüfen. IONOS-Anfrage ist dann ein
gezieltes Diagnosemittel, keine normale pauschale Vorbedingung.

**PGP05_STARTUP_REVIEWED=yes** bezeichnet nach dieser dokumentierten Neufassung
ausschließlich die erfolgte unabhängige Prüfung/Annahme dieses Startup-Scope
und Infrastruktur-Vertrauensmodells. Es behauptet NICHT die tatsächliche
Inspektion oder Abwesenheit jedes Hooks. Der historische Feldname bleibt auf
der bereits getesteten SHA unverändert; keine stille Semantikänderung.
Receipt bleibt startup_hooks_independently_observed_by_probe=false,
server_enforced_readonly_account=false, domain_vhost_binding_proven=false,
production_capture_allowed=false.

**Checkpoint5 jetzt 8/10 (80%)**: Kriterium6 unabhängige Sicherheitsprüfung
im präzisen obigen Scope ERFÜLLT. #8 tatsächliche authentisierte Account-/
Namespace-/Domainbindung und #10 akzeptierter realer Metadatenlauf OFFEN.
Reviewabschluss ist weder durchgeführter Login noch Produktionsbackupfreigabe.
V1-Zähler unverändert62/71 historisch,M0–M7 0/8,P4 0/4,R0/18.

[Dreamloud-Wiederverwendung und nächster Schritt](../operations/PGP05_DREAMLOUD_REUSE_AND_BOUNDED_METADATA_DECISION_20261022.md).


## 2026-10-23 — Metadatenjob47 verweigert; feste redigierte Diagnose freigegeben
Job47 auf c952645... endet REFUSEDexit78; GitHub bestätigt failure, Offline45/
Approvalsuccess. Ursache und erreichteAuthentisierungsstufe unbekannt.
KeinBlindretry/Pfad-/Permissionwechsel. Code **b31e6cc0684593cca63d493ba0f56dd562e11f53**
ergänzt nur feste lokaleStufen/Remote-reportedExitkategorien ohne Rohoutput.
KeinGuard/Command/Pin/Timeout/Cleanup gelockert, keinPHP/WP/SQL/Writes/Discovery.
**121/121 lokaleTests**, separate unabhängige Nachprüfung/exakteBlobprüfungPASS;
neueSHAProviderprüfung nochOFFEN. ScriptHash
1906f7c716d32983f2f0e412449c929f4d92ccedd27cb8716c485e5483d12c58.
[Diagnose-/Wiederanlaufplan](../operations/PGP05_METADATA_REFUSAL_DIAGNOSTICS_20261023.md).
[Akzeptiertes unabhängiges Review](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/27#issuecomment-6358998616).

NächsterBetreiberpunkt: nur exakteContextrevision+PGP05_APPROVED_SHA auf neueSHA,
PGP05_REMOTE_SHA256 auf neuenHash, PGP05_METADATA_REVIEW_REF auf neuesReview
aktualisieren. Host/User/Passwort/Root unverändert. NeueMetadata-Pipeline, erst
aktuellerOfflineerfolg und richtigeExpression/Graph vor erneutemApproval prüfen.
KeinAssistenten-Livejob/Contextmutation/Backup/Deploy/Merge/Kauf.
CP5 weiter8/10,#8/#10offen; productioncapturefalse. V1 unverändert62/71historisch,
M0–M7 0/8,P4 0/4,R0/18; PR32Draft,alpha.2zuletztbelegt.


## 2026-10-23 — Job50 eingegrenzt; SSH-Diagnostik unabhängig freigegeben
Job50 auf b31e6cc0684593cca63d493ba0f56dd562e11f53 meldet
SSH / PROCESS_EXIT; Offline48 und Approval unabhängig als success bestätigt.
Settings und Hostkeypin passiert, aber Authentisierung/Ursache unbekannt.
Kein Passwort-/Pfad-/Rechtewechsel auf Verdacht, kein alter Job-Rerun.

Neuer exakter Code **f5e35ea36b192288920558b783fee2c46c854c18**:
125/125 lokale synthetische Tests PASS, separat unabhängig wiederholt;
Prüfer hat publizierte Probe3fd7610d53978f722485f27c2a337beb88deac7d,
Testsbf4064608f5613d372d41b5dd1e2e1320f0ed8e8 und unveränderte
Config2085eb8e2296df0fa97908564de1faffe0931a1c nachgeprüft. **APPROVED**
im unveränderten PGP05-SSH-20261022.2 Scope, keine externe menschliche
Auditbehauptung. [Exakter Reviewbeleg](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/27#issuecomment-6359237075).
Nur vollständige kanonische bounded stderr-Zeilen werden festen
SSH_REPORTED_* Kategorien zugeordnet; leeres stdout/zulässige Exitklasse
nötig, mehrdeutig/unbekannt generic. Keine Rohtexte/Identifiers/Returncodes.
Die Kategorie beschreibt gemeldeten Text, keinen bewiesenen Fehlergrund.
Remote-Skript/SSHargs/Config/Guards unverändert, ScriptSHA256 weiter
1906f7c716d32983f2f0e412449c929f4d92ccedd27cb8716c485e5483d12c58.
NeueSHA hat noch keine Provider-CI; VorgängerOffline48 ist kein solcher Beleg.

Nächste tatsächliche Betreiberaktion: im vorhandenen MetadataContext nur
Expressionrevision und PGP05_APPROVED_SHA auf f5e35ea36b192288920558b783fee2c46c854c18,
PGP05_METADATA_REVIEW_REF auf https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/27#issuecomment-6359237075 setzen.
ScriptHash und private Settings bleiben unverändert. Dann eine NEUE Pipeline
auf exakt dem Featurebranch mit pgp05_ssh_probe=metadata; aktueller
kontextfreier Offlinejob muss vor manueller Approval erfolgreich sein.
Keine Contextmutation/Liveausführung durch Assistent; keine Secrets gelesen.

**CP5 unverändert8/10 (80%)**, #8 authentisierte Account-/Namespace-/Domainbindung
und #10 akzeptierter echter Metadatenlauf offen. production_capture_allowed=false.
V1 offen:62/71 historische Codingpakete, M0–M7 0/8,P4 0/4,R0/18;
PR32 Draft/unmerged, alpha.2 zuletzt belegt. Kein Backup/Deploy/Merge/Kauf.


## 2026-10-23 — tatsächliche Dreamloud-Transportübernahme statt weiterer Sonderdiagnose
Der Betreiber verlangt erneut die vorhandene funktionierendeDreamloud-Lösung.
Die frühere reineÜbernahmeplanung wird nicht als fertigePortierung gewertet.
AktuelleVorlage backup/production-circleci07d4ffbcc3c6d70f0716fda070050d6532a69df6;
DreamloudIssue434 weist jetzt8/8Abnahme und Tagescapture1139/writer1141 aus.
DortigerSSH-Transport wird benötigt, um QuellDateien/DB aufIONOSzu sichern.
EigeneOrgaVelaStartup-/Wrapper-/Diagnosekonstruktion ist keineallgemeinePflicht.

**Umgesetzt auf Source 8c4b445ea5dced969f63b0f30e0e371342f64680:** initiale festeMetadata-Anweisung direkt als
SSH-command wieDreamloudsprobe_circleci_password_source.py; kein extraabsolutes
env-/bin/sh-Wrapper, kein stdinInterpreter. Validierte/gequoteteParameter vor
identischemMetadataBody. TatsächlicheSammlung/Guards/SSHoptionen unverändert.
PrivateSSHargv/Root/User undRohoutput nichtloggen. ManagedPOSIX-Shell/Environment
explizitvertrauenswürdigangenommen; inkompatibleShell verweigert ohneFallback.
Dies ist keineBeobachtungallerStartuphooks und keinBeweis fürhistorischeFehlerursache.
BodyHash1906f7c716d32983f2f0e412449c929f4d92ccedd27cb8716c485e5483d12c58
bleibt; gesamteBuilder/Transportidentität ist durch neueSourceSHA gebunden.

**127/127lokaleTests PASS, separatwiederholt; exaktepublizierteBlobs geprüft,
APPROVED.** [Review](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/27#issuecomment-6359766814),
[Implementierung/Abgrenzung](PGP05_DREAMLOUD_TRANSPORT_REUSE_20261023.md).
NeueSourcehatnochkeineProviderCI und keinenLiveerfolg. Die historischgrüne
Offline51prüfung gilt fürf5e35ea, nichtfürneueSHA. KeineautomatischePipeline.

**WeitererÜbernahmepfad:** vorhandeneCapture/PublicKeyVerschlüsselung/separater
CiphertextWriter/ImmutableReadbacks/isolierterWPMariaDBRestore ausDreamloud
adaptieren; bestehendeOrgaVelaPGP04-Nachweise weiterverwenden. EigeneQuellidentität,
Tabellenerwartungen,PGPonyEmpfänger und orgavela-backups bleiben getrennt.
VollständigerProduktionscollector ist nochNICHTportiert/ausgeführt. PHP/DBlocks/
Wartung erst in späterer getrenntgegateterCapturephase, nicht im aktuellen
readonlyCheck. KeineneuenpauschalenStartup-Anforderungen/Blindrerruns.

NächsterkonkreterTest benötigtContextrevision+PGP05_APPROVED_SHA auf neueSHA
und PGP05_METADATA_REVIEW_REF aufobigesReview; RemoteBodyHash/privateWertegleich.
Dann neueexakteFeaturebranch-MetadataPipeline, aktuelleOfflineprüfung vorApproval.
CP5unverändert8/10,#8/#10offen,productioncapturefalse. V1offen62/71historisch,
M0–M7 0/8,P4 0/4,R0/18;PR32Draft,alpha.2zuletztbelegt. KeineDreamloudÄnderung,
keineSite-/Backup-/Deploy-/Merge-/Kostenaktion.


## 2026-10-23 — tatsächlicher Metadatenlauf PASS; CP5 9/10
**Neue verbindlicheEvidenz:** Pipeline542,Source8c4b445ea5dced969f63b0f30e0e371342f64680,
Workflowb97d93e3-802e-466d-9124-89a73a553314. GitHub unabhängig bestätigt
Offline54/Approval/Metadata56 alle success:
https://circleci.com/gh/janjrre/Projekt-Wordpress-Plugin/56 .
BetreiberScreenshotimage(20261023-074040).png lokal gelesen: PASS,
authenticated_ssh=true,expected_user_and_root_shape_matched=true,
hostkey_candidate_matches_provider=true. MaskierteSource/Hashwerte nicht
"zurückgelesen"; Sourcebindung überGitHubStatus+geprüfteImplementierung/Context.
GenauerErfolg benötigt erfolgreichenboundedSSH-Prozess,exaktesstdout/leeresstderr.
Providerhostpin jetzt im authentisiertenHandshake genutzt, nichtnurKeyscan.

**UnabhängigeResultatprüfung:** separatbeauftragterPrüfagent las Originalbild
undexakteGitHubstatuses selbst. Kriterium#10 ausdrücklichACCEPTED im begrenzten
MetadataScope,keineexterneHuman-Auditbehauptung.
[Ergebnis-/Reviewbeleg](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/27#issuecomment-6360036292).
**Checkpoint5 jetzt9/10 (90%), genau#8 offen.** DessenAccount-/Namespace-Teil
ist bestanden; nuraktuelleDomain->Installation/DocumentRootRelation muss durch
expliziteProviderUI-Kette komplettiert werden. BisherigeWordPressTarget-/Pfad-
undWP_HOME/SITEURL-Indizien allein ersetzen dievollständigeKette nicht.

**Einzige verbleibende#8Beobachtung:** IONOS orgavela.com aktuelleVerwendung/Ziel
ansehen; beiProjekt/Appziel dessenverknüpfteInstallationbiszumWebspacefolder
verfolgen undprivatmitbereitsgeprüftemRoot vergleichen. ZusammenhängendeUI-Kette
reicht,keineEinbild-LiteralRootpflicht/Providerbrief/neuerSSH-/PHP-/WP-Lauf.
Nichtsverbinden/trennen/speichern/DomainoderDateienändern. Nurrelevante
redigierteAnsichtteilen; keineCredentials/Kundendaten. OffizielleUI-Hilfe:
https://www.ionos.de/hilfe/domains/mit-webspace-verbinden/domain-mit-einem-webspace-verzeichnis-verbinden/
und https://www.ionos.de/hilfe/wordpress/administration-von-wordpress/verbinden-einer-domain-mit-wordpress/ .
DomainvhostFeldimProbeReceipt bleibtfalse; externeEvidenzkann#8erfüllen,ohne
historischesReceipt umzudeuten. NachAbschluss temporärenMetadata-Credentialpfad
bereinigen; nochkeineBereinigungbehauptet.

**Grenzen:** database_connected/php_requested/wordpress_bootstrap_requested,
server_enforced_readonly_account,startup_hooks_independently_observed_by_probe,
production_capture_allowed,isolated_restore_proven bleibenfalse.
FrühereFehlerursache nichtbewiesen; keinweitererRerun nötig. KeinBackup,
DB-Dump,Restore,Deploy,Merge,ScheduleroderKauf. Dreamloudunverändert.
V1offen62/71historisch,M0–M7 0/8,P4 0/4,R0/18,PR32Draft,
alpha.2zuletztbelegt. NachCP5: tatsächlicheCapture-/Writer-/Restoreübernahme
mitgetrenntenGatesweiterbearbeiten; keinProductionCollectorportvorgegeben.


## 2026-10-23 — PGP05 CHECKPOINT5 ABGESCHLOSSEN: 10/10 (100%)
**Kriterium#8jetztACCEPTED:** Betreiberbildimage(20261023-075106).png zeigt
IONOSWeb-ProjektOrgaVela,zugewieseneDomainorgavela.com unddenselbenprivat
verglichenenHostingvertragwiedieaktuelleDomainansicht074717. ZweitesBild
075109istnureinwinzigerAusschnitt,keinzusätzlicherNachweis. Separatebeauftragte
PrüfinstanzhatdasvolleOriginalbild selbstgelesenunddieKombinationsketteabgenommen:
aktuelleDomain->konkretesProjekt/Installation -> vorherakzeptierteprojektbezogene
Explorer-/WordPressSiteHealthRootbelege -> authentisierterMetadatenlauf56.
DasneueBildzeigtdenRootnichtselbst; keinefingierteEinbild-/ProviderAPIprüfung.
KeineVertragskennungen/privatenPfade/Rohbilderöffentlichübernommen.

[Abschluss-/Reviewbeleg](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/27#issuecomment-6360194115).
PrüferistseparaterAgent,keinexternerMenschenaudit. Sourceweiterexakt
8c4b445ea5dced969f63b0f30e0e371342f64680,PR32Draft/unmerged;
GitHubbestätigtOffline54/Approval/Metadata56alle success.127lokale/separat
wiederholteTestsbelegt,keinneuerProviderEinzeltestcountgelesen.
Kriterium#10bereitsseparatalsausgeführterbegrenzterMetadataCheckakzeptiert.

**Alle10festenKriterienerfüllt,keinoffenesCheckpoint5-Kriterium:**
1. IONOSProjekt-/DomainUIbelege.
2. WordPressStruktur/ConfigmodeUIbelege.
3. 52/52InnoDBTabellen-Metadaten.
4. MUPluginDateinameninventar.
5. SynthetischerNoBootstrapScanner/Guards/Tests.
6. UnabhängigertechnischerScope-/SourceReview.
7. PR32Implementierung+aktuelleProviderOfflineCI(keinMerge).
8. Hostpin/authentisiertesKonto/Namespace+separateDomain/Projekt/RootKette.
9. PrivaterWP-/IONOSDBNamensvergleich(owner-attested).
10. BetreiberfreigegebenerrealerMetadatenlauf+redigiertergebnis+separateAbnahme.

**Evidenzgrenzen bleiben:** domain_vhost_binding_provenimProbeReceipt=false,
daProviderrelationexternbelegt; Receiptwirdnichtrückwirkendumgeschrieben.
server_enforced_readonly_account/startup_hooks_independently_observed_by_probe/
database_connected/production_capture_allowed/isolated_restore_provenbleibenfalse.
CP5AbschlussistwederVollbackupportnochBackup-/Deploy-/Merge-/Schedulerfreigabe.
KeineweitereSSH-/PHP-/WordPress-/SQL-/DateioperationindiesemAbschnitt.

**OperativerNachlauf(keinerfundenes11.Kriterium):** nurPGP05_SSH_PASSWORDaus
orgavela-pgp05-reviewed-metadataentfernen. KeinIONOSPasswortreset/Keyrotation,
keineÄnderungandererDeploy-/Backupcontexts. OhnePflichtfeldverweigertder
Metadatentool-SettingscheckvorNetzwerk; Contextrestriktionenstehenlassen.
Bereinigungistvorbereitet,nichtdurchgeführt/nichtbestätigt. Danachkeine
weitereMetadataPipeline. ÜbergangzurvorhandenenDreamloudCapture-/Verschlüsselungs-/
CiphertextWriter-/Readback-/RestoreübernahmefürOrgaVela,eigeneWerte/Gates.
Gesamtissue27bleibtoffen,keinProduktivbackup/Restore/Deploy/Merge/Kauf.

V1unverändertoffen:62/71historischeCodingpakete,M0–M7 0/8,P4 0/4,R0/18,
alpha.2zuletztbelegt. CP5AbnahmeerhöhtkeinenanderenAbnahmezähler.


## 2026-10-23 — Dreamloud-Collector als geprüfter Offline-Kandidat (W0/O03, #27)
Checkpoint 5 bleibt abgeschlossen 10/10 (100%); kein Kriterium offen. Der nächste
Collector-/Verschlüsselungs-/Writer-Baustein liegt auf `feat/pgp05-dreamloud-collector-port-20261023`
als gestapelter Draft auf PR31, mit hart deaktivierten produktiven Entry Points.
16/16 lokale Python-Tests, PHP-Hilfstests, vier PHP-Lints und öffentliche
Verschlüsselungsintegration PASS. Temporärer künstlicher Testschlüssel: Decrypt
und Byte-/Hashvergleich aller drei Komponenten PASS. Separates Agentenreview
nach drei behobenen Befunden APPROVED ausschließlich für Offline-Draft; kein
externes menschliches Audit. Provider-CI des neuen Kandidaten noch offen.
Bericht: [Portierung und Prüfgrenzen](../operations/PGP05_COLLECTOR_PORT_OFFLINE_20261023.md).
Nächster Schritt: genau eine context-/secretfreie CircleCI-Pipeline auf diesem
Branch mit `run_collector_port_tests=true`; danach tatsächlichen begrenzten
Transport/Runtimepins und konkrete getrennte Source-/Writer-Gates fertigstellen.
Kein echtes Backup/Restore/Livezugriff/Deploy/Merge/Schedule. Vollständige produktive
Collectorportierung bleibt offen; production_capture_allowed=false. V1-Zähler
unverändert 62/71, M0–M7 0/8, P4 0/4, R0/18; alpha.2 zuletzt belegt.

## O03/PGP05 – bounded Capture und synthetische WP-Recovery, 06.11.2026

Neue getrennte Capture-/Ciphertext-Writer-/Restore-Einstiege implementiert,27 lokale Regressionen und echte synthetische verschlüsselte Docker-WP/MariaDB-Recovery bestanden. [Belege und verbleibende Live-/Integrationsgates](../operations/O03_BOUNDED_CAPTURE_RESTORE_2026-11-06.md). Kein echter Snapshot/Restore, keine Liveänderung oder neue formale Abnahme; PR62 bleibt Draft.


### O03-Nachtrag 06.11.2026 – Sicherheitskorrekturen zur erneuten Work-Prüfung

PR64 bleibt Draft/unintegriert: ausführbaren Restore von Source-PHP getrennt, tatsächlichen TAR-Inhalt gegen Inventar gebunden und private Controller-/integrierte Engineidentität getrennt geprüft. Lokal34 Regressionen und synthetische PHP-Drop-in-/DB-/Abbruchproben erfolgreich; kein echter Snapshot oder IONOS-Abbruchnachweis. Nächste Zuständigkeit Work: unabhängige Sicherheitsnachprüfung des neuen Kandidaten; anschließend reguläre Parentintegration und Codex für echte Abbruch-/private Sicherungs-/Readback-/Restoreprobe. PR62 Draft4fb50cb; kein Sitechange, alpha.2 letzter Livebeleg. V1-Zähler unverändert62/71 historisch,M0–M7 0/8,P4 0/4,R0/18. Aktuelle Run-/Commitbelege im append-only Arbeitslog.


### O03 – Registrierungsabbruch, lokaler Kandidat 06.11.2026

Work-Befund 44554bd auf Basis 7c170f3 rot reproduziert und korrigiert. Erweiterte synthetische Containerprobe 13/13, 34 Python-Regressionen und ergänzte PHP-Hilfstests grün: eigene registrierte Gruppen einschließlich temporärer vollständiger Registrierung beendet, WRITE-Locks freigegeben, HTTP erholt, fremde Prozesse/Marker erhalten. Unvollständige Identitäten bleiben ohne Signalautorität und liefern Fehlerstatus; der eigene Marker wird trotzdem hashgebunden entfernt. Details/Prüfgrenzen im Arbeitslog docs/operations/ASSISTANT_WORK_LOG.md. Work-Nachprüfung, Provider-CI und Parentintegration offen; PR62/64 Draft und ungemergt. Kein IONOS-Abbruch, Snapshot, Rückdownload oder Restore. Zähler unverändert: 62/71 historisch, M0–M7 0/8, P4 0/4, R0/18; alpha.2 letzter Livebeleg.
