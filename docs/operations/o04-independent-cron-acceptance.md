# O04 – Unabhängiger Guardian-Queue-Trigger und Betriebsabnahme (Entwurf)

**Stand:** 04.11.2026 · **Status:** WP-CLI-Kandidat in CI verifiziert; unabhängiger Cron des bestehenden IONOS-Hosts noch nicht geprüft. Gilt für **OrgaVela**, nicht für Dreamloud.

## Verifizierter IONOS-Ist-Stand und noch offene Freigabe

Die tatsächlich über OrgaVela-MCP verbundene Website ist **https://orgavela.com** (WordPress 7.1.4, PHP 8.3.36, MariaDB 11.8.8, utf8mb4). Dort ist **UOP Core 0.1.0-alpha.2 aktiv**. Die frühere inkompatible latin1-Verbindung gehört ausschließlich zur getrennten WordPress.com-Atomic-Testwebsite; sie ist **keine Blockade von orgavela.com**. Detailnachweise stehen unter `docs/operations/o04-orgavela-live-host-baseline.md`.

`DISABLE_WP_CRON=true` und mehrfach überfällige WordPress-Jobs wurden read-only festgestellt. **Weder vorhandene IONOS-Cron-Einträge noch die tatsächliche Shell-/WP-CLI-Verfügbarkeit, Alarmierung und ein voller Backup-/Restore-Test wurden über die WordPress-Verbindung bestätigt.** Ein vorhandener WordPress-Host ist kein Nachweis eines funktionierenden externen Cron-Dienstes.

Vor Eingriffen auf dieser Installation: tatsächlichen Hostingvertrag, eigenständige Ressourcen/Isolation von Dreamloud, Cron-Konfiguration und vollständige Backup-/Restore-Nachweise absichern. Keine zusätzlichen Verträge, keine Workarounds an der WordPress-Datenbankverbindung und keine automatischen Produktionsmigrationen.

**Wichtige IONOS-Unterscheidung:** Hosting-Control-Center-Cronjobs sind reine HTTP-GET-Aufrufe und können keine `--hooks/--group`-WP-CLI-Filter ausführen. IONOS dokumentiert zudem 60 Sekunden Laufzeitlimit und empfiehlt mindestens fünf Minuten Abstand. Für den unten getesteten **selektiv gescopten** WP-CLI-Befehl ist ein tatsächlich verfügbarer *serverseitiger Shell-/Cron-Zugang* erforderlich. Der alternativ mögliche HTTP-Aufruf `https://orgavela.com/wp-cron.php` verarbeitet **alle** fälligen WordPress-Jobs, nicht nur Guardian-Retention; vor Einrichtung müssen ausstehende Aktionen und Benachrichtigungswirkungen geprüft werden. Wir haben den produktiven HTTP-Endpunkt **nicht** ausgelöst.

## Nachgewiesenes CLI-Prinzip (noch nicht auf einem Host freigegeben)

Action Scheduler unterstützt die Filter --hooks und --group. Der gezielte Kommando-Kandidat lautet:

    wp action-scheduler run --hooks=uop_guardian_quota_prune --group=uop --batch-size=1 --batches=1 --path=/ABSOLUTER/PFAD/ZUM/ISOLIERTEN/ORGAVELA-WORDPRESS

Der WordPress-Pfad ist ein **nicht ermittelter Shell-Pfad** der bereits bestehenden OrgaVela-Installation, kein bestätigter CLI-Zugang. Ein solcher Aufruf muss ohne Website-Besucher funktionieren und benötigt keinen öffentlich erreichbaren Wartungs-Endpunkt. Durch die Hook-/Gruppenfilter soll nur die Guardian-Retention laufen. **Andere UOP-Jobs** (Outbox, E-Mail etc.) werden damit nicht erledigt und brauchen einen gesondert geregelten Betriebsweg.

### Technische Voraussetzung: vollständiger Action-Scheduler-Datenspeicher

