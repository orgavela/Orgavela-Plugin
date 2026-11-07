# PGP-05: Sicherer Backup-Repository-Pin nach Organisationsmigration

**Datum:** 04.11.2026 | **Scope:** W0/O03, [Issue #27](https://github.com/orgavela/Orgavela-Plugin/issues/27), Offline-Kandidat [PR #33](https://github.com/orgavela/Orgavela-Plugin/pull/33). **Test/Änderungsart:** ausschließlich synthetischer Code-/Boundary-Fix im gestapelten Entwicklungszweig, keine produktive Freigabe.

## Ausgangsproblem

Die lokale Provider-/Offline-Writer-Bibliothek `scripts/ci/production_backup/github_api.py` enthielt den hart kodierten GitHub-REST-Zielpfad `janjrre/orgavela-backups`. Nach der Migration liegt das vom verbundenen GitHub-Konto am 04.11.2026 **unabhängig bestätigte private** Backup-Repository unter `orgavela/orgavela-backups`. Die GitHub-API-Schicht erzwingt den Pfad bereits fail-closed; mit dem veralteten Pin würde sie jedoch die aktuellen Repository-URLs verweigern bzw. den falschen Namen verwenden. Dies ist vor einer produktiven Aktivierung zu korrigieren.

## Implementierung und Regressionstest

- `scripts/ci/production_backup/github_api.py`: genau die Repository-Konstante auf `orgavela/orgavela-backups` umgestellt. **Keine** allgemeine Umgebungsvariable, dynamische Zielwahl, Redirection-Freigabe oder Credential-Umgehung eingeführt.
- `scripts/ci/production_backup/test_collector_port.py`: neue Negativprüfung bindet die **exakte** Repository-Konstante, vergleicht den Import des `writer`-Moduls und lässt die alte Namespace-URL sowie eine URL des öffentlichen Source-Repositories durch `request_json` **vor** jeder Netzwerkanfrage ablehnen. Hierfür wird `urllib.request.build_opener` gemockt und `network.assert_not_called()` verwendet.
- Beide realen `writer.py`-Endpunkte für GitHub API und Asset-Upload/-Readback verwenden die importierte Konstante; das produktive `writer.main()` und `github_api.installation_token()` bleiben unverändert auf **Exit 78 / Credential-Disabled**. Keine Remote-Verbindung, Tokenablage, URL-Weiterleitung oder echte Datenübertragung.

## Umfang und Prüfung

**Quell- und Testcode-Review:** Pfadbindung ist weiterhin strikt; die alte Repo-Identität wird vor HTTP abgewiesen. Die eigentlichen Python-/PHP-/OpenPGP-Integrationstests müssen auf **dem neuen HEAD** der Änderung laufen; frühere 16/16 Tests auf PR-#33-HEAD `0d419c991d75999dd264aba2a912fedf663a1cf0` sind **historisch** und dürfen nicht als Beleg für diese Revision gewertet werden. Falls keine aktuelle CI läuft, steht der Testnachweis ausdrücklich aus.

**Nicht erfolgt:** Kein echter Backup-/Restore-, IONOS-/WordPress-/Produktionszugriff, keine Betreiber-Recovery-Schlüssel, keine Konten-/Secret-Konfiguration, kein Dreamloud-Zugriff, keine Freigabe/Integration von PR #33, keine kostenpflichtigen Tools.

**Nachweisweg / nächster Schritt:** Kostenfreien synthetischen Provider- oder isolierten GitHub-CI-Test auf dem exakten neuen SHA ausführen. Nur die bestehenden Fixture-/Abwehr-/Crypto-Tests ohne Secrets/Contexts. Nach einem grünen Ergebnis im abhängigen PR reviewen und in korrekter Stack-Reihenfolge integrieren. Reale W0/O03- und PGP-05-Gates bleiben separat offen.

**V1 unverändert:** 62/71 implementiert (historisch), M0–M7 0/8, P4 0/4, R01–R18 0/18.


## Zusätzliche kostenlose CI-Absicherung (04.11.2026)

Neuer GitHub-Actions-Workflow `.github/workflows/pgp05-offline-regression.yml` (Commits `960520d258f73a6f8dedd0617a7a7b8da0000b71` und `1592bd6d03cf888c66b401005eb5bb1ecc99c048`): nur auf Push zu exakt den benannten PGP-05-Entwicklungsbranches, Pfadfilter ausschließlich auf Offline-Collector/Testdateien/Workflow. Öffentliches Repository mit Standard-`ubuntu-24.04`-Runner (laut GitHub-Abrechnungsdokumentation kostenlos), Job-Timeout 12 Minuten, `permissions: contents: read`, Checkout an feste SHA gebunden und `persist-credentials: false`. Kein Environment, keine Token-/Contextanforderung, kein Upload von Artifacts, Cache, Release oder WP-/IONOS-Aufruf.

Das Jobskript prüft zuerst die exakte neue Repository-Konstante, dass Python-Writer/Capture-Entry-Points `78` zurückgeben und dass Quell- und Writer-Credentials getrennt bleiben; anschließend alle bestehenden synthetischen Unit-, PHP-Hilfs-, Fixture-/OpenPGP-/Temporary-Key-Roundtrip-Tests. **GitHub-Workflow-Ausführung/Status auf dem neuen Commit war über den GitHub-Connector mit PR-run-only-Filter nicht nachweisbar:** Deshalb *CI-Ergebnis offen*; keine Kennzahl auf PASS setzen. Kein Self-hosted-/Larger-Runner und keine kostenpflichtigen Optionen.

**Stack-Zustand:** [abhängiger Draft PR #36](https://github.com/orgavela/Orgavela-Plugin/pull/36) gegen den weiter ungemergten PR #33. Fachliche PGP05/Backup-/Restore-Freigabe bleibt gesperrt. Der Workflow selbst ist ein Prüfwerkzeug, keine produktive Aktivierung.


## GitHub-Actions-Erstnachweis: PASS, Commit 3b123b61

Die PR-getriggerte [GitHub-Actions-Ausführung #41793866237](https://github.com/orgavela/Orgavela-Plugin/actions/runs/41793866237) auf HEAD `3b123b612e1b51eb2b1c33611fb044becdada441` wurde direkt über `fetch_workflow_run_jobs` und den Joblog `126453253125` verifiziert: **1/1 Job success**. Checkout ohne gespeicherte Credentials, Einrichtung, Sicherheits-Stopps und alle Offline-Testschritte **grün**. Im Log: `PGP05_SAFE_TARGET_AND_DISABLED_PRODUCTION=PASS`, `ORGAVELA_REAL_WRITER=DISABLED`, `ORGAVELA_REAL_CAPTURE=DISABLED`; **4/4 PHP-Lints**, PHP-Helper-Test `PASS`, **17/17 Python unittest PASS**, unabhängige Offline-Ciphertext-Handoff-Integration mit **3 Assets**, temporäre Public-/Private-Key-Roundtrip-Prüfung mit **3 Komponenten/Hashes**. `remote_calls=0`, `actual_site_restore_tested=false`, `production_capture_allowed=false`. Das ist echte **synthetische CI**, keine echte Sicherungsabnahme.

**Wartungshinweis:** Der auf diesem ersten HEAD verwendete Checkout v4.2.2 war zwar SHA-gepinnt und erfolgreich, aber der Runner warnte vor der zwangsweisen Node20→Node24-Umstellung. Für den **Folge-HEAD** wird deshalb die upstream-offizielle Actions-Checkout-v7.0.1-Revision `3d3c42e5aac5ba805825da76410c181273ba90b1` eingesetzt und die gesamte Offline-CI erneut geprüft. Der Erstlauf gilt bis dahin als historische SHA-genaue Baseline.
