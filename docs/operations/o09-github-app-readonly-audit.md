# O09 – OrgaVela GitHub App: nachweisbarer Read-only-Integrationscheck

Stand: 2026-11-05 · Gegenstand: **nur** GitHub-Repository `orgavela/Orgavela-Plugin`. Keine Änderung an WordPress, IONOS, CircleCI oder Branchschutz.

## Auftrag und Zweck

Die vom Betreiber angelegte und installierte GitHub App `OrgaVela-Dev-Assistant-2026` soll für GitHub Actions nachweislich authentifizierbar sein und O09-Integrationsblocker ohne Umgehung bestehender Regeln lesend untersuchen. Die GitHub-App-Installation an sich verbindet diese App **nicht** als neuen direkten ChatGPT-Connector; die App ist über den GitHub-Actions-Token-Mechanismus nutzbar.

## Secrets und begrenzte Tokenrechte

- Repository-Secrets `ORGAVELA_APP_CLIENT_ID` und `ORGAVELA_APP_PRIVATE_KEY` wurden vom Betreiber in GitHub angelegt; deren Werte wurden **nicht** ausgelesen, exportiert oder im Protokoll gespeichert.
- Der Workflow `.github/workflows/orgavela-app-auth-readonly.yml` nutzt `actions/create-github-app-token@v3` und überprüft den App-Slug explizit auf `orgavela-dev-assistant-2026`.
- Das daraus erzeugte kurzlebige Token ist auf **ein** Repository begrenzt und fordert ausdrücklich nur `contents: read`, `administration: read`, `statuses: read` an, obwohl die App selbst weiterreichende Berechtigungen besitzt.
- Die GitHub-Action widerruft das Token am Jobende. Kein Token, SSH-Secret, Teilnehmerdatensatz, unmaskierte API-Antwort oder Roh-Branchregel wird veröffentlicht.
- Für diesen Test ist nur `workflow_dispatch` bzw. ein Push auf den fest benannten Testzweig beim Ändern dieser Workflow-Datei konfiguriert. Der Workflow schreibt nicht in Repository, Protection-API, Secrets, CircleCI oder Live-Site.

## Verifizierte Tests (Github Actions)

| Commit/Beleg | Ergebnis |
|---|---|
| [Run #41999373886](https://github.com/orgavela/Orgavela-Plugin/actions/runs/41999373886) | Installationstoken und lesender Repository-/Dateizugriff erfolgreich |
| [Run #41999537289](https://github.com/orgavela/Orgavela-Plugin/actions/runs/41999537289) | App-Slug `orgavela-dev-assistant-2026` explizit bestätigt, Tokenzugriff erfolgreich |
| [Run #42002164587](https://github.com/orgavela/Orgavela-Plugin/actions/runs/42002164587) | Lesender Zugriff auch auf GitHub-Branch-Protection-Endpunkt mit `administration:read` und Status-API bestätigt |
| [Run #42002621189](https://github.com/orgavela/Orgavela-Plugin/actions/runs/42002621189) | Präzisierte Diagnose von `main`, sicher ohne Schreiboperationen erfolgreich |

## O09-Befunde auf dem letzten Lauf

- `main` meldet `protected=true` und ein vorhandenes `lock_branch`-API-Feld, **aber `lock_branch.enabled=false` zum Zeitpunkt der Messung**. Der historische Merge-Fehler von PR #45 (`405 Cannot change this locked branch`) bleibt dadurch **nicht rückwirkend widerlegt**. Kein erneuter Mergeversuch und keine Änderung an Branchschutz oder Bypass-Regeln durch diese Diagnose.
- Im ausgelesenen Branch-Protection-Payload: `enforce_admins=false`, keine Required-Status-Kontexte, keine verpflichtenden PR-Reviews, keine expliziten Push-Restrictions, `required_linear_history=false`, `required_conversation_resolution=false`; API gibt null passende Repository-/Parent-Rulesets zurück. Diese Befunde sind eine **Momentaufnahme**, keine Garantie, dass ein Merge berechtigt ist.
- Die Datei `.circleci/config.yml` fehlt weiterhin auf `main`, ist aber im getrennten [PR #45](https://github.com/orgavela/Orgavela-Plugin/pull/45) vorhanden. Der aktuelle Commitstatus auf `main` meldet `CircleCI Pipeline: error` mit dem Befund `No configuration was found in your project`. Der richtige CircleCI-Konfigurationsquellen-/Triggernachweis auf dem aktuellen PR-HEAD liegt **nicht** vor.
- Weitere offene Pruefung: tatsächliche aktuelle GitHub-PR-Merge-Entscheidung, CircleCI-Verknüpfung mit der übertragenen Organisation und echter CircleCI-Job-SUCCESS. Kein künstliches Hochsetzen von Statuschecks.

## Nächste kontrollierte Schritte

1. Im CircleCI-Projekt die neue Organisationsrepository-Bindung sowie Quelle/Trigger für `.circleci/config.yml` korrekt konfigurieren, dann den **echten** `source-integrity`-Job auf dem aktuellen PR-HEAD #45 verifizieren.
2. Erst nach identitätsgebundenem Review, echtem CI-Nachweis und regulär zulässigem PR-Merge die betroffenen O09-/O04-Fixes integrieren; kein Bypass geschützter Branches.
3. Danach den read-only OrgaVela-SSH-Cron-Audit **separat** auslösen und fachlich auswerten; keine aktivierende Cron-/Hosting-Migration.
4. O03-Backup/Restore, P4-Rechts-/Minderjährigenregeln und M6/M7-Release-Abnahmen bleiben vollständig eigenständige Gates.

## Fortschritt und Geltungsgrenze

Die neu bestätigte GitHub-App-Authentifizierung ist eine **Betriebswerkzeug-Verbesserung unter O09**, kein implementiertes normatives M-Paket und keine Produktabnahme. **V1 weiterhin offen:** 62/71 historisch technisch implementiert, 0/8 M formal abgenommen, 0/4 P4 formal abgenommen, 0/18 Release-Gates grün. Live-Plugin `UOP Core 0.1.0-alpha.2` wird durch diesen Workflow nicht verändert.
