# O03 – freigegebene Ausnahme für Datenbank-TLS

Die ausdrückliche Betreiberfreigabe ist in Commit
`bd622f9fa9e926d7af866676af1a0224cb9d92c0` dokumentiert. Sie gilt ausschließlich
für den OrgaVela-Datenbank-Backup-/Probezugriff. Der Standardmodus bleibt TLS
mit CA-/Serverzertifikatsprüfung. SSH-Hostkey- und HTTPS-Prüfungen bleiben aktiv.

Der private Source-Schritt konfiguriert für `database-probe`, `filesystem-probe`
und `backup-acceptance` ausdrücklich:

`ORGAVELA_DATABASE_TLS_MODE=orgavela-backup-encrypted-unverified-20261107`

Andere Werte werden verweigert; ohne Einstellung gilt `verified`. Die getrennten
Abbruchproben behalten ihren bisherigen Ablauf. Die Freigabe erlaubt keine
Zeitpläne, Löschungen, Datenbankkonfigurationsänderungen oder Zugriffe auf andere
Projekte.

## Verschlüsselung ohne authentisierte Serveridentität

`--ssl` mit abgeschalteter Zertifikatsprüfung allein kann beim geprüften
MariaDB-Client still auf Klartext zurückfallen. Die Implementierung verlangt
zuerst einen tatsächlichen MySQL-TLS-Handshake mit `openssl s_client -starttls
mysql`. Dabei werden keine Datenbankzugangsdaten gesendet. Der beobachtete
öffentliche Peer-Fingerabdruck wird nur für diesen Lauf im Speicher an Probe
und Dump gebunden (`--ssl-fp` zusätzlich zu `--ssl`). Die Tests bestätigen,
dass Client und Dump damit einen Server ohne TLS vor Ausgabe von Dumpdaten
abweisen und einen wechselnden Fingerabdruck nicht akzeptieren.

**Diese Kontinuitätsbindung authentisiert den Server nicht.** Es werden weder
CA-Vertrauen noch Hostname gegen eine unabhängige Quelle bestätigt; ein aktiver
Angreifer kann weiterhin eine eigene TLS-Identität präsentieren. Der
Fingerabdruck wird nicht als neuer langfristiger Trustanker installiert.
Genau dieser fehlende Serveridentitätsnachweis ist durch den Betreiber erlaubt.
Die Kontroll-/Lockverbindung nutzt ebenfalls TLS und prüft vor Anwendungsqueries
den nichtleeren `Ssl_cipher`. Verbindungs-/Lesezeiten sind begrenzt. Bestehende
Pfad-, Runtime-, Prozess-, Größen-, Wartungs- und Recoveryprüfungen bleiben aktiv.

## Ehrliche versionierte Nachweise

Im freigegebenen Modus gilt:

- Capture-Evidenz `orgavela-ssh-capture-evidence/v4`: TLS aktiv,
  `database_server_certificate_verified=false`, exakter Modus und
  Freigabecommit, verschlüsselte Kontrollverbindung und erzwungene
  Peer-Kontinuität.
- Ressourcenproben v3 weisen dieselbe Ausnahme ausdrücklich aus.
- Paket- und privates Release-Manifest v3 enthalten `database_transport` mit
  `tls_encrypted=true`, `server_certificate_verified=false`, Policy und
  Freigabereferenz. Publisher und Restore prüfen diese Angaben unverändert.
- Capture-/Restore-Jobquittungen geben den ungeprüften Zertifikatszustand aus.
- Alte Capture-v3-/Paket-v1/v2-Nachweise behalten ihren geprüften Vertrag. Ein
  ungeprüftes Zertifikat darf nicht als Legacy-Nachweis mit `verified=true`
  durchgereicht werden. Falsche/fehlende Freigabe, Klartext, fehlende Kontinuität
  oder vorgetäuschte Zertifikatsprüfung werden verweigert.

Synthetische Prüfung: vertrauenswürdiges Zertifikat im Standardmodus, Ablehnung
unbekannter CA/falschen Hostnamens, freigegebener tatsächlicher TLS-Dump mit
unbekannter CA und ehrlicher False-Quittung, Ablehnung von Klartextservern durch
Client/Dump und Kontrollverbindung, fehlender Freigabe und wechselndem Peer.
Der verschlüsselte Rückdownload-/Entschlüsselungsrundlauf bewahrt die False-Angabe.

## Integrierter Stand und tatsächliche IONOS-Versuche

