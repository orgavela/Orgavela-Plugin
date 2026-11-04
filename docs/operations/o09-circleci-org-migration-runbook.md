# O09 / OrgaVela – CircleCI nach GitHub-Organisationsumzug wieder verbinden

Stand: 04.11.2026. **Betriebsanweisung, keine Freigabe zur Abschaltung eines Pflichtchecks.**

## Nachgewiesene Ausgangslage

Die Organisation besitzt jetzt das öffentliche Repository **`orgavela/Orgavela-Plugin`**. Der bestehende geschützte Default-Branch `main` (zum Check `0adacb427bd95dd87594e358ec9c6f2f5b38aae8`) enthielt keine `.circleci/config.yml`. Der ungemergte [PR #45](https://github.com/orgavela/Orgavela-Plugin/pull/45) repariert die fest auf das ehemalige Repository `janjrre/Projekt-Wordpress-Plugin` eingestellten SSH-Audit-Skripte und enthält nun erstmals eine **reale** CircleCI-Konfiguration.

Auf einem früheren PR-Commit meldete CircleCI ausdrücklich `No configuration was found in your project`. Die GitHub-Gegenprüfung `static-safety` bestand, der echte SSH-Audit wurde korrekt übersprungen. Auf dem späteren HEAD des PRs lag noch **kein erfolgreicher** neuer CircleCI-Commitstatus vor. Ein SHA-geprüfter Mergeversuch wurde von GitHub mit `405 Cannot change this locked branch` abgewiesen. Der Branchschutz greift daher verbindlich.

**Kein** grüner GitHub-Sicherheitstest darf als Ersatz für den noch fehlenden CircleCI-Status bezeichnet werden. Ebenso ist die neue CircleCI-Konfigurationsdatei auf einem PR noch kein Beleg, dass die CircleCI-Plattform das Projekt korrekt eingerichtet und die Pipeline tatsächlich ausgeführt hat.

## Sichere Wiederanbindung ohne Abschaltung des Branchschutzes

1. Im bestehenden CircleCI-Konto die Organisation/Projektintegration auf `orgavela/Orgavela-Plugin` kontrollieren. Unter **Org → VCS Connections** prüfen, ob die GitHub-Authentifizierung und der Zugriff des CircleCI-GitHub-Apps oder des OAuth-Projekts nach dem Transfer noch gültig sind. Keine neuen kostenpflichtigen Organisationen, Planwechsel oder Kontexte anlegen.
2. In **Projects → OrgaVela → Project Settings → Project Setup/Pipelines** die Quelle (Config Source), den Checkout-Repository-Link und den **genauen Pfad `.circleci/config.yml`** kontrollieren. Für GitHub OAuth muss die Datei im verbundenen Repository unter diesem Pfad liegen; bei GitHub App gibt es einen expliziten Config-Source-/Trigger-Dialog. Konfiguration und Trigger müssen **den neuen Org-/Repo-Namen** referenzieren.
3. Den bereits bestehenden `source-integrity`-Job aus [PR #45](https://github.com/orgavela/Orgavela-Plugin/pull/45) **auf dem PR-Commit** anstoßen, sobald die App die neue Config-Quelle sieht. Der Job führt echte Bash- und PHP-Parserchecks aus, und wenn der Branch tatsächliche Pluginquellen enthält, zusätzlich Composer/WPCS/PHPStan/PHPUnit. Er erhält **keine** IONOS-Secrets. Nur ein authentisch vom Provider übermittelter SUCCESS-Status auf genau diesem SHA erfüllt die CircleCI-Seite.
4. **Keine** künstlichen Commitstatus-Pseudoläufe, keine als erfolgreich markierten Dummyjobs, keine temporäre Abschaltung des erforderlichen Checks und kein ungeschützter Direkt-Push nach `main`. Wenn die CircleCI-Integration nach dem Transfer eine ältere Pipeline oder einen falschen Webhook referenziert, das im CircleCI-Projekt dokumentieren und auf regulärem Weg berichtigen.
5. Nach Providerstatus und ggf. verlangtem unabhängigem Review PR #45 regulär integrieren. Anschließend den wieder aktivierten **read-only** SSH-Audit auf `main` mit `operation=cron` ausführen; dabei keine Cronjobs starten und keine Hosting-Backups erstellen/überschreiben. Für O03 separat den [synthetischen Restore-PR #46](https://github.com/orgavela/Orgavela-Plugin/pull/46) prüfen und die gleichen Schutzbedingungen beachten.

## Freier Betrieb und Belege

Nach den offiziellen CircleCI-Anleitungen ist `.circleci/config.yml` die Standard-Konfigurationsquelle; bei GitHub App muss die Projektquelle samt Trigger korrekt auf das neue Repository verweisen. Ein GitHub-OAuth-Projekt folgt anderen Regeln beim Umbenennen oder Verschieben. Die Fehlermeldung zur fehlenden Konfigurationsdatei kann entweder tatsächlich fehlende Dateien oder eine falsche eingestellte Config-Datei/-Quelle bedeuten; **sie allein beweist nicht** die genaue Art des Fehlers im CircleCI-Konto.

Offizielle Quellen:
- https://circleci.com/docs/guides/security/rename-organizations-and-repositories/
- https://circleci.com/docs/guides/orchestrate/pipelines/
- https://circleci.com/docs/guides/integration/using-the-circleci-github-app-in-an-oauth-org/

Der verbindliche V1-Zustand bleibt unabhängig von dieser CI-Reparatur offen: O03 echtes Backup/Restore, O04 externer Cron/Monitoring und P4 Rechts-/Datenschutzfreigaben sind **nicht** automatisch abgenommen. Dieser Leitfaden löst keine kostenpflichtigen Aktionen aus.
