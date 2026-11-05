# UOP Core – verbindliche V1-Abnahmematrix und Prüfnachweise

**Version 1.0, 12.10.2026. Status: keine finale V1-Abnahme.**  
**Masterplan:** [V1_MASTER_PLAN.md](V1_MASTER_PLAN.md) · **Live-Fortschritt:** [V1_STATUS.md](V1_STATUS.md).  
Der jeweils verantwortliche Reviewer dokumentiert für jeden Test **Test-ID, exakte Plugin-/Git-SHA, Umgebung, synthetische Fixture-ID, Schritte/Eingaben, erwartetes und tatsächliches Ergebnis, CI-/Browser-Run-ID oder sanitisierten Screenshot, Defect-Issue, Datum und Freigabe**. Ein früherer grüner Lauf kann als Vorbeleg dienen, ist aber keine Abnahme einer neueren V1-Version.

## A. Verbindliche Abnahmestufen

| Gate | Ergebnis, das tatsächlich vorliegen muss | Wer bestätigt |
| --- | --- | --- |
| **A0 – Spezifikation** | Scope/ADRs, Rollen und V1-MUST-/OUT-Matrix unverändert oder genehmigte Change Requests | Produktverantwortlicher |
| **A1 – Code Review** | M0–M6 plus #7–#10 geprüft, in richtiger Reihenfolge in main, ausstehende PRs/Drafts beseitigt | Entwickler + unabhängiger Reviewer |
| **A2 – CI/Build** | alle Pflicht-Checks auf genau einem Git SHA grün, reproduzierbares Paket mit SHA-256/SBOM, kein Critical/High | Entwickler/Security |
| **A3 – Migration** | fresh-install, v1→v2→V1, Re-Install, DB-Schema, Interrupted-Run und Restore vollständig nachgewiesen | Entwicklung/Betrieb |
| **A4 – Fachliche E2E** | Adult, Guest, Guardian, Staff, Waitlist, Consent und Privacy vollständig mit echten REST/Browser-Strecken | Produkt/Fachteam |
| **A5 – Nichtfunktional** | Security, Last, WCAG, Mobile, Backups, Cron, Mail, Datenschutz und Monitoring bestanden | Security/Betreiber |
| **A6 – RC** | exakt signiertes/versioniertes ZIP getestet, kein offener Blocker; Changelog/Handbücher vollständig | Release-Verantwortliche |
| **A7 – V1 formale Abnahme** | unterschriebenes/erklärtes Abnahmeprotokoll und separate Veröffentlichungsentscheidung | Betreiber |

Jeder nachfolgende Fall besitzt zur Baseline den Status **OFFEN FÜR V1**. Historische erfolgreiche Teststrecken sind **verifizierte Vorarbeit** und dürfen nach erfolgreicher Aktualisierung für denselben Releasekandidaten angerechnet werden.

## B. Identität, Berechtigungen, Personen – S01 bis S09

| ID | Testablauf und Erwartung | Kanal/Prüftyp | Gate |
| --- | --- | --- | --- |
| **S01** | Zwei Konten besitzen identische Familien-E-Mail, aber getrennte Personen; weder Links noch Rechte/Exportdaten entstehen automatisch. | WP + REST + Portal + DB | A4/A5 |
| **S02** | Org A versucht per UUID/REST/Admin/CPT/Export auf Org B zuzugreifen; Antwort verborgen/403/404, kein Datenleck. | Multi-Org/Policy | A4/A5 |
| **S03** | Rolle „Viewer“ sieht ausschließlich freigegebene Felder, auch in CSV, Audit, Error Responses und Portal. | Field Projection | A4/A5 |
| **S04** | Delegation wird bei offener Browser-Session widerrufen: nächste REST-/UI-Aktion verweigert, kein Cache-Leak. | Portal/REST | A4/A5 |
| **S05** | Abgelaufene, inaktive oder falschem Event zugeordnete Delegation kann nicht gewährt/genutzt/replayed werden. | Policy/DB | A4 |
| **S06** | WordPress-Account-Entfernung entkoppelt Person, widerruft Zuordnungen, verliert nicht unnötig historische Registrierungsnachweise. | Account/Privacy | A3/A4 |
| **S07** | Staff kann sich nicht selbst Ceiling-Rechte verleihen; Reviewer=Antragsteller wird bei Guardian-Antrag verweigert. | Admin/REST | A4/A5 |
| **S08** | CRUD über alle Kanäle führt identische Org-/Objekt-/Feldberechtigungen aus; keine „Schattenberechtigung“. | Code Review + E2E | A1/A5 |
| **S09** | Abgelaufene Session/Nonce führt zu 401/403, private REST-Antworten enthalten keine Personendaten; Re-Login nötig. | Browser/HTTP | A4/A5 |

