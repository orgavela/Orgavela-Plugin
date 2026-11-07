# PGP-04 – geschützte Testumgebung für einen **rein synthetischen** GitHub-Release

Stand 20.10.2026 · Branch `security/pgp04-synthetic-release-20261020` · Ausgangspunkt Draft PR #25 (PGP03: 2/2 CircleCI-Jobs PASS, 9/9 negative Checks, Quell-SHA `4eac786c3868dc4e79f1cf1dd16dc11b93512ff8`).

## Absicht und aktueller Sicherheitszustand

Dieser Branch wurde **nur für den späteren isolierten PGP04-Test** vom dokumentierten PGP03-Stand abgezweigt. **Branch angelegt bedeutet NICHT Branch geschützt.** Vor dem nächsten Test werden **weder** GitHub-Tokens **noch** CircleCI-Kontexte mit Secrets **noch** Produktivzugänge vergeben. Der PGP03-Workflow bleibt default-off; PGP04-Remote-Release-Workflow ist nicht freigeschaltet. Keine automatischen Zeitpläne, kein IONOS- oder WordPress-Zugriff und keine produktiven Backups.

**Vorherige belegte Warnung:** `feat/o03-pgpony-openpgp-migration-20261020` war laut GitHub `protected=false`. Ein reiner YAML-Branchfilter verhindert weder Änderungen am Workflow noch die Verwendung eines unzureichend eingeschränkten CircleCI-Contexts. Deshalb kein Token auf PR #25 oder einem noch ungeschützten Testbranch.

## Betreiberaktion 1 – GitHub-Testbranch **schützen und einfrieren**

