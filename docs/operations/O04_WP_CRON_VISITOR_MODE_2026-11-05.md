# O04 – WordPress-Visit-Cron aktiviert: Übergangsmodus und Monitoring (05.11.2026)

**Projekt:** OrgaVela, `orgavela.com` · **Releaseentscheidung:** unverändert **NO-GO** · **O04:** weiterhin `blocked`.

## 1. Betreiberänderung und tatsächliche Live-Verifikation

Der Betreiber hat `wp-config.php` direkt über den IONOS-WordPress-Dateieditor gespeichert und die Konstante so eingestellt:

```php
define('DISABLE_WP_CRON', false);
```

**Die WordPress-Core-Datei `wp-cron.php` wurde nicht geändert** und darf nicht für diese Umstellung geändert werden.

Die verbundene OrgaVela-WordPress-Read-only-Schnittstelle hat **nach** dieser Betreiberänderung mehrfach bestätigt:

- `disabled_by_config=false`: Der eingebaute, an Website-Aufrufe gebundene WordPress-Cron ist eingeschaltet.
- `can_run=true`, WordPress-Loopback `HTTP 200`.
- Die letzten gezielten Statusprüfungen ergaben **0 überfällige Aufgaben**.
- `action_scheduler_run_queue` ist als alle 60 Sekunden wiederkehrender Hook registriert; zum letzten beobachteten Zeitpunkt war seine nächste geplante Ausführung in etwa **41 Sekunden**.
- Eine frühere unmittelbar nach der Umstellung erhobene Momentaufnahme hatte dagegen **3 überfällige Aufgaben**, darunter einen ca. **800 Sekunden** überfälligen Queue-Hook. Diese wurden später nicht mehr als überfällig gemeldet. Ein WordPress-Cron-Status-Snapshot allein zeigt nicht die tatsächliche Ausführung jeder einzelnen ActionScheduler-Aufgabe.

**Prüfumfang:** Live-Metadaten über WordPress-Abilities, keine Ausführung von `cron-run-event`, keine Datenbankänderung, keine neuen Web-Requests an `wp-cron.php`, keine SSH- oder Hostingänderung durch den Assistenten.

## 2. Sicherheit und Zuverlässigkeitsgrenze

Der neue Betriebsmodus ist ein **Visitor-triggered Cron**. Fällige Aufgaben werden von WordPress angestoßen, wenn entsprechende Website-Aufrufe stattfinden; deshalb ist die Pünktlichkeit bei niedriger oder unregelmäßiger Besucherzahl oder Caching **nicht garantiert**. Ein registrierter Hook mit zukünftigem Zeitpunkt und keine aktuell überfälligen Aufgaben ersetzen keinen Langzeitbeleg für eine aktive Queue.

Ein **externer IONOS-Cron wurde weiterhin weder bestätigt noch eingerichtet**. Bei künftigem nachgewiesenem Server-Cron-Trigger darf der Operator die Alternative `DISABLE_WP_CRON=true` erst wieder aktivieren, wenn der externe Trigger vollständig getestet ist und keine doppelten/unerwünschten Worker entstehen.

**Die Website bleibt beim derzeit bestätigten sicheren Zwischenzustand**, die `wp-config.php` und `wp-cron.php` werden von diesem PR nicht verändert.

## 3. Aktualisiertes O08-Monitoring

Die tägliche rein lesende GitHub-Actions-Prüfung (07:17 UTC, vorhandener `orgavela-ionos`-geschützter Workflow) kontrolliert weiterhin:

- Das OrgaVela-Repository, den Hauptbranch, das konkrete Ziel und die installierte Alpha.2-Version.
- `ORGAVELA_CRON_WP_OPTION=valid`, `ORGAVELA_CRON_OTHER_OVERDUE_BUCKET=none` (dieses ältere Statusfeld zählt alle überfälligen WP-Hooks einschließlich des Queue-Hooks), ActionScheduler-Store vorhanden, keine Pending-Aktionen >10 Minuten, keine neuen Failed-Actions innerhalb 24 Stunden.
- Einen eindeutigen, ausreichend frischen `action_scheduler_run_queue`-Hook.
- **Neu:** Die aktuelle, ausdrücklich vom Betreiber aktivierte Einstellung `ORGAVELA_CRON_WP_AUTO=enabled`. Eine Rückkehr zu `disabled`, ein fehlender Status oder eine unerwartete Änderung führen zunächst **fail-closed** zum roten Überwachungslauf. Vor einem Wechsel auf einen nachweislich funktionierenden externen Trigger ist ein gesonderter geprüfter O04-Änderungs-PR erforderlich.

