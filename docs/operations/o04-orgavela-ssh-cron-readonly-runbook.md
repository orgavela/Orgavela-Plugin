# O04 – Nur lesende GitHub-SSH-Cron-Inventur von OrgaVela

**Stand 04.11.2026 · Ziel:** https://orgavela.com, eigenständiger IONOS-Webspace. Keine Dreamloud-Prüfung, keine Cron-Ausführung.

## Anlass und Abgrenzung

Die mit der OrgaVela-WordPress-Verbindung beobachtete `action_scheduler_run_queue`-Fälligkeit schwankte zwischen einem mehr als sechs Minuten überfälligen Termin und einem späteren Zustand ohne Rückstand. `DISABLE_WP_CRON=true` ist aktiv. **Ein Wechsel des WordPress-Cron-Zeitstempels allein beweist weder eine bestimmte IONOS-Cron-Konfiguration noch einen erfolgreichen Action-Scheduler-Dispatch.**

Ein bereits vorhandener GitHub-Workflow `.github/workflows/orgavela-readonly-audit.yml` konnte nach dem Umzug zu `orgavela/Orgavela-Plugin` nicht mehr starten, weil Workflow und Shellskript den alten `janjrre/Projekt-Wordpress-Plugin`-Pfad erzwangen. Der neue Draft repariert nur diese Zielbindung und fügt eine zusätzliche **rein lesende** Operation `cron` hinzu. Alle bisherigen Operationsmodi (`sftp`, `probe`, `schema`, `backup-preflight`) bleiben verfügbar und unverändert von ihren Sicherheitsgrenzen.

## Aufruf nach Integration in main

In GitHub: **Actions → OrgaVela - strictly read-only SSH health audit → Run workflow → main → operation: cron**.

Der manuelle `workflow_dispatch`-Aufruf ist der vorgesehene, reguläre Weg für den echten Hostingcheck. Keine automatisierte Schreibanforderung an `.github/audit-requests/orgavela-readonly.json` erzeugen, um fehlende Workflow-Dispatch-Berechtigungen zu umgehen. Eine reine Änderung des Codes in einem Draft-PR führt **keinen** SSH-Audit aus.

Vorbedingungen: bestehendes, geschütztes GitHub-Environment `orgavela-ionos`, vorhandene und weiterhin gültige IONOS-SSH-Secrets, offiziell verifizierter ED25519-Hostkey und exakt festgestelltes WordPress-Ziel `https://orgavela.com`. Das Skript akzeptiert nur das aktuelle Organisationsrepository auf `main`, verifiziert die WordPress-Identität sowie die installierte Bestandsversion `UOP Core 0.1.0-alpha.2`. Bei Abweichungen **hart abbrechen**. `/public` ist ein bereits historisch festgelegtes SFTP-Root; für Shell-Zugriff werden nur fest definierte OrgaVela-Root-Kandidaten probiert, ohne andere Websites zu durchsuchen.

## Was die Operation `cron` liest

Ohne Plugins/Themes zu laden, wird über WP-CLI ausschließlich vorhandene Konfiguration und **aggregierte** Queue-Metadaten abgefragt:

- `DISABLE_WP_CRON`: enabled/disabled.
- WordPress-Cron-Option: gültig/ungültig, Status des einen `action_scheduler_run_queue`-Hooks, andere stark überfällige Events **nur als Größenklasse**. Zeitstempel und Eventargumente bleiben lokal.
- `actionscheduler_actions`-Tabelle, sofern vorhanden: Anzahl überfälliger Pending-Aktionen und Failed-Aktionen als Größenklassen. Es werden **keine** IDs, Hooks, Aktionsargumente, Nutzerdaten, Tabellennamen oder SQL-Zeilen geloggt.
- Vorhandensein eines `crontab`-Binary im SSH-Pfad, **ohne crontab-Einträge zu lesen oder zu verändern**.

Alle Roh-Ausgaben von SSH, WP-CLI und SQL bleiben temporär innerhalb des GitHub-Runners und werden durch eine explizite Status-Key- und Werte-Allowlist in die öffentlich sichtbaren CI-Protokolle übertragen. Keine Zugangsdaten, SQL-Dumps, Dateien oder Logs vom Hosting herunterladen. Keine `wp cron event run`-/`wp action-scheduler run`-/`wp-cron.php`-Aufrufe.

