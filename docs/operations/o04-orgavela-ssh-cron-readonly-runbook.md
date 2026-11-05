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
