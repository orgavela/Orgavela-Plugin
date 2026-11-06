# O03 – temporärer privater CI-Restore statt eigenem Linux-Rechner

Betreiberfrage 06.11.2026: GitHub Actions oder CircleCI als kostenloses Linux-Restoreziel verwenden. **Technisch möglich.** Ein CI-Runner ist ein kurzlebiges Testziel, kein dauerhaft betriebener Linuxserver. Die Windows-/WSL-Einrichtung ist für diesen Weg nicht erforderlich und wird vorerst zurückgestellt.

## Bevorzugter Weg und tatsächlicher Prüfstand

GitHub Actions im bereits vorhandenen **privaten** Repository orgavela/orgavela-backups, Standard-Linuxrunner, manuell gestartet. Dort lassen sich reale Dateien/SQL in neuem Docker-Netz und temporären Volumes ohne öffentliche Ports wiederherstellen. Keine echten Klartextdaten oder Recoverykeys in Logs/Artefakten; Ziel/temporäre Daten am Ende entfernen. Source, Ciphertext-Writer und Restore logisch und über Credentials getrennt halten. Ein Job im öffentlichen Pluginrepo ist dafür keine geeignete Abkürzung.

Lesend verifiziert: Backuprepo private, Main vorhanden, Actions bisher0 Workflowläufe. Actions-Einstellung, Defaulttokenrechte, Secretinventar sowie Organisations-Billing-/Actions-Kontingent per verfügbarer Integration HTTP403. **Weder Aktivierung noch kostenloses Restkontingent oder wirksame Kostensperre damit bestätigt. Kein privater CI-Lauf gestartet.** Keine neuen Dienste, Karten, Abos oder kostenpflichtigen Runner angelegt.

Offizielle aktuelle Dokumentation am06.11.2026 geprüft:

- https://docs.github.com/en/billing/concepts/product-billing/github-actions – Standardrunner in öffentlichen Repos kostenlos; private Repos nutzen monatlich enthaltene Compute-/Storagekontingente, Mehrverbrauch kann berechnet werden. Aktuelle Tabelle beschreibt GitHub Free Compute als Geldäquivalent, daher keine veraltete feste Minutenbehauptung. Große Runner können auch bei öffentlichen Repos kostenpflichtig sein.
- https://docs.github.com/en/actions/reference/runners/github-hosted-runners – Linuxrunner für private Repos verfügbar.
- https://circleci.com/pricing/ – Free-Credits auch für private Repos möglich; tatsächlich verfügbarer Accountplan, Credits und Refillzustand hier nicht über einen Connector prüfbar.

Vor einem privaten Job verfügbares Compute-/Storagekontingent und die wirksame Sperre kostenpflichtigen Mehrverbrauchs administrativ prüfen. Ein Jobtimeout allein ist keine Kostensperre. CircleCI ist eine Alternative, falls dort kostenlose Ressourcen nachweisbar verfügbar sind; weder „gratis Linuxserver“ noch unbegrenzte Privat-Runnerzeit versprechen.

## Eigentümerschlüssel muss nicht in CI

Für den CI-Restore ist eine **zusätzliche Verschlüsselung desselben echten Snapshots** an einen frischen kurzlebigen Testempfänger möglich:

1. Privater Restorejob erzeugt seinen temporären Schlüssel im Arbeitsspeicher und veröffentlicht ausschließlich den öffentlichen Testempfänger im privaten Workflow-Handoff. Der private Testschlüssel bleibt im laufenden Restorejob; kein Joboutput, Secret oder Artefakt mit dem privaten Schlüssel.
2. Der freigegebene Sourcejob erstellt **einmal** die konsistenten Datei-/SQL-Komponenten. Aus exakt diesen validierten Komponenten erzeugt er das dauerhafte PGPony-Eigentümerarchiv und separat das Archiv für den Testempfänger. Übereinstimmung der internen Komponentenhashes ist Pflicht. Kein zweiter, zeitlich abweichender Export als vermeintlich gleicher Snapshot.
3. Der Restorejob erhält über private Übergabe das Testarchiv, entschlüsselt lokal im Job, prüft Manifest und alle Komponentenhashes und führt den isolierten Docker-WP/MariaDB-Restore aus. Nur sanitisiertes Prüfergebnis und Build-/Backupreferenzen verlassen den Job. Dauerhafte verschlüsselte Sicherung/Readback bleibt Aufgabe des getrennten privaten Writers.

**Wichtige Grenze:** Dies prüft die tatsächliche Datenwiederherstellung aus demselben Snapshot mit einem Testschlüssel. Es ersetzt nicht den separaten Nachweis, dass der Betreiber sein dauerhaftes PGPony-Archiv mit seinem Recoverykey entschlüsseln kann. Dafür muss kein Linuxserver betrieben werden; Schlüssel-/Recoveryprüfung bleibt auf dem Betreibergerät. Der vorhandene private PGPony-Schlüssel wird nicht nach CI übertragen.

