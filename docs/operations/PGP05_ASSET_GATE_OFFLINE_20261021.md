# PGP-05: offline ciphertext-workspace contract gate (synthetic only)

**Stand 21.10.2026 · Entwicklungspaket 1 · noch keine produktive Freigabe.**

Quellstand: geschuetzter PGP-04 V3 Commit `3c9f0bb8bd85fb97e09b84c870fb42fdf491a8de`. Separater Entwicklungsbranch `feat/pgp05-offline-ciphertext-gate-20261021`. Referenz [PGP-05 Issue #27](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/27) und [OpenPGP/PGPony ADR](W0_O03_PGPONY_OPENPGP_MIGRATION_ADR_2026-10-20.md).

## Implementierter Umfang
- `scripts/ci/orgavela_pgp05_asset_gate.py` liest **ausschliesslich einen lokal explizit angegebenen, bereits verschluesselten synthetischen Workspace**. Es kontaktiert keine Website oder GitHub-API, ruft keinen Provider auf, entschluesselt keine Archive, liest keine echten Backupdaten und erstellt keine Releases.
- Striktes 3-Dateien-Allowlist-Schema: `orgavela-synthetic-*.tar.gpg`, korrespondierende `.sha256` und `manifest.json`; nur exakt neun definierte Manifestfelder, keine doppelten JSON-Schluessel. Kein Klartext, kein zusaetzliches Sidecar/Log in der Uebergabe.
- `synthetic=true` und `contains_production_data=false` **zwingend**; ausschliesslich der bekannte OrgaVela-OpenPGP-Public-Key-Fingerprint `7C9DC60D8E1B293E514696EB333D15A4D8BA625C`; limitierter Umfang, SHA-256-Vergleich von Archiv, Sidecar und Manifest, keine Symlink-/Hardlink-Assets, keine weltbeschreibbare Workspace-Wurzel und keine Symlinks in Vorfahren.
- Fehlversuche verweigern die Freigabe (`PGP05_ASSET_CONTRACT=REFUSED`, exit 1); nur bei erfolgreicher lokaler Synthetikpruefung `PGP05_ASSET_CONTRACT_SYNTHETIC_ONLY=PASS`.
- Kein eingebundener CircleCI-Job und kein automatischer Runner. **Kein Produktionsmodus**; die zusaetzliche produktive Quellidentitaets-, Signatur-, Quiescence-, GnuPG-Packet- und Real-Restore-Verifikation ist spaeter getrennt zu entwerfen und abzunehmen.

## Tests (lokal, keine CircleCI-Credits)
```bash
python3 -m unittest discover -v -s scripts/ci -p 'test_orgavela_pgp05_asset_gate.py'
python3 scripts/ci/orgavela_pgp05_asset_gate.py --workspace /ABSOLUTER/SYNTHETISCHER/CIPHERTEXT/WORKSPACE
```

**Tatsaechlich am 21.10.2026 lokal getestet:** Python **3.13.5**, **20/20 reine Contract-Unit-Tests PASS**; SHA-1 Git-Blob-Identitaet zwischen lokalem geprueften Source und GitHub-Uploads: `56dc9960dfdb946e7286d9a9044946e6cd3545e5` (Modul), `fff7ca70634955f0a063a6e1fcea8ca68ffcd600` (Tests). Ein Positivfall mit **fiktiven Bytes** und 19 Negativfaelle (Production Flag, synthetisch=false, korrupte Checksums, Sidecar-/Namensfehler, fehlende/zusaetzliche Dateien inkl. Klartext, Symlink, Hardlink, falsche Keys/Schema/Typen, doppelte JSON Keys, unsichere Pfade/Rechte). Fake-Bytes pruefen **nicht** OpenPGP-Paketvaliditaet – dies ist eine separate GnuPG-/PGP-04-Pruefgrenze.

**Sicherheitsstatus:** Nur statische lokale/offline Checks; kein Code in main oder produktivem CI, keine Credentials, keine Test- oder Echtdaten in GitHub Releases. PGP-04 synthetisch E2E bereits PASS; PGP-05 echtes IONOS-Capture / verschluesselter Realdaten-Writer / isolierter Live-Backup-Restore / Scheduler weiter **BLOCKED**. Formale V1-Werte: historische Coding-Pakete 62/71, M 0/8, P4 0/4, Release-Gates 0/18. Keine PR-Merges.

## Ergänzender echter Public-Key-/GnuPG-Integrationscheck

Neuer reproduzierbarer Offline-Test `scripts/ci/test_orgavela_pgp05_real_gpg_fixture.py` verwendet ausschließlich das bereits veröffentlichte OrgaVela-`PUBLIC`-Key-ASC aus `.github/backup/orgavela-backup-candidate-public.asc`. Er importiert es in ein isoliertes temporäres `GNUPGHOME`, bestätigt den Primary-Fingerprint und die Abwesenheit von Private Keys, verschlüsselt fiktive Bytes mit GnuPG, prüft `pubkey enc`- und `encrypted data`-Pakete inklusive freigegebener Encryption-Subkey-ID `D9E58CC6484AF1DE`, entfernt die künstlichen Klartextbytes vor dem Workspace-Check und prüft das Ergebnis inklusive manifest-/sidecar-/Datei-SHA256 mit dem neuen Contract-Gate. Am Testende wird der temporäre Arbeitsbereich gelöscht. **Kein Entschlüsseln, keine Keys angefordert, keine Netzwerk- oder GitHub-Uploads.**

```bash
python3 -m unittest discover -v -s scripts/ci -p 'test_orgavela_pgp05_*.py'
```

**Lokal auf Python 3.13.5 / GnuPG 2.4.7: 21/21 PASS**, davon 20 synthetische Metadaten-Vertragstests und ein echter Public-Key-only GnuPG-Verschlüsselungstest. Git-Blob des zusätzlichen Integrationstests: `081bba2b48f4663c98c10d5f5ca1989fb93f996c`. `gpg --list-packets` kann bei **abwesendem Private Key** für die bloße Packet-Inspektion non-zero zurückgeben; der Test prüft deshalb die Paketsignaturen in stdout, ohne eine erfolgreiche Entschlüsselung zu behaupten. Dieser Test ist **nicht** gleichzusetzen mit produktivem PGP-05-Source-/Writer-Lauf oder einem Besitzer-Restore.
