# OrgaVela / W0–O03: ausschließlich IONOS + privates GitHub-Backup-Repository

**Verbindlicher Betreiberentscheid: 13.10.2026** · Status: **GH-01 privates Repository verifiziert; GH-02 bis GH-08 offen, kein echtes Backup/Restore nachgewiesen**. Dieses Runbook ersetzt das externe Google-Drive-Backupziel aus früheren O03-Arbeitsschritten; alte Einträge bleiben als Historie bestehen.

## 1. Architektur und Grenzen

**Alle künftig selbst erstellten OrgaVela-Offsite-Backups** werden in **einem separaten GitHub-Repository mit Sichtbarkeit `private`** als **vor dem Upload verschlüsselte GitHub-Release-Assets** abgelegt. Zusätzlich sollen die Sicherungsmöglichkeiten des **eigenen** IONOS-OrgaVela-Hostingvertrags bereitstehen. Es findet **kein** Google-Drive-Backup oder Cloud-Upload mehr statt; der zuvor manuell angelegte leere private Drive-Ordner wird nicht als Ziel benutzt. Der bisherige nur synthetische GitHub-Actions-Drive-Workflow wurde auf `main` entfernt, Commit `58b148cb2db1f8e9031d2e9a14a05db62b1c5c7a`.

- **Quelle:** ausschließlich echte OrgaVela-WordPress-Dateien + zugeordnete MariaDB; WP-Installationsidentität und Host-Key vor dem Lesen prüfen; **nie Dreamloud**.
- **Ausführungsanbieter:** **CircleCI Free** als getrenntes OrgaVela-Projekt; dessen kleiner geheimnisfreier Offline-Pilot hat echten [Build #2 success](https://circleci.com/gh/janjrre/Projekt-Wordpress-Plugin/2) nachgewiesen. Das ist **noch kein** Backupjob. GitHub-hosted Actions werden wegen des berichteten Budgetstopps nicht vorausgesetzt.
- **Ziel:** empfohlenes neues Repo **`janjrre/orgavela-backups`**, zwingend **Private**, nur notwendige Benutzer/Apps, nie GitHub Pages. Repo am 13.10.2026 über GitHub API als **private**, Branch `main`, mit Push-/Adminrechten **verifiziert**. In das Git-Repository selbst dürfen **keine** Backup-TARs, SQL-Dumps, Konfigurationen, Archive oder privaten Schlüssel committed werden.
- **Speicherart:** GitHub **Release-Assets**; je Asset **unter 2 GiB**, bei größeren Gesamtarchiven mehrere einzeln verschlüsselte Paketteile mit Manifest und prüfbarer Reihenfolge. Release-Metadaten/Tag/Namen nur mit nicht vertraulichen Zeit-/Typinformationen, keine WordPress-Personendaten. Die normalen per Release automatisch generierten Source-ZIPs sind **keine** WordPress-Backups.
- **Verschlüsselung:** lokale, vom Betreiber kontrollierte `age`-Public-/Private-Key-Verschlüsselung **vor** Veröffentlichung bei GitHub. CircleCI erhält **nur den öffentlichen Empfängerschlüssel**. Privater Recovery-Key mit zweiter offline gelagerter Kopie **nie** in GitHub, CircleCI, IONOS, Google Drive, Chat oder Issues. Es muss für den **echten Wiederherstellungstest** ein erreichbarer Betreiber-/privater Restorehost existieren.
- **Nachweis:** Snapshot-Konsistenz und Zeitabgleich Datei/SQL, SHA-256 der verschlüsselten Daten **vor Upload und nach vollständigem Download**, verschlüsselt enthaltenes Inventar/Hashliste für WordPress und SQL, separates privates Entschlüsseln und **wirklich isolierter WordPress+MariaDB-Restore**. Nur beim Bestehen ist O03/B04 abnahmefähig.

**Bewusst akzeptierter Nachteil:** Quellcode und zusätzliche Backups liegen beim **gleichen Anbieter und mutmaßlich Konto GitHub**. Ein Kontoverlust oder GitHub-Ausfall betrifft beide. Der Betreiber hat diese Variante **bewusst statt Google Drive gewählt**. Die separate private Repository-Grenze und selbst kontrollierte Verschlüsselung verringern Datenoffenlegung, sind aber **kein unabhängiger Drittanbieter / unveränderlicher WORM-Speicher / vollständiges 3-2-1-Backup**. Daher IONOS-Recoveryverfügbarkeit tatsächlichen Hostingvertrags prüfen, unabhängig vom GitHub-Quellzugang, und Notfallkontakt/Key/Wiederherstellungsablauf außerhalb GitHub verfügbar halten (kurze schriftliche Runbook-/Key-Kopie offline, **keine** zusätzliche Daten-Backupablage entgegen Betreiberentscheidung).

## 2. Einmalige Betreiberaktion: privates Repository anlegen

Der hier verfügbare GitHub-Connector kann GitHub-Dateien/Issues/PRs bearbeiten, aber **kein neues Repository erstellen und keine privaten Repo-/CircleCI-Secrets konfigurieren**. Ohne manuelle GitHub-Oberfläche nicht als „bereits angelegt“ deklarieren.

1. [GitHub → Neues Repository](https://github.com/new) öffnen.
2. **Owner:** `janjrre` · **Repository name:** empfohlen `orgavela-backups` · **Visibility:** ausdrücklich **Private**.
3. Kein Template, keine importierten persönlichen Dateien. Eine kurze README ist zulässig und als Tag-Ziel für Releases praktisch (falls GitHub sie anbietet, „Add a README“ aktivieren); keine privaten Tokens dort hinterlegen.
4. Neue Seite öffnen und verifizieren, dass die Sichtbarkeit **Private** ist. Eine neue URL ist **erst nach tatsächlicher Erstellung** der Beleg: `https://github.com/janjrre/orgavela-backups`.
5. Kein Zahlungsupgrade/Actions-Budgetanschub nötig. Ein normales privates Repository ist grundsätzlich auf GitHub Free nutzbar; zusätzliche kostenpflichtige Funktionen, automatisierte Credits oder Verträge **nicht** aktivieren.

**Wichtig:** Bis die geschützte Repo-Identität und tatsächliche Rechte bestätigt wurden, weder Testarchiv noch echten WordPress-/SQL-Snapshot dorthin hochladen. Falls der Name bereits belegt ist: anderes privates `janjrre/*`-Repo wählen und Dokumentation/Werte **vor dem ersten Upload** konsistent anpassen.

## 3. Einmalige Betreiberaktion: Schlüssel und GitHub-API-Zugriff

- Auf eigenem vertrauenswürdigem Rechner `age-keygen -o orgavela-private-age-key.txt`; `age-keygen -y orgavela-private-age-key.txt` gibt den **öffentlichen** `age1...`-Empfänger aus. Privaten Schlüssel und separate Recovery-Kopie sicher offline verwahren. Niemand in ChatGPT/CircleCI/GitHub fragt den privaten Schlüssel an.
- Im [GitHub Developer settings → Fine-grained tokens](https://github.com/settings/personal-access-tokens/new) **einen** ablaufenden, nur auf das **private Backup-Repo** eingegrenzten **fine-grained PAT** erstellen: Resource owner `janjrre`, **Only select repositories → `orgavela-backups`**, `Contents: Read and write`, Minimum Metadata-Lesen; **keine** Admin/Secrets/Workflow-Berechtigungen, keine `Projekt-Wordpress-Plugin`-/Dreamloud-Rechte. Ablauf/Rotation/Revocation dokumentieren. [Offizielle GitHub REST: Release create / Contents write](https://docs.github.com/en/rest/releases/releases).
- Private Token-Eingabe **nur** im geschützten CircleCI-Context für **OrgaVela**, beispielsweise `orgavela-backup-releases`, als `ORGAVELA_BACKUP_GITHUB_TOKEN`; **niemals** ChatGPT, öffentliche Repo-Datei, Commit oder Issue. Öffentlicher `age1...`-Empfänger kann als CircleCI-Environment-Variable `ORGAVELA_BACKUP_AGE_RECIPIENT` gesetzt werden. **Noch keine** produktiven SSH-Credentials eintragen.
- Den CircleCI-Context **mindestens** auf das Projekt `janjrre/Projekt-Wordpress-Plugin` und genau geschützte Branches/Workflows beschränken; **SSH-Reruns unterbinden**, soweit CircleCI UI unterstützt; GitHub-Branchschutz auf tatsächliche Push-Rechte prüfen. **Solange nicht beweisbar, dass nicht vertrauenswürdige PR-/Branch-Codeänderungen keinen Zugriff auf den Token erhalten, Token nicht eintragen und keine geheime Jobstufe starten.** Bei CircleCI öffentlichem Quellrepository sind normale Config-Branch-Filter allein kein ausreichender Schutz gegen bösartigen Commit auf demselben Branch.
- Für die spätere *echte* Daten-Sicherung werden separate **OrgaVela-only** IONOS-SSH-Geheimnisse im streng beschränkten **Quelljob** benötigt (GitHub-Environment-Secrets werden nicht automatisch an CircleCI übertragen). Ein Uploadjob sollte nur ein **bereits verschlüsseltes Artefakt** plus beschränkte GitHub-Repo-Schreibrechte sehen; **nicht** gleichzeitig IONOS- und GitHub-Secrets in einem unüberprüften Job kombinieren.
- Beim ersten Test vor echten Daten nur **synthetische** Dateien und verschlüsselte Testarchives benutzen; keine echten Produktiv-Backups im öffentlichen CircleCI-Artifactspeicher/Logs. Credentials nur unter Betreiberfreigabe.

## 4. Erste technische Teststufen und Erfolgskriterien

| Gate | Aktion | Nachweis und Stopbedingung |
|---|---|---|
| GH-01 | Privates separates GitHub-Repo + Sichtbarkeit/Owner/Release-Rechte prüfen | GitHub API belegt `visibility=private`; bei `public`, 404 oder ungeklärten Rechten: **stop** |
| GH-02 | CircleCI Free-Credits, Branch-Protection und Context-Sichtbarkeit prüfen | Nur Betreiber-/Reviewed-Branchcode darf Release-PAT erhalten; keine Context-Secrets auf offenen PRs |
| GH-03 | age-Public-Key-Encrypt/Decrypt und synthetische Manifest-/SHA-256-Regressionsprobe | Privater Schlüssel ausschließlich lokal, Prüfdaten künstlich, Negativtests für defekten Ciphertext |
| GH-04 | Einmaliger **manueller**, rein synthetischer encrypted Release-Asset-Upload aus CircleCI → privates Backup-Repo | GitHub API liefert Release-/Asset-ID, ausschließlich Ciphertext unter 2 GiB; voller Rückdownload + SHA-256 identisch. Keine echte WP-/SQL-Datei |
| GH-05 | Betreiber entschlüsselt genau dieses synthetische Asset **privat**, Prüfinhalt stimmt | Erst danach Authentifizierung/Backups mit echten Daten überdenken |
| GH-06 | Geprüfter vollständiger **einmaliger echter** WordPress-Datei-/MariaDB-Read-Snapshot (nur OrgaVela) → vor Upload `age` → Release Asset → unabhängiger Rückdownload | Alle Files/DB, kontextgebundenes Manifest, SQL-Export-Konsistenz, Vollständigkeit + Hash. Keine Klartexte in Release, Git-Historie, Jobartefakten oder Logs |
| GH-07 | Genau GH-06 in einer isolierten privaten WordPress/MariaDB-Kopie zurückspielen, ohne externen Netzwerkzugang/Cron/Mail/Webhooks | 29 UOP-Tabellen-/Index-/Optionen-Health, DB-Version 2/2, notwendige Dateien, reale Restorezeit. Bei Fehlschlag O03/B04 **offen** |
| GH-08 | Regelmäßigen CircleCI-Cron/Zeitplan, Benachrichtigungen und ggf. Rotation konfigurieren | **Nur nach GH-07.** Bis Betreiber ausdrückliche Aufbewahrungsentscheidung trifft: **keine automatische Löschung** bestehender Releases. Speicher-/API-/Quota-Folgen prüfen |

GitHub Releases erlauben laut [GitHub Docs](https://docs.github.com/en/repositories/releasing-projects-on-github/about-releases) jeweils Assets **unter 2 GiB**, bis 1000 Assets je Release, ohne publiziertes Gesamtvolumen- oder Bandbreitenlimit. **Kein Backup-SLA:** Release-Assets lassen sich mit ausreichenden Repository-Rechten löschen, ein gesperrtes Konto kann Recovery erschweren. GitHub-Artefakte und Git-Commits sind keine gleichwertige langfristige Backupablage.

## 5. Status, Rollback und Kosten

**Aktueller Stand nach Betreiberentscheidung:** Noch **kein** `janjrre/orgavela-backups`-Repository durch GitHub API nachgewiesen; hierfür besitzt der verbundene GitHub-Connector keine `create_repository`-Aktion. **Kein** CircleCI Context/PAT, kein privater `age`-Recovery-Key, kein GitHub Releases Asset, kein echter WordPress-/MariaDB-Dump, kein echter isolierter Restore erstellt oder abgenommen. Existing `O03-CI-01` CircleCI Offline-Test bleibt weiterhin grün; O03 bleibt `partial`, B04 und R03 weiter offen.

**Rollback:** Neuer Release-/Backupmechanismus wird niemals ungeprüft produktiv aktiviert. Bei Testfehler keine Löschung oder Wiederherstellung auf der realen OrgaVela-Site; Pilotbranch/Workflow lässt sich ohne Datenmutation stoppen, Tokens im GitHub/CircleCI UI zurückziehen. Der alte Google-Drive-Workflow ist stillgelegt, kein automatischer Fallback dorthin. IONOS-Sicherungen/Vertrag unabhängig davon unverändert; ihren echten verfügbaren Verlauf im IONOS-Control-Panel bestätigen.

**Kosten:** Nur bestehender GitHub-Account und CircleCI Free, kein bezahlter Anbieter/Upgrade ohne gesonderte Zustimmung. GitHub-Free-/CircleCI-Free-/API-/Sicherheitsregeln vor geplanten regelmäßigen Übertragungen bestätigen, keine Annahme unendlicher freier Backup-Infrastruktur.


## 6. GH-02/GH-03 Vorbereitung nach erfolgreicher Repo-Erstellung (13.10.2026)

**GH-01 überprüft:** GitHub `get_repo` hat `janjrre/orgavela-backups`, `visibility=private`, `default_branch=main`, `permissions.push=true` und `permissions.admin=true` zurückgegeben. Dieser Nachweis ersetzt die früheren „nicht vorhanden“-Hinweise. Es existiert dadurch noch **kein** Release-/Backupasset.

**GH-02 Operator-Check (CircleCI, noch nicht technisch bestätigt):** Unter [CircleCI Project Settings](https://app.circleci.com/) beim eigenständigen Projekt `Projekt-Wordpress-Plugin` die verbleibenden Free-Credits und Konfiguration der Organisation prüfen. Für einen neuen Context `orgavela-backup-releases` ausdrücklich *Project Restriction* auf das UOP-Projekt und, falls für die Kontokonfiguration verfügbar, *Security Group Restriction* und *Expression Restriction* auf den **geschützten** `main`-Branch setzen. Wichtig: Das bestehende öffentliche Source-Repo hat noch **ungemergten** Draft PR #17 und keine nachgewiesene wirksame Branch-Protection für diesen Secret-Zugang. Ein normaler `filters.branches.only` in YAML **ist kein Zugriffsschutz**. Vor Anlage eines PAT/Contexts muss sichergestellt sein, dass ein Angreifer mit Schreibrechten auf Quell-Branches oder untrusted PRs **keinen** Backup-Context verwenden kann. Für ein minimales Rechteprofil sollte nach Möglichkeit eine separate, ausschließlich manuell genehmigte Backup-Konfiguration mit geprüftem Commit auf geschütztem `main` genutzt werden. Bei fehlendem Restriction-Support **nicht** einfach Tokens in den Context legen: GH-02 bleibt blockiert.

**GH-03 Betreiber-Schlüssel:** Nur auf eigenem vertrauenswürdigem Rechner `age-keygen -o orgavela-private-age-key.txt` ausführen, zweite sichere Offline-Kopie des privaten Schlüssels anlegen und Rücklesbarkeit testen. `age-keygen -y orgavela-private-age-key.txt` liefert den **öffentlichen** Empfänger `age1…`. Es ist sicher, ausschließlich diesen öffentlichen Empfänger zur Konfigurationsvorbereitung mitzuteilen; **niemals** die Datei `orgavela-private-age-key.txt`, den privaten Inhalt, Passwörter, Recovery-Codes oder GitHub PATs hier senden. Die lokale Schlüsselgenerierung ist **noch nicht erfolgt/verifiziert**. Eine generische synthetische Offline-Verschlüsselungsprüfung darf nur mit eigens generiertem **Wegwerf-Testschlüssel** erfolgen, nicht mit echtem Betreiber-Recovery-Key.

**Nächste Entwicklungsaufgabe ohne Secrets:** Branch-/PR-isoliertes synthetisches `age`-Ciphertext-/SHA-256-/Manipulations-Testskript und ein opt-in CircleCI-Job, der **weder** echte OrgaVela-/IONOS-/GitHub-Tokens **noch** öffentliche CI-Artefakte mit Klartext verwendet. Erst nach grünem Providerstatus und bestätigtem GH-02/03-Operator-Setup darf ein echter privater Release-Upload getestet werden. Der bisherige CircleCI-Pilot ist nur im separaten Branch `ci/circleci-free-pilot-o03`; auf `main` existiert noch keine `.circleci/config.yml`. Ein Branchfilter und eine geprüfte Freigabe vor Merge sind erforderlich.


## 7. GH-02 Sicherheitsbefund vom 13.10.2026: `main` **nicht geschützt**

**Neue Betreiberauskunft:** Context `orgavela-backup-releases` besteht; die Project Restriction `Projekt-Wordpress-Plugin` wurde per Screenshot gesehen, und die Expression `pipeline.git.branch == "main" and not job.ssh.enabled and not (pipeline.config_source starts-with "api")` soll laut Betreiber gespeichert sein. Da keine CircleCI-Admin-/Context-Read-Schnittstelle vorliegt, ist der **effektive gespeicherte Ausdruck nicht unabhängig nachgewiesen**.

**Unabhängige GitHub-REST-Nachweise:** Auf dem **öffentlichen** Quell-Repository `janjrre/Projekt-Wordpress-Plugin` liefert GET `/branches/main` **`protected: false`** und GET `/rulesets` eine leere Liste. Der weitere Schutz-Endpunkt `/branches/main/protection` liefert für den aktiven GitHub-Connector einen **403** wegen fehlender API-Administrationsberechtigung. Kein aktiver GitHub-Schutz ist damit bestätigt. Ohne Schutz kann geänderter `main`-Code trotz CircleCI-Expression auf den Context zugreifen; **keine Tokens und keine Live-Backups**, bis die Gefahr behoben ist.

**Erforderlicher Betreiber-Schritt (keine verfügbaren Connector-Admin-Writes):** [Source-Repository → Settings → Branches](https://github.com/janjrre/Projekt-Wordpress-Plugin/settings/branches) → **Add classic branch protection rule** → Branch name pattern **`main`**. **Require a pull request before merging** aktivieren und **Do not allow bypassing the above settings** aktivieren, sofern UI verfügbar. **Allow force pushes** und **Allow deletions** ausgeschaltet lassen; gegebenenfalls **Require conversation resolution** einschalten. **Require approvals** nur konfigurieren, wenn ein tatsächlich unabhängiger Reviewer mit erforderlichen Rechten existiert (sonst Single-Owner-Deadlock). **Require status checks** erst dann festlegen, wenn ein nachweislich verfügbarer CI-Check auf dem geplanten Branch läuft. Speichern und GitHub API `/branches/main` erneut lesen: `protected: true` allein beweist nicht alle einzelnen Regeln, deswegen UI oder Admin-API zusätzlich kontrollieren. Eine GitHub-Free-öffentliche Repository-Branchregel ist offiziell verfügbar: [GitHub Docs](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-protected-branches/managing-a-branch-protection-rule).

**Nach Aktivierung:** Künftige Assistenten-Dokumentation und Codeänderungen ausschließlich in neuen Branches/PRs (keine direkten `main`-Updates); existierende ungemergte PRs unverändert lassen; Merge nur mit separater Betreiberfreigabe. Selbst bei `protected=true` vor Context-PAT-Einrichtung noch CircleCI-PR-/Fork-/Branch-Semantik und tatsächliche Context-Regeln verifizieren. Eine noch robustere Option wäre später ein separates CircleCI-Projekt für das private Backup-Repo, statt Tokens im öffentlichen Source-Repo auszuwerten; die bisherige Betriebsstrategie nicht stillschweigend ändern. GH-02 `partial/blocked`, GH-03 bis GH-08 offen; keine produktiven Uploads oder Kosten.


## 8. GH-02 neuer Sicherheitsnachweis und GH-03 Betreiber-Schlüsseltest (13.10.2026)

### GH-02 – GitHub-Branchschutz nach Betreiberaktion

Der Betreiber hat den Branchschutz für das öffentliche Source-Repository erstellt. Neu **direkt über GitHub REST GET `/repos/janjrre/Projekt-Wordpress-Plugin/branches/main`** nachgewiesen: `protected: true`, `protection.enabled: true`, HEAD `0adacb427bd95dd87594e358ec9c6f2f5b38aae8`. Dies **ersetzt den früheren `protected:false`-Befund** aus Abschnitt 7. Im selben Abruf: `required_status_checks.enforcement_level=off`, leere Checks – bewusst noch keine festen Statuschecks verlangt. GET `/rulesets` weiter `[]`; dies widerspricht einer klassischen Branchschutzregel nicht. **GET `/branches/main/protection` bleibt mit 403 wegen GitHub-App-Rechten gesperrt**, daher sind Pflicht-PR, No-Bypass, Force-Push/Löschen und tatsächliche Admin-Ausnahmen **noch nicht unabhängig bestätigt**. Die CircleCI-Expression im Backup-Context bleibt **nur betreiberbestätigt**, nicht über CircleCI-Admin-API gelesen. **GH-02 ist deshalb teilweise belegt, NICHT voll abgenommen.** Bis alle Voraussetzungen geklärt sind **keine Backup-PATs, keine IONOS-Secrets** in CircleCI. Der Assistent bearbeitet ab jetzt GitHub ausschließlich auf separaten Branches/PRs und führt keine eigenmächtigen Merges durch.

Zur endgültigen Freigabe der Backup-Tokenverwendung: (a) GitHub-UI-Screenshot **Source-Repo Settings → Branches → classic rule for main** ohne Zugangsdaten mit Require PR, Do not allow bypass, no force push/no deletions; (b) CircleCI-UI-Screenshot des gespeicherten Expression-Ausdrucks (vorzugsweise Context-Restriktionsanzeige, keine Secrets); (c) CircleCI Free Usage/Plan und Prüfung der Sichtbarkeit für untrusted Branch-/Fork-Pipelines. Erst danach Security-Risiko und GH-02 abschließend beurteilen.

### GH-03 – `age` auf einem **vertrauenswürdigen Betreiber-Windows-PC** (noch nicht ausgeführt)

Dieser Schritt ist eine **lokale Betreiberaktion**, keine Schlüsselgenerierung durch ChatGPT oder CircleCI. Der Benutzer speichert **nur den öffentlichen `age1…`-Empfänger** zur späteren Konfiguration; der private Schlüssel darf niemals GitHub, CircleCI, IONOS, diesem Chat oder einem Synchronisationsordner/öffentlich sichtbaren Terminal-Log übermittelt werden.

1. Auf einem **eigenen Windows-PC** im PowerShell-Terminal die offiziell dokumentierte [age-Installation](https://github.com/FiloSottile/age#installation) ausführen (Free/Open Source, keine Cloudregistrierung):

   ```powershell
   winget install --id FiloSottile.age --exact
   ```

   PowerShell neu öffnen und mit `age --version` / `age-keygen --version` prüfen.

2. Sicheren **lokalen, nicht mit OneDrive/Google Drive/iCloud synchronisierten** Ordner oder (besser) **verschlüsselten USB-Datenträger** auswählen, dorthin wechseln. **Nicht** in `Dokumente`/`Desktop`/`Downloads` erzeugen, sofern die Geräte diese Ordner synchronisieren. Beispiel (nur Platzhalter durch tatsächlichen *privaten* Laufwerksbuchstaben ersetzen):

   ```powershell
   Set-Location "E:\OrgaVela-Recovery"
   age-keygen -o orgavela-private-age-key.txt
   age-keygen -y orgavela-private-age-key.txt
   ```

   Das `age-keygen -o` erstellt die **vertrauliche** Schlüsseldatei. `age-keygen -y` zeigt nur die öffentliche `age1…`-Empfängeradresse. Den privaten Dateiinhalt unter keinen Umständen kopieren oder hochladen. Die `age1…`-Zeichenfolge darf später zur Einrichtung mitgeteilt werden.

3. **Zweite verschlüsselte Offline-Kopie** dieser Datei auf einem getrennten geschützten Medium erstellen und die Lesbarkeit mit `age-keygen -y <Pfad-der-Zweitkopie>` **lokal** gegen die erste öffentliche Adresse prüfen. Das ist **Schlüsselredundanz**, keine zusätzliche WordPress-Backupablage. Ohne sichere zweite Kopie noch keinen produktiven GitHub-Backupflow aktivieren: Schlüsselverlust macht alle verschlüsselten Sicherungen unlesbar.

4. Lokalen **rein synthetischen** Verschlüsselungs-/Entschlüsselungstest im selben vertraulichen Arbeitsordner ausführen (keine echten Daten, keine Cloud, kein CircleCI):

   ```powershell
   $recipient = (age-keygen -y .\orgavela-private-age-key.txt).Trim()
   "synthetischer Test – keine OrgaVela-Daten" | Set-Content -Encoding utf8 .\orgavela-test.txt
   age -r $recipient -o .\orgavela-test.txt.age .\orgavela-test.txt
   age -d -i .\orgavela-private-age-key.txt -o .\orgavela-test-restored.txt .\orgavela-test.txt.age
   if ((Get-FileHash .\orgavela-test.txt -Algorithm SHA256).Hash -ne (Get-FileHash .\orgavela-test-restored.txt -Algorithm SHA256).Hash) {
     throw "Verschlüsselungs-/Entschlüsselungstest FEHLGESCHLAGEN"
   }
   Write-Host "Lokaler age-Test erfolgreich; Hashes identisch."
   Remove-Item .\orgavela-test.txt, .\orgavela-test.txt.age, .\orgavela-test-restored.txt -Force
   ```

   Wenn Installation oder Test fehlschlägt, kein produktives Setup, sondern Fehlermeldung **ohne** privaten Schlüsselinhalt dokumentieren.

**Abnahmelogik:** GH-03 erst nach Nutzerbestätigung des echten privaten Offline-Schlüssels, Wiederherstellungszweikopie und erfolgreichem synthetischem lokalen Roundtrip; dabei die privaten Dateien nicht an den Assistenten senden. Nur die öffentliche `age1…`-Adresse ist später für die Verschlüsselungsjobs erforderlich. GH-04 (synthetischer Release-Upload) und GH-06/07 (echte Datensicherung und privater Restore) bleiben separat offen.


## 9. GH-03 ohne PC – GitHub-Token ≠ Entschlüsselungsschlüssel (14.10.2026, Beratungsnachtrag)

**Betreiberfrage:** Kein PC verfügbar; kann das verschlüsselte Backup stattdessen nur mit einem GitHub-Token eingerichtet werden?

**Verbindliche Unterscheidung:** Ein GitHub fine-grained PAT mit ausschließlich `Contents: Read and write` auf dem separaten **privaten** `janjrre/orgavela-backups` authentifiziert **Upload/Download von GitHub-Releases**. Es erzeugt **keine eigenständige, offline wiederherstellbare Sicherungsverschlüsselung** und darf **nicht als Encryption Key oder einziges Recovery-Geheimnis** benutzt werden. Repository-`private`-Sichtbarkeit ist Zugriffssteuerung, kein Ersatz für die beschlossene `age`-Verschlüsselung **vor** Upload. Ein nur in GitHub/CircleCI gespeicherter privater Recovery-Key wäre vom gleichen Account-Ausfall betroffen und erfüllt GH-03 nicht.

**Telefon-/iPhone-Option (nur nach Betreiberentscheidung):** Die kostenlose [iSH-Shell für iOS](https://ish.app/) läuft auf dem iPhone mit lokalem Alpine-Linux-Terminal. Offizielle [`age`-Installationsanleitung](https://github.com/FiloSottile/age#installation) nennt `apk add age` für Alpine. In iSH könnte man zunächst **ohne echte Secrets** die Verfügbarkeit testen:

```sh
apk update
apk add age
age --version
age-keygen --version
```

Dies ist noch **kein nachgewiesener iSH/age-Lauf** und kann je nach App-/Alpine-Paketstand scheitern. Erst nach erfolgreicher Installation lokal in einer sicheren App-Umgebung `age-keygen -o orgavela-private-age-key.txt` und `age-keygen -y orgavela-private-age-key.txt` verwenden. Den privaten Schlüssel **nicht** in GitHub, Chat, CircleCI, iCloud Drive oder einem sonstigen Cloud-/App-Sync-Ordner speichern. Eine nur in der iSH-App verbleibende Datei kann bei App-Löschung/Telefonverlust verloren gehen; der Schutz durch iPhone-Sperrcode alleine ersetzt keine **zweite getrennte, verifizierte Offline-Recovery-Kopie**. Diese zweite Kopie kann ggf. erst bei Zugriff auf ein gesichertes physisches Medium erstellt werden. **Bis dahin nur unverbindliche synthetische Kryptoexperimente, keine echten WordPress-/DB-Backups oder automatisierten CI-Secrets aktivieren.** Auf mobilen Geräten besondere Vorsicht bei Copy/Paste, Screenshots und Debug-Logs mit privaten Identitäten.

**Sichere Zwischenlösung ohne Betreiber-PC:** Assistent kann ohne Secrets/Kosten einen **rein synthetischen CI-Test mit temporärem Wegwerf-age-Schlüsselpaar** vorbereiten, der keinerlei echte WordPress-/DB-Daten sichert und dessen temporären Schlüssel ausschließlich zur Verifikation einer **nichtproduktiven** Verschlüsselungsfunktion verwendet. Ein echter GitHub-Release-Canary mit Fine-grained PAT bleibt weiterhin bis zu GH-02-Context-/Branch-Detailprüfung und GH-03 echtem sicher verwahrten Besitzer-Recovery-Key gesperrt. Empfehlung: für sofortige Fortschritte **nur Vorbereitung**, bei länger fehlendem PC optional lokales age-Setup auf dem iPhone, ansonsten lieber warten.

**Status:** Keine GitHub-PATs angelegt oder gepostet, kein iSH installiert/getestet, kein age-Schlüsselpaar auf Betreibergerät generiert, kein CircleCI-Upload, keine echten Backupdaten. GH-01 bestanden; GH-02 teils nachgewiesen (Branch `main` API `protected:true`, Details unbestätigt); GH-03 offen, GH-04..08 offen; keine Abnahmen/Zähleränderungen.


## 10. Vergleich mit Dreamloud – „nur GH_TOKEN“ bedeutet **synthetischer** Test, nicht produktives Recovery (14.10.2026)

**Betreiberfrage:** Warum konnte Dreamloud nach eigener Erinnerung nur mit einem GitHub-Schlüssel betrieben werden, während hier zunächst ein langlebiger `age`-Recovery-Key gefordert wurde? Dreamloud hierfür **ausschließlich lesend** über GitHub geprüft; keine Änderung an Dreamloud.

**GitHub-verifizierte Tatsachen:**
- Dreamloud verwendet das private Backup-Repository [`janjrre/dreamloud-backups`](https://github.com/janjrre/dreamloud-backups); [Betriebsstatus](https://github.com/janjrre/dreamloud-portal/blob/main/docs/operations/CURRENT_STATE.md), Abschnitt 4p: Betreiber hat **nur einen auf das private Repo begrenzten kurzlebigen Fine-grained GitHub-Token als `GH_TOKEN`** im eingeschränkten CircleCI-Context `dreamloud-github-backup-test` hinterlegt. Der Token erlaubt den **GitHub-Release-Transport**; er ist *kein* GPG-Entschlüsselungs- oder Recovery-Key.
- Das [synthetische Remote-E2E-Skript](https://github.com/janjrre/dreamloud-portal/blob/test/github-release-transport-434-v3/scripts/test-github-release-remote-e2e.sh) erzeugt **im Job** mit `gpg --quick-generate-key` ein **temporäres** OpenPGP-Schlüsselpaar für ausdrücklich künstliche Daten, verschlüsselt und entschlüsselt lokal und versucht danach Remote-Upload, vollständiges Readback und separaten synthetischen Restore. Dabei erhält CircleCI vorübergehend einen künstlichen privaten Testschlüssel. Das ist zulässig **nur für synthetische Testdaten**; ein so erzeugter/gelöschter **produktiver** Recovery-Key würde spätere Wiederherstellung unmöglich machen.
- [CircleCI-v3-Testkonfiguration](https://github.com/janjrre/dreamloud-portal/blob/test/github-release-transport-434-v3/.circleci/config.yml) fordert `GH_TOKEN` nur für den per Pipeline-Bool manuell aktivierten Testjob und filtert auf Branch `test/github-release-transport-434-v3`. Der normale Offline-QA-Job hat keinen Token nötig.
- GitHub [Backup-Repo-Releases-API](https://api.github.com/repos/janjrre/dreamloud-backups/releases): bislang **zwei Entwurfs-Releases**; `backup-dreamloud-synthetic-circleci-70` besitzt echte Test-Assets `*.tar.gpg` (547 B), SHA (106 B), Manifest (336 B), ist aber `draft=true`, `immutable=false`, `published_at=null`; der ältere CircleCI-51-Draft besitzt keine Assets. [Dreamloud-Status 4v](https://github.com/janjrre/dreamloud-portal/blob/main/docs/operations/CURRENT_STATE.md) meldet CircleCI Test #93 **fehlgeschlagen** nach Upload; vollständiger unabhängiger Remote-Restore/Publikation und **jede produktive WordPress/MariaDB-Sicherung** noch unbestätigt. **Ein Testupload ist nicht dasselbe wie ein fertiges Backup.**

**Übertragbare Empfehlung für OrgaVela, ohne Architekturentscheidung vorwegzunehmen:**
1. **Geheimnisfreier Offline-Verschlüsselungs- und Hash-Test** mit temporären Wegwerf-`age`-Schlüsseln kann sofort auf sicher reviewtem Branch eingerichtet werden; Betreiber-PC/Telefon und echter Recovery-Key sind dazu **nicht** nötig.
2. Nach separat verifiziertem GH-02 (GitHub Branch Protection *Einzelregeln*, CircleCI Context Expression/Projekt-/Branch-/Forkschutz und Free-Credits) genügt für einen **synthetischen GitHub-Release-Testupload** ein isolierter, kurzer fine-grained `Contents: read/write`-PAT ausschließlich für `janjrre/orgavela-backups` im eingeschränkten OrgaVela-CircleCI-Context. Der synthetische Test **darf** eine ausschließlich vorübergehende private Wegwerfidentität besitzen; `GH-04` als **Teststufe** kann technisch vor GH-03 (langlebiger Besitzer-Recovery-Key) vorbereitet/ausgeführt werden, sofern im ursprünglichen Masterplan der Betriebsweg nicht ohne Betreiberentscheidung geändert wird. Keine echte WP-/SQL-Datei übertragen.
3. Erst **vor dem ersten echten** Backup/automatisierten Job muss GH-03 abgeschlossen sein: vom Betreiber kontrollierter langfristiger privater `age`-Key mit unabhängig aufbewahrter, getesteter Recovery-Kopie. CircleCI bekommt nur den **öffentlichen** Schlüssel; ausschließlich GitHub-PAT würde diese Bedingung **nicht** erfüllen. Der Betreiber benötigt dazu nicht zwingend einen PC, aber eine vertrauenswürdige unabhängige Key-Verwahrung.
4. Dreamloud-`GH_TOKEN`, `GPG`-Keyfiles, Contexts und andere Credentials **niemals** für OrgaVela wiederverwenden. Der OpenPGP-Mechanismus bei Dreamloud muss nicht zwangsläufig übernommen werden: OrgaVela plant weiter `age`, für beide Systeme gilt derselbe Vertrauensgrundsatz.

**Kein Ausführungsclaim:** Dieser Vergleich ist read-only; im OrgaVela-Repo nur dokumentiert, kein CircleCI-Job gebaut/gestartet, kein neuer GitHub-Token gesetzt, kein Cloud-Test/Release und keine WordPress-/MariaDB-Sicherung durchgeführt. GH-02 noch teilweise, GH-03 langlebiger Key offen, GH-04 Test offen. Formal O03/B04/R03 und V1 unverändert.


## 10. Dreamloud-Paritätsnachweis und **PC-freier synthetischer GitHub-Canary** (14.10.2026; Masterplan v2.12)

**Betreiberhinweis zutreffend:** Dreamloud erforderte für seinen **synthetischen** GitHub-Releases-Test lediglich einen GitHub-Token (`GH_TOKEN`) im geschützten CircleCI-Context. Ein dauerhaft privat gespeichertes Besitzer-Keypair wurde bei diesem Test **nicht** benötigt, weil der CI-Job selbst ein **nur für künstliche Daten verwendetes ephemeres OpenPGP-Keypair** in einem temporären Runner erzeugte. Die [verifizierte Dreamloud-Testimplementierung](https://github.com/janjrre/dreamloud-portal/blob/test/github-release-transport-434-v3/scripts/test-github-release-remote-e2e.sh) erzeugt `gpg --quick-generate-key` in temporärem `GNUPGHOME`, verschlüsselt künstliche TAR-Daten mit öffentlichem Empfänger, prüft vollständigen Rückdownload/Hashes, die `immutable=true`-Eigenschaft und entschlüsselt nur synthetische Daten. Die temporäre Testidentität wird nach dem Job verworfen.

**Direkt über GitHub bestätigt:** [Dreamloud CircleCI Build #78](https://circleci.com/gh/janjrre/dreamloud-portal/78) = success auf SHA `bd31128366ad5d732633a888d3051810acb90165`. [Privates GitHub Release `backup-dreamloud-synthetic-circleci-78`](https://github.com/janjrre/dreamloud-backups/releases/tag/backup-dreamloud-synthetic-circleci-78) ID `412690719` ist veröffentlicht `draft=false`, `immutable=true` und enthält drei vollständig hochgeladene Assets: künstliches GPG-TAR (547 Bytes), SHA256-Sidecar (106 Bytes), Manifest JSON (336 Bytes). **Kein echter Dreamloud-WordPress-/MariaDB-Backup- oder Restore-Beleg**. Das Dreamloud-Zielrepo/Token/Secrets dürfen **nicht** nach OrgaVela übertragen oder übernommen werden.

**Verbindliche Abgrenzung:** Das frühere Runbook schrieb für **jeden** synthetischen Cloudtest vorab Betreiber-`age`-Recovery-Key vor. Das wird präzisiert: Für einen **rein synthetischen, vom Betreiber manuell gestarteten** Release-Canary **ist ein temporäres Runner-Testkeypair ausdrücklich erlaubt**, **OHNE PC und OHNE dauerhaftes Besitzer-Keypair**. Dies ist nur ein Verschlüsselungs-/GitHub-Transport-/Unveränderlichkeits-Selbsttest. **GH-03 echter Besitzer-Recovery-Key bleibt zwingend vor jedem echten OrgaVela-WordPress-/Datenbank-Backup**. Die frühere iSH/iPhone-Option in §9 ist nur nötig, wenn der Betreiber schon jetzt den *dauerhaften* Key anlegen möchte.

**OrgaVela-Umsetzung, noch nicht ausgeführt:** Ein eigener OrgaVela-CircleCI-Testjob (keine Dreamloud-Secrets) soll mit `default:false` nur nach ausdrücklichem manuellen Parameter starten, in einer eng geschützten GitHub-Quellbranch/Context-Kombination laufen, ausschließlich künstliches Archiv + ephemeren Test-Key erstellen, vor GitHub-Zugriff verschlüsseln, ein **eigenes** auf `janjrre/orgavela-backups` begrenztes **ablaufendes fine-grained GitHub-PAT** im Kontext `orgavela-backup-releases` verwenden, unter 2 GiB mit API numeric Release-/Asset-IDs als Draft hochladen, alle Dateien vollständig per Download und SHA256 prüfen, erst danach veröffentlichen, `immutable=true` und unabhängige synthetische Entschlüsselung/Datei-Integrität prüfen. Keine echten IONOS-, WP-, MariaDB-, Minderjährigen- oder Teilnehmerdaten, kein Klartext im öffentlichen Sourcecode, Logs oder Assets. Keine produktiven FTP/SFTP-/SSH-Secrets in diesem Testjob, kein Copy-Paste von Dreamloud-Credentials.

**GH-02-Abhängigkeit bleibt bestehen:** Die OrgaVela-CircleCI-Expression war bislang **auf `main`** gerichtet, der frühere CircleCI-Pilot liegt aber auf dem ungemergten separaten CI-Branch; ein neuer Testbranch muss daher **vor** Token-Nutzung selbst schreibgeschützt sein und die einzig zugelassene Expression muss genau ihn treffen (oder ein gesondert freigegebener Merge integriert den Test in geschütztes `main`). Die **effektiven** Context-/Bypass-/PR-/Fork-Berechtigungen und CircleCI-Free-Credits bleiben vor produktiver Secret-Nutzung zu bestätigen. Ein bloßer GitHub-Token ohne diese Sicherheitsvorkehrungen ist nicht zulässig. Kein Merge ohne explizite Freigabe.

**Ergebnis/Status:** Für OrgaVela wurde **nur die Testreihenfolge korrigiert und dokumentiert**, nicht behauptet, dass ein OrgaVela-GitHub-Test schon gelaufen ist: GH-01 erledigt, GH-02 teilweise, GH-04 synthetischer Live-E2E und GH-03 echter Schlüssel offen, GH-06+07 echter Snapshot/Restore offen. Die V1-Abnahmen bleiben unverändert. Siehe [normativer Masterplan v2.12](V1_ACCEPTANCE_PLAN.md), [JSON](V1_PROGRESS.json).


## 11. GH-02 – Exakte Betreiber-Einstellungen und Abnahmeprotokoll (15.10.2026)

**GH-02 ist bis zur tatsächlich überprüften Konfiguration NICHT bestanden.** GitHub-GET am 15.10.2026: `main protected=true`, aber **`test/orgavela-github-release-o03 protected=false`**, SHA `a03b0e079ad0f8bd782574abd5b0ed581bf3c12a`. `GET /rulesets` = `[]` (Classic Protection erscheint hier nicht). Die GitHub-Integration kann Regeln lesen, besitzt aber keine Branch-Protection-Schreibaktion. Kein GitHub-Backup-Token erzeugt/verwendet; kein CI-Run ausgelöst.

### A. GitHub: neuen Testbranch schützen

1. Auf [Repository → Settings → Branches](https://github.com/janjrre/Projekt-Wordpress-Plugin/settings/branches) → **Add classic branch protection rule** klicken. **Exaktes Branch name pattern**: `test/orgavela-github-release-o03`. Keine pauschale Wildcard, keine Änderung der bestehenden `main`-Regel.
2. **Require a pull request before merging** aktivieren; optional **Require conversation resolution before merging**. Keine Mindestzahl unabhängiger Approvals erzwingen, wenn tatsächlich kein zweiter freigabeberechtigter Reviewer existiert. Keine noch nicht vorhandenen Pflichtchecks auswählen.
3. **Do not allow bypassing the above settings** aktivieren. **Allow force pushes** **nicht** aktivieren. **Allow deletions** **nicht** aktivieren. Kein Push-/Fork-Ausnahme- oder Admin-Bypass. Bei verfügbarem `Lock branch` würde das spätere Änderungen zusätzlich sperren; nur bewusst nutzen, nicht aus Versehen als einzige PR-Schutzmaßnahme.
4. **Create/Save**; danach GitHub GET `/repos/janjrre/Projekt-Wordpress-Plugin/branches/test/orgavela-github-release-o03` auf **`protected=true`** und exakt **denselben HEAD** nachprüfen. Die API-Flag-Bestätigung allein weist die Einzelregeln nicht nach: der Betreiber muss diese in der GitHub-UI kontrollieren bzw. die Detail-API mit geeigneter eigener Adminberechtigung auslesen.

**Achtung GitHub-Free-Funktionsgrenzen:** Die UI zeigt manche Push-Einschränkungen nur auf bestimmten Organisations-/Tarifmodellen. `Require a pull request` plus **No Bypass** und weder Force Push noch Delete müssen auf dieser tatsächlich vorhandenen öffentlichen Repositoryregel anwendbar und bestätigt sein. Falls nicht, GH-02 als blockiert belassen und keine Secrets aktivieren.

### B. CircleCI: exakt passenden Context begrenzen

1. In CircleCI über **Organization Settings → Contexts** bestehenden `orgavela-backup-releases` öffnen.
2. **Project Restriction** weiterhin *nur* für das Projekt `janjrre/Projekt-Wordpress-Plugin` (nicht Dreamloud, nicht alle Projekte). Falls CircleCI das Owner-/Projektformat anders darstellt, die eindeutig selbe Repo-Zuordnung prüfen.
3. Bei **Expression Restrictions** die bisherige auf `main` bezogene Expression **ersetzen**, nicht eine weitere zulässige Regel ergänzen und nicht per `or` beide Branches öffnen. Gewünschter exakter Ausdruck:

   ```text
   pipeline.git.branch == "test/orgavela-github-release-o03" and not job.ssh.enabled and not (pipeline.config_source starts-with "api")
   ```

   CircleCI dokumentiert diese Branch-/SSH-/API-Vorbehalte offiziell: https://circleci.com/docs/guides/security/contexts/ . Keine untrusted Fork-PRs, PR-Branch-/Config-Source-Varianten oder GitHub-App-Konfigurationspfade mit Schreibzugriff auf diesen eingeschränkten Context zulassen; Trigger- und tatsächliche Job-Semantik vor Tokengebrauch kontrollieren. YAML-Branchfilter alleine **schützt keinen Secret-Context**.
4. Noch **kein** `GH_TOKEN` eintragen. Nach dem Speichern effektive, sichtbare Expression erneut lesen und Projekt-/Context-Restriktionen dokumentieren, ohne Secret-Werte oder unbereinigte Screenshots in das **öffentliche** Repository zu veröffentlichen. Nur ein einziger aktiv zulässiger Testbranch.
5. Unter **Plan / Plan Usage** Free-Tarif, Restkontingent und kein kostenpflichtiges Upgrade/Billing-Transfer prüfen: https://circleci.com/docs/guides/plans-pricing/credits/ . Ein kostenloser Anbieterplan ist nicht automatisch ein verifiziert verfügbares Freikontingent für genau diesen Job.

### C. GH-02-Abnahme

- [x] GitHub-Zweig `test/orgavela-github-release-o03` **protected=true** auf geprüftem HEAD. **Am 15.10.2026 unabhängig per GitHub GET bestätigt**, unveränderter HEAD `a03b0e079ad0f8bd782574abd5b0ed581bf3c12a`.
- [x] GitHub **Require PR, No Admin Bypass, No Force Push, No Deletion** laut Betreiber-UI-Prüfung am 15.10.2026 bestätigt; GitHub-Connector kann Einzeleinstellungen weiterhin nicht selbst auslesen (Details GET 403).
- [x] CircleCI-Context **Project Restriction nur OrgaVela-Quellprojekt** laut Betreiber-UI-Prüfung am 15.10.2026 bestätigt, nicht direkt ausgelesen. **Separate Fork-PR-Secretweitergabe** weiterhin sicherheitshalber in Projekteinstellungen prüfen (siehe GH-02d).
- [x] CircleCI-Context **neue Expression für exakt den Testbranch mit SSH-/API-Schutz** laut Betreiber am 15.10.2026 gespeichert; der Betreiber war zuvor angewiesen, die alte `main`-Expression zu ersetzen. **Wirksamer Context-Inhalt nicht unabhängig per CircleCI-API gelesen.**
- [x] CircleCI **Free-Plan** laut ausdrücklicher Betreiberbestätigung vom 15.10.2026 aktiv; **keine kostenpflichtige Nutzung** für den vorgesehenen Test freigegeben. Der tatsächliche OSS-Extra-Credit-Reststand ist nicht einsehbar; ein nicht nachprüfbarer Zahlenwert wird nicht erfunden.
- [x] Source-Testworkflow auf unverändertem GitHub-Testbranch erneut gelesen: `enable_synthetic_github_release_e2e=false` als Default, nur manueller Synthetik-Job mit Context und exaktem Branchfilter, explizite Laufzeit-Verweigerung bekannter IONOS-/Private-Key-Secrets. **Einen tatsächlichen CI-Run oder den Inhalt des Contexts haben wir nicht geprüft.**

**GH-02 erst nach Abschluss der gesamten Checkliste als `accepted` markieren.** *GH-02 erlaubt danach höchstens die gesonderte Freigabe eines synthetischen GH-04-Canary; es ist weder GH-04-Abnahme noch eine Erlaubnis für produktive Backups.* Betreiber braucht **keine** Token, private Schlüssel oder Passwörter im Chat mitzuteilen. Bei nicht unabhängiger CircleCI-Sichtbarkeit: UI-Bestätigung durch den Betreiber, keine freie Erfindung von API-Nachweisen. Keine PR-Merges, keine kostenpflichtigen Verträge.


### Nachprüfung 15.10.2026 – GH-02a bestanden, GH-02-Gesamt noch offen

Nach Betreiber-Änderung liefert der **direkte GitHub-API-Zweigabruf** erstmals `protected=true`, `protection.enabled=true` für exakt `test/orgavela-github-release-o03` (SHA unverändert `a03b0e079ad0f8bd782574abd5b0ed581bf3c12a`). Die **erste** Checkbox in obigem §11C ist deshalb tatsächlich abgehakt. `GET .../protection` liefert **403** (App ohne Admin-Leserecht); vier Einzeloptionen kann der Betreiber nur in der GitHub-UI nachsehen. CircleCI wurde nicht geändert oder abgefragt; die übrigen fünf Checkboxen bleiben offen. Für neue Context-Expression die bisherige `main`-Freigabe **ersetzen**. CircleCI verknüpft mehrere Expression Restrictions mit AND, eine parallele zweite abweichende Branch-Expression würde beide Branches sperren. Ohne bestätigte effektive Context-Projekt- und Expression-Restriktionen sowie Free-Kontingent keinen `GH_TOKEN` anlegen und kein GH-04 auslösen. Grundlage: [CircleCI Using Contexts](https://circleci.com/docs/guides/security/contexts/).


### GH-02d – Betreiberbestätigung vom 15.10.2026 und letzter No-Cost-/Fork-Check

**Nach aktuellen Betreiberangaben erledigt:** Exakte GitHub-Einzelregeln wurden im UI geprüft; CircleCI-Context `orgavela-backup-releases` enthält laut Betreiber die neu gespeicherte Testbranch-Expression, und die Context-Project-Restriction wurde geprüft. Die unabhängige GitHub-Abfrage bestätigt `protected=true` und unveränderten Branch-HEAD. **Geprüft durch den Betreiber ≠ Admin-API-Einzeldaten von uns gelesen**; die GitHub-Detail-API bleibt wegen Berechtigung 403 und es gibt in der verwendeten Verbindung keine CircleCI-Context-Admin-API.

**Zwei verbleibende Sicherheits-/Kostenpunkte vor der formalen GH-02-Gesamtabnahme:**
1. **CircleCI → Plan → Plan Overview**: Free-Tarif für die Organisation und keine bezahlte, mit anderer Organisation geteilte Abrechnung/kein Upgrade bestätigen. Laut [CircleCI Credits-Dokumentation](https://circleci.com/docs/guides/plans-pricing/credits/) sind im Free-Plan bis 400.000 kostenlose Credits pro Monat für öffentliche Open-Source-Linux-Builds verfügbar, deren **Reststand ausdrücklich nicht im UI angezeigt wird**. Daher darf die Checkliste keinen dort nicht existierenden Restcredit-Wert verlangen. Auf tatsächliche Free-Plan-Zugehörigkeit, keinen kostenpflichtigen Upgrade/Share und keine neuen Zahlungsverpflichtungen kommt es an.
2. **CircleCI → Projekt `Projekt-Wordpress-Plugin` → Project Settings → Advanced/Fork-PR-Einstellungen**: `Pass secrets to builds from forked pull requests` **disabled/off**. Default laut offizieller [CircleCI-Sicherheitsdokumentation](https://circleci.com/docs/guides/security/security-overview/) aus; die konkrete OrgaVela-Projekteinstellung dennoch prüfen. Alle neuen unbekannten Fork-/PR- oder SSH-/API-Trigger weiterhin fail-closed behandeln.

**Aktueller Stand:** Die 1., 2., 3., 4. und 6. §11C-Checkbox sind je entsprechendem unabhängigem GitHub-/Source-Beleg oder Betreiber-UI-Nachweis erledigt; die 5. bleibt offen und Fork-Einstellung bleibt als separater expliziter Preflight offen. Vor Bestätigung **GH-02 formal nicht `accepted`**, `GH_TOKEN` nicht eintragen, kein GH-04 Cloudtest. Danach GH-02 positiv festhalten; GH-04-Test separat mit kurzlebigem OrgaVela-only PAT und vollständigem synthetischem Restore prüfen.


### GH-02 formale Abnahme – 15.10.2026 nach bestätigtem Free-Tarif und Fork-Secret-Sperre

**GH-02 insgesamt ACCEPTED** als Sicherheits- und Kosten-*Vorprüfung* für einen **später manuell freigegebenen rein synthetischen** GitHub-Release-Test. Der Betreiber hat nach der technischen GitHub-Branchprüfung die vorhandenen offenen Prüfpunkte ausdrücklich bestätigt: **CircleCI Plan = Free** sowie **Pass secrets to builds from forked pull requests = disabled**. Zuvor bestätigte er die kontrollierten GitHub-Einzelregeln sowie gespeicherte Expression/Project Restriction. GitHub-GET bestätigt protected=true und unveränderten Branch-SHA; Source-.circleci/config.yml bestätigt synthetischen Test als opt-in mit default false. **Alle sechs** Prüfpunkte in §11C sind nun abgehakt, plus Fork-Secret-Schutz separat attestiert.

**Transparente Evidenzgrenze:** GitHub-Admin-Detail-GET liefert 403, CircleCI-Kontext und Organisationsplan wurden nicht über einen angemeldeten Admin-Connector selbst ausgelesen. Der signierende Betreiber bestätigte konkrete UI-Einstellungen; der accepted-Status ist eine Abnahme **auf Kombination aus unabhängiger GitHub-API und Betreiber-UI-Aussagen**, **kein** unabhängiger End-to-End-Nachweis. CircleCI-Free-OSS-Restcredits sind nicht im UI sichtbar und werden nicht als konkreter Wert belegt.

**GH-04–GH-08 weiterhin offen:** Ohne Token kein Upload; bis jetzt keine OrgaVela-Assets im privaten Backup-Repository. Ein neuer kurzlebiger GH_TOKEN darf **erst im separaten GH-04-Vorgang** vom Betreiber direkt in den eingeschränkten Context gesetzt werden und muss nach Test wieder widerrufen/entfernt werden. GitHub-Target repo-only janjrre/orgavela-backups, Contents: Read and write, ausschließlich künstliche Daten/temporärer Test-Schlüssel, manueller Bool enable_synthetic_github_release_e2e=true. Sollten GH-02-Sicherheitsbedingungen geändert werden, Stop/erneut prüfen; Free-Limitüberschreitung führt zum Abbruch ohne kostenpflichtiges Upgrade. Die produktive Besitzer-Key-/Restore-Recovery ist separat GH-03 und GH-05–08.

**Statusauswirkung:** GH-02 angenommen; O03/W0 und V1 **noch nicht** angenommen. Kein Token, keine CI-Pipeline, keine Produktionssicherung, keine Backuptests, keine Merges/Kosten in dieser Sitzung.


## GH-04 – manueller PC-freier synthetischer GitHub-Backup-Test (15.10.2026)

**Abnahmevorbedingung:** GH-02 ist accepted mit GitHub-API-Branch-`protected=true` und explizit Betreiber-bestätigtem CircleCI-Context/Free/Fork-Secret-Schutz. Das ist kein Beleg, dass `GH_TOKEN` schon vorhanden ist. **Der künstliche Verschlüsselungstest wurde isoliert lokal ausgeführt und bestanden:** temporäres OpenPGP-Testschlüsselpaar, TAR mit festem Testtext, Kopie des verschlüsselten Archivs mit SHA256, Import in getrenntes Keyring, bytegenaue Entschlüsselung und Extraktion PASS (537 Bytes, SHA256 `0018ecaa027d88a4e45760fac2149707233b1d6bafe78b1c0a5ad3392fc3db0d`); Testmaterial inklusive Wegwerf-Key gelöscht. **Das ist kein Cloud-Backup und keine GH-04-E2E-Abnahme.**

### Einmalig: GitHub-PAT sicher direkt am Anbieter erstellen

1. Im **eigenen authentifizierten GitHub-Konto** [Fine-grained tokens → Generate new token](https://github.com/settings/personal-access-tokens/new) öffnen; Einstellungen ggf. trotz vorbelegtem Formular prüfen.
2. Name `orgavela-synthetic-transport-once`, **kurze Expiration** (z. B. 7 Tage oder kürzer, wenn verfügbar), **Resource owner `janjrre`**.
3. **Repository access → Only select repositories → `orgavela-backups`** und **Repository permissions → Contents → Read and write**. Keine All-repos-Auswahl, keine Administration-, Secrets- oder Workflow-Write-Berechtigung. GitHub `Metadata: Read` kann automatisch gesetzt sein. Änderungen an Dreamloud verboten.
4. Token **einmalig auf der GitHub-Seite kopieren und direkt im CircleCI-Context** einsetzen, niemals hier im Chat, auf dem Telefon in unsicheren Notizen, in Issues/PRs/Logs oder im öffentlichen Quellrepository hinterlegen. Nur nach GH-02-Check und falls der Free-Plan weiter besteht.

### Einmalig: CircleCI-Context-Secret speichern

1. [CircleCI Organization Settings](https://app.circleci.com/settings/organization) → **Contexts** → `orgavela-backup-releases` → **Environment Variables** → **Add Environment Variable**.
2. **Name `GH_TOKEN`**, Wert das neue kurzlebige, ausschließlich private OrgaVela-Backup-Repositories autorisierende Token. Project Restriction muss **nur** `janjrre/Projekt-Wordpress-Plugin` erlauben. Expression Restriction muss **nur** den Branch `test/orgavela-github-release-o03` mit `not job.ssh.enabled` und Ausschluss der API-Konfigquelle zulassen. Fork-PR-Passing weiterhin aus.
3. **Hier enden alle Token-Aufgaben des Betreibers, ohne den Wert preiszugeben.** Noch keine produktive Serververbindung, kein privater Besitzer-Key und keine echten WordPress-/MariaDB-Daten.

### Manuelle CircleCI-Pipeline und sichere Abnahmelogik

1. Erst nachdem `GH_TOKEN` im beschränkten Context gespeichert wurde, die [OrgaVela-CircleCI-Pipelines](https://app.circleci.com/pipelines/github/janjrre/Projekt-Wordpress-Plugin) öffnen. Falls CircleCI den Button **Trigger Pipeline** / **Run Pipeline** anbietet: **Branch `test/orgavela-github-release-o03`**, Pipeline-Parameter **`enable_synthetic_github_release_e2e` als Boolean `true`**. Der Default bleibt **`false`**. Nur manuell, niemals Schedule/Auto-Deploy.
2. Job `synthetic-github-release-e2e` prüfen. Er muss die **synthetischen** Dateien mit ephemerem OpenPGP-Key erzeugen, als private GitHub Release Draft mit **genau drei Assets** hochladen und den binären SHA256-Readback vor/nach Publish prüfen. Erst nach Publish können `immutable=true`, unabhängiger erneuter Download und GPG-Restore bestanden sein. Fehler/fehlende Assets/403 sind **kein** Backup-Beleg.
3. Run-ID und Status sowie Release-Tag/ID aus der Pipeline **ohne Tokens/Secrets** dokumentieren, mit CircleCI-/GitHub-Connector anschließend GitHub-Release-Zustand und gegebenenfalls Build-Log nachprüfen. Nur bei eindeutig bestandenem Gesamt-E2E **GH-04 `accepted`** setzen.
4. **Nach dem einen synthetischen Versuch** – erfolgreich oder nicht – GitHub-Token **widerrufen** und den CircleCI-Context `GH_TOKEN`-Eintrag **entfernen**. Keine Tokens im GitHub-Journal. Kein ungeprüftes Aufräumen immutabler Releases.
5. GH-03 und GH-05–08 bleiben ausdrücklich separate Anforderungen vor einem einzigen echten WordPress-/DB-Backup bzw. Schedule. Beim Überschreiten von Free-Kontingent, geänderter Branch-Sicherheit oder Secret-Injektion: **sofort stoppen, kein kostenpflichtiges Upgrade**, keine Sicherheitsregeln lockern.

**Technischer Stand bei Erstellung dieser Anleitung:** `test/orgavela-github-release-o03 protected=true` auf `a03b0e079ad0f8bd782574abd5b0ed581bf3c12a`, PR #19 weiterhin Draft, privates `orgavela-backups` 0 Releases, GitHub-PAT und manuelle Pipeline **nicht** ausgeführt. Offizielle GitHub-PAT-Dokumentation: https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/managing-your-personal-access-tokens


## 16.10.2026 – GH-04 Status nach Tokenanlage, letzter GitHub-/CircleCI-UI-Vorbereitungsschritt

**Betreiber bestätigt:** Neues kurzlebiges Fine-grained GitHub PAT ausschließlich für privates `janjrre/orgavela-backups` ist als `GH_TOKEN` im geschützten CircleCI-Context `orgavela-backup-releases` hinterlegt. Kein geheimer Tokenwert wird übertragen, niemand soll ihn hier einfügen. **Tool-Zugriff zeigt nicht**, ob der Context ihn zur Joblaufzeit erfolgreich bereitstellt. GH-04 ist noch nicht ausgeführt: `janjrre/orgavela-backups` hat **0 Releases**, keine CircleCI-Run-ID.

**Vor dem ersten GH-04-Test: EINE ZUSÄTZLICHE GITHUB-EINSTELLUNG UNBEDINGT PRÜFEN.** Öffne [orgavela-backups → Settings](https://github.com/janjrre/orgavela-backups/settings) → **General → Releases**. Kontrolliere, dass **Enable release immutability** aktiviert ist; falls nicht, **aktivieren**, **bevor** irgendein Release erstellt wird. Die aktuelle GitHub-Anleitung https://docs.github.com/en/code-security/how-tos/secure-your-supply-chain/establish-provenance-and-integrity/prevent-release-changes erläutert: Der Schutz gilt erst für danach erstellte Releases. Die einzelne Einstellung kann das angeschlossene GitHub-Lesetool **nicht** direkt lesen (`GET .../immutable-releases` verlangt Admin Read; App-Allowlist lehnt diese URL ab). `Contents: Read and write` des neuen Testtokens ist richtig; keine höheren Tokenrechte nötig.

**Wenn Release Immutability aktiviert und der Free-/Context-/Fork-Schutz weiter unverändert sind**, im eigenen CircleCI-Webinterface [OrgaVela Pipelines](https://app.circleci.com/pipelines/github/janjrre/Projekt-Wordpress-Plugin) → **Trigger Pipeline** (oder **Run Pipeline**, UI kann variieren):
- Richtige Pipeline/Projekt: **`janjrre/Projekt-Wordpress-Plugin`**.
- Bei getrennter Auswahl **Config Source** und **Checkout Source** jeweils **dieses Repository** und **`test/orgavela-github-release-o03`** (nicht `main`).
- **Add Parameters → Boolean:** **Name `enable_synthetic_github_release_e2e`**, **Value `true`**. Exakt ein Bool, kein Textstring; die Datei definiert `default:false`.
- Genau einmal starten. Erwarteter Workflow **`synthetic-github-releases-e2e-manual`**, Job **`synthetic-github-release-e2e`** mit synthetischem Text, temporärem GPG-Testkey, Upload in privates Backuptarget, drei vorverschlüsselten Release-Assets, binären Remote-SHA-Vergleichen, `immutable=true` und unabhängigem Remote-only TAR-Wiederherstellungstest.
- Bei Problemen kein `or` in den Context-Restriktionen, kein Admin-Token, kein Fork-Secret-Passing, keine Push-/Schedule-/API-Trigger-Umgehung oder kostenpflichtiges Upgrade. Stattdessen **Run-URL und den fehlerhaften Steptext ohne Secret** mitteilen. Über den Job-Status lässt sich klären, ob die Triggeroberfläche/Branch-Parameter/Token überhaupt wirksam sind.
- Nach dem Versuch **PAT in GitHub widerrufen** und **`GH_TOKEN` im CircleCI-Context entfernen**, bevor echte Daten-/Restore-Themen beginnen.

**Abnahmeschwelle:** `GH-04 accepted` **ausschließlich** nach echtem Anbieter-Run `success` und per REST/Logs belegtem privaten unveränderlichen veröffentlichten synthetischen Release samt SHA-Readback und getrenntem vollständigem Remote-Recovery. Bisher **kein Upload/Restore**, keine CI-Run-ID. Alle V1-Abnahme-Gates bleiben davon getrennt.

**Offizielle Provider-Bedienung:** https://circleci.com/docs/guides/orchestrate/triggers-overview/ (getrennte Source-Auswahl für GitHub-App-Projekte), https://circleci.com/docs/guides/orchestrate/pipeline-variables/ (Add Parameters / Boolean).


## GH-04 – erfolgreicher synthetischer immutable GitHub Release nach CircleCI-Job-SUCCESS (16.10.2026)

**Technischer Nachweis, unabhängig in GitHub gelesen:** Betreiber meldet CircleCI-`d70d145b-b76b-47f4-854c-838d7ad227ed` `success`. Das private Ziel `janjrre/orgavela-backups` enthält inzwischen ein **veröffentlichtes immutable Release** [`backup-orgavela-synthetic-circleci-3`](https://github.com/janjrre/orgavela-backups/releases/tag/backup-orgavela-synthetic-circleci-3) mit numerischer ID `414750281`, `draft=false`, `immutable=true`, `published_at=2026-10-16T09:06:20Z`, genau **drei** Assets:
- `646772868`, `orgavela-synthetic-circleci-3.tar.gpg`, 549 Bytes, `sha256:0fbb3b4077b7b360f76ec9d105772936f48b76f9c531e3c490804267eda4e70a`.
- `646772871`, `manifest.json`, 331 Bytes, `sha256:adbb788993ba9cf8be0ebc48ba9291ede912f16b1e112d53467326e6de213800`.
- `646772865`, `orgavela-synthetic-circleci-3.tar.gpg.sha256`, 104 Bytes, `sha256:b20ba2dc4b6576a373dfd94df684b06a3e873849121265d59142c38b44cf658a`.

Die Metadaten der drei Assets wurden jeweils separat via GitHub REST GET mit `state=uploaded` bestätigt. Alle Payloads sind **ausschließlich synthetisch**; Quell-Testbranch `test/orgavela-github-release-o03 protected=true`, HEAD `a03b0e079ad0f8bd782574abd5b0ed581bf3c12a`. Die `immutable=true`-Release-Flag ist nun **direkt belegter konkreter Releasezustand** (anders als zuvor unbekannte Admin-Einstellung). Die unveränderten GH-04-Skripte erzwingen in einem erfolgreich beendeten Job den Remote-Hash-Readback vor und nach Publish sowie unabhängiges synthetisches Restore per eigenem GPG-Keyring; wegen nicht verfügbarem CircleCI-Admin-/Log-Read ist diese Restore-Aussage **vom gemeldeten Job-SUCCESS plus Source-Kontrollfluss abgeleitet**, nicht unabhängig aus Provider-Step-Logs/Asset-Bytes wiederholt.

**GH-04 technischer Test: PASS. GH-04 formale Sicherheitsabnahme bis auf Token-Revoke noch offen.** Die bei GH-04 angelegten Secrets dürfen **nicht** dauerhaft bestehen bleiben:

- [x] **GitHub-PAT widerrufen:** https://github.com/settings/personal-access-tokens → unter Fine-grained Tokens genau das für diesen Test angelegte Token `orgavela-synthetic-transport-once` **Revoke**. Nicht neu erzeugen, keine Secrets übertragen.
- [x] **CircleCI `GH_TOKEN` löschen:** https://app.circleci.com/settings/organization → Context `orgavela-backup-releases` → Environment Variables → **`GH_TOKEN` entfernen**.
- [x] **Nach Betreiberbestätigung GH-04 akzeptieren und abschließen:** kein weiterer Cloud-Run nötig; Private-Release-Assets als synthetische Nachweisdokumentation beibehalten. GH-03/05–08 offen, echte Daten erst nach Offline-Owner-Key und isoliertem vollständigem Restore.

**GH-04-Ergebnis darf niemals als produktives WordPress-Backup umgedeutet werden.** 549-Byte-GPG-Archiv ist nur eine harmlose fixe Textfixture; die separate reale Recovery-Sicherung steht erst in GH-03–GH-07 an.


### GH-04 Abschlussbestätigung am 17.10.2026 – keine weitere Testtoken-Verwendung

**Operator-Nachweis:** Betreiber bestätigt ausdrücklich, dass das **einmalige** GitHub-Fine-grained-PAT **widerrufen** und der CircleCI-Context-Eintrag **`GH_TOKEN` gelöscht** wurde. Die zwei Checkboxen wurden daher **auf Grundlage der Betreiber-UI-Bestätigung** abgehakt. Es gab keine heimliche Admin-Read-Verifikation, da GitHub-Tokenverwaltung und CircleCI-Secrets mit der verwendeten Integration nicht lesbar sind. Kein neues PAT generieren, keinen weiteren synthetischen oder produktiven Test mit diesem Secret starten; die Context-Projekt-/Expression-Sicherheitsregeln unverändert belassen.

**Bestehender privater Nachweis bei unabhängiger GitHub-REST-Nachprüfung erhalten:** Release `414750281`, `backup-orgavela-synthetic-circleci-3`, `draft=false`, `immutable=true`, drei hochgeladene Assets samt GitHub-SHA256. CircleCI-Job-/Workflow-ID `d70d145b-b76b-47f4-854c-838d7ad227ed` und `success` vom Betreiber gemeldet; Programmfluss erzwingt vor erfolgreichem Ende künstliche unabhängige Wiederherstellung. Der CircleCI-Run-Log und binäre Remote-Nachdownload sind **nicht** unabhängig über diese Verbindung überprüft. Nach Evidenzmix aus technischer Release-Prüfung, geprüfter fehlerabbrechender Quelllogik und Betreiber-Success **GH-04 SYNTHETIC ONLY = ACCEPTED**. Dies ist keine reale WordPress-/Datenbank-Sicherung und kein V1-Release-Gate.

**GH-03 ist jetzt der operative nächste Schritt:** Kryptoformat **age oder OpenPGP** als ADR entscheiden; eine **neue dauerhafte private Recovery-Identität** darf ausschließlich auf dem kontrollierten Betreibergerät erzeugt und an mindestens zwei getrennten sicheren **Offline-Orten** gespeichert werden, **nie** in Chat, GitHub, CircleCI, E-Mail oder auf dem Produktionsserver. Erst nach Überprüfung der Kopien, einem unabhängigen Wiederherstellungstest und dokumentierter Verlust-/Rotationsstrategie GH-03 abnehmen. CI bekommt für echte Backups **nur den öffentlichen Empfänger/Schlüssel**, nie Private-Key-Material. GH-05/06/07/08 müssen danach getrennt technisch und fachlich abgenommen werden; bis dahin keine echten Daten hochladen, keine Cron-Jobs/Schedules aktivieren. Das unveränderliche künstliche Testrelease als Auditbeleg erhalten.


## GH-03 17.10.2026 – Entscheidung age getroffen; neuer Offline-Owner-Key-Runbook ab jetzt führend

**Neu entschieden und in GitHub dokumentiert:** Für **zukünftige echte neue OrgaVela-WordPress-/MariaDB-Backups** gilt `age` mit dem von Betreiber lokal kontrollierten privaten Schlüssel und öffentlichem `age1…`-Verschlüsselungsempfänger. Das früher in GH-04 verwendete `gpg`/`.tar.gpg` ist **ausschließlich** ein bestandenes synthetisches Testverfahren und ist kein produktiver Recovery-Key oder freigegebener age-Writer.

**Führende Referenzen ab GH-03:**
- [GH-03 Kryptoformat-/Owner-Recovery-ADR](W0_O03_GH03_ENCRYPTION_RECOVERY_ADR.md): dokumentierter Entscheid age vs OpenPGP, alter und neuer Artefaktformat-Bereich, Private-Key-Grenzen, zwei unabhängige Offline-Kopien, Verlust-/Rotationsverfahren und Stop-Gates.
- [GH-03 Owner Offline-Key und Copy2-Recovery-Rundgang (Windows)](W0_O03_GH03_OWNER_OFFLINE_KEY_RUNBOOK.md): kostenfreier age-Client installieren, getrennt offline `age-keygen -o` direkt auf verschlüsselten physischen USB-Stick ausführen, zweite Offline-Kopie auf anderem separat geschütztem physischen Medium erstellen, PUBLIC `age1…` aus beiden lokal vergleichen, künstlichen Klartext mit `age -r` verschlüsseln, nur Kopie 2 via `age -d -i` zum Restore nutzen, SHA256 identisch.
- Die in diesem älteren kombinierten Runbook vorhandenen früheren Vorschläge zu GitHub-Synthetik-Sequenzen und GPG-Versuchen bleiben **historische Protokollschritte**. Die neuen Dokumente sind der maßgebliche operative GH-03-Ablauf; `V1_ACCEPTANCE_PLAN.md` ist weiterhin allein normativer V1-Masterplan.

**Aktueller GH-03-Abnahmestand:** Nur die Architekturentscheidung `age` und Owner-Anleitung abgeschlossen. **Kein echter privater Besitzerkey, keine zwei geschützten Offline-Kopien, kein Test aus Kopie 2 nachgewiesen, kein öffentlicher age-Recipient konfiguriert**. PCjan war in dieser Runde offline; eine Schlüsselgenerierung durch den Assistenten/Cloud-Runner ist ausdrücklich verboten. Nach eigenem lokalem Ablauf Betreiberbestätigung **ohne private Schlüsseldatei**. GH-03 bleibt bis dahin `in_progress`; keine echten Backups oder Cron/Retention/Schedules.