## C. Event/Form, Registration, Capacity – F01 bis F12

| ID | Prüffall und Erwartung | Testart | Gate |
| --- | --- | --- | --- |
| **F01** | Staff erstellt Event mit korrekter org-/wp_post-Autorisierung, Start/Ende und Zeitzone inkl. DST-Grenze. | Admin/REST + DB | A4 |
| **F02** | Entwurfsformular versionieren und veröffentlichen; bereits publizierte Version/Snapshots bleiben unverändert. | Builder/DB | A4 |
| **F03** | Condition AST: Zyklus, nicht vorhandene Referenz, verbotene private Alterstatsache → serverseitig sicher abgelehnt. | Service/REST | A2/A4 |
| **F04** | Erwachsene Person veröffentlichtes Formular → Consent → REST → Annahme/Portal, genau ein Datensatz/Audit. | Chromium+REST | A4 |
| **F05** | Dasselbe Formular zweimal senden, stalen Client / Retry simulieren: idempotente oder explizit sichere Konfliktbehandlung, keine doppelte Kapazität. | E2E/Concurrency | A4/A5 |
| **F06** | Gast registriert → keine Platzvergabe vor E-Mailprüfung; private 24h Einmalmail, Einmallink nur über echte empfangene Mail, Wiederholung verweigert. | HTTPS + SMTP-Testpostfach | A4/A5 |
| **F07** | Volle Veranstaltung: zugelassener Gast auf FIFO-Warteliste; nach Admin-Storno entsteht genau ein 48h Angebot, private E-Mail, einmalige Annahme. | REST + DB + Mail + Browser | A4 |
| **F08** | 50+ parallele Registrierungen für letzten Platz: eine Bestätigung, keine Überbuchung/Double Offer; mehrere DB-/PHP-Konfigurationen. | Mehrprozess-Stress | A2/A5 |
| **F09** | Angebot abgelaufen/erneut angeklickt/fremder Token/falsche Organisation: denied, keine Platzvergabe und kein Bearer-Leak. | Security E2E | A4/A5 |
| **F10** | Event-Verschiebung/Abbruch auch bei 100+ Anmeldungen: Snapshot intakt, Claims/Offers atomar freigegeben, Audit konsistent. | Integration/Failure Injection | A3/A4 |
| **F11** | Abgemeldeter/abgelaufener/delegierter Actor versucht POST: endgültige Berechtigung serverseitig frisch geprüft. | REST Policy | A4/A5 |
| **F12** | Deaktivierter JS-Client, Mobile 320px, langsames Netzwerk: semantische Fehler-/Ladezustände und kein versehentliches Duplicate Submit. | Browser+Keyboard | A4/A5 |

## D. Minderjährige, Altersnachweise und Consent – P01 bis P12

