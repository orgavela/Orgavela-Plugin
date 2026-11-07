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


### O03-Integration 07.11.2026 – nach Work7041789

PR65/64/36/33/31/30/29 regulär zusammengeführt bis c991fde, kein Hostingzugriff. Historischer PR28-Zielref ist eingefroren (GitHub405); bleibt unverändert. Eigener regulärer Main-Integrations-PR enthält die vollständige Parent-Historie und bewahrt die aktuelle App-CI sowie aktuelle Status-/Ops-Belege. Der neue Main-Stand benötigt eigene Pflicht-CI; erst danach darf der private Source-only-Controller an ihn gebunden werden. Recoveryjob ist vorbereitet, sein praktischer Zugriff und die getrennten IONOS-Preflight-/Exception-/Hard-Disconnect-Proben bleiben vorerst offen. Details im append-only Arbeitslog. Kein Snapshot, Release oder Restore; V1 unverändert offen,62/71 historisch,M0–M7 0/8,P4 0/4,R0/18,alpha.2 letzter Livebeleg. PR62 unberührt.


### W0/O03-ABORT-IONOS-20261107 – isolierter technischer Livebeleg

Engine57fd2e6 / Controllerddc7c63,07.11.2026UTC. Keine formale R-/V1-Gateabnahme.

| Teilprüfung | Tatsächlicher Beleg | Ergebnis/Prüfgrenze |
| --- | --- | --- |
| Reguläre Integration und exakte CI | PR66; Main-Runs42319085653/42319085654/42319085659 + echter CircleCI | PASS, vollständige Parent-Ancestry, eingefrorene Testrefs unverändert |
| Unabhängiger Recoveryzugriff | privater Run42319787014, separate Linuxjobs/SSH-Sitzungen | PASS,eigene Canary/Hardlinks entfernt; keine Provider-Konsole behauptet |
| IONOS-Preflight ohne Wartung | Run42319993924 | PASS,CLI8.3.36,POSIX/setsid/proc/nohup/Hardlink,Schema/Locks; kein .maintenance/Snapshot |
| Kontrollierte Exception | Run42320128895,job128242551763 | PASS,eigene Prozesse/WRITE-Locks/Marker/Origin einzeln bestätigt |
| Harter SSH-Kanalabbruch | Run42320380871,job128243378650 | PASS,Remote-ARM,eigene sleep-Gruppe,Abbruch,neue Beobachtersitzung,Watchdogquittung/Prozessscan/Locks/Marker/Origin |
| Echter Snapshot/Release/Rückdownload/Restore | nicht Teil dieser isolierten Probe | OFFEN,separater O03-Abnahmeblock |

Alle drei Wartungs-/Preflightquittungen snapshot_created=false; keine Dump-/Archiv-/Verschlüsselungs-/Publisherfortsetzung. Fremdprozess-/Fremdmarkergrenze und tatsächliche TAR/Dump-Nachkommen wurden in13 synthetischen Fällen geprüft, nicht als zusätzliche Fremdmarker auf der Website injiziert. Aktuelle Pluginversion nicht neu abgefragt; alpha.2 bleibt letzter entsprechender Livebeleg. V1-Zähler62/71 historisch,M0–M7 0/8,P4 0/4,R0/18 unverändert. Detaillierte sanitiserte Protokolle/Verantwortung im append-only Arbeitslog, operative Rohdaten verbleiben privat.


### W0/O03-BACKUP-20261107 – integrierte Pipeline, synthetischer Release-Restore, echte TLS-Sperre

