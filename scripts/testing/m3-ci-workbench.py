#!/usr/bin/env python3
"""Run the exact reviewed M3 artifact in a disposable, local Docker WordPress."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import secrets
import subprocess
import time
import tarfile
import zipfile

PACKAGE_SHA = '4e92063a3983725577856b63f87a32da6d826c61e89dc95f9ecce9915061a670'
ASSET_SHA = '868d2ad9eda83b2bd99851bbfe3e8087f95dc075beae4a241003b231ab0969fe'
PREFIX = 'orgavela-m3-4fb50cb'
WP_IMAGE = 'wordpress@sha256:a4fff017eb08c03187e90fe52d4bff8d3a4d4a5103de05829a6e747c4ca3056e'
DB_IMAGE = 'mariadb@sha256:d769e37960821dfdcf096954f6e7ac89f747e116c1bba98e48edd265dfc7deae'
CORE_SHA = '5b36390233e32fef68cb5f66435bb32bdd50e0b3dfa5750aceb2de3c5993d720'


def docker(*args, capture=False):
    # Always address this environment's local daemon; never an inherited remote context.
    result = subprocess.run(['docker', '--host=unix:///var/run/docker.sock', *args], text=True, capture_output=capture)
    if result.returncode:
        raise SystemExit('Docker operation failed: ' + args[0] + '; inspect the local containers (credentials are not printed).')
    return result.stdout


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('operation', choices=['start', 'stop', 'verify'])
    parser.add_argument('--package', type=Path, default=Path('uop-core.zip'))
    parser.add_argument('--wordpress', type=Path, default=Path('wordpress-6.9.tar.gz'))
    parser.add_argument('--state', type=Path, default=Path('.orgavela-m3-workbench'))
    args = parser.parse_args()
    state = args.state.resolve()
    if args.operation == 'stop':
        docker('stop', PREFIX + '-web', PREFIX + '-db')
        return  # Preserve volumes and synthetic work; no destructive reset command.
    if args.operation == 'verify':
        package = args.package.resolve()
        if hashlib.sha256(package.read_bytes()).hexdigest() != PACKAGE_SHA:
            raise SystemExit('Wrong CI ZIP.')
        with zipfile.ZipFile(package) as archive:
            manifest = ''.join(hashlib.sha256(archive.read(name)).hexdigest() + '  /var/www/html/wp-content/plugins/' + name + '\n'
                               for name in archive.namelist() if not name.endswith('/'))
        result = subprocess.run(['docker', '--host=unix:///var/run/docker.sock', 'exec', '-i', PREFIX + '-web',
                                 'sha256sum', '-c', '-'], input=manifest, text=True, capture_output=True)
        if result.returncode:
            raise SystemExit('Installed plugin differs from CI ZIP: ' + result.stdout + result.stderr)
        print('All ' + str(len(manifest.splitlines())) + ' installed file hashes match the reviewed CI ZIP.')
        return
    package = args.package.resolve()
    if hashlib.sha256(package.read_bytes()).hexdigest() != PACKAGE_SHA:
        raise SystemExit('Wrong CI ZIP: SHA-256 mismatch; nothing started.')
    with zipfile.ZipFile(package) as archive:
        if hashlib.sha256(archive.read('uop-core/assets/m3-form-builder.js')).hexdigest() != ASSET_SHA:
            raise SystemExit('Wrong editor asset.')
    if hashlib.sha256(args.wordpress.read_bytes()).hexdigest() != CORE_SHA:
        raise SystemExit('Unexpected WordPress core archive; nothing started.')
    state.mkdir(mode=0o700, parents=True, exist_ok=True)
    state.chmod(0o700)
    readable_package = state / 'reviewed-uop-core.zip'
    readable_package.write_bytes(package.read_bytes())
    readable_package.chmod(0o644)  # Public plugin bytes; state directory stays private.
    credential_file = state / 'credentials.json'
    if credential_file.exists():
        config = json.loads(credential_file.read_text())
    else:
        config = {'db_password': secrets.token_urlsafe(32), 'admin_password': secrets.token_urlsafe(32), 'editor_password': secrets.token_urlsafe(32)}
        fd = os.open(credential_file, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
        with os.fdopen(fd, 'w') as handle:
            json.dump(config, handle)
    if 'editor_password' not in config:
        config['editor_password'] = secrets.token_urlsafe(32)
        credential_file.write_text(json.dumps(config))
    # Fixed names fail closed if another workbench already exists. Resume with docker start.
    names = docker('ps', '-a', '--format', '{{.Names}}', capture=True).splitlines()
    if PREFIX + '-web' in names or PREFIX + '-db' in names:
        if PREFIX + '-web' not in names or PREFIX + '-db' not in names:
            raise SystemExit('Partial workbench exists; inspect before resuming.')
        docker('start', PREFIX + '-db', PREFIX + '-web')
        installed = docker('exec', PREFIX + '-web', 'sha256sum', '/var/www/html/wp-content/plugins/uop-core/assets/m3-form-builder.js', capture=True)
        if installed.split()[0] != ASSET_SHA:
            raise SystemExit('Existing workbench is incomplete or changed; inspect before use.')
        print('Resumed at http://127.0.0.1:8762/wp-admin/; run verify before testing.')
        return
    docker('network', 'create', '--internal', PREFIX)
    docker('network', 'create', PREFIX + '-webnet')
    docker('volume', 'create', PREFIX + '-db')
    docker('volume', 'create', PREFIX + '-wp')
    envfile = state / 'database.env'
    envfile.write_text('MARIADB_DATABASE=orgavela_synthetic\nMARIADB_USER=orgavela\nMARIADB_PASSWORD=' + config['db_password'] + '\nMARIADB_RANDOM_ROOT_PASSWORD=1\n')
    envfile.chmod(0o600)
    docker('run', '-d', '--name', PREFIX + '-db', '--network', PREFIX,
           '--env-file', str(envfile), '-v', PREFIX + '-db:/var/lib/mysql', DB_IMAGE)
    core = state / 'core'
    core.mkdir(exist_ok=True)
    with tarfile.open(args.wordpress.resolve()) as archive:
        for member in archive.getmembers():
            if (member.name != 'wordpress' and not member.name.startswith('wordpress/')) or member.issym() or member.islnk() or '..' in Path(member.name).parts:
                raise SystemExit('Unexpected WordPress archive entry.')
        archive.extractall(core, filter='data')
    docker('run', '--rm', '--user', '0:0', '--entrypoint', 'sh',
           '-v', PREFIX + '-wp:/var/www/html', '-v', str(core) + ':/seed:ro',
           WP_IMAGE, '-c', 'cp -a /seed/wordpress/. /var/www/html/ && chown -R 33:33 /var/www/html')
    docker('run', '-d', '--name', PREFIX + '-web', '--network', PREFIX + '-webnet',
           '-p', '127.0.0.1:8762:8080', '--user', '33:33', '--entrypoint', 'php',
           '-e', 'PHP_CLI_SERVER_WORKERS=4', '-v', PREFIX + '-wp:/var/www/html',
           WP_IMAGE, '-S', '0.0.0.0:8080', '-t', '/var/www/html')
    docker('network', 'connect', PREFIX, PREFIX + '-web')
    cli_args = ['run', '--rm', '--network', PREFIX, '--user', '33:33',
                '-v', PREFIX + '-wp:/var/www/html',
                '-v', str(readable_package) + ':/artifact/uop-core.zip:ro', '--entrypoint', 'wp', WP_IMAGE]
    for _ in range(60):
        probe = subprocess.run(['docker', '--host=unix:///var/run/docker.sock', 'exec', PREFIX + '-db',
                                'healthcheck.sh', '--connect', '--innodb_initialized'], capture_output=True)
        if probe.returncode == 0:
            break
        time.sleep(1)
    else:
        raise SystemExit('Database did not become ready; inspect the local containers.')
    docker(*cli_args, 'config', 'create', '--dbname=orgavela_synthetic', '--dbuser=orgavela',
           '--dbpass=' + config['db_password'], '--dbhost=' + PREFIX + '-db', '--dbcharset=utf8mb4')
    docker(*cli_args, 'config', 'set', 'DISABLE_WP_CRON', 'true', '--raw')
    docker(*cli_args, 'config', 'set', 'WP_HTTP_BLOCK_EXTERNAL', 'true', '--raw')
    docker(*cli_args, 'core', 'install', '--url=http://127.0.0.1:8762', '--title=OrgaVela M3 synthetic acceptance',
           '--admin_user=orgavela_m3_test', '--admin_password=' + config['admin_password'],
           '--admin_email=orgavela-test@example.invalid', '--skip-email')
    docker(*cli_args, 'user', 'create', 'orgavela_m3_editor', 'editor-test@example.invalid',
           '--role=administrator', '--user_pass=' + config['editor_password'])
    docker(*cli_args, 'option', 'update', 'blog_public', '0')
    # A test-only MU plugin blocks mail even if runtime egress changes later.
    docker('exec', PREFIX + '-web', 'mkdir', '-p', '/var/www/html/wp-content/mu-plugins')
    subprocess.run(['docker', '--host=unix:///var/run/docker.sock', 'exec', '-i', PREFIX + '-web',
                    'sh', '-c', 'cat > /var/www/html/wp-content/mu-plugins/orgavela-synthetic-only.php'],
                   input="<?php\nadd_filter('pre_wp_mail', '__return_false');\n", text=True, check=True)
    docker(*cli_args, 'plugin', 'install', '/artifact/uop-core.zip', '--activate')
    docker(*cli_args, 'core', 'version')
    print('Ready: http://127.0.0.1:8762/wp-admin/ (local loopback only)')
    print('User: orgavela_m3_test; private password file: ' + str(credential_file))
    print('No Internet-facing URL or public credentials created. Run verify before acceptance.')


if __name__ == '__main__':
    main()
