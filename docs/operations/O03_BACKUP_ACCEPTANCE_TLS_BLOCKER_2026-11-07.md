# O03 – integrierte Backup-Pipeline, tatsächliche TLS-Sperre

Stand 07.11.2026. **Kein echter Snapshot, kein Produktionsrelease und kein
Restore echter Backupdaten aus dieser Abnahme. O03/V1 bleiben offen.**

Die Pipeline ist regulär integriert. Capture, Veröffentlichung und Restore
laufen mit getrennten Rechten. Nur der Source-Schritt erhält Hostingbindings;
nur der Publisher erhält Release-Schreibrecht. Ein zusätzlicher temporärer
Restore-Empfänger ist für diese Abnahme ausdrücklich autorisiert; sein privater
Schlüssel bleibt im Receiver-RAM. Der Eigentümer-Empfänger bleibt unverändert.

## Exakter geprüfter Stand

- Engine `0f0676d53e23fac7169667a14d1bed0197cce22d`, regulärer PR #72.
- Exakte Main-CI: [Foundation 42367959744](https://github.com/orgavela/Orgavela-Plugin/actions/runs/42367959744),
  [O03 42367959597](https://github.com/orgavela/Orgavela-Plugin/actions/runs/42367959597),
  [Truth 42367959617](https://github.com/orgavela/Orgavela-Plugin/actions/runs/42367959617)
  und echter CircleCI source-integrity erfolgreich.
- Privater Controller `3655abf9f4dd0a437a3bea135b713971246f2a1b`, regulärer PR #8;
  eigene Main-CI `42368110168` erfolgreich.
- 44 Python-Regressionen, 13 synthetische Abbruchfälle und Protokollprobe;
  erweiterte tatsächliche TLS-/Dumpprobe mit unbekannter CA, falschem Hostnamen
  und sourcekontrollierten Trustpfaden als Negativfälle erfolgreich.
  Private CI: zehn Gate-/Recovery-/PHP-Quittungstests und drei Paket-/Rechtetests.

## Tatsächlich ausgeführt

| Prüfung | Beleg | Ergebnis |
| --- | --- | --- |
| Ein verschlüsseltes Release vollständig zurückladen und genau diese Komponenten restaurieren | Privater synthetischer Run `42362945948`, Release `437494217` | PASS ausschließlich synthetisch: drei Assets, 52 Tabellen, 3.354 inerte Dateien; kein Source-PHP ausgeführt, Ziel entfernt |
| Unabhängiger Recoveryzugang auf finalem Controller | Privater IONOS-Run `42368230230` | PASS: zweite Sitzung entfernt ausschließlich eigene Canary, kein Wartungsmarker |
| Preflight auf derselben Controller-SHA | Privater IONOS-Run `42368333459` | PASS: CLI 8.3.36, Prozess-/Watchdog-/Hardlink-/Tabellen-/Lockpfad, Origin erreichbar; kein Marker/Snapshot |
| Tatsächliche TLS-/Dump-Ressourcenprobe | [Privater IONOS-Run 42368432293](https://github.com/orgavela/orgavela-backups/actions/runs/42368432293) | FEHLER: Zertifikatsprüfung verweigert Verbindung, vor Dump und Wartung |
| Laufzeit-/Archivgrenzen mit echtem Dump und Dateisystemarchiv | Nicht abgeschlossen | OFFEN; nach TLS-Positivbeleg |
| Echter Snapshot, privates Produktionsrelease, vollständiger Rückdownload und isolierter Restore genau dieses Snapshots | Nicht gestartet | OFFEN |

Die finale TLS-Probe erreicht im Joblog um 11:31:01 UTC
`dump_client_validated`, `database_validated`, `database_tls_probe_started`, dann
`dump_tls_certificate_untrusted` (Clientfehler 2026). Der feste System-CA-Speicher,
die PHP-Laufzeit und deren kanonische Pfadvorfahren haben zuvor die Prüfung auf
bekannte Eigentümer/Modusbits und fehlende Source-Schreib-/Ersatzrechte bestanden.
Die Voraussetzung einer bestimmten numerischen System-UID ist behoben.
Die tatsächliche Zertifikatsprüfung bleibt trotzdem erfolglos.

Der Fehler tritt vor Wartungsmarker, Tabellenlocks dieser Dump-Probe,
Dump/Archivierung und Verschlüsselung auf. Es wurde anschließend keine
Dateisystemprobe oder echte Capture gestartet. Eine unabhängige öffentliche
HTTP-Prüfung nach dem Fehler lieferte 200; das ersetzt keinen TLS- oder
Cleanupnachweis. Keine TLS-Ausnahme und kein aus dem abgewiesenen Peer übernommener
Trustanker. Keine Source-Rohdaten oder privaten Schlüssel im öffentlichen Beleg.

## Fehlende Voraussetzung und nächster Schritt

Es fehlt eine unabhängig bestätigte IONOS-CA-/Zwischenzertifikatskette oder ein
vom Provider bestätigter TLS-Endpunkt mit prüfbarer Identität für die bestehende
Datenbank. Der Betreiber kennt derzeit keine entsprechende Angabe. Die geprüften
öffentlichen IONOS-Anleitungen zu [CLI-Verbindungen](https://www.ionos.com/help/hosting/using-mysql-databases-for-web-projects/connecting-to-mysqlmariadb-databases-with-the-command-line-environment/),
[SSH-Backups](https://www.ionos.com/help/hosting/backing-up-and-restoring-a-mysql-database/backing-up-and-restoring-mysql-and-mariadb-databases-using-ssh/)
und [Zugriffsproblemen](https://www.ionos.com/help/hosting/troubleshooting-mysql-databases/connection-and-access-problems-with-mysqlmariadb-databases/)
liefern diese CA-/Fingerprintangabe nicht. Das ist keine Aussage über sämtliche
IONOS-Unterlagen oder die konkrete Kontooberfläche. Ein Provider-Kontozugang ist
hier nicht praktisch belegt; der verifizierte unabhängige Zugang ist SSH.

Eine konkrete, noch nicht versendete Providerfrage liegt im
[privaten TLS-Befund](https://github.com/orgavela/orgavela-backups/blob/main/operations/O03_TLS_TRUST_BLOCKER_20261107.md).
Es wurde keine Nachricht an IONOS gesendet. Nach belastbarer Providerangabe:
Herkunft, Hostname und Kette prüfen; nötige enge Anpassung regulär integrieren
und auf dem exakten Commit testen; frische gleiche-SHA-Recovery/Preflight;
erst dann TLS-Dump-/Dateisystemgrenzen und echter Capture/Release/Rückdownload/
isolierter Restore. Keine automatische Zeitplan- oder Löschfreigabe.

V1 unverändert offen: 62/71 historisch implementiert, M0–M7 0/8, P4 0/4,
R01–R18 0/18. PR #62 bleibt Draft; #64/#65 sind integriert. Alpha.2 bleibt
letzter Plugin-Liveversionsbeleg. Unabhängige O03-Nachprüfung sowie weitere
formale Recovery-Time-/Retention-/Providerinventar- und V1-Belege bleiben offen.
