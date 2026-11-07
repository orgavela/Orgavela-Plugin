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


## 22.10.2026 — O01/O03 / PGP05 Checkpoint5 SSH-Prüfkandidat, keine Gate-Abnahme

| Nachweis | Tatsächlicher Stand |
| --- | --- |
| Neuer begrenzter SSH-Prüfer mit Default-off, getrennten Context-/Approval-Modi | Implementiert auf Draft PR32, nicht live freigegeben |
| Synthetische lokale Regression und Subprozess-/Shell-Negativtests | 116/116 PASS, keine echten Zugangsdaten oder IONOS-Zugriffe |
| YAML-/SSH-Options-/Remote-Shellsyntax | Lokal PASS; keine CircleCI-Providerkompilierung |
| Serverseitige Projekt-/Revisions-Context-Restriction vor Secret-Injektion | Betreiber-Setup noch ausstehend |
| Offizieller Hostkey gegen tatsächlich präsentierten Kandidaten | Live noch nicht ausgeführt |
| Unabhängiges Review von Transport, Credential-Isolation und Server-Startup | OFFEN, Autorentests nicht als unabhängige Abnahme zählen |
| Authentisierte Konto-/Root-/Domainbindung und akzeptierter Metadatenlauf | OFFEN, vHost bleibt im Receipt false |
| Produktionsbackup/Real-Restore und formale V1-Releaseabnahme | Unverändert OFFEN |

[Ausführungsschritte, Reviewgrenzen und Rollback](../operations/PGP05_CIRCLECI_SSH_PROBE_OPERATOR_GUIDE_20261022.md). Checkpoint5 unverändert **7/10**, #6/#8/#10 offen. Keine Änderung der V1-Abnahmezähler.


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


### O03 – Registrierungsabbruch, lokaler Kandidat 06.11.2026

Work-Befund 44554bd auf Basis 7c170f3 rot reproduziert und korrigiert. Erweiterte synthetische Containerprobe 13/13, 34 Python-Regressionen und ergänzte PHP-Hilfstests grün: eigene registrierte Gruppen einschließlich temporärer vollständiger Registrierung beendet, WRITE-Locks freigegeben, HTTP erholt, fremde Prozesse/Marker erhalten. Unvollständige Identitäten bleiben ohne Signalautorität und liefern Fehlerstatus; der eigene Marker wird trotzdem hashgebunden entfernt. Details/Prüfgrenzen im Arbeitslog docs/operations/ASSISTANT_WORK_LOG.md. Work-Nachprüfung, Provider-CI und Parentintegration offen; PR62/64 Draft und ungemergt. Kein IONOS-Abbruch, Snapshot, Rückdownload oder Restore. Zähler unverändert: 62/71 historisch, M0–M7 0/8, P4 0/4, R0/18; alpha.2 letzter Livebeleg.


### O03 – Erstprobe isolieren, PR65 (06.11.2026)

Work-Prüfliste ec83e31 umgesetzt als abhängiger Draft [PR65](https://github.com/orgavela/Orgavela-Plugin/pull/65): Cleanup complete/unknown/survivors, Wartung und Eskalation bei ungeklärtem Ende; eigener Abort-CLI mit Capability-, Exception- und hartem Kanalmodus samt unabhängiger Prozess-/Lock-/Marker-/Originprüfung und Watchdog-Abschlussquittung. Kein automatischer Capture-/Package-Fallthrough. Details: docs/operations/O03_ABORT_ONLY_2026-11-06.md und append-only Arbeitslog. Lokale/Providerprüfungen sind synthetisch; Main-Ancestry, Work-Nachprüfung, private Source-only-Runneranbindung und praktisch belegter unabhängiger Recoveryweg bleiben vor IONOS erforderlich. PR62/64 bleiben Draft und ungemergt; kein Live-Abbruch, Snapshot oder Restore. V1 unverändert offen: 62/71 historisch, M0–M7 0/8, P4 0/4, R0/18, alpha.2 letzter Livebeleg.

Provider-Nachtrag PR65: Engine `5c395148bd395a5688e9d5ef240433d63c48a351`, [Run42311225902](https://github.com/orgavela/Orgavela-Plugin/actions/runs/42311225902) samt Joblog vollständig grün:37 Regressionen,13 Abbruchfälle und neue vollständige synthetische Abortprotokollprobe. Kein echter SSH-/IONOS-Beleg, kein Merge oder Signoff; Work-Nachprüfung/Mainintegration/privater Sourcejob/Recoveryroute bleiben vor Live offen.