Die negativen Tests enthalten zusätzlich `wpcron-disabled`, `wpcron-missing`, `other-overdue` und `other-missing`; keiner dieser Zustände darf als gesund gelten. Diese Monitoring-Änderung selbst hat **keinen** Zugriff auf `wp-config.php`, löst **keine** Cronjobs aus und ändert **keine** WordPress-Daten.

**Belege:** [O08-Monitor-PR #53](https://github.com/orgavela/Orgavela-Plugin/pull/53), [strenger manueller Testmodus PR #54](https://github.com/orgavela/Orgavela-Plugin/pull/54), [älterer negativer Cron-Live-Test #42042200606](https://github.com/orgavela/Orgavela-Plugin/actions/runs/42042200606). Die ältere Fehlmessung wurde **vor** der jetzigen Betreiberänderung erhoben und darf nicht als neuer aktueller Zustand ausgegeben werden.

## 4. O04-/O08-Abnahme bleibt ausdrücklich offen

- **O04:** Es fehlen die tatsächliche zuverlässige Worker-Ausführung über mehrere Intervalle, ein verifizierbarer externer Scheduler oder eine explizit fachlich abgenommene Alternative, Recovery-/Retry-/Crash- und Alarmpfade. `status=blocked` und `accepted=false` bleiben unverändert.
- **O08:** Der erste reguläre tägliche Monitorlauf und eine tatsächliche Benachrichtigung/Eskalation bei Fehlern sind weiterhin nicht nachgewiesen. `status=partial` bleibt.
- **O03/O06/O07/O09/O10/O11/O12:** Backup/Restore, Datenschutz, vollständige Feature-CI und Release-Abnahmen bleiben eigene Voraussetzungen. Keine Produktivfreigabe durch die Cron-Aktivierung.

Nach zwei bis sieben Tagen ohne Rückstände kann eine **weitere positive Zwischenbewertung** sinnvoll sein, aber weder ein Datenbankeintrag noch ein dauerhaft zuverlässig arbeitender externer Trigger werden allein dadurch bewiesen. `V1_PROGRESS.json` bleibt die kanonische technische Statusquelle.


## 5. Unabhängige Wiederholbarkeit des O08-Sicherheitstests

Der ursprünglich direkt in `.github/workflows/orgavela-readonly-audit.yml` eingebettete kleine Fixture-Test wurde zusätzlich als **rein lokaler, von beiden CI-Providern ausführbarer** Test `scripts/ci/orgavela-cron-evaluator-tests.sh` extrahiert. Dieser Test hat **zwei** gesunde synthetische Messungen und **18** negative Varianten, darunter unerlaubtes Abschalten von WP-Cron, fehlende und doppelte Felder, überfällige WordPress- und Action-Scheduler-Hooks, kürzlich gescheiterte Aktionen, falsche OrgaVela-Zielidentität und fingierte Cron-Ausführung.

GitHub Actions führt sowohl `shellcheck` als auch alle synthetischen Negativfälle aus; der echte CircleCI-Provider führt **denselben Test** als separaten Schritt der `source-integrity`-Pipeline aus. Damit können künftige Änderungen nicht allein durch einen grünen PHP-Parsercheck als funktionierende O08-Überwachung erscheinen.

Auch ein positives Ergebnis dieser beiden Offline-Prüfungen ist **keine** nachgewiesene Live-Cron-Ausführung oder Alarmzustellung. O04 bleibt `blocked`, O08 `partial`, formale Releaseentscheidung `NO-GO`. Die Testskripte nutzen keine echten Datensätze, SSH-Keys, WordPress-Secrets oder Netzwerkzugriffe.
