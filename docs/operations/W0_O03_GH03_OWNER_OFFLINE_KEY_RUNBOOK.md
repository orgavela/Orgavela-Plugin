**HISTORICAL age/AgePony guide – not the current OrgaVela backup format as of 20.10.2026.** New operator process: [PGPony/OpenPGP owner guide](W0_O03_PGPONY_OWNER_OFFLINE_KEY_RUNBOOK.md). Preserve existing AgePony identity and printed key as historical materials; they do not recover OpenPGP.

# GH-03 – age-Schlüssel ausschließlich beim OrgaVela-Betreiber erzeugen und Recovery aus zweiter Offline-Kopie prüfen

**Datum:** 17.10.2026 · **Status:** Anleitung vorbereitet, **auf Betreibergerät nicht ausgeführt**, GH-03 nicht abgenommen.  
**Verbindliches Format:** [GH-03 Verschlüsselungs-ADR](W0_O03_GH03_ENCRYPTION_RECOVERY_ADR.md): **age** für künftige echte Backups, künstliches GH-04-OpenPGP bleibt nur Testnachweis.  
**Sicherheitsgrenze:** Der **PRIVATE** Schlüssel wird **nie** von ChatGPT, GitHub, CircleCI, IONOS, E-Mail, Cloud-Sync oder einem Backup-Producer gesehen. **Keinen privaten Schlüssel, keinen Screenshot und keine Schlüsseldatei in den Chat schicken.**

## 1. Vorbedingungen und Medien

- Ein eigenes, vertrauenswürdiges Windows-Gerät mit aktuellen Sicherheitsupdates und aktuellem Malware-Schutz; für die **Schlüsselgenerierung** vom Netz trennen. Wenn das Gerät nicht verfügbar ist, **GH-03 bleibt offen**, aber es dürfen Vorbereitungen getroffen werden.
- Zwei unabhängig gegen Verlust und unberechtigten Zugriff geschützte **verschlüsselte Offline-Medien** (zum Beispiel zwei separat verschlüsselte USB-Sticks mit Windows BitLocker To Go oder anderweitiger vollumfänglicher Medienverschlüsselung); nach Prüfung an **zwei unterschiedlichen Orten** verwahren. Keine Synchronisation mit OneDrive, iCloud, Google Drive, Dropbox oder Cloud-Sicherungssoftware. Die Medien müssen im Notfall ohne den ursprünglichen PC nutzbar sein; Wiederherstellungs-Passphrasen separat gesichert.
- Sichere Aufbewahrung ist wichtiger als bloßes Kopieren: zwei Dateien auf demselben PC/Stick gelten **nicht** als zwei unabhängige Kopien.
- Für den Test ausschließlich künstlichen Text; **keine WordPress-Dateien, Datenbanken, Benutzer- oder Minderjährigendaten**. Wenn bereits echte Schlüssel unter anderem Format existieren, **nicht überschreiben**.
- Das langlebige Besitzer-Keymaterial **nicht** auf dem CircleCI-Runner oder GitHub-Repository generieren. Der zuvor einmalig für GH-04 verwendete GPG-Testkey ist nicht geeignet.

## 2. Einmalige Installation auf Windows – noch online, ohne Schlüssel

PowerShell auf dem eigenen PC öffnen (noch **online**, zum Installieren des offiziellen kostenlosen age-Clients):

```powershell
winget install --id FiloSottile.age --exact
```

PowerShell gegebenenfalls neu öffnen und **vor** dem Erzeugen eines Schlüssels prüfen:

```powershell
age --version
age-keygen --version
```

Wenn `age-keygen` nicht gefunden wird, **nicht** auf zufällige Drittanbieter-Downloader ausweichen. Im Zweifel die offizielle Seite https://github.com/FiloSottile/age und deren Release-Dateien konsultieren.

**Erst nach Installation Netzverbindung trennen**, alle Cloud-Sync-Prozesse stoppen, die zwei verschlüsselten USB-Datenträger lokal entsperren und einen privaten Offline-Arbeitsbereich öffnen.

