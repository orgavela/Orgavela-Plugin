# orgavela.com – dauerhafte geschützte Testumgebung

Betreiberfreigabe vom 06.11.2026, Europe/Berlin, Übergabe `fc257244dbd58e74d14fd53f61d09aa9d1c9280e`. Ziel ist ausschließlich orgavela.com. Die frühere lokale Dockerinstanz ist kein für Work erreichbarer Abnahmeort und ersetzt dieses Ziel nicht.

## Tatsächlich verifizierter Beginn am 06.11.2026

- OrgaVela-Connector: home/site/admin über HTTPS auf orgavela.com, kein Multisite; WordPress7.1.4 / Core-DB-Version61833, PHP8.3.36, MariaDB11.8.8/utf8mb4.
- UOP Core0.1.0-alpha.2 aktiv, automatische Pluginupdates derzeit eingeschaltet; insgesamt zwei bestehende WP-Konten, deren Identitäten/Inhalte nicht abgefragt oder veröffentlicht wurden.
- GitHub-main `d21976b73e94208db050d108a3761529ca953a9c`, geschützt. GitHub-Environment orgavela-ionos existiert; API listet keine Environment-Schutzregeln. Secret-/Variablenlisten HTTP403: deren Inhalt und vollständige Konfiguration nicht ausgelesen. Funktionierender Audit belegt verwendbaren Runner-Zugriff, keine allgemeine administrativ geprüfte Secretkonfiguration.
- Echter manuell gestarteter SSH-Backup-Preflight auf main: [Run42225546300](https://github.com/orgavela/Orgavela-Plugin/actions/runs/42225546300), readonly-audit success. Zielidentität und Shell verifiziert; mysqldump/mariadb-dump/mysql/tar/gzip/sha256sum verfügbar; Datei-Größenklasse100–500MiB, DB unter25MiB, freier Hostplatz über5GiB. `EXPORT_EXECUTED=no`, `RESTORE_EXECUTED=no`. Provider-Restorepunkte nicht über Shell verifizierbar.
- Echter Schema-Audit: [Run42225685432](https://github.com/orgavela/Orgavela-Plugin/actions/runs/42225685432), readonly-audit success. Schema good, 29 UOP-Tabellen, Default-Organisation und Migration-/Datenversionen good. Das ist kein Vollbackup und keine fachliche Datenprüfung.
- Ohne Anmeldung: Startseite200, Login200, REST-Index200, direkte Editor-Datei200, Admin leitet zum WP-Login um. Kein vorgeschalteter Authentifizierungs-Challenge in diesen Antworten. Das beweist fehlenden umfassenden Hostschutz, nicht fehlende WordPress-Adminberechtigungen.
- Aktuell öffentlich ausgelieferter Editor SHA256 `348fd1fdd33b0d68bb81cc672a167f275f632cab4a4bf3c8656b914f32dcf6dc`; erwartetes CI-Asset `4fb50cb` hat `868d2ad9eda83b2bd99851bbfe3e8087f95dc075beae4a241003b231ab0969fe`.
- Suchmaschinenabschreckung ist aus; automatische WP-Cron-Ausführung laut Serverinfo nicht deaktiviert. Mail-/Webhook-Sperre und ausschließlich synthetischer Datenbestand sind nicht nachgewiesen. Keine bestehenden Personen-/Kontoinhalte exportiert oder verändert.

## Exakter Blocker vor Sicherung und Änderungen

Das private Repository orgavela/orgavela-backups ist erreichbar und enthält nur drei ausdrücklich synthetische Releases. Kein aktueller vollständiger Website-Snapshot und kein realer Restore sind nachgewiesen.

Der vorhandene O03/PGP05-Collector auf `fix/pgp05-backup-repo-pin-20261104` ist **kein ausführbarer Backupprozess**:

- `scripts/ci/production_backup/collector.py::main()` meldet REAL_CAPTURE=DISABLED und endet immer mit78; erzeugter Remote-Entrypoint setzt ENABLED=false.
- `writer.py::main()` meldet REAL_WRITER=DISABLED und endet immer mit78.
- README nennt den begrenzten SSH-Snapshottransport, Credential-/Watchdog-Kompatibilität und Live-Fences als noch zu implementieren/reviewen. Temporärer synthetischer Schlüssel-Roundtrip ist ausdrücklich kein WordPress-/MariaDB-Real-Restore.
- PR36 ist Draft auf PR33s Branch, kein integrierter Backupweg. Eine COMMENT-Review zu dessen Repo-Pin bestätigt keine Gesamtfreigabe.

Die neue Betreiberfreigabe erlaubt die Einrichtung; **eine erneute pauschale Erlaubnis ist nicht der Blocker**. Es fehlen eine ausführbare, geprüfte Sicherungs-/Restorekette und ihr tatsächlicher Lauf. Ein bloßes Umschalten der Disabled-Konstanten oder Benutzen des Metadaten-Audits als Snapshottransport würde diese fehlende Implementierung nicht ersetzen.

Daher bisher **keine** Website-Datei-/DB-Änderung, kein Zugangsschutzschalter, kein Pluginupdate, kein M3-Upload. Keine Schlüssel erzeugt/übertragen, keine echten Archive in öffentliche CI-Artefakte gelegt. Frühere Restorebelege mit synthetischen Daten ersetzen den hier verlangten Rückweg nicht.

## Nächster konkreter Auftrag – Codex, O03/PGP05

1. Den vorhandenen Collectorpfad vervollständigen und als separaten reviewbaren Change vorbereiten: bounded SSH-Snapshottransport, fail-closed Domain-/Root-/DB-Identität, konsistenter Wartungs-/Schreibzaun, Dateien und DB aus demselben Fenster, Hashmanifest, Zeit-/Größenlimits und eigene Cleanup-/Watchdogbehandlung bei Verbindungsabbruch. Keine PR-Skripte mit Hostsecrets ausführen. Ausführbare Runtime/Tools pinnen und Daten-/Secret-Ausgaben verhindern.
2. Manuelle vertrauenswürdige Runnerdefinition getrennt vom ausgewählten Payload, quellseitige und Ciphertext-only Writerrechte trennen. Vor echten Daten die unveränderte öffentliche Empfängeridentität und private Backuprepo-Grenze binden; private Recoverykeys bleiben beim Betreiber. Fehlende administrative GitHub-/IONOS-Funktionen einzeln als technische Setupanforderung ausweisen, keine Secrets im Chat anfordern.
3. Fehler-/Tamper-/Unterbrechungspfade mit synthetischem vollständigem Datei+SQL-Snapshot prüfen; dann echte Sicherung verschlüsselt ablegen, vollständig zurücklesen und Hashgleichheit bestätigen. Echte separate private Wiederherstellung dieses Snapshots mit WordPress/MariaDB und blockiertem ausgehendem Verkehr nachweisen. Für die Betreiberentschlüsselung einen privaten Restoreweg vereinbaren, niemals den privaten Schlüssel ins öffentliche Repository/CI holen.
4. Erst nach diesem echten Nachweis Schritt2 Zugangsschutz ausführen. Dieser technische O03-Auftrag bleibt bei Codex; Work bekommt noch keine M3-Abnahme auf dem Altbuild.

## Nach dem O03-Nachweis

Hostseitigen HTTPS-Zugangsschutz für HTML, Admin, REST und statische Dateien gemeinsam einrichten. Zugangsdaten außerhalb des Webroots, auf OrgaVela begrenzt; vorher SSH-Rückweg und atomare Wiederherstellung der geänderten Hostkonfiguration prüfen. Keine REST-/Asset-Ausnahme als bequemen Bypass. Host-Basic-Auth und WordPress-Application-Passwords konkurrieren gegebenenfalls um Authorization: Browser-Login mit WP-Cookie/REST-Nonce und Connectorzugriff getrennt prüfen; keine Erreichbarkeit des Connectors nach Umstellung versprechen. Work-Zugang über privaten Credentialweg bereitstellen. Extern jeweils401/403, angemeldet200 samt richtigem Assethash; Indexierung zusätzlich abschalten.

Danach synthetische Editoraccounts/Testdaten, Mail-/Webhook-Sperre und nur gezielt laufende Jobs einrichten und durch Negativtests bestätigen. Eigener manuell gestarteter Testdeploy mit Domain/Repo/Run/Commit/ZIP-Hash, Parallelitätssperre, Datei- und ggf. DB-Rollback; Erstinstallationsworkflow bleibt bestehen. Erster Payload ist CI-Paket4fb50cb aus Run42221730284 / Artifact13503402652. Buildjournal und gezielter Reset auf gesicherten synthetischen Ausgangszustand. Anschließend Work-M3-08/10 und unabhängige Architektur-/Policy-Freigabe; PR62 bis dahin Draft/ungemergt. Keine automatische V1-Abnahme.


### O03-Sicherheitskorrekturen – Rückgabe an Work,06.11.2026

PR64-Engine317ce93: Snapshot-PHP inert/separater gepinnter Core, tatsächlicher TAR-Inventarabgleich und signierter privater Controller mit unabhängigem integriertem Engine-Gate. Öffentliche34 Regressionen grün (42268076080/42268085720). Privater Controllercb834f3: synthetische Pipeline42268124152 mit3/3 Jobs erfolgreich, vier Abbruchfälle und3354 inert geprüfte Dateien; Identitätsprobe42268119301 mit2/2 erfolgreich und unintegrierte Engine erwartungsgemäß verweigert. Kein echter Snapshot, IONOS-Abbruch, privater Release-Readback oder echter Restore. Nächste Zuständigkeit Work: [unabhängige Sicherheitsnachprüfung](O03_SECURITY_RECHECK_2026-11-06.md), dann reguläre Parentintegration; anschließend Codex für echte private Sicherungs-/Restorekette. PR62/64 Draft/ungemergt, Website unverändert, alpha.2 letzter Livebeleg; V1-Zähler unverändert.
