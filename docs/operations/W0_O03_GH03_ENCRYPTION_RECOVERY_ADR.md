**SUPERSEDED FOR NEW ORGAVELA BACKUPS, 20.10.2026:** Following owner decision to adopt PGPony/OpenPGP, this age-specific format choice is retained only as **historical record**. Current binding decision: [W0_O03_PGPONY_OPENPGP_MIGRATION_ADR_2026-10-20.md](W0_O03_PGPONY_OPENPGP_MIGRATION_ADR_2026-10-20.md). Do not delete older AgePony key or old synthetic age evidence.

# W0/O03 / GH-03 – Entscheidung zur produktiven Backup-Verschlüsselung und Recovery

**Status:** GH-03-Architekturentscheidung getroffen; **Owner-Key und Recovery-Abnahme ausstehend**  
**Datum:** 17.10.2026 · **Entscheidung:** `age` für **neue produktive** OrgaVela-Offsite-Backups  
**Bezug:** [einziger V1-Masterplan](V1_ACCEPTANCE_PLAN.md) · [fortgeschriebener GH-03-Operator-Guide](W0_O03_GH03_OWNER_OFFLINE_KEY_RUNBOOK.md) · [GH-04-GitHub-Runbook](W0_O03_GITHUB_PRIVATE_BACKUP_RUNBOOK.md)

## 1. Kontext und entschiedene Architektur

GH-04 hat einen **künstlichen** OpenPGP-`.tar.gpg`-GitHub-Release-Transporttest nach Betreiber-Success und unabhängig bestätigtem `immutable=true` abgeschlossen. Der **kurzlebige Test-Private-Key war nur für künstliche Daten** und wurde entsorgt. Die Betreiber-CircleCI-Test-PATs sind laut Betreiber widerrufen/entfernt. Daraus folgt **nicht**, dass reale WordPress-/MariaDB-Dateien produktiv mit OpenPGP gesichert werden dürfen oder ein dauerhafter privater Recovery-Key existiert.

Für **erstmals echte neue OrgaVela-Backups** wird **`age` (nativer `age1…`-/`AGE-SECRET-KEY-…`-Schlüsselmodus, asymmetrische Dateiverschlüsselung)** als Zielformat festgelegt. Dies setzt die bisherige im GitHub-Backup-Runbook vorgeschlagene `age`-Public-Recipient-Architektur fort und reduziert die notwendige Schlüssel-/Konfigurationskomplexität im zukünftigen headless Backup-Producer. Das GH-04-PoC mit GPG wird **weder umbenannt noch als age-Protokollbeweis ausgegeben**.

Begründete Alternativen:
- **`age` – gewählt:** einfache CLI mit klarer Trennung `age-keygen -o identity.txt`, öffentlicher Empfänger `age-keygen -y identity.txt`, `age -r age1…` verschlüsselt und `age -d -i identity.txt` entschlüsselt. Kein privater Schlüssel auf CI/IONOS/GitHub erforderlich. Öffentlicher `age1…`-Empfänger später separat integrity-geprüft konfigurierbar.
- **OpenPGP/GPG – nicht als Produktionsformat gewählt:** technisch durch den temporären GH-04-Test erprobt, aber langlebige Keyring-/Subkey-/Export-/Passphrase-/Wiederherstellungsprozesse sind umfangreicher. Ein erfolgreicher synthetischer OpenPGP-Canary verpflichtet das reale OrgaVela-Backup nicht auf GPG.

**Kein Cross-Format-Mischen:** Produktionsartefakte erhalten `.tar.age`, Manifest `schema=orgavela-backup-manifest/v1` mit `encryption=age`, `recipient`, verschlüsselte SHA256-/Unverschlüsselt-Inventarlisten innerhalb des Ciphertexts, Außenmetadaten ohne sensible Pfade. Das bestehende synthetische `.tar.gpg` bleibt **explizit synthetic/GPG**. Sämtliche späteren Writer, Checker und Restore-Reader müssen einheitlich `age` unterstützen und als eigenes GH-05/06/07-Implementierungs-/Testpaket geprüft werden. Keinen `age`-Recipient an `gpg --encrypt` übergeben.

## 2. Unverrückbare Sicherheits- und Aufbewahrungsgrenze