## Auswertung und Grenzen

Ein Status mit vielen `pending` und überfälligen Events unterstützt die Hypothese eines Cron-Problems. Einige kürzlich erledigte Aktionen (oder das Ausbleiben von Rückstand) sind **kein** Beweis für einen externen Cron, die tatsächliche Frequenz, dessen Auslöser oder eine Alarmierung. Ein SSH-Account kann über den IONOS-Control-Center-Jobbestand keine verbindliche Aussage liefern; diese Einträge und ihre letzten Ausführungsprotokolle müssen anschließend **im IONOS-Hostingkonto** selbst kontrolliert werden.

**Kein Doppel-Cron ohne Inventur.** Das normale IONOS-Hosting-Cron-Menü unterstützt nur HTTP-GET (ohne Hook-/Gruppenfilter); ein gescoptes WP-CLI benötigt einen gesondert unterstützten Shell-Cron. Hier wird **keines davon eingerichtet**. Für O04-Abnahme fehlen außerdem betrieblicher Ausfall-/Retry-/Alarm-Nachweis und W0/O03 verifizierter Vollbackup-/Restore. Live-Version bleibt `0.1.0-alpha.2`, alle späteren Feature-PRs vorerst Draft.

## Testnachweise

- Der zusätzliche Pull-Request-Job `static-safety` prüft **ohne Zugriff auf GitHub-Environment-Secrets und ohne SSH** die Skriptsyntax, das feste Organisationsziel und die Negativgrenze gegen erkennbare Scheduler-Ausführungsbefehle.
- Die bisherige Foundation-CI und HTTPS-Proben der P4-/O04-Entwicklungszweige sind eigenständige Code-Nachweise, **kein** erfolgreicher Betriebsaudit auf `orgavela.com`.
- Erst ein per regulärem Workflow-Dispatch abgeschlossener SSH-Job kann einen realen read-only Zustand belegen. Bis dahin O04 formal **offen**.


## Echt durchgeführte Cron-Inventur – 05.11.2026