## 3. Private Identität erstellen und zwei sichere Offline-Kopien testen

Die folgenden PowerShell-Befehle sind **eine lokale Betreiberaktion**. Die Laufwerksbuchstaben `E:` und `F:` sind **nur Beispiele** und müssen auf dem Betreiber-PC die tatsächlich gewählten **zwei verschlüsselten USB-Sticks** bezeichnen. **NICHT** in einem Git-Klon, im öffentlichen Repository, in synchronisierten Benutzerordnern oder in einem Chat/Remote-Terminal ausführen.

```powershell
$ErrorActionPreference = 'Stop'
$stick1 = 'E:\OrgaVela-Recovery'
$stick2 = 'F:\OrgaVela-Recovery'

# Beide Datentraeger muessen jeweils EIGENE geschuetzte OFFLINE-USB-Medien sein.
if (-not (Test-Path 'E:\') -or -not (Test-Path 'F:\')) {
    throw 'Mindestens ein geschuetzter USB-Datentraeger fehlt.'
}
New-Item -ItemType Directory -Force -Path $stick1, $stick2 | Out-Null
$identity1 = Join-Path $stick1 'orgavela-age-recovery-v1.txt'
$identity2 = Join-Path $stick2 'orgavela-age-recovery-v1.txt'

# Nie stillschweigend einen bereits existierenden langlebigen Schluessel ersetzen.
if ((Test-Path $identity1) -or (Test-Path $identity2)) {
    throw 'Ein Recovery-Key liegt bereits vor. NICHT ueberschreiben.'
}

age-keygen -o $identity1
if ($LASTEXITCODE -ne 0) { throw 'Erzeugen des private Keys fehlgeschlagen.' }

# Nur der folgende age1... PUBLIC RECIPIENT darf spaeter weitergegeben werden.
$recipient = (age-keygen -y $identity1).Trim()
if ($LASTEXITCODE -ne 0 -or $recipient -notmatch '^age1[0-9a-z]+$') {
    throw 'Oeffentlicher age-Empfaenger ungueltig.'
}

Copy-Item -LiteralPath $identity1 -Destination $identity2 -ErrorAction Stop
$recipient2 = (age-keygen -y $identity2).Trim()
if ($LASTEXITCODE -ne 0 -or $recipient -cne $recipient2) {
    throw 'Zweite Key-Kopie hat einen anderen oeffentlichen Empfaenger.'
}
if ((Get-FileHash -LiteralPath $identity1 -Algorithm SHA256).Hash -ne
    (Get-FileHash -LiteralPath $identity2 -Algorithm SHA256).Hash) {
    throw 'Private Offline-Kopien sind nicht bytegleich.'
}
Write-Host 'Offline-Kopien pruefbar identisch (PRIVATE Datei niemals anzeigen).'
Write-Host 'PUBLIC age recipient:' $recipient
```

**Achtung:** `age-keygen` könnte in einer Terminalausgabe einen öffentlichen Empfänger anzeigen; das ist unproblematisch. Die Inhalte der `orgavela-age-recovery-v1.txt` **niemals mit `Get-Content`, `cat`, `type` oder Screenshots anzeigen**. Auf einem gemeinsam benutzten Rechner lieber von vorneherein auf einem eigenen offline gehaltenen Rechner arbeiten.

## 4. Unabhängiger lokaler Recovery-Test aus Stick 2 – ohne Produktivdaten

Der folgende Test nutzt den `PUBLIC age1…`-Empfänger aus dem vorigen Schritt und **ausschließlich die zweite verschlüsselte Offline-Kopie** des privaten Schlüssels für den Restore. Der Ordner `$env:TEMP` enthält **nur künstliche Texte**, niemals das Keyfile.

