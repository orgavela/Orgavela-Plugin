"""Restore validated components into new private local Docker volumes only.

No source connection, published port or operator recovery key. Original config,
MU plugins and active plugin options are not executed in the recovery clone.
All resources are invocation-owned and removed on completion or failure.
"""
import gzip
import hashlib
from pathlib import Path
import re
import secrets
import subprocess
import tarfile
import tempfile
import time

WP_IMAGE = 'wordpress@sha256:a4fff017eb08c03187e90fe52d4bff8d3a4d4a5103de05829a6e747c4ca3056e'
DB_IMAGE = 'mariadb@sha256:d769e37960821dfdcf096954f6e7ac89f747e116c1bba98e48edd265dfc7deae'


def docker(*args, data=None):
    result = subprocess.run(['docker', '--host=unix:///var/run/docker.sock', *args],
                            input=data, capture_output=True, timeout=180)
    if result.returncode:
        raise RuntimeError('isolated Docker operation failed; private output suppressed')
    return result.stdout


def inspect_snapshot_files(path):
    """Hash every recovered regular file without loading PHP or extracting it."""
    inventory = hashlib.sha256()
    files = 0
    with tarfile.open(path, 'r:gz') as archive:
        for member in archive:
            if member.isdir():
                continue
            if not member.isreg():
                raise ValueError('recovered file type refused')
            digest = hashlib.sha256()
            with archive.extractfile(member) as content:
                while block := content.read(65536):
                    digest.update(block)
            inventory.update(member.name.encode('utf-8') + b'\0' + digest.digest())
            files += 1
    return {'files': files, 'inventory_sha256': inventory.hexdigest()}