## Erforderliche Anpassung und nächster konkreter Schritt

Das ist ein **Architekturvorschlag, noch keine ausgeführte Pipeline**. PR64 stellt bounded Capture, getrennten Publisher und isolierte Recoveryfunktionen bereit; er enthält weder die privaten Workflowdefinitionen noch die parallele Empfänger-/Handoffkoordination. Seine Main-Repo-Bindung verweist aktuell auf orgavela/Orgavela-Plugin; eine Ausführung im privaten Backuprepo benötigt eine explizit reviewte vertrauenswürdige Orchestrierung und darf nicht durch gefälschte Kontextvariablen umgangen werden.

Nächste Arbeit: Work prüft PR64 weiter; Codex ergänzt nach administrativ belegtem Freikontingent/Kostenschutz die private Workflowdefinition, Source-/Writerrechte und den kurzlebigen Testempfänger-/Handoffweg. Receiverjob muss Schlüssel während der Source-/Writerarbeit ausschließlich im Speicher halten, Timeouts/Abbrüche und Cleanup nachweisbar behandeln. Kein persistenter privater Schlüssel zwischen Jobs, keine öffentlichen Backup-Artefakte, keine Source-Secrets für unreviewten PR-Code. Anschließend synthetische Providerprobe, dann echter Capture/Readback/CI-Restore. Vor diesem Nachweis keine Zugangsschutz-/M3-Uploadbehauptung.

PR62 und64 bleiben Draft, orgavela.com unverändert, V1-Abnahmen unverändert offen.

## Fortschreibung: Runnerfreigabe und tatsächlicher Providerlauf

Betreiber bestätigt 0€-Actionsbudget und erlaubt Standard-Linuxrunner. Privater Probe-Run [42231617036](https://github.com/orgavela/orgavela-backups/actions/runs/42231617036) auf6e22311 erfolgreich: Linux/Docker, internes Netz, private temporäre Fixtures, keinerlei Quellverbindung/Backupdaten/Keys/Artefakte. Frühere0-Run-Inventur ist damit historisch. Keine Kostenkonfiguration geändert. Nächster tatsächlicher Blocker ist das Anlegen des Source-Environments: IntegrationHTTP403, ReadbackkeineEnvironments. [Exakter administrativer Setupschritt im privaten Repo](https://github.com/orgavela/orgavela-backups/blob/2719278/operations/O03_ADMIN_ENVIRONMENT_SETUP.md). Orchestrierung und echter Restore weiterhin nicht ausgeführt.

## Fortschreibung: gespeicherte Werte und synthetischer Providerrestore erfolgreich

Run42261464603 prüft die tatsächlich hinterlegten Sourcewerte erfolgreich; Root wurde als Secret erkannt und kann dort bleiben. Private Controller430c000 ist für **synthetischen** Betrieb implementiert, Run42262492546 mit3/3Jobs erfolgreich: neuer WP/MariaDB-Testbestand, Public-Key-Handoff bei speichergebundenem privatem Testschlüssel, Dualverschlüsselung derselben Komponenten, drei Hashgegenprüfungen und isolierter Dockerrestore. Kein Betreiberkey, keine Source-Secrets im synthetischen Workflow, keine Live-Capture-/Releasefunktion. Reviewbarer Code und genaue Belege im privaten Backuprepo operations/O03_SYNTHETIC_HANDOFF_EVIDENCE.md. Frühere Aussage „Orchestrierung noch nicht implementiert“ ist für die synthetische Variante damit historisch überholt; echte Capture/Publish-/Readbackanbindung bleibt offen.


### O03-Sicherheitskorrekturen – Rückgabe an Work,06.11.2026

PR64-Engine317ce93: Snapshot-PHP inert/separater gepinnter Core, tatsächlicher TAR-Inventarabgleich und signierter privater Controller mit unabhängigem integriertem Engine-Gate. Öffentliche34 Regressionen grün (42268076080/42268085720). Privater Controllercb834f3: synthetische Pipeline42268124152 mit3/3 Jobs erfolgreich, vier Abbruchfälle und3354 inert geprüfte Dateien; Identitätsprobe42268119301 mit2/2 erfolgreich und unintegrierte Engine erwartungsgemäß verweigert. Kein echter Snapshot, IONOS-Abbruch, privater Release-Readback oder echter Restore. Nächste Zuständigkeit Work: [unabhängige Sicherheitsnachprüfung](O03_SECURITY_RECHECK_2026-11-06.md), dann reguläre Parentintegration; anschließend Codex für echte private Sicherungs-/Restorekette. PR62/64 Draft/ungemergt, Website unverändert, alpha.2 letzter Livebeleg; V1-Zähler unverändert.