```powershell
# Voraussetzung: $identity2 und $recipient sind aus dem vorigen Abschnitt bekannt.
$probe = Join-Path $env:TEMP 'orgavela-gh03-synthetic-age'
if (Test-Path $probe) { throw 'Probeverzeichnis existiert bereits; zuerst sicher prüfen.' }
New-Item -ItemType Directory -Path $probe | Out-Null
$plain = Join-Path $probe 'synthetic.txt'
$encrypted = Join-Path $probe 'synthetic.txt.age'
$restored = Join-Path $probe 'restored.txt'

'Synthetische GH-03-Wiederherstellung; keine WordPress-Daten.' |
    Set-Content -LiteralPath $plain -Encoding utf8

age -r $recipient -o $encrypted $plain
if ($LASTEXITCODE -ne 0) { throw 'age encryption fehlgeschlagen.' }

# Test der Kopie auf Stick 2, NICHT Test des urspruenglichen Schluessels.
age -d -i $identity2 -o $restored $encrypted
if ($LASTEXITCODE -ne 0) { throw 'Recovery aus Offline-Kopie 2 fehlgeschlagen.' }

$hash1 = (Get-FileHash -LiteralPath $plain -Algorithm SHA256).Hash
$hash2 = (Get-FileHash -LiteralPath $restored -Algorithm SHA256).Hash
if ($hash1 -cne $hash2) { throw 'Recovery-Inhalt stimmt NICHT exakt ueberein.' }
Write-Host 'PASS: synthetische Verschluesselung und unabhängige Kopie-2-Recovery, SHA256 identisch.'
Remove-Item -LiteralPath $probe -Recurse -Force
```

**Für die tatsächliche Betriebssicherheit zusätzlich empfohlen:** Den Stick 2 **später am zweiten vertrauenswürdigen Gerät oder frisch getrennten lokalen Benutzerprofil** mit identischer `age`-Version testweise lesen und die Probe dort mit Stick 1 abgezogen wiederherstellen. Das ist ein besserer Nachweis für Geräteverlust als derselbe Windows-Account.

Nach bestandener Probe USB-Datenträger sauber auswerfen, Offline-Speicherung und den Schlüsselwiederherstellungszugang jeweils getrennt absichern. Den **öffentlichen** `age1…`-Empfänger separat (ohne private Bytes) offline notieren und bei der späteren GH-05-/GH-06-Implementierung gegen den tatsächlichen Besitzerkey prüfen.

## 5. Rotation, Verlust, Stop-Gates, Abnahme

**Verlust:** Wenn alle privaten Offline-Kopien und dazu erforderliche Medienpassphrasen verloren sind, werden alle bereits mit diesem `age1…`-Empfänger verschlüsselten Archive **dauerhaft unlesbar**. Repo-Privatheit, eine CircleCI-Session, GitHub-Tokens oder GitHub Release Immutability ersetzen den privaten Key nicht.

**Rotation:** Bei Verlustverdacht einer Kopie alle betroffenen Schlüssel als kompromittiert behandeln. Neue **lokale** Keypair-/Recipient-Version erzeugen, Backup-Writer auf neuen öffentlichen Empfänger umstellen, alten privaten Schlüssel für verschlüsselte **historische** Releases bis zur geregelten Migration aufbewahren. Nicht einfach alte Keys/Releases löschen. Berechtigte Zugriffe/Notfallkontakte außerhalb des GitHub-Accounts dokumentieren, niemals geheime Recovery-Daten in ein öffentliches ADR.

**GH-03-Abnahme erforderlich (alles, nicht nur einzelne Punkte):** Betreiber bestätigt (1) laufende age-Version auf eigenem Gerät, (2) neuen privaten owner-only Offline-Key, (3) zwei **physisch getrennte** und geschützte Key-Kopien, (4) denselben **öffentlichen** Recipient und bytegleiche private Schlüsselkopien, (5) künstlichen Wiederherstellungs-HASH-PASS ausschließlich aus Kopie 2, (6) realistischen Geräteverlust-/Rotationplan. Erst danach GH-03 `accepted`. Bis dahin `status=in_progress`.