**Betriebsnachweis:** Nach der ausdrücklich durch den Betreiber freigegebenen weiteren OrgaVela-Arbeit wurde der bestehende, auf `main` geprüfte Workflow nicht durch eine unautorisierte Datei in `.github/audit-requests/` ausgelöst. Stattdessen lief über [PR #50](https://github.com/orgavela/Orgavela-Plugin/pull/50) ein einmalig auf einen ganz bestimmten Squash-Merge begrenzter GitHub-App-Dispatcher. Dieser verwendete nur das Zielrepository `orgavela/Orgavela-Plugin` und ein temporäres `actions:write, contents:read`-Installationstoken, um den **normalen** `workflow_dispatch` mit `operation=cron` zu senden. In seinem eigenen Workflow war kein IONOS-Environment verfügbar. Der Dispatcher wurde nach erfolgreichem Test in diesem Folge-PR wieder **aus dem Repository entfernt**, damit er keine dauerhafte zusätzliche Berechtigungs- oder Ausführungsoberfläche bildet.

- [GitHub Actions #42036853511 – Dispatch](https://github.com/orgavela/Orgavela-Plugin/actions/runs/42036853511): `success`; zeigt ausschließlich den korrekten API-Auslösevorgang.
- [GitHub Actions #42036870948 – echter OrgaVela-SSH-/Cron-Audit](https://github.com/orgavela/Orgavela-Plugin/actions/runs/42036870948): **`success`**, `readonly-audit` mit allen acht Arbeitsschritten auf dem damaligen, geschützten `main`-Commit `134f1cc3c2ab33a70dfc66ba78ff489f3f9db4d5`.
- Der Lauf hat OrgaVela-SSH-/WP-CLI-Ziel und **`UOP Core 0.1.0-alpha.2`** bestätigt. Nur die in der Audit-Allowlist vorgesehenen Werte wurden in den CI-Logs veröffentlicht.

### Sichtbare, erlaubte Ergebniswerte

| Statusschlüssel | Vom Lauf gemeldeter Wert | Interpretation |
|---|---|---|
| `ORGAVELA_AUDIT_TARGET` | `verified` | WordPress-Host-Ziel erlaubt |
| `ORGAVELA_AUDIT_SHELL` | `available` | SSH und WP-CLI bestanden |
| `ORGAVELA_AUDIT_PLUGIN_VERSION` | `alpha2` | Installierte UOP-Baseline identifiziert |
| `ORGAVELA_CRON_SHELL_BINARY` | `unavailable` | `crontab`-Binary in diesem SSH-Pfad nicht vorhanden; **kein** Beweis für fehlenden IONOS-Control-Center-Cron |
| `ORGAVELA_CRON_WP_AUTO` | `disabled` | `DISABLE_WP_CRON=true`; serverseitiger Trigger nötig |
| `ORGAVELA_CRON_WP_OPTION` | `valid` | WordPress-Cron-Speicherstruktur gültig |
| `ORGAVELA_CRON_QUEUE_EVENT` | `due-within-5m` | Fälliger Action-Scheduler-Queue-Hook zum Messzeitpunkt höchstens fünf Minuten überfällig |
| `ORGAVELA_CRON_OTHER_OVERDUE_BUCKET` | `none` | Keine mehr als zehn Minuten überfällige sonstige Cron-Hook-Gruppen nach der begrenzten Klassifizierung |
| `ORGAVELA_AS_STORE` | `present` | Action-Scheduler-Datenbanktabelle vorhanden |
| `ORGAVELA_AS_PENDING_OVERDUE_BUCKET` | `none` | Keine mehr als zehn Minuten überfälligen wartenden Aktionen |
| `ORGAVELA_AS_FAILED_BUCKET` | `1-10` | Mindestens eine, höchstens zehn als fehlgeschlagen gespeicherte Aktionen; deren Alter, Ursache und Herkunft wurden **nicht** erfasst |
| `ORGAVELA_CRON_EXTERNAL_TRIGGER` | `not-verifiable-from-wordpress` | Externer IONOS-Job und Ausführungsintervalle nicht beweisbar |
| `ORGAVELA_CRON_DISPATCH_EXECUTED` | `no` | Keine Cronjobs gestartet |
| `ORGAVELA_CRON_MIGRATION_EXECUTED` | `no` | Keine Cron-Konfiguration verändert |

**Plausibilitätsvergleich:** Die separat verbundene OrgaVela-WordPress-Schnittstelle meldete am selben Tag `DISABLE_WP_CRON=true`, Loopback HTTP 200, 19 geplante WordPress-Hooks und nur einen minimal überfälligen Action-Scheduler-Hook; das ist mit der SSH-Inventur vereinbar, beweist aber keine regelmäßig funktionierende externe Host-Cron-Ausführung. Ein einzelner Snapshot kann nicht ausschließen, dass die registrierten Aktionen auf anderem Wege abgearbeitet wurden.

### O04-Restarbeiten / Sicherheitsentscheidung

1. Im **IONOS-Control-Center** prüfen, ob bereits ein zeitlich gesteuerter Job für **exakt OrgaVela** existiert, welcher Endpunkt/Pfad und welches Ausführungsintervall konfiguriert ist, und wann er zuletzt tatsächlich lief. Keine weiteren Jobs blind erstellen. Keine geheimen Werte in GitHub-Logs.
2. Den tatsächlichen Ausführungs-/Queuefortschritt über mehrere repräsentative Intervalle vergleichen; Fehlaktions-Count `1-10` nach Alter und Ursache **nur kontrolliert und datensparsam** untersuchen, bevor Fehler bereinigt werden. Keine ungeprüfte Wiederholung potenziell nicht-idempotenter Aktionen.
3. Spätere ActionScheduler-/Outbox-Worker-Golden-Paths mit synthetischen E-Mail- und Retry-Szenarien auf der **integrierten** UOP-V1-Kandidatenversion belegen. Auf Alpha.2 ist das kein vollständiger Betriebsnachweis.
4. Erst nach externer Scheduler-Historie, sicherem Alarm-/Recovery-Konzept und realem Backup-/Restore O04/R08 unabhängig abnehmen. Der kanonische Status **`O04=blocked` bleibt bestehen**, die SSH-Read-only-Vorprüfung ist tatsächlich bestanden.

**V1-Werte unverändert:** 62/71 historisch technisch implementiert; 0/8 Meilensteine, 0/4 P4 und 0/18 Release-Gates förmlich abgenommen. Keine OrgaVela-Website-/Plugin-Aktualisierung.
