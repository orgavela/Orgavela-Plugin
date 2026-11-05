# Development

The source priority is Phase 3, Phase 2, Phase 1, including the owner's table-count correction: 28 tables. Implement M0 before M1; do not implement M2 or product workflows.

## Requirements and commands

PHP 8.3+, Composer 2, Node 22+, npm, MySQL 8.0 or MariaDB 10.11. WordPress requires InnoDB and utf8mb4.

```sh
composer install
composer validate --strict
composer audit
composer lint
composer analyse
composer test
npm ci
npm audit --audit-level=high
npm run lint
npm run build
```

Integration tests require an installed disposable WordPress, with this checkout at wp-content/plugins/uop-core. Set WP_ROOT to its absolute path and UOP_TEST_ALLOW_DATABASE=1, then run composer test:integration. Never run integration tests against production data.

The CI workflow creates independent disposable databases for PHP 8.3/8.4/8.5 on MySQL 8.0 and MariaDB 10.11. PHP 8.3 exercises WordPress 6.9; PHP 8.4/8.5 exercise 7.1.1. Browser smoke tests use a generated test administrator credential supplied via environment.

## Build

In a clean checkout set COMPOSER_ROOT_VERSION to the version in package.json (currently 0.1.0-alpha.2), then run npm ci, npm run build, composer install --no-dev --optimize-autoloader, and php bin/package.php. Pinning the root version keeps Composer metadata independent of the checkout branch or source archive. The archive uses sorted paths, stable timestamps and an explicit allowlist; it contains runtime dependencies and source. There are no frontend assets in M0. Install the generated dist/uop-core.zip on a disposable WordPress to smoke-test the actual artifact. Restore development dependencies with composer install before running tests.

## Foundation contracts

- Bootstrap captures environment information read-only; the same gate guards web and CLI activation and normal boot.
- Missing dependencies fail activation; incompatible environments never construct the kernel.
- Services are explicit, lazy, singleton factories. Cycles and duplicate registration fail.
- Deactivation preserves data; no automatic uninstall erasure exists.
- Classes are inert when directly loaded. The plugin entrypoint blocks direct HTTP execution.
- Action Scheduler is locked and bundled, but no queue consumer or feature is enabled in M0.
- WordPress Coding Standards are blocking, with only the filename convention adjusted for the mandated PSR-4 structure. No static-analysis baseline hides errors.

M1 may begin only after M0 activation, incompatible-environment, build, quality and matrix tests pass.

## Persistence contracts (M1)

M0 passed on commit 196d0998d8ea6a2768b145516d60308a862bbc36 before M1 work began; see verification-m0.md.

The runtime schema is schema/manifest.json. It contains all 26 Phase 2 tables plus email_templates and export_jobs, including template_revision, template_hash and locale in email_messages. Run node bin/schema-manifest.mjs --check to detect divergence from the authoritative documents. Integer display widths are normalized between MySQL and MariaDB; types, column order, nullability, defaults, auto-increment, full index columns, uniqueness, InnoDB, collation and absent foreign keys are verified against the real database.

Activation and normal upgrade boot share the environment gate. WordPress options must also use InnoDB because checkpoints and the organization seed share a transaction. No unsupported-environment path writes UOP state. The explicit Installer composition root wires persistence services; no business feature is wired.

The migration runner uses a connection-owned, database/site-specific advisory lock. It records each verified DDL checkpoint in WordPress options, re-verifies completed checkpoints on resume, and promotes uop_db_version/uop_data_version only after verification. DDL is additive and resumable, not represented as rollback-capable. Transactional DML steps include their checkpoint in the same transaction. There is no destructive down-migration. A failed attempt retains progress and records safe exception type/code in Site Health; SQL, values and traces are never included.

After resolving the cause, an administrator can retry by activating the plugin or running the following command on the intended site:

```sh
wp eval '\UOP\Infrastructure\Database\Installer::runner()->run();'
```

The transaction manager forbids nested calls, performs at most three complete retries after errno 1213, discards callbacks from failed attempts, and executes successful callbacks only after commit. Other errors are not retried. The wpdb adapter pins the connection identity and disables automatic reconnection during its queries so a lost transaction or advisory lock cannot silently become autocommit work.

Repository queries require a non-null OrgScope, validated PublicId, explicit columns, allowed filters and bounded keyset pagination (default 50, maximum 100). The base query currently supports indexed ID ordering in either direction. Internal rows/cursors stay in persistence/mapping; they are not public DTOs. OrgScope is the storage boundary; actor authorization and field projection remain M2 work.

Tests use disposable fixtures only. They cover initial installation, exact physical schema, repeat with unchanged DDL/seed, interruption after DDL before checkpoint, resume, competing connections and lock recovery, transactional-step rollback, connection loss, cross-organization queries and intentional schema drift. No person, event, form, registration, email, consent, export or other product service is implemented.