**Was dem Assistenten mitgeteilt werden darf:** „age installiert; zwei getrennte verschlüsselte Offline-Sticks mit privatem Key; öffentlicher Empfänger identisch; synthetischer Wiederherstellungstest aus Kopie 2 PASS“. **Die öffentliche `age1…`-Adresse ist bei Bedarf erlaubt, private Dateien/Passphrasen/Bilder sind verboten.** Kein eigener PC verfügbar? **Nichts erzwingen:** GH-03 offen lassen, bis zwei unabhängig prüfbare Offline-Medien verfügbar sind. Die iSH/iPhone-Alternative aus dem GH-Backup-Runbook ist eine mögliche mobile Vorbereitung, erfüllt aber allein keine redundante Offline-Recovery.

**Offizielle Quellen:** https://github.com/FiloSottile/age und https://github.com/FiloSottile/age/releases . Keine eigenen GH-03-Private-Keys wurden beim Erstellen dieser Anleitung erzeugt.


## Anhang A – AgePony auf dem iPhone als GH-03-Owner-Key-Weg (Betreiberwahl 19.10.2026)

**Geltungsbereich:** Alternative zur Windows-`age-keygen`-Erzeugung; `age` bleibt das Produktionsformat des ADRs. **Dies ist eine vorbereitete Operatoranleitung, kein Nachweis für erzeugte Schlüssel oder bestandene Restore-Tests.** Der Betreiber hat AgePony als gewünschte App gewählt. GH-03 bleibt unakzeptiert, solange Offlinekopien und ein von Kopie 2 wirklich unabhängiger Restore fehlen.

### A1. Verifizierte App-Eigenschaften / Quellen

Laut offiziellem AgePony-Leitfaden https://agepony.com/guides/generate-an-age-key.php kann die App unter **Keys / Identities** eine native age-Identität und einen öffentlichen `age1…`-Recipient erzeugen. Bei Auswahl einer **nicht hardwaregebundenen, exportierbaren** App-Identität beschreibt der Hersteller einen späteren Export; hardwaregebundene Secure-Enclave-/Keystore-Keys sind dagegen nicht exportierbar. Herstellerreferenzen: https://agepony.com/support.php, https://agepony.com/docs.php und https://agepony.com/security.php. Die App unterstützt auch postquantum-`age1pq1…`, SSH und Passphrase – diese Formate sind **nicht** die durch GH-03-ADR festgelegte klassische native age-X25519-Identität. Die veröffentlichte Herstellerdokumentation belegt weder das genaue UI des jeweiligen installierten Builds noch den Erfolg eines vom Betreiber ausgeführten Exports.

### A2. Sichere Betreiber-Vorbereitung (keine privaten Daten im Chat)