def restore_components(components, *, expected_home):
    if expected_home not in ('https://orgavela.com', 'http://127.0.0.1:8762'):
        raise ValueError('unexpected source identity')
    components = Path(components)
    # The independent package validator must have completed before this call.
    from collector import validate_components
    validate_components(components)
    sql = gzip.decompress((components / 'database.sql.gz').read_bytes())
    # Snapshot files are inspected as data only, never mounted into executable WordPress.
    inspected = inspect_snapshot_files(components / 'wordpress-files.tar.gz')
    from trusted_core import download_core, CORES
    core_version = '7.1.4' if expected_home == 'https://orgavela.com' else '6.9'
    core = download_core(core_version)
    with tarfile.open(components / 'wordpress-files.tar.gz') as archive:
        config = next((m for m in archive if str(Path(m.name)) == 'wp-config.php'), None)
        if config is None or config.size > 1024*1024:
            raise ValueError('invalid recovery configuration')
        text = archive.extractfile(config).read().decode('utf-8')
    matches = re.findall(r"^\s*\$table_prefix\s*=\s*(['\"])([A-Za-z0-9_]{1,64})\1\s*;", text, re.MULTILINE)
    if len(matches) != 1:
        raise ValueError('literal unique table prefix required')
    table_prefix = matches[0][1]
    del text
    prefix = 'orgavela-recovery-' + secrets.token_hex(6)
    created = []
    with tempfile.TemporaryDirectory(prefix='orgavela-isolated-recovery-') as folder:
        environment = Path(folder) / 'database.env'
        password = secrets.token_urlsafe(32)
        environment.write_text('MARIADB_DATABASE=restore_private\nMARIADB_USER=restore\nMARIADB_PASSWORD=' + password + '\nMARIADB_RANDOM_ROOT_PASSWORD=1\n')
        environment.chmod(0o600)
        try:
            docker('network', 'create', '--internal', prefix)
            created.append(('network', prefix))
            for suffix in ('-db', '-wp'):
                docker('volume', 'create', prefix + suffix)
                created.append(('volume', prefix + suffix))
            docker('run', '-d', '--name', prefix + '-db', '--network', prefix,
                   '--env-file', str(environment), '-v', prefix + '-db:/var/lib/mysql', DB_IMAGE)
            created.append(('container', prefix + '-db'))
            for _ in range(60):
                try:
                    docker('exec', prefix + '-db', 'healthcheck.sh', '--connect', '--innodb_initialized')
                    break
                except RuntimeError:
                    time.sleep(1)
            else:
                raise RuntimeError('isolated recovery database timeout')
            docker('exec', '-i', prefix + '-db', 'sh', '-c',
                   'mariadb --binary-mode --user=restore --password="$MARIADB_PASSWORD" restore_private', data=sql)
            # Assert source identity/counts before modifying clone runtime settings.
            query = ("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE();"
                     f"SELECT option_value FROM `{table_prefix}options` WHERE option_name='home';")
            result = docker('exec', '-i', prefix + '-db', 'sh', '-c',
                            'mariadb --batch --skip-column-names --user=restore --password="$MARIADB_PASSWORD" restore_private', data=query.encode())
            if result.splitlines() != [b'52', expected_home.encode()]:
                raise RuntimeError('restored source identity/table inventory mismatch')
            query = f"UPDATE `{table_prefix}options` SET option_value='a:0:{{}}' WHERE option_name='active_plugins';"
            docker('exec', '-i', prefix + '-db', 'sh', '-c',
                   'mariadb --user=restore --password="$MARIADB_PASSWORD" restore_private', data=query.encode())
            docker('run', '--rm', '-i', '--network', 'none', '--user', '0:0', '--entrypoint', 'sh',
                   '-v', prefix + '-wp:/restore', WP_IMAGE, '-c',
                   'set -eu; tar -xzf - --strip-components=1 -C /restore; '
                   'rm -rf /restore/wp-content; mkdir /restore/wp-content; '
                   'chown -R 33:33 /restore', data=core)
            args = ['run', '--rm', '--network', prefix, '--user', '33:33',
                    '-v', prefix + '-wp:/var/www/html', '--entrypoint', 'wp', WP_IMAGE]
            docker(*args, 'config', 'create', '--dbname=restore_private', '--dbuser=restore',
                   '--dbpass=' + password, '--dbhost=' + prefix + '-db', '--dbcharset=utf8mb4', '--dbprefix=' + table_prefix)
            for name in ('DISABLE_WP_CRON', 'WP_HTTP_BLOCK_EXTERNAL', 'DISALLOW_FILE_MODS'):
                docker(*args, 'config', 'set', name, 'true', '--raw')
            docker(*args, 'core', 'is-installed', '--skip-plugins', '--skip-themes')
            assertion = ("global $wpdb; if ((int)$wpdb->get_var('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')!==52) exit(2); "
                         "foreach(['options','posts','users','usermeta','comments','uop_organizations','uop_persons','uop_consent_records','uop_registrations','uop_waitlist_entries'] as $suffix) "
                         "{if($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($wpdb->prefix.$suffix)))!==$wpdb->prefix.$suffix)exit(3);} "
                         "if(file_exists(ABSPATH.'snapshot-executed') || file_exists(WP_CONTENT_DIR.'/db.php') || file_exists(WP_CONTENT_DIR.'/object-cache.php'))exit(4); "
                         "echo 'ISOLATED_WP_DB_RESTORE=PASS';")
            result = docker(*args, 'eval', assertion, '--skip-plugins', '--skip-themes')
            if b'ISOLATED_WP_DB_RESTORE=PASS' not in result:
                raise RuntimeError('isolated WordPress assertions failed')
        finally:
            failures = []
            for kind, name in reversed(created):
                try:
                    docker('rm', '-f', name) if kind == 'container' else docker(kind, 'rm', name)
                except Exception:
                    failures.append(kind)
            if failures:
                raise RuntimeError('private recovery cleanup incomplete; inspect local Docker resources')
    return {'isolated_wordpress_bootstrap': True, 'restored_tables': 52, 'public_ports': 0,
            'source_accessed': False, 'clone_plugins_disabled': True, 'temporary_target_removed': True,
            'snapshot_files_inspected': inspected['files'], 'snapshot_inventory_sha256': inspected['inventory_sha256'],
            'snapshot_php_executed': False, 'trusted_core_version': core_version, 'trusted_core_sha256': CORES[core_version]}