Die freigegebene Ausnahme ist über PR #74–#76 regulär integriert: Engine `42c1f95`, privater Controller `da0df3b` (PR #10–#12), exakte Main-CI einschließlich CircleCI grün.46 Pythonregressionen, PHP-Hilfstests und synthetische TLS-/Dump-Positiv-/Negativfälle bestanden. Frische IONOS-Recovery `42401850165` und Preflight `42401981757` erfolgreich. Die gezielte Dump-Ressourcenprobe `42402105567` bestätigt nach erfolgreichem Peerlesen eindeutig `dump_tls_peer_option_unsupported`: Der ausgewählte IONOS-Dumpclient bietet `--ssl-fp` nicht an. Abbruch vor Kontrollverbindung, Wartung und Dump; kein Snapshot, Produktionsrelease oder echter Restore. Die IONOS-Clientversion und andere installierte Clients wurden nicht erhoben. Nächster O03-Block: vorhandene Clientfähigkeiten ohne Wartung inventarisieren und einen kompatiblen, TLS zwingend erzwingenden Dumppfad bereitstellen; anschließend frische Vorprüfungen, Dump-/Archivgrenzen und vollständige Release-/Restorekette. `--ssl` allein ist wegen synthetisch bestätigtem Klartextfallback ungeeignet. Ausnahmequittungen weisen ausdrücklich `server_certificate_verified=false` aus, SSH/HTTPS bleiben geprüft. Unabhängiges HTTPS nach der Ablehnung200. V1/O03-Abnahme und formale Zähler bleiben offen.

| Nachweis | Exakter Stand / Lauf | Ergebnis |
| --- | --- | --- |
| Reguläre Engineintegration | PR74/75/76, `42c1f9523d397a3cee3fe9fd179f73b3af35b65b` | In main |
| Exakte Engine-Main-CI | Foundation42401486061, O0342401486086, Truth42401485996, echter CircleCI | SUCCESS |
| Privater Controller | PR10/11/12, `da0df3bc7a59f92f21f0e15dd7ae9a456e4ab477` | In main, exakte CI42401581759 SUCCESS |
| Unabhängiger Recoveryzugriff | 42401850165 | Zweite Sitzung entfernt nur eigene Canary, keine Wartung |
| IONOS-Preflight | 42401981757 | Runtime, Watchdog, Prozessidentität, Hardlinks, Tabellen-/WRITE-Lockpfad und Origin PASS; kein Snapshot |
| Tatsächliche Dump-Ressourcenprobe | [42402105567](https://github.com/orgavela/orgavela-backups/actions/runs/42402105567) | FAILED18:37:06UTC: fehlendes `--ssl-fp`, vor DB-Kontrollverbindung/Wartung/Dump |
| HTTPS danach | Unabhängige öffentliche Abfrage, Zertifikat geprüft | 200 |
| Echter Capture / Release / exakter Restore | Nicht gestartet | Offen |

Frühere Versuche42399235057 und42400555502 gehören zur dokumentierten Eingrenzung; ihre gröberen Phasen werden nicht als Dump-TLS-Erfolg ausgegeben. Der dritte Lauf bestätigt die fehlende Option direkt am ausgewählten Client. Eine andere vorhandene kompatible Clientinstallation ist weder bestätigt noch ausgeschlossen. Keine tatsächliche IONOS-Version aus einem Container abgeleitet.

Hostingzugangsdaten bleiben auf Source-Schritte begrenzt; Veröffentlichung hat eine getrennte Schreibrolle und Receiver nur Leserechte. Der zusätzliche Restore-Schlüssel bleibt wie freigegeben ausschließlich im RAM. In diesen abgewiesenen Ressourcenproben wurde noch kein Restore-Empfänger benötigt.

Die Peer-Leseprobe allein ersetzt keinen Nachweis der tatsächlich datenführenden Verbindung. Es wurde kein erfolgreicher Produktionsdump und kein Backupmanifest mit Produktionsmetriken erzeugt. Das False-Profil ist synthetisch bis zum verschlüsselten Paket, vollständigen simulierten Releaseasset-Rückdownload, Entschlüsselung und Restore-Paketvalidator geprüft; dies ersetzt keinen neuen realen Release-/Datenbankvollrestore. Der ältere isolierte synthetische Gesamtrestore42362945948 bleibt ein gesonderter historischer Beleg.

Keine Source-Clientinstallation, keine Provider-/DB-/Trustkonfiguration verändert, keine CA aus dem unauthentisierten Peer importiert. Der nächste Pfad muss eigene Laufzeit-/Klartext-/Cleanupprüfungen bestehen und regulär integriert/exakt CI-geprüft werden; danach neue gleiche-Controller-Vorprüfungen vor einem echten Backup. Keine Zeitpläne, Retention-Löschung oder formale Abnahme aus diesem Befund.