1. Öffne [Settings → Branches](https://github.com/janjrre/Projekt-Wordpress-Plugin/settings/branches) im **Quellrepo** (nicht im Backup-Repo).
2. Erstelle eine **Branch protection rule** ausschließlich für `security/pgp04-synthetic-release-20261020` (exakter Name; kein Wildcard-`main`).
3. Nach Fertigstellung der Testskripte und vor **jeder** Vergabe von Zugangsdaten: **Lock branch** aktivieren, damit niemand neue Workflow-Dateien/Commits in diesen Testbranch pushen kann. Aktiviere **Do not allow bypassing the above settings**, falls im UI verfügbar; **Allow force pushes** und **Allow deletions** müssen aus sein. Wenn diese Sicherung auf dem Kontotyp nicht wirksam angeboten wird: **STOP**, nicht ersatzweise auf den ungeschützten Featurebranch wechseln.
4. GitHub-Sicherheitsanzeige und GitHub-API müssen `protected=true` für den **exakten** Branch melden. `protected=true` alleine genügt nicht: die wirksame Lock-/Bypass-/Force-/Delete-Regel prüfen. Wenn Branch Lock bei deinem GitHub-Tarif nicht möglich ist, alternative nachvollziehbar gleichwertige Freigabe erst separat bewerten.

**Wichtig:** Ein Branch-Lock verhindert nachträgliche Code-Updates. Daher diesen Schritt erst nach dem finalen, geprüften PGP04-Code-Freeze ausführen; vorerst kann die Regel bereits angelegt werden, aber ohne Secrets. Bei Änderungen später zuerst Token/Context deaktivieren, Branch entsperren, Änderungen prüfen, wieder sperren und neue SHA attestieren.

## Betreiberaktion 2 – CircleCI-Sicherheitskonzept (noch NICHT aktivieren)

- Separater neuer CircleCI-Context **nur** für PGP04 und **nur** für den oben eingefrorenen Branch. Keine bestehenden Dreamloud-, OrgaVela-Produktiv- oder IONOS-Contexts wiederverwenden.
- Wenn CircleCI eine Branch-/Projekt-Contextrestriktion nicht tatsächlich erzwingen kann: **STOP**. Workflow-Branchfilter im YAML ist kein Ersatz für eine serverseitige Contextrestriktion.
- Später nur ein neuer **kurzlebiger, fine-grained GitHub PAT** mit Repository-Auswahl **ausschließlich** `janjrre/orgavela-backups`, `Contents: Read and write` (und ausschließlich die GitHub-seitig zwingend erforderlichen Metadaten-Leserechte). Keine Organisations-, Admin-, Actions-, Packages- oder sonstigen Scopes. Token niemals im Chat, Commit, Screenshot, Artifact oder CircleCI-Log anzeigen.
- PAT erst nach expliziter Betreiberfreigabe und überprüfter Branchsperre im eingeschränkten CircleCI-Context hinterlegen. Unmittelbar nach einmaligem synthetischem Test den PAT widerrufen und Context-Variable entfernen. Bei fehlerhaftem Run nicht automatisch wiederholen.
- Der **Writer** darf nur `.tar.gpg`, `.tar.gpg.sha256` und sanitiertes `manifest.json` aus der vorherigen PGP03-Handoff-Stufe erhalten. Keine Private-Key- oder Passphrase-Dateien im CI.
- Kein Zugriff auf IONOS, WordPress, Datenbank, E-Mail oder Dreamloud. Keine Cron-/Schedule-Trigger, keine automatischen PR-Merges.

## PGP04 geplanter Abnahmetest (NOCH NICHT ausgeführt)

1. Einmaliger manueller Test auf eingefrorenem, branchgeschütztem, exakt attestiertem Commit.
2. Erzeuge ausschließlich künstliche WordPress-/SQL-Probedaten, verschlüssele vor Upload mit dem **geprüften öffentlichen** PGPony-Empfängerschlüssel; führe Ciphertext-only-Workspace zu getrenntem Writer.
3. Writer verifiziert SHA256, Public-Key-Recipient-ID, Manifestschema und `synthetic=true`; erstellt **genau ein** neues, eindeutig als `synthetic` markiertes Release im privaten Backup-Repo, ohne vorhandene Releases zu überschreiben.
4. GitHub-API-Readback verifiziert Zielrepo privat, Release-ID, Immutable-Flag **tatsächlich true**, Tag/Assetnamen, Assetanzahl, Remote-SHA256 und Dateigröße. Ist Immutable-Flag nicht nachweisbar oder Release nicht tatsächlich unveränderlich: **FAIL**, nicht als bestanden deklarieren.
5. Nur künstlichen Ciphertext aus GitHub herunterladen, **auf Besitzergerät mit PGPony** isoliert entschlüsseln, künstliche DB-/WordPress-Dateien und Hash vergleichen. Kein privater Schlüssel in CircleCI.
6. Betreiber widerruft Testtoken, entfernt Contextvariable, bestätigt den Revocation-Status. Release als Auditbeleg erhalten.

**Nicht freigegeben:** Echtes WordPress-/MariaDB-Backup, Retention/Löschung, Scheduling, Deploy, Production Key Export, V1-Abnahme, kostenpflichtiger Dienst. GH03 Recovery und PGP03 Source/Writer bereits PASS; PGP04 noch BLOCKIERT.

## Checkliste vor jedem PGP04-Run

- [ ] Endgültige PGP04-Implementierung Code-reviewed, SHA protokolliert
- [ ] Testbranch `protected=true`, Lock/no-bypass/no-force/no-delete nachweisbar
- [ ] CircleCI-Context serverseitig auf genau Branch und Projekt beschränkt
- [ ] Nur Zielrepo `janjrre/orgavela-backups`, privat, Release-Immutability-Einstellung geprüft
- [ ] Kurzlebiger Fine-Grained-PAT nur für Zielrepo, Betreiberfreigabe; keine anderen Secrets
- [ ] Manueller Trigger, kein Cron, nur synthetische Dateien
- [ ] Nachweis Remote-Immutable, SHA256, Besitzer-Restore und anschließende Token-Revocation

**STOP**, sobald ein Kriterium offen bleibt. Dokumentationsänderungen an diesem Branch sind bis zur späteren Lock-Aktivierung möglich; deshalb **jetzt ausdrücklich noch nicht für Secret-Nutzung freigegeben**.

## Ergänzung 20.10.2026 – Zielrepository: GitHub Release Immutability aktiv prüfen

**Wichtig:** Der offizielle GitHub-Workflow für immutable Releases ist: im PRIVATEN Zielrepository `janjrre/orgavela-backups` unter `Settings` → `General` bis zum Bereich `Releases` scrollen und `Enable release immutability` prüfen/aktivieren (GitHub Dokumentation: https://docs.github.com/en/code-security/how-tos/secure-your-supply-chain/establish-provenance-and-integrity/prevent-release-changes). Die Option wirkt **nur auf zukünftige Veröffentlichungen**. Deshalb muss sie VOR PGP04-Synthetik-Release tatsächlich aktiv sein. Historisch bestanden bereits andere, getrennte GH04/GH06 Release-Synthetiktests; das beweist allein nicht den aktuellen Konfigurationszustand.

**Sicherheits-Gate:** Ein Release als Draft anlegen, genau drei verschlüsselte Artefakte noch im Draft übertragen, dann publishen; serverseitiges `immutable=true`, binären Remote-Readback und PGPony-Besitzer-Restore einzeln nachweisen. Dieser Publisher kann mit minimalem GitHub Contents RW Token **nicht** die Repository-Administrationskonfiguration selbst einschalten. Die offizielle schreibende Immutable-Einstellung verlangt Repository Administration WRITE, die gerade NICHT auf den Kurzzeit-Token erweiterbar ist. Bei nicht bestätigter Checkbox STOP; keine Tokenfreigabe.

**Wahrheitsgrenze:** Dieser Nachtrag ist eine Code-/Dokumentationsprüfung, keine aktuelle bestätigte Einstellung im privaten Backup-Repository, keine Code-SHA-Freigabe und keine PGP04-Release-Abnahme.