| ID | Prüffall und Erwartung | Notwendiger Nachweis | Gate |
| --- | --- | --- | --- |
| **P01** | „Alter unbekannt“ erlaubt keine Eventregel, die einen verifizierten Altersstatus verlangt; weniger restriktive Policy funktioniert unabhängig weiter. | #7 Policy/REST/Browser | A4 |
| **P02** | Selbst angegebenes Alter ist **nicht** als verifiziert ausgewiesen oder für geprüfte Eligibility zugelassen. | UI/REST/Export | A4 |
| **P03** | Extern geprüfte Altersklasse ist Zweck, Org, Event und Schwelle zugeordnet, hat Status/Gültigkeit/Prüfer; keine unnötige Ausweiskopie. | #7 Audit + Datenschutz | A4/A5 |
| **P04** | Alter attestiert, danach Event privat, Attest abgelaufen oder widerrufen: alle positiven Eligibility-Auswertungen stoppen. | #7 cross-channel | A4 |
| **P05** | Guardian-Claim/Einladungscode/gleiche E-Mail ohne gesonderte Freigabe gewährt **null** Berechtigungen. | #9 Rechte-/Abuse-Test | A4/A5 |
| **P06** | Unabhängiger, dokumentiert berechtigter Reviewer akzeptiert eng befristeten Scope; Reviewer ist nicht Antragsteller. | #9 Admin-Browser + Audit | A4 |
| **P07** | Zwei Vertreter/Mehrfachvertretung, widersprüchliche Ansprüche, Ablauf und Widerruf: aktuelle Rechte konsistent und sofort neu abgeleitet. | #9 Integrationsmatrix | A4 |
| **P08** | Consent-Zweck mit ausschließlich Selbstentscheidung: separate Historie, Vertreter nicht automatisch erforderlich. | #8 ADR/Version/REST | A4 |
| **P09** | Consent-Zweck mit ausschließlich legitimer Vertretungsentscheidung: separate Actor-/Subject-Evidence, Zeitpunkt, Textversion. | #8 REST/DB/UI | A4 |
| **P10** | Consent-Zweck mit **zwei unabhängigen** erforderlichen Entscheidungen: erst nach beiden wird Status complete; ein Account/Session kann nicht beide vortäuschen. | #8 zwei Actor-Tests | A4/A5 |
| **P11** | Eigen- oder Vertreterentscheidung wird unabhängig widerrufen; aktueller Consent wird ungültig, historische Beweise bleiben unverändert. | #8 Revocation/Export | A4 |
| **P12** | Minderjährigen-Golden-Paths samt Alterskontrolle, Einladungen, Consent, Portal, Admin, Screenreader/mobile und zweckbezogener rechtlicher Dokumentation. | #10 vollständige Review-Signatur | A4/A5 |

**Rechtliche Grenze:** Technische Consent-Evidence, eine Staff-Checkbox oder ein Jugend-/Kind-Label ist keine amtliche Identitäts-, Alters- oder Sorgerechtsprüfung. Zweckbezogene zulässige Verfahren/Verantwortlichkeiten liegen in der Freigabe der Einsatzorganisation und müssen dokumentiert sein.

## E. Datenschutz, Kommunikation, Export, Arbeitersystem – D01 bis D10

| ID | Prüffall und Erwartung | Testart | Gate |
| --- | --- | --- | --- |
| **D01** | Einwilligung exakt an immutable Dokument-/Formularversion mit getrennten Actor und Subject gebunden; keine zweite Wahrheit im Snapshot. | DB/REST/Audit | A4 |
| **D02** | Consent-Entzug stoppt nachfolgende Nutzung gemäß Policy, frühere Historie bleibt erforderlichenfalls als Nachweis. | Service/Privacy | A4 |
| **D03** | E-Mail-Vorlagen auf DE/EN, Variablen-escaping, niemals Bearer in Betreff/REST/Audit. | Template/Unit/E2E | A2/A4 |
| **D04** | Action Scheduler und Outbox nach Cron-Einrichtung: Queue tatsächlich abgearbeitet; ein fehlgeschlagener/ambiguous Versand nicht unkontrolliert doppelt gesendet. | IONOS Worker-/SMTP-Run | A5 |
| **D05** | WordPress-Privacy-Export/Eraser respektiert Person ≠ E-Mail, schützt fremde Familienkonten. | WP Datenschutz-Tools | A4/A5 |
| **D06** | Retention-Hold/Rechtspflicht/Lösch-Policy, Dry-Run und Cursor-Replay; kein irreversibler Datenverlust ohne Freigabe. | DB/Service/Betreiberreview | A5 |
| **D07** | Private Exportdateien nie weböffentlich; Scope-/Role-Recheck beim Erstellen, Verarbeiten und Download; 24h-Ablauf. | Pen/REST/Filepath | A5 |
| **D08** | CSV enthält nur berechtigte Felder, korrektes UTF-8; Formelpräfix-Schutz für =,+,-,@. | Export/Security | A5 |
| **D09** | Logs/Site Health/Audit zeigen minimale Diagnostik, keine Passwörter, private E-Mailkörper, Token oder andere PII. | Betrieb/Security | A5 |
| **D10** | Teilnehmer-, Export-, Mail- und Auth-Fehlpfade bieten verständliche Lade-/Fehler-/Recovery-UI und korrekte Retry/Queue-Status. | Browser/Operator | A4/A5 |