| Prüffall | Tatsächlicher Beleg | Ergebnis und Prüfgrenze |
| --- | --- | --- |
| Exakter integrierter Engine-Commit und CI | `191df34`; Foundation `42362634089`, O03 `42362646410`, Truth `42362634121`, echter CircleCI | PASS: 44 Regressionen, 13 Abbruchfälle, Protokollprobe; keine Gesamtabnahme |
| Rechte von Capture, Publisher und Receiver | Privater PR #3 / `22f0715`; Main-CI `42362765358`; 8+3 Offlineprüfungen | PASS: Hosting nur im Source-Schritt, Release-Schreibrecht nur beim Publisher, privater Schlüssel nur im Receiver-RAM |
| Gemeinsamer Ciphertext, Veröffentlichung, vollständiger Rückdownload und Restore | Synthetischer Run `42362945948`; unveränderliches Release `437494217`, drei Assets | PASS ausschließlich synthetisch: 52 Tabellen, 3.354 Dateien, Snapshot-PHP inert, Ziel entfernt |
| Neue unabhängige Recovery und Preflight | IONOS `42363188793` / `42363309280` auf derselben SHA `22f0715` | PASS; keine Wartungsmarker oder Snapshots |
| Tatsächlicher Dump mit TLS und Zertifikatsprüfung | IONOS `42363410543` | FEHLER 2026 vor Wartung und Snapshot; TLS-Ursache noch zu prüfen |
| Archiv-/Laufzeitgrenzen, echter Snapshot, tatsächlicher Release-Restore | Kein neuer Produktionsbeleg | OFFEN; erst nach geprüfter TLS-Lösung |

O03/B04/R-Gates bleiben formal offen. Der zusätzliche temporäre Empfänger ist nur für diese Abnahme ausdrücklich autorisiert; der Owner-Schlüssel bleibt unverändert. Kein Owner-Privatschlüssel und keine Source-Rohdaten im öffentlichen Beleg. Kein Pluginversionswechsel, keine Migration oder Fachabnahme. Stand: 62/71 historisch, M0–M7 0/8, P4 0/4, R 0/18; Alpha.2 bleibt letzter Livebeleg, PR #62 bleibt Draft.

Diagnose-Nachtrag: Engine `dc9e3ca` / Controller `d34d2aa`, exakte CI vollständig grün. Neue Recovery `42364266996` und Preflight `42364398492` PASS; TLS-Wiederholung `42364472006` endet vor Wartung/Snapshot mit `dump_tls_certificate_untrusted`. Neue synthetische TLS-Fixture: expliziter System-CA-Speicher ermöglicht tatsächlichen Dump; unbekannte CA und falscher Hostname werden abgewiesen. Kein IONOS-Positivbeleg dieser Korrektur, keine formale Abnahme.


**O03-Systemtrust-Nachtrag 07.11.2026:** Engine `ec25173` vollständig auf Main CI-geprüft. Private Inventur-PRs #6/#7 integriert; Main-CI `42366532777`, neue Recovery `42366606695` und Preflight `42366729353` erfolgreich. Die erste Inventur `42366212019` war an einem eigenen Quittungsvariablenfehler korrekt gescheitert; tatsächliche PHP-Ausgabe wird jetzt zusätzlich im Container gegen den Validator geprüft. Aktuelle Metadaten zeigen lesbaren Standard-CA-Speicher unter einem anderen Systemkonto als UID 0, ohne Source-Eigentum/Schreib- oder unmittelbares Ersatzrecht. Die bisherige UID-0-Prüfung wird gezielt durch Bindung an den geschützten Laufzeiteigentümer und sichere Pfadvorfahren ersetzt. Synthetische Tests bestätigen positiven TLS-Dump mit abweichendem Systemkonto und Ablehnung eigener/beschreibbarer/ersetzbarer Truststores, unbekannter CA und falschen Hostnamens. Kein neuer IONOS-TLS-Positivbeleg, kein echter Snapshot/Restore und keine formale Abnahme.