**Der langfristige PRIVATE RECOVERY KEY wird ausschließlich vom Betreiber auf einem vertrauenswürdigen privaten Gerät erzeugt und vollständig unter Betreiberkontrolle gehalten.** Nicht im Assistenten-Container, Chat, einem Git-Commit, GitHub Library/Secrets, CircleCI, WordPress, IONOS, Drive, E-Mail, Screenshots oder synchronisierten Ordnern. Schlüssel nur auf vorab verschlüsselten, physischen **Offline-Speichermedien** vorsehen. Für diese GH-03-Abnahme gilt: zwei vollständige, voneinander getrennt aufbewahrte **Offline-Kopien**, jede durch Zugangsschutz/Medienverschlüsselung gesichert und geprüft. Die zweite Kopie soll auch bei Verlust des Original-PCs lesbar sein.

**Public recipient `age1…` ist nicht geheim**, darf später in das Projekt bzw. als nicht geheime CI-Variable übernommen werden – aber erst nach Betreiber-Fingerprint-/Empfängerabgleich. Der öffentliche Wert gehört **nicht** automatisch in den test-synthetischen Context; die GH-04-Tokenfreigaben bleiben gelöscht. **Nur öffentlicher Empfänger darf dem späteren Backup-Producer zugänglich sein.** Für ein Recovery muss ein anderer, vom Cloud-Backup-Anbieter unabhängiger Owner-Restorehost zur Verfügung stehen.

**Zwei Kopien sind kein Backup der WordPress-Daten:** sie sichern ausschließlich den Entschlüsselungszugang. GitHub-Quellcode und GitHub-Backups teilen Anbieter/Account; IONOS-Provider-Restore-Punkte müssen unabhängig geprüft werden (separates Arbeitspaket).

## 3. Verbindliche GH-03-Acceptance-Kriterien

- [x] Architekturentscheidung `age` für reale **neue** Sicherungen in diesem ADR dokumentiert; GH-04 synthetisches GPG sauber getrennt.
- [ ] Betreiber erstellt lokale langfristige private `age`-Identität in einem **nicht cloud-synchronisierten, verschlüsselten** Offline-Ordner auf eigenem Gerät.
- [ ] **Zwei separat geschützte/offline aufbewahrte** Kopien geprüft, zweite Kopie nicht am gleichen Ausfallort wie die erste; Betreibertest bestätigt identischen **öffentlichen** `age1…`-Empfänger beider Kopien.
- [ ] Betreiber verschlüsselt ausschließlich **künstlichen** Testtext mit dem öffentlichen Empfänger und entschlüsselt ihn aus **der zweiten Kopie** in getrenntem Arbeitsbereich; SHA256 von Ausgangs- und Testdatei identisch; Testartefakte bereinigt; Erfolgsnachweis **ohne privaten Keyinhalt**.
- [ ] Verlust-/Rotation-/Geräteaustausch-Plan verstanden: verlieren beide Kopien den Zugriff, sind die echten age-Ciphertexte prinzipiell nicht mehr lesbar; neue Schlüssel **vor** Nutzung produktiv prüfen, alte entschlüsselungsfähige Kopien für alte Releases aufbewahren.
- [ ] Operator bestätigt die genannten Fakten ausdrücklich, aber übergibt **ausschließlich** den öffentlichen `age1…`-Empfänger **wenn** er später für das Writer-Gate benötigt wird; niemals private Bytes.

**Erst nach Belegen alle GH-03-Kriterien mit `accepted` versehen.** Keine echte IONOS-/MariaDB-/WordPress-Sicherung oder täglich geplante Cloud-Backups als Abkürzung. GH-05 und GH-06 entwickeln konsistenten Source-Capture und `age`-Writer. GH-07 beweist eigenen isolierten echten WordPress-/DB-Full-Restore; GH-08 ist danach Betriebsfreigabe.

## 4. Sichere Operator-Durchführung und Zukunft

[W0_O03_GH03_OWNER_OFFLINE_KEY_RUNBOOK.md](W0_O03_GH03_OWNER_OFFLINE_KEY_RUNBOOK.md) enthält Windows-/PowerShell-Schritte, die **auf dem Betreibergerät**, nicht von diesem Chat ausgeführt werden. Installation/Prüfung erfordert zu Beginn Netz, **Generation bei abgetrenntem Netz und in verschlüsselter Offline-Umgebung**. Die Nachweise für die Dokumentation lauten nur `age Version`, **öffentlicher Empfänger** oder redaktierter Hash/Format, `copy1_checked/copy2_checked/recovery_smoketest_passed`, keine Laufwerksnamen/Benutzerpfade, keine privaten Schlüssel.

**Quellen:** age-CLI-Dokumentation https://github.com/FiloSottile/age ; aktuelle offizielle Releases https://github.com/FiloSottile/age/releases . Referenz zum GH-04-GitHub-Testrelease https://github.com/janjrre/orgavela-backups/releases/tag/backup-orgavela-synthetic-circleci-3 .
