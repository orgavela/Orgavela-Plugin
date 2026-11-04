# W0 – OrgaVela Live-Read und offene Betriebsgates (04.11.2026)

**Scope:** Eigenständige OrgaVela WordPress-Verbindung `link_6acc9fc926c88191847cc839a24f0b87`, ausschließlich als **read-only** deklarierte Abfragen. GitHub-`orgavela/Orgavela-Plugin` auf Basis von [OV-00](OV00_READONLY_BASELINE_20261104.md). Keine Änderung an Website, Cron, Plugin, Datenbank, Backup, IONOS, Secrets, CI-Dispatch oder Dreamloud.

## 1. Am 04.11.2026 unabhängig live ermittelt

| Feld | Tatsächlich gelesen | Prüftiefe / Einordnung |
| --- | --- | --- |
| UOP Core | `uop-core/uop-core.php`, `0.1.0-alpha.2`, **active** | `mosmcp__plugin-get`; Auto-Update-Flag = `true`, ohne Aussage über verfügbaren Plugin-Updatekanal |
| WordPress | `7.1.4`, `environment=production` | `core__get-environment-info`; Umgebungsbezeichnung ist kein V1-Releasebeleg |
| PHP | `8.3.36`, `cgi-fcgi` | `core__get-environment-info`, `mosmcp__site-get-server-info` |
| Datenbank | MariaDB `11.8.8`, `utf8mb4`, `utf8mb4_unicode_520_ci` | Server-/Verbindungsmetadaten, **kein** aktueller vollständiger Tabellen-/Index-Scan |
| HTTPS | Server `is_ssl=true`, Start-URL `https` | Serverinfo; keine Auth-/CSRF-/TLS-Konfigurationsabnahme |
| WordPress Scheduler | `DISABLE_WP_CRON=true`, Loopback HTTP `200`, `can_run=true` | `mosmcp__cron-test` **beweist nicht**, dass ein externer Hosting-Cron regelmäßig auslöst |
| Geplante WP-Ereignisse | `19` insgesamt, zuerst `0` überfällig; unmittelbar bei einer separaten Vollabfrage `1` überfällig | Snapshots variieren zeitlich; `action_scheduler_run_queue` mit Minutentakt, **keinen** manuellen Run gestartet |
| Suche | `discourage_search_engines=false` | Öffentliches Branding nicht ungeprüft auf globales `noindex` setzen |
| WP-Debug | `debug=false`, `debug_display=true`, `debug_log=false` | Kein Beweis für aktuelle Fehlerausgaben; bei Härtung getrennt bewerten |

**Datensparsamkeit:** Es wurden keine Teilnehmer, Benutzer, Tokens, Log-Zeilen mit Geheimnissen, SQL-Datenreihen, Schema-Dumps oder Cron-Argument-Payloads abgerufen. `cron-list-events` wurde nur als Anzahl, Hook, Zeit und Frequenz ausgewertet.

## 2. Gegenüber den bisherigen Nachweisen

- Vorheriges `V1_PROGRESS.json` meldete live WordPress `7.1.3`, PHP `8.3.33` und zuletzt zwei überfällige Cron-Ereignisse. **Neue Live-Read-Werte:** WordPress `7.1.4`, PHP `8.3.36`, ein kurzfristig wechselnder Cron-Rückstand von `0` zu `1`. MariaDB `11.8.8`, Plugin `0.1.0-alpha.2`, HTTPS/utf8mb4 unverändert.
- **O02:** Der historische [read-only WP-CLI-Schemaaudit #38264354774](https://github.com/orgavela/Orgavela-Plugin/actions/runs/38264354774) dokumentiert `Installer::health()` mit 29 UOP-Tabellen sowie Index-/Default-Organisation-/Version-Checks, `good` auf der damaligen aktiven alpha.2. **Heute kein neuer physischer Schema-Lauf**, deshalb weder neue Live-Schema-Garantie noch formale O02-Gate-Freigabe.
- **O01:** UOP-Hosting und Dreamloud-Projekt weiterhin nicht als Nachweis einer funktionierenden Least-Privilege-SFTP-Einschränkung behandeln; bestehende getrennte Verträge sind Betreiberbestätigung, keine neue Host-/Zugriffsprüfung.
- **O03:** [Private Backup-Repository `orgavela/orgavela-backups`](https://github.com/orgavela/orgavela-backups) über GitHub-Connector als `private`, `admin=true`, `push=true`, `main`, nicht archiviert bestätigt. Der frühere `janjrre/orgavela-backups`-Verweis ist historisch; kein neuer verschlüsselter realer Snapshot, kein isolierter Real-Restore. Nur PGP-04 synthetisch und PGP-05 Offline-Kandidat aus [Issue #27](https://github.com/orgavela/Orgavela-Plugin/issues/27) belegt.
- **O04:** `DISABLE_WP_CRON` weiterhin aktiv. Dynamisch zeitweise `0` überfällige Jobs darf nicht als externer Scheduler-PASS gelten. Keine manuelle Queue-/Mail-Ausführung.
- **O06:** Öffentliches `discourage_search_engines=false` und Environment `production` sind bekannt; für UOP-Test-/Guardian-Endpunkte Schutz, Auth, Cache und Mail-Absicherung weiterhin erforderlich.

## 3. Verbindliche nächste Schritte

1. **W0 / O02 / O03:** Kein Upgrade, kein realer Datenimport und keine Live-Migration ohne aktuelle, ausreichend belegte Schema-/Recovery-Evidenz. IONOS-Backupinventar, Recovery Points und Quell-/Dateien-Isolation klären; keine administrative Restore-Aktion.
2. **W0 / O04:** Prüfen, ob IONOS tatsächlich extern `wp-cron.php` ausführt (Hosting-Konfiguration/gesonderter Betriebsbeleg), anschließend Mail/Action-Scheduler nur in abgesichertem synthetischem Rahmen testen. Nicht auf Verdacht überfällige Events abfeuern.
3. **PGP-05:** Kosten- und Geheimnissicherheit eines **rein synthetischen** Provider-CI-Nachweises zu PR #33 separat prüfen, bevor Dispatch/Trigger erfolgt. Kein Production-Collector-Enablement und kein Real-Datenbackup ohne die operativen Gates.
4. **M6/P4:** Nach W0-Sicherheitspriorisierung PR #6/#11 um Consent/Guardian- und E2E-Golden-Paths ergänzen, mit jedem Fehler einen Negativ-/Regressionstest und SHA-genauen CI-Nachweis.
5. **Dokumentation:** Betreiber-Dauerfreigaben für kostenfreie autonome Arbeit in [AGENTS.md](../../AGENTS.md) und [Freigabendokument](OWNER_FREE_TOOL_AUTHORIZATIONS_20261104.md) erneut schriftlich fixiert. Keine Freigabe für kostenpflichtige Aktionen oder Security-Gate-Bypass.

## 4. Status-/Abnahmegrenze

**W0: nicht abgenommen.** Die live überprüften **Kompatibilitäts- und Betriebsmetadaten** sind ein Fortschritt in der Nachweisqualität, jedoch **keine** neuen fachlichen M/P4-/Release-Abnahmen. Die einzige kanonische Zählung bleibt `62/71` technisch implementiert, M0–M7 `0/8` formal, P4 `0/4`, R01–R18 `0/18`. Letzte aktive Plugin-Version live heute `0.1.0-alpha.2`.
