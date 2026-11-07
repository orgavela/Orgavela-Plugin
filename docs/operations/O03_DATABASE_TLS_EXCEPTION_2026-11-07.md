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

Zum Zeitpunkt der Implementierung: noch kein neuer IONOS-Lauf auf diesem Code.
Erst reguläre Integration und exakte Engine-/Controller-CI, dann neue unabhängige
Recovery/Preflight, TLS-Dump-/Dateisystemgrenzen, echter Capture, private
Veröffentlichung, vollständiger Release-Rückdownload und isolierter Restore
genau dieses Snapshots. V1 und die Backup-Gesamtabnahme bleiben offen.