**Finale Eigentümerregel dieser Anpassung:** Für CA-Speicher und PHP-Laufzeit wird jeweils separat nachgewiesen, dass sie einem vom Sourcekonto verschiedenen Systemkonto gehören und samt kanonischen Vorfahren nicht unter Sourcekontrolle stehen. Unterschiedliche geschützte Systemkonten sind zulässig; eine zusätzliche Gleichheitsannahme zwischen CA- und Runtime-UID ist für den unveränderbaren festen Betriebssystem-Trustpfad nicht erforderlich. Die Fixture prüft deshalb ausdrücklich zwei unterschiedliche System-UIDs. Keine Zertifikatsprüfung, Pfadschutz- oder Source-Schreibsperre wird dadurch abgeschaltet.


### O03-TLS-FINAL-20261107 – echter verbleibender Blocker

| Kriterium | Exakter Beleg | Stand |
| --- | --- | --- |
| Integration und CI | Engine `0f0676d`: Foundation `42367959744`, O03 `42367959597`, Truth `42367959617`, CircleCI; Controller `3655abf`, CI `42368110168` | PASS technisch |
| Finale unabhängige Recovery und Preflight | IONOS `42368230230` / `42368333459` auf derselben Controller-SHA | PASS, kein Marker/Snapshot |
| System-CA-/Runtime-/Vorfahrenintegrität | Tatsächliche Probe `42368432293` erreicht `dump_client_validated` und anschließend TLS | PASS, feste OS-Pfade; Source-Schreib-/Ersatzrechte ausgeschlossen |
| Tatsächliches DB-Zertifikatsvertrauen | Dieselbe IONOS-Probe endet `dump_tls_certificate_untrusted`, Fehler 2026 | GESPERRT, verifizierbare Provider-CA/Kette oder gültiger TLS-Endpunkt fehlt |
| Echter Dump, Archiv-/Zeitgrenzen, Produktionsrelease und dessen kompletter Rückdownload/Restore | Kein positiver Produktionsbeleg | OFFEN, vor Wartung/Snapshot gestoppt |

Eine unabhängige öffentliche HTTP-Prüfung nach dem Fehler lieferte 200. Das ist kein Ersatz für Zertifikats-, Prozess- oder Restorebelege. Kein Providerkontakt versendet; konkreter privater Supportentwurf vorbereitet. Synthetischer Exact-Release-Restore `42362945948` bleibt ausdrücklich synthetisch. V1/O03/B04/R formal offen, Zähler unverändert. [Gesamtbefund](../operations/O03_BACKUP_ACCEPTANCE_TLS_BLOCKER_2026-11-07.md).


### O03 – freigegebene Datenbank-TLS-Ausnahme (07.11.2026)

Betreiberfreigabe `bd622f9fa9e926d7af866676af1a0224cb9d92c0` ersetzt für den OrgaVela-Datenbank-Backup-/Probezugriff die bisherige CA-/Hostnamen-Abnahmevoraussetzung. TLS bleibt zwingend, SSH-/HTTPS-Prüfungen bleiben aktiv. Source-Konfiguration ausdrücklich pro Datenbank-Backup-/Probeweg; kein Klartextfallback, keine anderen Projekte oder Zeitpläne. Capture-v4, Ressourcen-v3 und Paket-/Release-v3 müssen Zertifikatsprüfung False samt exaktem Ausnahmemodus/Freigabe ausweisen. Beobachteter Peer-Fingerabdruck ist nur Kontinuitäts-/TLS-Zwang und keine authentisierte Serveridentität. Standardmodus und alte geprüfte Nachweise behalten ihren Vertrag. Lokal46 Regressionen und tatsächliche TLS-/Dump-Positiv-/Negativproben PASS; neuer IONOS-Beleg fehlt noch. Zuerst reguläre Integration/exakte CI, frische Recovery/Preflight, Dump-/Archivgrenzen, dann eigentlicher Capture/Release-Rückdownload/isolierter Restore. Formale V1-/O03-Abnahme und Zähler unverändert. [Details](../operations/O03_DATABASE_TLS_EXCEPTION_2026-11-07.md).


### O03 – TLS-Ausnahme integriert, IONOS-Dumpclient inkompatibel (07.11.2026)