## F. Migration, Betrieb, Sicherheit, Performance – B01 bis B12

| ID | Prüffall und Erwartung | Testart | Gate |
| --- | --- | --- | --- |
| **B01** | Frische WP-Installation inkl. MySQL/MariaDB, neuestes UOP-ZIP aktiviert; genau erwartete Tabellen/Indizes/Org, keine Fehler. **M1-08 zusätzlich:** `uop_schema` liest das aktuelle 29-Tabellen-Schema, Migration 2/2/complete sowie gespeicherten Default-Org-Verweis auf genau eine tatsächlich aktive Organisation; Negativtest: archivierte Organisation/falscher Verweis ergibt `critical` ohne automatische Reparatur. Site-Health-Befund und DB-Metadaten auf *installiertem* Release-SHA separat belegen. | Install/Schema Health + M1-08-Negativtests + unabhängige OrgaVela-Liveprüfung | A3 |
| **B02** | Bereits installierte Alpha mit schema 2 auf Kandidat upgraden, kompatibel und ohne Datenverlust, neuer Source/Hash exakt geprüft. | isolierter Upgrade-Klon | A3 |
| **B03** | Unterbrochene Migration, Lock-Takeover, Deadlock, defekter Index: fail-closed, protokolliert, Recovery erfolgreich. | Failure Injection | A3/A5 |
| **B04** | Datenbank-/Datei-Backup wird auf isolierter Umgebung real wiederhergestellt, übereinstimmende Datenversion und Funktion. | IONOS Restore-Protokoll | A3/A5 |
| **B05** | WP-Cron deaktiviert? Belegter externer Cron-Runner mit Fälligkeits-/Überfälligkeitsprobe und Alarm. | IONOS Cron/Scheduler | A5 |
| **B06** | Externe E-Mail-Zustellung in autorisiertes Testpostfach, SPF/DKIM-/Sender-/Bounce-Regeln; keine produktiven Testpersonen. | synthetischer Mailbetrieb | A5 |
| **B07** | GitHub-SFTP/SSH Least Privilege; kein Zugang zu Dreamloud/anderen Projekten; Secret-/Hostkey-/Checksum-Guards bestehen. | Scope-Negativtest/Review | A5 |
| **B08** | Site-URL HTTPS und erforderlicher Testzugriffsschutz/Noindex vorhanden, vor Freigabe eigener Publikationsentscheid. | Public Surface/Robots | A5 |
| **B09** | PHP 8.3/8.4/8.5 x MySQL 8.0/MariaDB 10.11; unterstützte WP-Versionen und Browser-Testmatrix vollständig aktuell grün. | CI-Matrix | A2/A5 |
| **B10** | Lastfixtures 100, 10.000, 100.000 Personen inkl. Zeit-/Querybudgets und aktiver Indizes; keine N+1-Ausreißer. | M7 Performance Report | A5 |
| **B11** | Manuelle WCAG 2.2 AA/Keyboard/Screenreader/320px für alle fünf Blocks, Admin, Builder, Portal, Consent-UI. | A11y-Protokoll mit Findings | A5 |
| **B12** | Sicherheits-Angreifermatrix BOLA/IDOR, CSRF, XSS, SQLi, CSV, Token, Replay, Privilege, Supply Chain; kein offenes Critical/High. | M7 Security Report | A5 |

## G. Release und Verantwortung – R01 bis R08

