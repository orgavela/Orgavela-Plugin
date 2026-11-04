# ADR 314 (Entwurf) – Unabhängige Einwilligungen von Betroffenen und verifizierten Vertretungen

**Status:** ENTWURF / zur fachlichen und technischen Prüfung, **nicht akzeptiert**.  
**Datum:** 04.11.2026 · **Issue:** [P4-02 #8](https://github.com/orgavela/Orgavela-Plugin/issues/8) · **Abhängigkeiten:** M5 `consent_records`, M6 `ConsentRecordService`, P4-01/ADR-313 Altersatteste, P4-03 Verifikations-/Guardian-Modell.  
**Priorität:** Hoch, datenschutz- und autorisierungskritisch. Dieser Entwurf führt **keine** Schemaänderung, neue Migration, aktive REST-Route oder reale Einwilligungsfreigabe aus.

## Problem und Ausgangslage (Repository geprüft)

Eine derzeitige Entscheidung in `consent_records` ist bereits **append-only** mit `organization_id`, `definition_version_id`, `subject_person_id`, `actor_user_id`, `registration_id`, `decision`, `supersedes_record_id`, `auth_context`, `evidence_json` und `decided_at`. `ConsentRecordService::record_submission()` verlangt bei delegierter Entscheidung eine gesonderte Rollenbestätigung. **Diese bestätigt aber keinen extern geprüften Guardian-Status.** Ein bestehendes `auth_context=delegate` ist deshalb **keine** geprüfte Einwilligung einer gesetzlichen Vertretung. Die bisherige Speicherung enthält für eine veröffentlichte Einwilligung **keine pro Veranstaltung/dokumentierter Policy festgelegte Anzahl unterschiedlicher Entscheider**. Eine nachträgliche zweite Zustimmung darf nie durch Wiederverwendung derselben WordPress-Session oder identische E-Mail simuliert werden.

## Beabsichtigter fachlicher Vertrag

**Policy-Modi pro freigegebenem Einwilligungszweck:** `self` (nur Betroffene/r), `representative` (nur tatsächlich verifizierte Vertretung) oder `both` (beide **getrennt**, mit verschiedenen WordPress-Accounts). Der Modus wird je Organisation, **genau einer Veranstaltung**, unveränderlicher Consent-Dokumentversion und Policy-Revision definiert. Keine automatische Minderjährigenregel, keine Annahme aus Geburtsdatum, Name, gleicher Familien-E-Mail, bloßer `guardian_of`-Beziehung oder Einladungscode.

**Wirksamkeitsregel:** Eine policy-gebundene Einwilligung gilt erst dann als vollständig, wenn für jede erforderliche Rolle genau eine **aktuelle, positive, serverseitig autorisierte** Entscheidung mit korrekter Tenant/Event/Registration/Subject/Document-Version vorhanden ist. Eine Vertreterentscheidung benötigt zusätzlich eine aktuell gültige, unabhängig geprüfte eventbezogene Vertretung (P4-03), inkl. Ablauf und Widerruf. Im Modus `both` müssen `actor_user_id` der beiden aktuellen positiven Records **verschieden und nicht NULL** sein. Keine Zwei-Personen-Freigabe durch zweimaliges Ankreuzen derselben Account-Sitzung.

**Entscheidungen und Widerrufe:** Beide Rollen haben jeweils eigene append-only Records und eigene, idempotente Widerrufsoperationen. Jede neue, abgelehnte, widerrufene oder abgelaufene **relevante** Entscheidung sowie ein entzogener Guardian-Grant kann die aktuelle Policy-Erfüllung sofort ungültig machen, **ohne frühere Consent-/Registration-Snapshots zu ändern**. Historische Nachweise und Rechte werden nicht aus älteren weiterhin positiv wirkenden Records wiederbelebt. Duplicate/Race auf derselben Role-Slot müssen transaktional serialisiert und fail-closed behandelt werden.

**Registration/Capacity:** Bei offener Doppelzustimmung bleibt der Vorgang als **consent-pending** markiert bzw. darf keine fachliche Complete-/Seat-Claim-Statuszusage erhalten. Konkreter bestehender Registration-State-Machine-Übergang ist vor Implementierung zu prüfen; keine neuen Statuswerte blind hinzufügen. Erst nach Bestätigung und erneuter Berechtigungs-/Capacity-Prüfung kann die abschließende Operation erfolgen. Mail-/Export-/Audit-Ausgaben erhalten nur notwendige minimierte Statusinformationen, niemals unberechtigte Subject- oder Secretdaten.

## Geplanter additiver Daten- und Migrationsvertrag – vor Änderung noch zu überprüfen

1. Neue **insert-only** `consent_requirement_versions` zur tenant-/event-/immutable-document-version-gebundenen Policy-Revision; neue Veröffentlichungen müssen eine streng validierte Rolle `self|representative|both` enthalten. Frühere Revisionen bleiben erhalten; jede Registration pinnt die aktuelle Revision beim Start. Das finale konkrete FK-/Unique-/Indexdesign erst nach physischer MariaDB-Version-2-Schemaprüfung.
2. Neues `registration_consent_requirements`-Linkset verknüpft bestehende Registration + Policy-Version, aber **enthält keine Consent-Wahrheit**. Die alleinige Entscheidungs-/Widerrufshistorie bleibt `consent_records`.
3. Additive, nullable Rollenkennzeichnung je `consent_records` für **neue** Policy-fähige Einwilligungsrecords; die bestehende `auth_context` bleibt bestehen und darf nicht als Rechtsrolle interpretiert werden. Zusätzliche P4-03-Grant-Referenz serverseitig, kurz und nur für positive Vertreterbefugnis gespeichert (keine Identitätsnachweis-Kopie). Vor Schemaentscheidung eine Integritäts-/Indexanalyse zur bestehenden `supersedes_record_id`-Kette und zu parallel möglichen Widerrufen durchführen.
4. **Neue, versionierte V3-Migration** nur additiv und nach bestätigtem O03 Backup + echtem isoliertem Restore; M1/V1-Baseline und V2-Altersmigration niemals umschreiben. Keine automatische Migration alter Einzelconsents zu `both`, keine nachträglichen positiven Entscheidungen, kein Auto-Grant.
5. Abwesenheit oder beschädigte Policy ist im **neuen Policy-Pfad** strikt nicht erfüllt. Bestehende Alt-Registrierungen behalten ihren historischen Vertrag; es darf keine stillschweigende Umdeutung oder rückwirkende Blockade geben. Legacy- und Neu-Workflows unterscheiden sich allein anhand tatsächlich angehefteter Policyrevision, **nicht** anhand frei gelieferter Clientflags.

## Technische Architektur und Abgrenzung des aktuellen Entwurfs

Die Business-Entscheidung wird **nicht** in Browser/JS oder neuen direkten REST-SQL-Routinen getroffen. Ein späterer `Application`-Service liest DB-Transaktion/Scope/Published Form Version, Security-Policy, Identity-Link und P4-03-Guardian-Grants **erneut live**; die reine Domain-Prüfung erhält ausschließlich solche kanonisch verifizierten Daten. Der in diesem PR erarbeitete **isolierte** `ConsentRequirementGate`-Prototyp ist nicht mit REST/WordPress verbunden und ändert keine existierende Registrierung. Seine booleschen `authorized`-/`guardian_verified`-Eingaben sind nur **intern** zulässige, serverseitig abgeleitete Testfakten – niemals vertrauenswürdige Clientwerte. Positives Proto-Ergebnis ist keine Produktivfreigabe.

Für multiple Sorgeberechtigte: eine zweifache Vertreterpflicht, Widersprüche und spätere Gruppeneinwilligung benötigen expliziten zusätzlichen fachlichen Policy-Modus und Legal-/Retention-Entscheid. Bei mehreren konkurrierenden aktuellen Vertretern ohne diese Regel verweigert der Prototyp die Erfüllung statt willkürlich einen auszuwählen.

## Sicherheits- und Abnahme-Matrix (noch nicht erfüllt)

- `self`, `representative`, `both`, fehlende zweite Rolle, zwei verschiedene Konten, derselbe Account doppelt.
- Unterschiedliche Organisation, Subject, Event, Registration, falsche Consent-Version, geteilte E-Mail, gestohlener/abgelaufener Invite, nicht geprüfter bzw. entzogener Guardian-Grant.
- Alte/gesperrte/mehrfach aktuelle Entscheidungen, konkurrierende Bearbeitungen, Replay, einzelner Widerruf, Denial, Ablauf und die geschützte Rückabwicklung eines Seat-Claims.
- Per-Role-REST- und Portal-Autorisierung, Cache/PII-Minimierung, Version-Pinning, Audit/Export/Retention, Mail/Outbox, Mobile/Keyboard/Screenreader, MariaDB-Migration, Rollback/Recovery.
- Test mit GitHub Actions auf **exaktem HEAD**, PHPStan/WPCS, reale isolierte WordPress-/MariaDB-Integration und P4-03-Verifikationsverfahren, danach juristisch/fachliche Freigabe je Organisation.

**Aktueller Status:** Ein technischer Entwurf und rein synthetischer Domain-Test können im Quellbranch entstehen; keine tatsächlich nutzbare doppelte Einwilligung, keine neue Datenbankspalte oder Compliance-Behauptung. **Issue #8 bleibt offen**, **P4 formal 0/4**, V1 weiterhin **OFFEN**.
