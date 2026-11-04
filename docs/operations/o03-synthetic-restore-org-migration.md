# O03/W0 – synthetischen Recovery-Test nach OrgaVela-Repository-Migration wiederherstellen

Stand: 04.11.2026. **Nur ein technischer CI-Test, kein produktives Backup und keine reale Wiederherstellung.**

## Ausgangslage

Das bestehende Workflow-Paar `.github/workflows/orgavela-restore-rehearsal.yml` und `scripts/ci/orgavela-synthetic-restore.sh` war weiterhin fest auf `janjrre/Projekt-Wordpress-Plugin` gebunden. Seit der Migration zu `orgavela/Orgavela-Plugin` wurde die eigentliche MariaDB-Restore-Prüfung deshalb durch die alte `if`-Bedingung **übersprungen**; ein grüner oder übersprungener Workflow konnte den Restore nicht neu bestätigen.

Das separate private Repository `orgavela/orgavela-backups` enthielt bei der letzten ausschließlich lesenden Metadatenkontrolle **synthetische Test-Releases**, nicht nachgewiesene DB-/Dateien-Vollbackups von `https://orgavela.com`. Ein externes IONOS-Backup und die erfolgreiche Wiederherstellbarkeit dieses realen Zustands wurden bislang **nicht verifiziert**.

## Was der neue Test wirklich prüft

Auf dem Organisationseigentum `orgavela/Orgavela-Plugin` wird für **Pull Requests** und nach Integration für **main** ein kurzlebiger GitHub-Runnerservice `mariadb:11.8` gestartet, ohne IONOS-Host- oder WordPress-Secrets. Das Shellskript verweigert alle Aufrufe ohne `GITHUB_ACTIONS=true`, im falschen Repository oder bei unpassendem GitHub-Ref/Event. Zusätzlich prüft die Pipeline diese Negativfälle **ohne** Datenbankzugriff.

Auf dem Runner entstehen zwei ausschließlich synthetische Datenbanken. Es werden exakt zwei Testzeilen, ein Index und zwei künstliche WordPress-Dateien erzeugt; danach schreibt der Test einen SQL-Dump und ein Dateiarchiv. Vor der Wiederherstellung verifiziert er beide SHA-256-Prüfsummen. Er führt die Rücksicherung nur in die getrennte **synthetische** Zieldatenbank und ein leeres temporäres Dateiverzeichnis durch und vergleicht Datensätze, Index und Dateiinhalte mit dem Ausgangszustand. Die temporären Dateien werden unmittelbar danach gelöscht, die Service-Datenbank wird mit dem Runner vernichtet.

Bei erfolgreichem Test sind ausschließlich solche Aussagen zulässig:

- `ORGAVELA_RESTORE_DRILL=synthetic-success`
- Der DB-/Datei-Roundtrip, synthetische Zeilen, Index und SHA-256-Integrität bestanden **für die Wegwerf-Umgebung**.
- Es wurde **kein** Live-Backup verarbeitet, kein Live-Restore ausgeführt und kein Cron-, Mail-, WordPress- oder Hostingprozess gestartet.

## Was der Test ausdrücklich nicht bedeutet

Er weist **nicht** nach, dass bei IONOS für `orgavela.com` aktuelle Datenbank-/Dateisicherungen vorhanden sind, sie vollständig und hinreichend privat gesichert werden, die realen Verschlüsselungsschlüssel gesichert wurden oder eine reale WordPress-Installation in einem separaten Testvertrag daraus konsistent wiederhergestellt werden kann.

Für O03/W0 fehlen weiter: gesicherte und vom Betreiber bestätigte Backupstände **der tatsächlichen Site**, die dokumentierte Verschlüsselungs- und Retentionsstrategie, eine isolierte Wiederherstellung eines echten verschlüsselten Backups, DB-/Dateikonsistenz, Versionierungs- und Integritätscheck sowie ein dokumentierter Rollback-Pfad mit Verantwortlichem.

## Abnahme und Sperren

Workflow: GitHub → Actions → **OrgaVela – synthetic isolated restore rehearsal**. Ein erfolgreicher GitHub-PR-Job ist lediglich ein Entwicklernachweis. Nach regulärem Review/Merge kann derselbe Test auf `main` manuell neu gestartet werden. Keine Workflowdatei darf `orgavela.com` kontaktieren, Produktions-Secrets verwenden oder das Testskript gegen eine reale Datenbank aufrufen. Es dürfen keine Kosten oder zusätzliche Verträge verursacht werden.

**O03/W0, O04 und sämtliche formalen V1-Freigaben bleiben bis zu echten Betreiberbelegen offen.** Geschützter `main` darf für die Integration nicht umgangen werden.

## Erweiterte Integritäts-Negativprüfung (04.11.2026)

Der neue Ablauf verifiziert unmittelbar vor der isolierten Wiederherstellung, dass **beide unabhängigen Eingabearten** einzeln durch SHA-256 geschützt sind: (a) die komprimierte synthetische SQL-Sicherung und (b) das synthetische WordPress-Dateiarchiv. Der CI-Job verändert jeweils ein **temporäres Exemplar** absichtlich und muss den Hashvergleich als **Fehler** feststellen. Anschließend stellt er das gültige CI-Archiv byteidentisch wieder her und prüft dessen Hash erneut. Zusätzlich muss die synthetische Restore-Zieldatenbank **vor dem erfolgreich bestandenen Integritäts-Gate leer** sein. Erst danach beginnt der bisher bereits getestete SQL-/Dateien-Restore.

Ausgabe bei erfolgreichem neuen Test: `ORGAVELA_RESTORE_DRILL_CORRUPTION_REJECTED=both-archives`. Dieser Nachweis ist **nicht** als Ende-zu-Ende-Verschlüsselungs- oder Signaturprüfung realer Offsite-Backups zu verstehen: Der Manifest selbst wird in der Wegwerf-Umgebung erzeugt. Betreiberseitig fehlen weiterhin authentisch gesicherter Manifest-/Schlüsselpfad, reale Sicherung, unabhängige Providerkopie und der isolierte Restore einer echten `orgavela.com`-Sicherung.

**Bisheriger geprüfter Stand:** [GitHub PR-Test #41864496614](https://github.com/orgavela/Orgavela-Plugin/actions/runs/41864496614) auf `3a8efa732f258bf9cf2829b13b744c16dc107762`: synthetische DB-/Datei-Sicherung und Wiederherstellung, Index-/SHA-256-Kontrollen sowie drei Sicherheits-Negativtests **erfolgreich**. Die beiden **zusätzlichen** Manipulationstests stehen erst ab Code-HEAD `77b72d44a5ed141da774d0ccdcb944573a1b3489` unter CI-Prüfung, sind also nicht mit dem früheren grünen Beleg zu verwechseln. Kein produktives Backup/Restore.
