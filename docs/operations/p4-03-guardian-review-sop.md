# P4-03 – Ablauf für Guardian-Prüfung und private Personenreferenz (Entwurf)

**Stand: 04.11.2026 · Keine rechtliche/operative Produktivfreigabe.** Grundlage: offene P4-03 Issue #9 und Draft-PR-Kette #11 → #38 → #39. Die tatsächliche gesetzliche Vertretungsberechtigung kann UOP Core nicht automatisch bestätigen.

## Prüfroutine – vor Genehmigung verbindlich festzulegen

1. **Antrag:** Jede vertretende Person benötigt ihr eigenes angemeldetes, einer eigenen Person zugeordnetes WordPress-Konto. Eine UUID zum betroffenen Personenrecord ist nur eine Such-/Antragsreferenz, kein Zugangstoken und kein Nachweis. Gemeinsame Familien-E-Mails erteilen niemals Rechte.
2. **Identität und Befugnis:** Eine vom Betreiber schriftlich bestimmte, unabhängige prüfende Person muss die Identität des Antragstellers, das betroffene Kind/die betroffene Person, die konkrete Vertretungsart und ihre Reichweite für die Veranstaltung außerhalb des Portals prüfen. Welche Nachweisarten rechtswirksam, angemessen und datensparsam sind, entscheiden Betreiber/Datenschutz-/Rechtsberatung vor Produktivbetrieb; keine automatischen Annahmen über Elternschaft oder Alter.
3. **Freigabe:** Ein Claim allein erzeugt kein Delegationsrecht. Nur nach unabhängiger externer Verifikation und autorisierter Reviewer-Entscheidung: eine ausdrücklich freigegebene veröffentlichte Veranstaltung, nur Berechtigungsart registration_manage, maximal 90 Tage. Die bestehende Checkbox bestätigt lediglich, dass die Person den Nachweis geprüft *haben will*; sie ist kein Beweis des Verhältnisses.
4. **Mehrfachvertretung:** Jeder Vertreter erhält eigenen Account, eigenen Antrag, separate Beziehung, gesonderte Entscheidung und unabhängige Berechtigung. Ablehnung, Widerruf oder Ablauf einer Berechtigung verändert nicht automatisch die Berechtigungen anderer Vertreter. Bei widersprüchlichen Behauptungen keine automatische Genehmigung; Fall außerhalb des Portals klären.
5. **Entzug:** Aktive Relationship, verknüpfter Account und begrenzte Delegation müssen bei jedem Folgezugriff geprüft werden; keine rückwirkende Änderung historischer Einwilligungen oder Audits.

## Private Ausgabe der Personenreferenz – derzeit OFFEN

- Es gibt keinen verifizierten Self-Service-Ausgabe- und Bestätigungsprozess für private Personen-UUIDs. Deshalb weder öffentliche Personenliste noch Versand an ungeprüfte/gemeinsame Familienadressen oder an irgendeinen allein namentlich identifizierten Kontakt implementieren.
- Für einen künftig gesondert freigegebenen manuellen Pilot muss der Betreiber den berechtigten Empfänger und einen sicheren, geprüften Ausgabekanal bestimmen. Keine Referenz mit Personendaten in URL-Query-Strings, Analytics, allgemeinen Logs, Tickettexten oder öffentlichen Screens weitergeben.
- Besitz der Referenz bleibt **nicht autorisierend**. Die REST-Antwort ist für bekannte/unbekannte Personen opak; das atomare Limit von 3 Versuchen pro 24 Stunden pro Tenant/Account schützt vor massenhaften Anträgen.
- Verlust/Fehlversand benötigt einen eigens überprüften Austausch- oder Rotationsprozess. Vor Freigabe einer solchen Funktion keinen Code als geheimes Authentifizierungsmittel bezeichnen.

## Dateninventar und Fristentscheidungen

| Objekt | Technik und offene Verantwortung |
| --- | --- |
| Person-UUID | reine Lookup-/Claimreferenz, keine Freigabe |
| Relationship | tenantbezogener Claim pending / active / rejected, Antragsteller und betroffene Person getrennt |
| Delegation | separater accountbezogener, eventgebundener Grant mit Beziehung und 90-Tage-Expiry |
| Audit und Outbox | Vorgangscodes/Korrelation; keine Passnummern, Urkundenscans, Geburtsdaten oder unstrukturierten Nachweisnotizen aufnehmen |
| Guardian-Quota in wp_options | Hash von Tenant-/Account-ID, Zahl/Ablauf; 24-h-Window und 24-h-Grace; bis zu 100 valide alte Einträge je stündlichem Scheduler-Lauf; O04-Betriebsnachweis offen |

**Ungeklärt:** Aufbewahrung und Erasure für abgelehnte/abgelaufene Relationships, Delegationen, historische Audits und beschädigte Quota-Datensätze; Privacy Export, Betriebsüberwachung, Verantwortung und Datenschutzhinweise. Fehlgeformte Quota-Einträge bleiben aus Sicherheitsgründen fail-closed und werden nicht blind gelöscht. Betreiber und fachkundige Stelle müssen konkrete Fristen/Verfahren dokumentieren.

## Abnahmekontrolle

- Baseline Draft #39: echte REST-Konkurrenz mit 16 parallelen Anträgen, maximal 3 persistierten Claims, 0 Grants und 9/9 grünen CI-Jobs auf SHA 17adc7b.
- Dieser Arbeitsabschnitt: Regressionstest für zwei getrennte Vertreter desselben Subjekts, identische E-Mail ohne Rechte, voneinander unabhängige Genehmigung/Ablehnung sowie Widerruf und Ablauf. Testergebnisse erst nach grünem Lauf als verifiziert behandeln.
- Nicht erfüllt: externer rechtlicher Guardian-Nachweis, gesicherte UUID-Ausgabe-/Bestätigungsstrecke, widersprüchliche echte Sorgerechtsfälle, O04-Scheduler und W0/O03-Restore.

**Go/No-Go:** Bis zu den fachlichen, Datenschutz-, Betriebs- und Release-Gates bleibt P4-03 unvollständig. Keine realen Testpersonen oder automatische Codeverteilung, kein Merge und kein produktiver Deploy.
