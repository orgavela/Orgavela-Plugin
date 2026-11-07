# PGP-05: Dreamloud-Collector für OrgaVela — Offline-Kandidat, 23.10.2026

Zuordnung W0/O03, Issue #27. Checkpoint 5 ist unverändert **10/10 (100%) abgeschlossen**. Diese Arbeit setzt den freigegebenen Weiterarbeitsplan Schritte 1/2 um.

## Quelle und Umsetzung

Unveränderte, ausschließlich gelesene Dreamloud-Referenz: `backup/production-circleci`, Commit `07d4ffbcc3c6d70f0716fda070050d6532a69df6`. Kein Zugriff auf Dreamloud-Laufzeit oder Backupinhalte. Neuer gestapelter Draft basiert auf PR #31 / `3ca6be16f4c9d8d17ebb8265bbc8e05bb3952c95`. PR #32 bleibt Draft auf `8c4b445ea5dced969f63b0f30e0e371342f64680`.

Portiert unter `scripts/ci/production_backup/`: statischer Configparser, PHP-Capture-Engine mit Wartungs-/Lock-/Watchdog-/Inventar-/Owned-Cleanup-Logik, privater Snapshotvalidator, OpenPGP-Verpackung, getrennte Writer-/Readback-Bibliothek, Sicherheits- und Integrationstests. Eigene OrgaVela-Identitäten: exaktes HTTPS home/siteurl, 52 eigene Präfix-Tabellen plus zehn bekannte Core/UOP-Tabellen, bereits akzeptierter öffentlicher Empfänger und privates eigenes Backupziel. Kein Dreamloud-App-Schlüssel oder Credential übernommen; keine Docket-Cache-Ausnahme.

**Produktive Entry Points sind hart deaktiviert (Exit 78), einschließlich des erzeugten PHP-Skripts und Tokenladers.** Es gibt noch keinen freigeschalteten vollständigen SSH-Snapshottransport. Ein Umgebungsflag kann dies nicht aktivieren. Snapshot-Evidenz im Test ist künstlich und beweist keine echten Locks/Wartung.

## Tatsächlich lokal ausgeführt

- 16/16 Python-Tests PASS; dieselben Tests separat unabhängig wiederholt.
- Vier PHP-Dateien syntaxgültig; PHP-Hilfstests PASS. Erzeugter PHP-Kandidat: Exit 78, leeres stdout, exakt deaktiviertes stderr.
- Integration: künstlicher Stream → Komponentenprüfung → echte GnuPG-Verschlüsselung mit freigegebenem öffentlichen OrgaVela-Empfänger → genau drei Ciphertext/Public-Assets → unabhängige Writer-Paketprüfung PASS.
- Separater temporärer Testschlüssel nur im Arbeitsspeicher: GnuPG verschlüsselt mit Public-Hälfte; PGPy entschlüsselt und bestätigt Bytegleichheit/Hash aller drei privaten Komponenten PASS. Kein Owner-Private-Key, kein echter WordPress-/MariaDB-Restore.
- Negativtests: Pfad-/Link-/Duplikat-/JSON-/Archivgrößen-/Eintrags-/Dekompressionsgrenzen, falscher Empfänger trotz neu berechneter Manifesthashes, Konsistenzflags, Bereinigung, fremde Credentials, API-Pfadausbruch, Upload-/Readbackreihenfolge, fehlende Digests/Immutability und Redirect-Tokenweitergabe.
- YAML syntaktisch geparst. Noch keine CircleCI-Providervalidierung für diesen neuen Kandidaten.

Lokale Werkzeuge: Python 3.12.14, GnuPG 2.4.4, separat extrahiertes PHP 8.3.6 mit Tokenizer; PGPy 0.6.0. PHP ist ein Fixturewerkzeug, keine Behauptung über die IONOS-Version.

## Fehlversuche und Korrekturen

Systempaketinstallation scheiterte am Container-setuid/setgroups; stattdessen ausschließlich lokale PHP-Paketextraktion. Öffentlicher GnuPG-Import versuchte den hier nicht zulässigen UNIX-Socket-Agenten; keine Umgehung, stattdessen öffentliche `--show-keys`/`--recipient-file`-Verarbeitung ohne Agenten/Private-Key-Store. Erster PHP-Hilfslauf ohne Tokenizer schlug fehl; Extension explizit geladen, danach PASS.

Separates Agentenreview zunächst CHANGES_REQUIRED: API-Dotpfade, unzureichende DB-/Passwort-Umgebungstrennung und uploads-Dateityp/Ancestor-/Membergrenzen. Alle drei behoben und durch Regressionstests belegt. Abschließendes unabhängiges Urteil **APPROVED nur für Offline-Draft** nach eigener Wiederholung der obigen Prüfungen. Kein externes menschliches Audit und keine Livefreigabe.

## Einmaliger nächster Betreiberschritt

Im bestehenden CircleCI-Projekt den Branch `feat/pgp05-dreamloud-collector-port-20261023` wählen. Neue Pipeline mit Boolean `run_collector_port_tests=true` starten, **keinen Context und keine Zugangsdaten hinzufügen**. Der Workflow heißt `collector-port-offline-only`, der Job `collector-port-offline`. Nur bestehendes kostenloses Kontingent verwenden; bei kostenpflichtiger Anforderung abbrechen. Ergebnislink zur exakten Revision zurückgeben.

Konfiguration: ein kleiner opt-in-Job, standardmäßig aus, keine Schedules, keine Artifacts/Workspaces oder Livejobs. Paketinstallation nutzt öffentliche Registries; Tests verwenden künstliche Daten und gemockte HTTP-Aufrufe. Image-Tag und OS-Pakete sind noch keine produktiven Runtimepins.

## Danach offen

Vollständiger begrenzter SSH-Snapshottransport und produktive Runtimepins; genaue Source-/Writer-Credentialverdrahtung mit getrennten Schutzkontexten; neuer konkreter Erst-Capture-/Publish-Entscheid; echte Konsistenz-/Wartungs-/Watchdogprüfung, verschlüsseltes Backup mit immutable Readbacks und isolierter tatsächlicher WordPress-/MariaDB-Restore. Erst danach Zeitplan/Alarmierung/Retention. Aktuell keine Betreiber-Secretablage erforderlich.

Keine IONOS-Verbindung, produktive Datei-/DB-Änderung, reale Sicherung, Veröffentlichung, Wiederherstellung, Deployment, Merge, Schedule oder kostenpflichtige Aktion ausgeführt. `production_capture_allowed=false`.

V1 bleibt offen: 62/71 Codinghistorie, M0–M7 0/8, P4 0/4, harte Release-Gates 0/18. Zuletzt belegte aktive Version 0.1.0-alpha.2; in diesem Abschnitt nicht neu live geprüft.
