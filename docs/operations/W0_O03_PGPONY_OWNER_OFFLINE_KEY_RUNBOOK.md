**AKTUELLER BELEGSTAND (20.10.2026):** Der Betreiber bestätigt inzwischen zwei getrennt verwahrte PGPony/OpenPGP-Papier-Wiederherstellungskopien, erfolgreiche Wiederherstellung aus beiden und einen offline sowie auf einem zweiten Gerät wiederbeschaffbaren Passwortschutz. Der öffentliche Fingerprint und der PGPony-GnuPG-iPhone-/iPad-Test sind ebenfalls bestätigt; no-secret CircleCI-QA bestand. **GH03 Owner-Key-Recovery: aufgrund dieser Betreiber-Nachweise als erfüllt eingetragen.** Der frühere Status „Instructions only / no key generated“ im historischen Anleitungstext weiter unten ist überholt. Keine Geheimnisse im GitHub gespeichert. **Keine** daraus abgeleitete Freigabe realer OrgaVela-Backups oder V1-Meilensteinabnahme.

# GH-03 – PGPony/OpenPGP: owner-only setup and independent recovery

**Status: instructions only. No new PGPony key, recovery copy, or GnuPG interoperability has been verified.** The owner selected PGPony/OpenPGP for future OrgaVela backups. Existing AgePony private identity and paper backup in the safe remain historical; they cannot decrypt new OpenPGP ciphertext.

## Owner procedure (iPhone, private material stays entirely private)

1. In PGPony use Keyring > + > Generate Key Pair to create a completely new **OrgaVela-only** OpenPGP identity; never import the Dreamloud key. Prefer a classic **OpenPGP v4** encoding with encryption-capable subkey for broad GnuPG compatibility, rather than v6/PQ before actual interop testing. The official PGPony default is Ed25519 plus Curve25519. RSA 4096 is an optional older-client alternative. Check what your installed version actually supports.
2. Protect the private key with a strong passphrase, recoverable in an emergency and stored separately. No key or passphrase should ever appear in chat, screenshots, GitHub/CircleCI/WordPress/IONOS, unencrypted cloud folders or email. Publishing to keyservers and enabling iCloud Keychain syncing are unnecessary for OrgaVela backups.
3. In PGPony key details record the full primary fingerprint, algorithm, version and encryption subkey. Export the PUBLIC key for eventual GnuPG compatibility testing. Sharing a PUBLIC key is technically permitted after inspecting its user IDs for unnecessary identifying details.
4. Use Keyring > own key > Export Private Key (the actual PGPony label may differ). The official PGPony guide calls for ASCII-armored **secret** .asc export; public-only export is **not** a recovery backup. Save and transfer this only via a controlled **offline** route, without uploading it to the assistant or cloud.
5. Protect **two independent offline private-key copies** at distinct safe locations; if paper is used, demonstrate that it really reconstructs the complete PGP key, and protect the passphrase separately. The old AgePony printout does not count as a PGP-key copy.
6. On a separate trusted **offline** device or isolated local test environment without the original PGPony vault, import a copy recovered from the offline medium. Compare the **full** primary fingerprint, decrypt a synthetic age-independent OpenPGP test text encrypted using the public key, and check SHA-256 equality. Never factory-reset or delete the original PGPony vault as a test.
7. Plan for loss, passphrase recovery, expiry and key rotation; keep old key versions to decrypt historic releases. Report only boolean success facts and the verified **public** fingerprint in the public project documentation, no private text/QR/screenshots/paths.

## Separate GnuPG compatibility gate

Use a fresh isolated GNUPGHOME, import a reviewed public .asc file only, ensure no secret keys are imported, verify the exact primary fingerprint and a usable encryption subkey, encrypt a synthetic archive, then decrypt via an independently restored owner key on a second device. PGPony OpenPGP v4/v6 and algorithm interoperability must be measured with the installed GnuPG version; a temporary GnuPG test key on its own does not prove that the owner's PGPony key works.

**No production source capture, SSH/SFTP, tokens, GitHub releases, automatic schedule, WordPress migration or personal data are permitted by this guide.**

References: https://pgpony.app/guides/generate-pgp-key-iphone.php ; https://pgpony.app/guides/backup-pgp-private-key.php ; https://pgpony.app/support.php .