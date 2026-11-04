# O04 – Unabhängiger Guardian-Queue-Trigger und Betriebsabnahme (Entwurf)

**Stand:** 04.11.2026 · **Status:** Nur CI-validierter Kandidat, kein eingerichteter Hosting-Cron. Gilt für das eigenständige **OrgaVela / UOP Core**-Projekt, nicht für Dreamloud.

## Hosting- und Freigabesituation

Die separat dokumentierte WordPress.com-Atomic-Staginginstallation von OrgaVela war zuletzt wegen einer inkompatiblen latin1-Verbindung **nicht aktivierbar**. Die ältere IONOS-Stagingplanung betrifft einen **nicht freigegebenen zusätzlichen Webspace** und erlaubt ausdrücklich keinen UOP-Zugriff auf Dreamloud-Produktivressourcen. Keine dieser Umgebungen ist als aktives Cron-Ziel bestätigt.

**Vor einer Host-Aktion:** Betreiber muss eine eigene, isolierte, ohne Zusatzkosten nutzbare und mit UOPs Datenbankanforderungen kompatible WordPress-Installation belegen, inklusive Shell/WP-CLI und unabhängigem Cron-/Job-Planer. Keine zusätzlichen Verträge ohne Freigabe, kein Workaround an der Datenbankzeichencodierung und keine Verbindung zu Dreamloud.

## Nachgewiesenes CLI-Prinzip (noch nicht auf einem Host freigegeben)

Action Scheduler unterstützt die Filter --hooks und --group. Der gezielte Kommando-Kandidat lautet:

    wp action-scheduler run --hooks=uop_guardian_quota_prune --group=uop --batch-size=1 --batches=1 --path=/ABSOLUTER/PFAD/ZUM/ISOLIERTEN/ORGAVELA-WORDPRESS

Der WordPress-Pfad ist ein **Platzhalter**, keine vorhandene Installation. Ein solcher Aufruf muss ohne Website-Besucher funktionieren und benötigt keinen öffentlich erreichbaren Wartungs-Endpunkt. Durch die Hook-/Gruppenfilter soll nur die Guardian-Retention laufen. **Andere UOP-Jobs** (Outbox, E-Mail etc.) werden damit nicht erledigt und brauchen einen gesondert geregelten Betriebsweg.

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
