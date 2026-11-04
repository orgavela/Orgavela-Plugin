# P4-02 – Übergabe und Sicherheitsnachweis (04.11.2026)

**Zweck:** [Issue #8](https://github.com/orgavela/Orgavela-Plugin/issues/8), [Draft PR #37](https://github.com/orgavela/Orgavela-Plugin/pull/37), direkt auf dem unveränderten P4-01-Head `a34f4195371f9edf1fa24ea4abdef1fd479f2c19` aufbauend. Nur source-/testseitiges Planungs- und Prototyp-Paket; keine Hauptbranch-/WordPress-/Datenbank-/Produktionsänderung.

## 1. Ausgangsbefund und Entscheidung

`src/Application/Consent/ConsentRecordService.php` nutzt die bestehende unveränderliche `consent_records`-Wahrheit mit `actor_user_id`, `subject_person_id`, `definition_version_id`, `auth_context`, `supersedes_record_id`. Das zusätzliche `representative_confirmation=true` bei einer delegierten Registrierung ist **kein** unabhängiger Guardian-Nachweis. P4-02 braucht eine pro Organisations-/Event-/Dokumentversion gebundene, veröffentlichte Policy, die `self`, `representative` oder `both` als unabhängige Anforderungen abbildet.

- [ADR 314](../specification/adr-314-independent-consent-policy.md) als **Entwurf**, nicht formal akzeptiert. Additiver V3-Migrationspfad, keine versteckte Legacy-Umdeutung und keine automatische Vertretungsberechtigung.
- `src/Application/Consent/ConsentRequirementGate.php`: **nicht-produktiv angebundener** rein deterministischer, fail-closed Evaluator auf ausschließlich trusted, serverseits live geladene Fakten. **Kein** REST-Endpunkt, UI, neue Rollen, Tabellen, Beschlussfassung oder Guardian-Authentifizierung hinzugefügt.
- `tests/Unit/p402-consent-gate-smoke.php`: 25 synthetische Positiv-/Negativfälle, darunter zwei unabhängige Accounts, Consent-/Actor-/Scope-/Version-/Widerruf-/Ablauf- und Formular-Manipulationsgrenzen.
- `.github/workflows/p402-consent-gate-offline.yml`: GitHub-Standardrunner `ubuntu-24.04` mit fixiertem Checkout-v7.0.1-SHA, `permissions: contents: read`, ohne Secrets, Persistenz, WP, DB, reale Daten, Deployment oder Release; max. fünf Minuten.

## 2. Echte Tests und bereits behobene Probleme

**Erster vollständiger PR-Lauf auf ursprünglichem Modell:** [Foundation #41795593711](https://github.com/orgavela/Orgavela-Plugin/actions/runs/41795593711) schlug mindestens im PHP-CodeSniffer und mehreren Unit-Matrix-Jobs fehl. Die festgestellten Befunde waren **nicht** bloße Provider-Fehler:
- WPCS: Gleichheitszeichen-Alignment und PHPDoc-Parameterformen.
- `ArchitectureTest::test_no_wildcard_reads_or_out_of_scope_namespaces` verbietet laut bestehendem Projektvertrag den neuen Ordner `src/Domain/Consent`.

**Korrektur:** Gate aus nicht erlaubtem `UOP\Domain\Consent` in die vorhandene `UOP\Application\Consent`-Schicht verschoben, alte Datei gelöscht, PHP-Formatierung korrigiert, Fixture-Require/Namespace und Workflow-Pfad angepasst. Bestehenden Architekturtest/WPCS nicht deaktiviert.

**Aktueller spezifischer Nachweis:** [P402 Offline Actions #41795896599](https://github.com/orgavela/Orgavela-Plugin/actions/runs/41795896599), Job `126459801346`, PASS: **25/25 synthetische Assertions**, beide PHP-Syntaxprüfungen PASS, `P402_WORDPRESS_DB_REQUESTS=0`, `P402_PRODUCTION_INTEGRATION=DISABLED`.

**Breitere CI:** Nach der Korrektur erneut gestartete [Foundation #41795896542](https://github.com/orgavela/Orgavela-Plugin/actions/runs/41795896542) und [M6 HTTPS #41795896583](https://github.com/orgavela/Orgavela-Plugin/actions/runs/41795896583) waren bei erster Dokumentation noch **pending/in_progress**. Resultate müssen auf dem **jeweils exakten** SHA nachgeprüft werden; bis dahin **kein** entsprechender Quality-/E2E-PASS.

## 3. Schutzgates und Nächstes

1. Vollständige Foundation-/PHPStan-/WPCS-/PHPUnit- und M6-HTTPS-Läufe auf dem finalen HEAD grün bekommen, ohne Regeln auszublenden.
2. ADR 314 und unveränderte V1-Kontrakte reviewen: pro Policyzweck, Version/Org/Event/Registration, keine aus identischen E-Mails oder Sessionkontext abgeleitete Minderjährigenvertretung.
3. Erst nach geprüften O03-Backup-/isolierten Restore-Gates neue V3-Tabellen/nullable Datenfelder und transaktionale Application-/P4-03-Anbindung umsetzen. Keine automatische oder rückwirkende Legacy-Doppelconsentpflicht.
4. Zwei unabhängige, jeweils wirksam autorisierte Entscheidungen in REST/Portal/Browser mit eigenen Widerrufen und gegen parallele Aktionen testen; Datenschutz-/Rechtsabnahme pro Organisation vor echtem Betrieb.

**Historisches V1-Cockpit unverändert:** 62/71 technische Pakete, M0–M7 0/8, P4 0/4 und Release-Gates 0/18. **Keine formale P4-02-Abnahme**, keine neuen produktiven Rollouts. Bitte diesen Zweig **erst nach dem übergeordneten PR-#11-Stack** in die normative Dokumentation integrieren.

**Worklog-Hinweis:** Der P4-01-Ausgangszweig enthält die spätere kanonische Datei `docs/operations/ASSISTANT_WORK_LOG.md` noch nicht. Die gesamte 230-KB-Historie von `main` wird deshalb **nicht** kopiert/überschrieben; dieser neue Bericht und ein angehängter Kommentar in Issue #8 sind der datensparsame und mergefähige Arbeitsnachweis. Bei späterer Integration muss er append-only in die kanonische Historie übernommen werden.