1. Nur die offizielle AgePony-App über App Store / Herstellerseite https://agepony.com/ installieren. App aktualisieren, Bildschirmcode/Face ID nutzen und vor Erzeugung eine offline Durchführung vorbereiten (Flugmodus, WLAN aus). Keine Secrets oder personenbezogene Daten in Screenshots/Chat senden.
2. In **Keys / Identities** die Erzeugung einer neuen Identität auswählen; **native age / X25519 mit `age1…`** wählen und ausdrücklich **in exportierbarem App-Vault-Speicher**, **nicht** hardwaregebunden / Secure Enclave oder FIDO-only, erstellen. Falls diese Auswahl oder der spätere Private-Identity-Export in der konkreten App-Version nicht möglich ist: **hier stoppen**; keinen allein auf dem Telefon gefangenen Langzeitschlüssel zum Backupziel ernennen.
3. Ein eigenes Label wie `OrgaVela Recovery v1` vergeben (falls angeboten). Nur den **öffentlichen** `age1…`-Recipient später anzeigen und aufbewahren. **Niemals den privaten `AGE-SECRET-KEY-…`-Text, eine Identitätsdatei, QR-Code eines privaten Keys, Export-Sheet oder Passphrase an ChatGPT, GitHub, CircleCI oder über Messenger senden.**
4. Die private Identity nur über einen nachweisbar **lokalen** Exportweg auf zwei **separat medienverschlüsselte**, physische Offline-Datenträger übertragen (ggf. kontrollierter Umweg über vertrauenswürdigen, vom Internet getrennten eigenen Computer oder unterstützten lokalen USB-Transfer). Bei der iOS-Dateiauswahl auf `Auf meinem iPhone` / externes Laufwerk statt iCloud Drive oder freigegebene Ordner achten. Keine private Identity in iCloud-/OneDrive-/E-Mail-/Cloud-Backups, Chat, Repository, Passwortmanager-Sync oder CI legen. Medienzugangscodes/Wiederherstellung separat aufbewahren; keine unverschlüsselten temporären Exportdateien zurücklassen.
5. Beide Kopien **unabhängig** lesen und denselben öffentlichen Recipient daraus bestimmen, Binär-/Textinhalt der beiden Identity-Exporte lokal vergleichen (z.B. SHA256, ohne die privaten Bytes auszugeben). **Nachweis als GH03-D:** künstliche Datei mit dem öffentlichen Empfänger verschlüsseln, App-Originalidentität aus der Testumgebung fernhalten und ausschließlich mit der zweiten **Offline-Kopie** auf einem vertrauenswürdigen separaten offline `age`-CLI-Host oder neuem isolierten Testprofil entschlüsseln; ursprünglichen vs. wiederhergestellten Datei-SHA256 vergleichen. Ein bloßes Entschlüsseln mit dem weiterhin entsperrten iPhone-Vault **zählt nicht** als Offline-Kopie-2-Restore.
6. Bei Erfolg nur die abstrahierten Prüf-Fakten melden: exportierbare native age-Identität, **zwei** separat gesicherte Offlinekopien, Recipient auf beiden identisch, künstliche Kopie-2-Recovery und SHA256 **PASS**, Verlust-/Rotationplan verstanden. Erst dann GH-03 zur formalen Abnahme prüfen. Auch dann **keine** echten Backups, bis GH-05/06/07 getrennt bestanden sind.

### A3. Entscheidende Abbruchkriterien

- Nur Secure-Enclave-Key oder kein verlustfrei importier-/exportierbarer Standard-age-Private-Key → **kein GH-03-PASS**; stattdessen vorhandenen Windows-Offline-Prozess nutzen.
- Private-Identity-Export wäre nur über Cloud/Messenger/einen Drittserver möglich oder eine der Offlinekopien fehlt → **Stopp, keine echten Backups**.
- Test nur innerhalb des ursprünglichen Telefon-Vaults erfolgreich → **kein Recovery-Beweis aus Offline-Kopie 2**.
- Bei ungeprüfter `age1pq1…`-/OpenPGP-/SSH-Identity keine stillschweigende Änderung des GH-03-ADRs oder Producerformats. Bleibt `age1…` klassisch, wie dort festgelegt.

## Anhang B – Erste physische Paper-Backup-Kopie im Safe (Betreiberbestätigung 20.10.2026)

**Neu bestätigter Teilnachweis:** Betreiber sagt, er habe einen eigenen Drucker und das zuvor lokal auf iPhone/iPad in Dateien gespeicherte AgePony-Paper-Backup-PDF bereits **ausgedruckt und in einem Safe verwahrt**. Dies ist eine **operator-attested physische Offline-Papierkopie 1**, kein visueller oder kryptografischer Wiederherstellungsbeweis. Nie ein Bild, QR-Code, einen Schlüsseltext oder die Paper-Backup-PDF an Assistenten/GitHub/CircleCI senden.