| ID | Ergebnis | Muss vorliegen |
| --- | --- | --- |
| **R01** | Feature Freeze | sämtliche V1-MUST-Funktionen, Versionen, Scope-/Deferred-Issues, ADRs |
| **R02** | Integrations-Freeze | keine gestapelten PRs offen, main grün, Review-Gates |
| **R03** | Betreiberbereitschaft | Backup/Restore, Cron, Mail, Datenschutz, Monitoring, SFTP |
| **R04** | Fachliche Akzeptanz | alle relevanten S/F/P/D-Fälle bestanden |
| **R05** | Nichtfunktionale Akzeptanz | B01–B12 bestanden, keine High/Critical |
| **R06** | RC-Paket | ZIP-SHA, Version, SBOM, Changelog, Upgrade/Uninstall |
| **R07** | Formale V1-Abnahme | Betreiberfreigabe mit Version, Commit, Run-Links, Restrisiken |
| **R08** | Separate Veröffentlichung | öffentliche Ausrollung explizit autorisiert, Monitoring/Support |

## H. Vorbelege vom 12.10.2026 – NICHT finales Release-Gate

- **OrgaVela SFTP Probe:** [38239184433](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38239184433) – erfolgreich, /public.
- **OrgaVela Paketupload:** [38241807035](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38241807035) – erfolgreich, ursprüngliches Alpha-2-Paket. Aktivierung und WP-Migrationsoptionen 2/2 separat bestätigt, SQL-Health noch nicht.
- **Quelle des Alpha-Builds:** SHA 4f2c4df800caf1e5708f05cce9536a7afb34aaa0; historisch [Foundation 38225097092](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38225097092) und [HTTPS E2E 38225097051](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38225097051) grün.
- **Letzter PR-#11-HEAD:** 1b98658704e1b4e8fb5350d7817e20af4fdecc35; Push Foundation [38233559467](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38233559467) 8/8, PR Foundation [38233565475](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38233565475) 7/8, PR HTTPS [38233565383](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38233565383) rot. Das ist aktuell **kein gültiges A2-Gate**.
- **Achtung:** E2E mit lokalen Mailpit-Mails beweist die Funktion der isolierten CI-Transportstrecke, **nicht** Zustellung über einen echten externen Mailprovider.
- **Aktivierung:** zwei UOP-Optionswerte 2/2 beweisen **keine** Prüfung jedes realen DB-Index. Noindex, Cron, Backup, Mail und zweckbezogener Minderjährigen-Nachweis bleiben offen.

## I. Ablage des finalen Abnahmeprotokolls

Erst beim tatsächlichen RC wird eine Datei **V1_RELEASE_SIGNOFF.md** angelegt, mit: freizugebender Version/Plugin-ZIP-SHA256/Source-SHA, Ziel-WP-/PHP-/DB-Matrix, Testfälle/Sicherheitsbefunde, manuelle Accessibility-/Datenschutzfreigaben, Protokoll eines getesteten DB-Restores, Ergebnis aller R01–R07-Pakete, verantwortliche bestätigende Person und Datum. Vorher nicht als „signiert“ simulieren.

**Stop-Bedingung:** Beim ersten Critical/High ohne akzeptable, dokumentierte und geprüfte Lösung kein Stable-/öffentlicher Go-Live, selbst wenn ein Workflow grün ist.


### O03 / B04 Belegabgleich (12.10.2026 – **KEINE B04-Abnahme**)