Die freigegebene Ausnahme ist über PR #74–#76 regulär integriert: Engine `42c1f95`, privater Controller `da0df3b` (PR #10–#12), exakte Main-CI einschließlich CircleCI grün.46 Pythonregressionen, PHP-Hilfstests und synthetische TLS-/Dump-Positiv-/Negativfälle bestanden. Frische IONOS-Recovery `42401850165` und Preflight `42401981757` erfolgreich. Die gezielte Dump-Ressourcenprobe `42402105567` bestätigt nach erfolgreichem Peerlesen eindeutig `dump_tls_peer_option_unsupported`: Der ausgewählte IONOS-Dumpclient bietet `--ssl-fp` nicht an. Abbruch vor Kontrollverbindung, Wartung und Dump; kein Snapshot, Produktionsrelease oder echter Restore. Die IONOS-Clientversion und andere installierte Clients wurden nicht erhoben. Nächster O03-Block: vorhandene Clientfähigkeiten ohne Wartung inventarisieren und einen kompatiblen, TLS zwingend erzwingenden Dumppfad bereitstellen; anschließend frische Vorprüfungen, Dump-/Archivgrenzen und vollständige Release-/Restorekette. `--ssl` allein ist wegen synthetisch bestätigtem Klartextfallback ungeeignet. Ausnahmequittungen weisen ausdrücklich `server_certificate_verified=false` aus, SSH/HTTPS bleiben geprüft. Unabhängiges HTTPS nach der Ablehnung200. V1/O03-Abnahme und formale Zähler bleiben offen. [Belege und Grenzen](../operations/O03_DATABASE_TLS_EXCEPTION_2026-11-07.md).


### O03 – inventarisierter Client und kompatibler TLS-Dumppfad (07.11.2026)

Wartungsfreie IONOS-Inventur42421684930 bestätigt MariaDB10.11.19, keine --ssl-fp/--ssl-mode-Option. Kompatibler, exakt hashgebundener TLS-Proxy-Dumppfad implementiert: privates Unix-IPC zum Client, zwingendes peergebundenes TLS vor Backend-Authentifizierung.48 lokale Pythonregressionen, tatsächliche TLS-Dumps, gestartete alte Clients ohne TLS/ohne Authentifizierungs- oder Ausgabebytes und falscher Peer PASS. Reguläre Integration/exakte CI und Negativprobe mit den tatsächlichen IONOS-Hashes stehen noch aus; danach frische Recovery/Preflight/Ressourcen und echter Capture/Release-/Restore. Kein neuer Snapshot, V1/O03 offen. [Vertrag und Prüfgrenzen](../operations/O03_TLS_BRIDGE_2026-11-07.md).


### O03 – echtes Backend-TLS belegt, Proxy-Rückstau korrigiert (07.11.2026)

Engine db6fb90/Controller ccb8cc5 regulär integriert und exakt CI-grün. Exakter IONOS-No-TLS-Test42424190487 mit beiden nativen10.11.19-Clients PASS (0 Auth-/Ausgabebytes). Frische Recovery42424311485 und Preflight42424497421 PASS. Ressourcenprobe42424620973 bestätigt Kontroll-TLS und tatsächliches CLI-Backend-TLS, scheitert danach im Dump an blockierender Proxy-Schreiboperation. Vor Wartung/Snapshot; HTTPS danach200. Mit über2MB synthetischem Dump exakt reproduziert und durch begrenzte nichtblockierende Puffer/parallel geleerte Clientausgabe korrigiert; letzte synthetische Zeile vollständig verifiziert. Große TLS-Dumps und alle No-TLS-/Peer-Negativfälle lokal PASS. Fix muss regulär integriert/exakt CI-geprüft werden; danach frische gleiche-Controller-Gates und Backup-/Release-/Restoreabnahme. V1/O03 und Zähler bleiben offen. [Details](../operations/O03_TLS_BRIDGE_2026-11-07.md).
