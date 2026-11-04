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