Ein erster **bewusst roter** CI-Lauf auf Source-Commit 382bc9c belegte einen eigenen Migrationszustand: Der interne Queue-Runner funktionierte, aber der gruppengefilterte WP-CLI-Lauf meldete, dass Gruppe uop nicht existiere, obwohl neue Aktionen dieser Gruppe gespeichert waren. Ursache kann der vorübergehende ActionScheduler_HybridStore während der Migration von alten Post-Records zur Custom-Table-Datenbank sein. **Den Gruppenfilter auf keinen Fall zur vermeintlichen Behebung entfernen.**

Die korrigierte, streng isolierte GitHub-CI prüft jetzt den echten Active Store und schließt **nur in der wegwerfbaren WordPress-Testdatenbank** bei Bedarf eine eingebaute Action-Scheduler-Migration ab. Anschließend muss ein frisch gestarteter Prozess als Store **ActionScheduler_DBStore** sehen; anderenfalls schlägt die CI sicher fehl. Erst danach wird die per Hook **und** Gruppe begrenzte Queue ausgeführt.

Auf einem späteren Betreiber-Host gilt **keine automatische Migrationsfreigabe**: Nur den Store read-only prüfen und bei HybridStore oder sonstiger Inkompatibilität **STOP**. Migration braucht vorher eigene Datensicherung, Recovery-Test, Wartungsfenster und explizite Freigabe. Damit ist der reale Hosting-/O04-Status weiter offen.

## Abnahmereihenfolge nach Freigabe eines kompatiblen Hosts

1. **Isolation und Voraussetzungen:** WordPress-URL, eigener Webspace, Datenbank, Laufzeit, InnoDB und utf8mb4, PHP >= 8.3 sowie UOP-Aktivierungscheck verifizieren. Keine realen Personendaten für den Pilot.
2. **Read-only CLI-Check:** Auf *diesem* Host die Hilfe von wp action-scheduler und wp action-scheduler run prüfen; die tatsächliche WP-CLI-/PHP-Version und den absoluten WordPress-Pfad dokumentieren. Andere Action-Scheduler-/WP-Cron-Jobs nicht abschalten.
3. **Unabhängige Ausführung:** Ein vom Host unterstützter Cron-/Job-Dienst führt den oben genannten gescopten WP-CLI-Befehl unter einem eingeschränkten Website-Benutzer aus. Vorher absolute Binärpfade, Überschneidungssperre, feste Laufzeitgrenze, Dateirechte und Fehlermeldungsweg prüfen. Keine öffentlichen Cron-URLs oder produktiven GitHub-Secrets als Ersatz anlegen.
4. **Beleg:** In einem freigegebenen isolierten Test mit einer fälligen synthetischen Guardian-Aktion den Wechsel pending → complete **ohne HTTP-Besucher** belegen, zugleich die tatsächliche selektive Quota-Bereinigung, den weiter vorhandenen Recurring-Job und unberührte Fremdaktionen kontrollieren. Keine privaten IDs/Payloads in Statusreports.
5. **Betriebsstörung und Monitoring:** Einen ausgebliebenen Trigger, überfällige und fehlgeschlagene Aktionen, überlappende Aufrufe und die Wiederherstellung nach einer Störung bewusst testen. Das vorhandene manuell sichtbare Control-Center-Label ist kein Alarmkanal. Verantwortlichen, Eskalationsweg und unbedenkliche Retry-Regel dokumentieren.
6. **STOP/Rollback:** Gibt es keinen geeigneten Cron-/CLI-Zugang, ist die Umgebung inkompatibel oder der Betrieb nur kostenpflichtig, bleibt O04 **blockiert**. Rollback nur des separaten Host-Cron-Eintrags; historische Queue-/Auditrecords nie blind löschen.

## GitHub-CI-Nachweis ohne Hostzugriff

