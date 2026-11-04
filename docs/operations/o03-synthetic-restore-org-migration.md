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
