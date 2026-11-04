# OrgaVela – Betreiber-Dauerfreigaben für kostenlose Entwicklungsarbeit

**Datum:** 04.11.2026  
**Gültigkeit:** Eigenständiges Projekt UOP Core / OrgaVela, Repository `orgavela/Orgavela-Plugin` sowie die ausdrücklich dazugehörigen Test-/Backup-Komponenten. Die Freigabe wurde vom Betreiber in einer direkten Beauftragung erneut und erweitert erklärt. `AGENTS.md` enthält die verbindliche Kurzzusammenfassung; die bestehenden V1-/W0-/PGP-05-Sicherheits- und Produktgates bleiben maßgeblich.

## Freigabeumfang

| Bereich | Standing Approval | Praktische Grenze |
| --- | --- | --- |
| Repo/Code | Eigenständig planen, lesen, entwickeln, Fehler beheben, refactoren, testen, Branches/PRs/Commits/Reviews anlegen | Nur belegte Änderungen, Abhängigkeiten/ADRs beachten, Code nicht ungeprüft integrieren |
| Kostenloses GitHub-Usage | Das komplette verfügbare kostenlose Kontingent darf bei Bedarf eingesetzt werden | Vor Ausführung Preis/Overage-Risiko des konkreten Kontos und Runs prüfen; keine kostenpflichtigen Add-ons, keine heimlichen Mehrkosten |
| GitHub Apps/Tools | Verfügbare kostenfreie Apps, Checks, Security-Tools verwenden, passende kostenlose neue Apps vorschlagen | Nur mit technischen Zugriffen; nötige App-Installations-/OAuth-Bestätigung durch Betreiber, wenn Tool dies verlangt |
| Skills | Alle verfügbaren/verbundenen Skills sowie weitere tatsächlich **kostenlose** Agensi-Skills verwenden; kostenlose Skills/Skill-Bundles/Erweiterungen vorschlagen | Keine kostenpflichtigen Marketplace-Skills erwerben; Tool-Anweisungen und Sicherheitsregeln wahren |
| Test/CI | Kostenfreie GitHub-/CircleCI-/lokale synthetische Test-, Review-, PHPStan-/WPCS-/E2E-/Security-/A11y-Läufe und sinnvolle Reruns eigenständig planen/auslösen | Keine CI-Ausführung, die untrusted Code an Secrets bringt, kein unkontrolliertes Re-Run in Gebührenkontexten |
| Integration/Release | Vorbereiten, prüfen und nach bestandenem aktuellem Test-/Privacy-/Backup-/Rollback-/Abnahmepfad ausführen | Keine generelle V1-Abnahme durch diese Freigabe; historische grüne Checks nicht auf neuen SHA übertragen |
| OrgaVela-Website | Begrenzt lesen, Sicherheit und Betriebszustand prüfen; notwendige technische Korrekturen nach bestandenen Schutzgates | Kein echter Teilnehmerdaten-Export, irreversible Löschung, ungesicherte Migration oder produktiver Restore ohne gesonderte im Runbook geforderte Betriebsfreigabe |
| Kosten | Keine Kosten auslösen | **Ausnahmslos keine** Paid-Tier-/Trial-to-Paid-/Credit-/Subscription-/Overage-/Tarif-/Bezahlfunktion ohne erneute getrennte Zustimmung |

## Verbindliche Grenzen und Stopps

1. Die Dokumentation ist eine **weite selbstständige Arbeitsfreigabe**; sie hebt den Sicherheits- und Datenschutzstandard nicht auf. Besondere, absichtlich eng gefasste reale Backup-/Restore-/Produktionsdaten-Gates aus [PGP-05 #27](https://github.com/orgavela/Orgavela-Plugin/issues/27) und dem V1-Masterplan werden nicht übergangen.
2. **Kein Zugang zu Dreamloud** als Test-/Deployment-/Backup-Ziel und keine gemeinsamen Secrets oder Betreiberkonten, sofern nicht projektseitig spezifisch erlaubt und isoliert.
3. Keine sensiblen Details, Tokens, personenbezogene Daten, KI-Chatprotokolle oder proprietäre Rohdaten in dieses öffentliche Repository. Keine ungeprüften Löschungen/Publikationen.
4. Wirtschaftlich ist nur **verifizierbar kostenloses** Arbeiten autorisiert. Bestehen Zweifel an automatischen Overages oder einer erforderlichen Bezahlmethode: Stop vor Ausführung.
5. Neu eingesetzte Skills/Tools benötigen keine eigene pauschale Zustimmung, aber eine externe App-Installation/Connector-Authentifizierung kann technisch eine bestätigte Betreiberaktion voraussetzen. Nicht installierte Apps niemals als installiert ausgeben.
6. Konkrete Arbeit innerhalb einer aktiven Beauftragung ausführen; **keine Arbeit im Hintergrund** ohne separat geplante Automation.
7. Normative Masterplan-/Abnahme-/Fortschrittszählung und append-only Arbeitsprotokoll bleiben bindend.

## Arbeitsweise und Qualität

- Bei jedem PR oder Betriebscheck: Ziel, SHA/Version, erwarteten/faktischen Teststatus, Findings mit Schweregrad, Kostenrisiko, mögliche Rollbackgrenze, Gate-Status dokumentieren.
- Befunde unterscheiden: **bestätigt**, **historisch dokumentiert**, **nur synthetisch getestet**, **nicht unabhängig überprüft**, **gesperrt**.
- Priorisieren: Sicherheit, Datenschutz, Integrität und Backup/Restore vor optionalen Features und kosmetischer WPCS-Bereinigung.
- Zielhorizont: M6/P4 fachlich fertigstellen, korrekte gestapelte PR-Integration, M7 absichern, dann R01–R18 und V1-Freigabe auf exakt einem Kandidatenstand.

**Status der Autorisierung:** im Branch dokumentiert, wird erst durch geprüften Merge in `main` Bestandteil der kanonischen Repo-Fassung. Eine gesonderte Dokumentation im [Master-Issue #15](https://github.com/orgavela/Orgavela-Plugin/issues/15) kann die Erklärung unabhängig vom offenen PR sichtbar machen.