| Teilnachweis | Tatsächlich beobachtet | B04-Bewertung |
|---|---|---|
| Live-Backupvoraussetzungen auf OrgaVela | [SSH-/WP-CLI-Read-only-Audit #38267570599](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38267570599): `mysqldump`/`mariadb-dump`, MySQL-Client, `tar`, `gzip`, `sha256sum` vorhanden; DB unter 25 MiB, Dateiumfang 100–500 MiB, freier Webspace über 5 GiB | **Hilfsbeleg erfüllt**, noch keine Backup-Datei |
| Technische SQL-/Datei-Restore-Prozedur mit synthetischen Fixtures | [isolierter MariaDB-11.8-Test #38267901371](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38267901371): dump+archive+Hashes, separate Test-DB und -Dateien, 2 synthetische Datensätze, Indexkontrolle **grün** | **Hilfsbeleg erfüllt**, **keine** produktive Sicherung benutzt |
| IONOS-OrgaVela-Backupzeiten und Wiederherstellungspunkte | Anbieterhilfe beschreibt bis 14 Tage bei regulärem Webhosting; kein Zugriff auf **konkrete** Konto-Backup-Liste | **Offen** |
| Echtes privates unabhängiges OrgaVela-SQL+Datei-Backup | Nicht erstellt, nicht auf sicheren Offsite-Speicher kopiert, Hash nicht verifiziert | **Offen** |
| Tatsächliche Wiederherstellung dieser OrgaVela-Sicherung im nichtöffentlichen Clone | **Nicht durchgeführt** | **Offen – blockiert B04/R03/Release** |

Bewusst **nicht** B04/R03 als grün zählen. [Operatives O03-Runbook](../operations/W0_O03_BACKUP_RECOVERY_RUNBOOK.md) benennt den erforderlichen echten Offsite-Backup- und isolierten Restore-Nachweis; [Masterplan v2.4](../operations/V1_ACCEPTANCE_PLAN.md) und [kanonischer Fortschritt](../operations/V1_PROGRESS.json) bleiben maßgeblich. Keine produktiven Migrationen/Upgrades vor Erfüllung.


### O03 / B04 Speichervariante seit Betreiberentscheid 13.10.2026 – **Private GitHub Releases statt Google Drive**

**Korrektur zu historischen Aussagen oben:** Die dortige allgemeine Forderung eines unabhängigen „Offsite“-Speichers wird nach neuer **expliziter** Betreiberentscheidung durch **separate private GitHub Release-Assets** als einziges selbstverwaltetes Backupziel neben IONOS ersetzt. Google Drive ist **nicht mehr** als O03-Ziel autorisiert. Diese Speicherwahl senkt die Zahl unabhängiger Anbieter; die korrelierte GitHub-Konto-/Ausfallproblematik wird im neuen [Runbook](../operations/W0_O03_GITHUB_PRIVATE_BACKUP_RUNBOOK.md) offengelegt und von der Wiederherstellungsanforderung **nicht** befreit.

| Neuer B04-Einzelnachweis | Status 13.10.2026 |
|---|---|
| Separates `private` GitHub-Backup-Repository inkl. geprüfter Besitzer-/Schreibrechte | **Offen** – bevorzugtes `janjrre/orgavela-backups` wurde **noch nicht** angelegt/verifiziert; Connector besitzt keine Repo-Erstellungsaktion |
| Verschlüsselung vor GitHub-Upload, Betreiber besitzt privaten Recovery-Key offline | **Offen** – kein privater Key in GitHub/CircleCI erlaubt |
| Authentifizierter CircleCI-Free-GitHub-Release-Push ausschließlich mit auf Backuprepo begrenztem Token | **Offen** – Context/Security/Token erst Betreiber-Setup und Review |
| Synthetischer verschlüsselter GitHub Release Canary inklusive vollständigem Download/Hash/Entschlüsselung | **Offen** – bisher nur der secret-freie CircleCI-Offline-Runner [#2](https://circleci.com/gh/janjrre/Projekt-Wordpress-Plugin/2) grün |
| Tatsächlicher vollständiger OrgaVela-SQL+WP-Datei-Snapshot in verschlüsselten privaten Release-Assets und vollständiges Remote-Readback | **Offen** |
| Isolierter Restore **dieses echten Releases** in sicherem WP/MariaDB-Klon, E-Mail/Jobs/Egress deaktiviert, 29 Tabellen/Version 2/2/Dateien geprüft | **Offen, formale B04-Abnahme gesperrt** |
| IONOS-OrgaVela-Vertragsbackups und konkrete Restorepunkte im Hosting-Konto überprüft | **Offen**, allgemeine Anbieterangaben genügen nicht |

Abnahme nur bei belegtem GH-01…GH-07 und tatsächlichem IONOS-Recoveryinventar. [Masterplan v2.11](../operations/V1_ACCEPTANCE_PLAN.md) · [Maschinenstatus v2.11](../operations/V1_PROGRESS.json); Release-Gates (darunter R03 und R15) bleiben ausdrücklich offen. Keine Datensicherungs- oder Löschaktivität in diesem Matrix-Nachtrag.


### O03 – Erneuter synthetischer Restore-Negativtest 04.11.2026

**Technischer Vorbeleg, kein W0-/B04-/R03-Signoff:** [PR #46](https://github.com/orgavela/Orgavela-Plugin/pull/46), SHA `ebe2ed3eb791e9dfc38237f5fb39bb58122285ad`, [echte GitHub-CI #41867617337](https://github.com/orgavela/Orgavela-Plugin/actions/runs/41867617337), Status **SUCCESS**. Wegwerf-MariaDB 11.8: synthetischer SQL-Dump und synthetisches Dateipaket einzeln absichtlich verändert; beide Integritätsprüfungen **mussten scheitern**, gültige Kopien wurden erneut geprüft, Zieldatenbank vor Restore leer, danach synthetischer Daten-/Index-/Datei-Roundtrip erfolgreich. Kein IONOS-Dump, kein personenbezogenes Material, kein echtes Restore. Die drei Guard-Negativtests für Repo/Runner/Ref waren ebenfalls erfolgreich. `ORGAVELA_RESTORE_DRILL_LIVE_BACKUP_USED=no` und `ORGAVELA_RESTORE_DRILL_LIVE_RESTORE=no` wurden im Job geloggt.

**B04/O03/R03 formal weiter offen**, bis unabhängige Nachweise einer echten verschlüsselten, unverfälschten `orgavela.com`-DB-/Dateisicherung und eines tatsächlich isolierten Restore-/Recovery-Pfads vorliegen. Keine Minderung der bestehenden Abnahmekriterien.


### W0-Integrationsnachtrag 05.11.2026 – reale IONOS-Read-only-Cronmessung und synthetisches Restore auf main

**Keine formelle Release-/W0-Abnahme:** Der ergänzte O04-Live-Nachweis ist [GitHub Actions #42036870948](https://github.com/orgavela/Orgavela-Plugin/actions/runs/42036870948), `readonly-audit=SUCCESS`. Echte WP-/Action-Scheduler-Statuswerte: `WP_AUTO=disabled`, `QUEUE_EVENT=due-within-5m`, `PENDING_OVERDUE_BUCKET=none`, `FAILED_BUCKET=1-10`, `EXTERNAL_TRIGGER=not-verifiable-from-wordpress`. **Noch offen:** IONOS-Control-Center-Cronjob und letzte Ausführung, Failures nach Alter/Ursache, betriebliche Fehlermeldung/Alarm, Mail-Outbox-Golden-Path. O04/R08 bleiben **blocked/red**. Der Worker wurde **nicht** ausgeführt, es fand keine Migration statt.

O03-Entwicklungsnachweis nach gesichertem Merge von [PR #46](https://github.com/orgavela/Orgavela-Plugin/pull/46): auf `main` SHA `98a064fc6c874055e9676f5e60e9ac4cade964d1` ist [GitHub Actions #42038116880](https://github.com/orgavela/Orgavela-Plugin/actions/runs/42038116880) **SUCCESS**. Der reale CI-Lauf stellte nur synthetische Datenbank und Dateien wieder her und prüfte die beiden absichtlich korrupten Eingabearchive; keine echte OrgaVela-Sicherung oder Wiederherstellung. Separat meldet [CircleCI #92](https://circleci.com/gh/orgavela/Orgavela-Plugin/92) `source-integrity=SUCCESS`. **Noch offen:** IONOS-Backupinventur mit vertrauenswürdigen Wiederherstellungspunkten, privater echter verschlüsselter DB+Dateien-Snapshot, konsistenter Write-/Capture-Pfad und ein isolierter echter Restore; O03/B04/R15 bleiben **partial/offen/rot**.

Die private Backup-Repo-Inventur ergab nur drei historische synthetische GitHub-Releases, keine reale `orgavela.com`-Sicherung. Der aktuelle geplante Kryptografie-/Handoff-Pfad ist **OpenPGP/PGPony**; das ältere Age-Release ist kein produktiver Proof. Alle normativen Sicherheits-/Datenschutz-/Minderjährigen-/Release-Gates bleiben ungeändert; keine Teilnehmerdaten freigegeben.
