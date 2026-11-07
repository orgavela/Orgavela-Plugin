# W0/O03 – OrgaVela OpenPGP/PGPony migration ADR (2026-10-20)

**Decision: APPROVED for future OrgaVela backup design, NOT live deployment.** The owner explicitly chose PGPony/OpenPGP instead of AgePony/age and requested a read-only comparison with the independently operated Dreamloud backup system. This ADR supersedes the **future encryption-format choice** in [GH03 age ADR](W0_O03_GH03_ENCRYPTION_RECOVERY_ADR.md) without rewriting that historical document. The master V1 acceptance plan and \`V1_PROGRESS.json\` remain canonical for acceptance and counts.

## Architecture decision / Dreamloud factual baseline

Read-only reference: private GitHub repo \`janjrre/dreamloud-portal\`, branch \`backup/production-circleci\`; no Dreamloud modifications or credentials reused.

- \`scripts/pack-encrypted-backup.sh\`: isolated GNUPGHOME, exact public OpenPGP **primary fingerprint** validation, deny accidental secret-key import; internal manifest with original compressed MariaDB/WordPress hashes encrypted with the archive; public sanitized manifest, encrypted \`.tar.gpg\` and \`.sha256\` sidecar.
- \`.circleci/config.yml\`: bounded source capture and ciphertext-only writer use **different restricted contexts**; manual opt-in / protected branch for production backups. Operational job IDs and security scopes are Dreamloud-specific, not OrgaVela tests.
- \`scripts/verify-offsite-backup.sh\`, \`scripts/run-isolated-full-restore.sh\`: verify ciphertext, OpenPGP recipient match and archive integrity; isolated restore checks the database and WordPress behavior in its own test environment.
- \`.github/backup/README.md\`: production and historical recipient fingerprints must be versioned and never conflated; historic ciphertext can remain unreadable after rotation if the corresponding old private key is lost. Source/writer contexts never get the permanent owner private key.

## Binding OrgaVela decision

1. **PGPony handles owner-controlled OpenPGP private recovery material; headless CI uses GnuPG and a verified exported PUBLIC key only.** PGPony iOS secret-key export/import format and exact GnuPG algorithm support require a real owner-only *synthetic* cross-tool interoperability test. Do not assume every OpenPGP v6/PQ mode works with installed CI GnuPG. Choose a widely compatible key plus encryption-capable subkey only after verifying support.
2. Separate keypair and policy for **OrgaVela only**. Never reuse Dreamloud fingerprint, key, identity, public policy, contexts, backup repo or tokens. Never import the owner's personal secret key to the GitHub source repo, CircleCI source/writer contexts, IONOS, WordPress or ChatGPT.
3. Proposed production backup file trio: \`orgavela-*.tar.gpg\`, \`orgavela-*.tar.gpg.sha256\`, \`manifest.json\` (public **sanitized**). An **encrypted internal manifest** shall identify source component digests, capture consistency, product/schema version and key fingerprint. The publicly visible manifest cannot include DB credentials, configuration, private filenames or row content.
4. Future two-job design: OrgaVela source reads only its own IONOS WordPress/MariaDB instance via scoped credentials, securely produces a consistent dump and filesystem snapshot, validates them, and encrypts **before** handing a ciphertext-only workspace to the private GitHub Release writer. Writer accesses **only** \`janjrre/orgavela-backups\` via a separate restricted short-lived token/context. No plaintext in CI persisted artifacts or public logs. A single release must be \`immutable=true\` with binary SHA-256 readback before and after publication.
5. Recovery uses **separate** restricted/manual process and independent owner-held PGPony exported private key. Verify fingerprint matches archived public policy, decrypted hashes, database import, media/config, isolated WordPress boot and core critical OrgaVela tables/portal without contacting production mail/cron/external systems. Restore test and staging access must be validated for the exact release source.
6. No automatic cron, real backup capture, owner-key upload, token creation, PR merge or production modification as part of this ADR. Prior synthetic GH-04 GPG and GH-06 age test **remain true historical results**; age results do not prove new owner-PGPony-key restoration or OpenPGP production readiness.
7. Preserve existing AgePony identity, original PDF and one physical printed backup in the safe until a **new PGPony backup format** has passed all recovery and rotation gates. Old \`.tar.age\` synthetic releases are retained as labeled historical fixtures, not silently converted to GPG.

## GH03 owner-key prerequisites

- Operator creates new **exportable** PGPony OpenPGP identity in an owner-controlled safe environment and selects a GnuPG-compatible encryption algorithm/subkey. Only **public** .asc key and its independently confirmed full primary fingerprint may later enter OrgaVela repo after review.
- Two protected, independent offline physical copies of owner-exported PRIVATE recovery key. Verify import without original app/phone, same full fingerprint, encrypt a **synthetic** fixture using PUBLIC key and decrypt using an independently restored private copy; compare SHA-256. Passphrase recovery separate and available after phone loss. Never send secret key, passphrase, private QR or paper-backup photos to chat.
- Rehearse key loss/rotation; keep historic keys for their corresponding encrypted releases. No assumption that current AgePony paper backup can decrypt PGPony/OpenPGP ciphertext.

## Staged implementation and gates (do not inflate 71 M-package counts)

- **PGP-01:** PGPony/GnuPG interoperability and owner copy-2 restore demonstrated **privately**; GH03 new OpenPGP owner acceptance only then.
- **PGP-02:** Synthetic-only offline OpenPGP package+manifest, wrong-fingerprint/secret-input refusal and separate decrypt proof; no cloud credentials.
- **PGP-03:** Integration of source/writer authorization boundaries in isolated default-OFF branch workflow; unit and security CI at exact SHA.
- **PGP-04:** One explicitly authorized **synthetic** private immutable GitHub Release transport plus full remote readbacks and independent GPG decrypt; revoke temporary PAT after.
- **GH05-08 productive:** real consistent source capture, encrypted immutable release, independent full IONOS/WordPress restore, then operational scheduling and monitoring. These remain BLOCKED until GH03/PGP gates and all applicable V1 R gates.
- **Rollback:** no format flipping for historical releases. If migration test fails, keep age reference and historical evidence intact, do not start real backups, revise ADR before another key type.

**Evidence level today:** Dreamloud **source code** reviewed; GH-04 previously passed GPG with disposable test key, but no owner PGPony key has been created/tested. GH-03 OpenPGP acceptance: 0 confirmed physical copies, 0 owner independent restore. V1 Coding 62/71 historical, formal M0–M7 0/8, P4 0/4, R01–R18 0/18.

## Addendum 20.10.2026 – PGPony public key candidate independently parsed

The owner supplied an **OpenPGP PUBLIC KEY BLOCK only** for UID `OrgaVela Backup 2026`, without an email UID. GnuPG 2.4.7 in a fresh isolated public-only GNUPGHOME imported it as **OpenPGP v4**, main signing/certification key `Ed25519` full FPR `7C9DC60D8E1B293E514696EB333D15A4D8BA625C`, encryption subkey `cv25519` full FPR `CF5D8A8498F65C6D894ECE2FD9E58CC6484AF1DE`. Both self-signatures validated; synthetic encrypt with the public key succeeded, and without any private key a decrypt was correctly unavailable.

**Candidate artifacts (NOT production authorization):** `.github/backup/orgavela-backup-candidate-public.asc` and `.github/backup/orgavela-pgpony-candidate-policy.json`; source repository is public, so the public key and its non-personal project UID are publicly visible in the Draft PR branch. A 288-byte synthetic GnuPG-encrypted test file with code `OV-PGP-2026-10-20-7C9DC60D` was given to the operator for decrypt **inside PGPony on their own device**. Until the owner confirms successful decrypt and independently recovers the PGPony private key from a second separated offline copy, **GH03 remains OPEN**, candidate is never enabled for real backups and GH05–08 remain BLOCKED. The previous "no owner PGPony key created" statement above is the earlier snapshot before the operator confirmed creation and supplied this public key.