Die wegwerfbare WP-/MariaDB-CI arbeitet ausdrücklich mit DISABLE_WP_CRON=true, GITHUB_ACTIONS=true und einem festen Loopback-Pfad. Die neue Testfixture tests/Fixtures/o04-external-cli-cron-probe.php bereitet vor:

- Einen abgelaufenen **synthetischen** Guardian-Quotazähler.
- Drei fällige Action-Scheduler-Aktionen: (a) Guardian-Hook in Gruppe uop als ausschließliches Ziel, (b) fremder Hook in uop, (c) derselbe Guardian-Hook in einer **anderen** Gruppe.
- Ein **neuer WP-CLI-Prozess** führt den tatsächlich bereitgestellten Action-Scheduler-Befehl mit Hook/Gruppe und einem Batch aus. Der Test ist nur grün, wenn (a) als complete gespeichert und die abgelaufene Quota gelöscht ist, während (b) und (c) pending bleiben. Der bestehende wiederkehrende Guardian-Job und dessen lesender Health-Status müssen unverändert gesund bleiben.
- Die vorherige Runner-/Pruner-Probe aus Draft #41 wird nicht ersetzt; #43 ergänzt den Beleg für die CLI-Schnittstelle. Ohne grünen CI-Lauf keine Behauptung eines technischen Erfolgs.

## Offene Freigaben

**O04 bleibt fachlich offen**, bis tatsächlicher kompatibler Host, unabhängiger (kostenloser) Job-Planer, regelmäßige Ausführung, Monitoring/Alarmierung und Wiederanlauf geprüft und dokumentiert sind. W0/O03 Recovery, Datenschutz/Legal, unabhängige echte Guardian-Prüfung und sichere private Referenzvergabe bleiben separate Gate-Blocker. Kein Merge, kein Deploy und keine zusätzlichen Kosten.


## SHA-genaues RED/GREEN-Protokoll (04.11.2026)

- **RED:** [PR-Foundation #41843619161](https://github.com/orgavela/Orgavela-Plugin/actions/runs/41843619161), PHP 8.3/MariaDB: Action Scheduler erhielt die drei fälligen CI-Aktionen, lehnte aber den gegrenzten WP-CLI-Run mit Hinweis auf eine nicht existierende Gruppe ab. Nicht durch Entfernen des Gruppenfilters repariert.
- **Ursache und technische Maßnahme:** Der hybride Übergangsstore kann beim gruppengefilterten CLI-Verfahren noch den alten WordPress-Post-Store abfragen. Die CI wird deshalb ausschließlich auf einer frischen, absichtlich wegwerfbaren Datenbank auf den vollständig migrierten ActionScheduler_DBStore eingestellt. Auf einem späteren Host müssen Backups/Restore und Migrationsgenehmigung **vor** jedem solchen Vorgang separat belegt sein.
- **GREEN Source-HEAD b21795d44900a9e47599942a4dd5dea327f96b04:** [Foundation Push #41844374035](https://github.com/orgavela/Orgavela-Plugin/actions/runs/41844374035) **8/8 erfolgreich**; [Foundation PR #41844383421](https://github.com/orgavela/Orgavela-Plugin/actions/runs/41844383421) ebenfalls **8/8 erfolgreich**. Testjob PHP 8.3/MariaDB **41 Unit-Tests/5.410 Assertions**, **154 Integrationen/1.944 Assertions**.
- **Positive Sicherheitsevidenz:** Das WP-CLI-Protokoll berichtet eine einzige gefundene fällige Aktion. Die separate Verify-Fixture bestätigte den erfolgreichen Abschluss ausschließlich der Guardian-Aktion in Gruppe uop, Löschung des synthetischen alten Quota-Datensatzes, weiterhin pending befindliche beiden Fremdaktionen und unveränderten gesunden lesenden Scheduler-Zustand.
- Dieser Dokumentationsnachtrag erzeugt einen neuen Commit; die obigen verifizierten Resultate beziehen sich auf den **Source-HEAD**, nicht automatisch auf den späteren Dokumentations-HEAD.