**Risiken und offene Bedingungen:** (a) Es muss geprüft werden, ob der **gedruckte** QR/Text eine vollständige wiederherstellbare native **age-X25519**-Identität enthält und lesbar ist; (b) Papier-Backup eventuell passphrasengeschützt – Passphrase darf nicht mit dem Papier zusammen ungeschützt verwahrt werden und muss im Verlustfall ohne iPhone verfügbar sein; (c) nur **eine** bestätigte physische Kopie und sie liegt an einem Ausfallort; eine **zweite, separat geschützte physische Kopie an einem anderen Ort** steht aus; (d) bisher kein wirklich unabhängiger Entschlüsselungstest mit dem aus Papier zurückgewonnenen Schlüssel. Der ursprüngliche AgePony-Vault ist nicht die unabhängige Offline-Kopie 2, selbst wenn seine Datei lokal liegt.

**Empfohlener sicherer, zerstörungsfreier nächster Test:**

1. **Original schützen:** Ursprüngliche AgePony-Identität und lokale Datei auf dem iPhone unangetastet lassen. Nicht deinstallieren, löschen, in „Recently Deleted“ verschieben, überschreiben oder den Vault zurücksetzen. Nur einen *synthetischen* Testtext als age-Datei für den öffentlichen `age1…`-Empfänger bereitstellen; keine WordPress- oder echte personenbezogene Daten.
2. **Originalschlüssel ausschließen:** Auf einem **separaten vertrauenswürdigen, offline betriebenen Gerät bzw. sauber isolierten lokalen Testprofil ohne ursprüngliche AgePony-Identität**, die private Identität **allein aus dem tatsächlichen Papierausdruck** lokal einlesen oder dessen QR lesen; nur Hersteller-/Open-Source-Offline-Restore-Funktion verwenden, nie Kamera-Webseiten, Cloud-OCR, Chat, E-Mail, Sync oder einen nicht vertrauenswürdigen Druckservice. Die genaue iOS-Menüführung ist versionsabhängig und hier nicht als bereits geprüft zu behaupten.
3. Ist das Paper-Backup **passphrasenverschlüsselt**, nur lokal mit einer *separat wiedererlangbaren* Passphrase entschlüsseln. Nicht Passphrase, QR-Inhalt, private Age-Identity oder PDFs in Tickets, Logs oder Chat kopieren. Aus der *aus Papier rekonstruierten* Identität den **öffentlichen Empfänger** ableiten und lokal mit dem vorher notierten öffentlichen `age1…` vergleichen (kein privater Vergleich im Chat).
4. Testdatei ausschließlich mit der gerade aus Papier eingelesenen Identität entschlüsseln. Inhalt byteweise bzw. SHA-256 mit Ausgangstext vergleichen; temporäre private Schlüssel-/Testkopien auf dem Zweitgerät sorgfältig entfernen, soweit dies dort sicher möglich ist. Erst dieser Erfolg beweist die **Wiederherstellbarkeit des Papierausdrucks**; eine Entschlüsselung in der Original-App mit deren Original-Vault reicht nicht.
5. **Zweites Papier-/Offline-Medium** mit denselben Recovery-Daten an einem **anderen** geschützten physischen Ort lagern und Zugang sowie erforderliche Passphrase getrennt für Notfälle sicherstellen. Ohne Schritt 4 und zwei unabhängige Ausfallorte **kein GH03-PASS** und keine echten GH05–GH08-Backups.

**Hersteller-/Implementierungsquellen:** AgePony Support https://agepony.com/support.php (age-Identitäten und Offline-Verarbeitung); AgePony Android v5.0.0 https://github.com/norsehorse-dev/AgePonyAndroid/releases beschreibt für diese **Android-Version** ein QR-/Passphrase-Paper-Backup und `Settings > Restore from a paper backup` bzw. standardkonformes age-CLI-Dechiffrieren. Diese Android-Menübezeichnung ist **nicht automatisch** als iOS-UI-Nachweis zu übernehmen; auf iPhone nur die tatsächlich angezeigten Menüoptionen verwenden.

**Stand nach Betreiberangabe:** 1 physische Papierkopie/Safe bestätigt, zweite getrennte Kopie noch nicht, unabhängiger Papier-Recovery-Test noch nicht, Schutz durch Paper-Passphrase unbekannt. GH03 bleibt `in_progress`, Produktivbackups und Zeitplan blockiert.
