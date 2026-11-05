<?php
// Runs through WP CLI on the CI-created disposable multisite; no network product features.
if (!defined('WP_CLI') || !WP_CLI || getenv('UOP_TEST_ALLOW_DATABASE')!=='1' || !is_multisite()) throw new RuntimeException('Disposable multisite required');
require_once dirname(__DIR__).'/vendor/autoload.php';
use UOP\Core\{Bootstrap,TransactionManager};
use UOP\Infrastructure\Database\{Installer,MigrationState,WpdbConnection};
function uop_assert(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); }
$home=get_current_blog_id();
$site=wp_insert_site(['domain'=>get_network()->domain,'path'=>'/uop-regression/','public'=>1]);
if (is_wp_error($site)) throw new RuntimeException($site->get_error_message());
$lock=Installer::lock_name();
$homeSeed=get_option('uop_default_organization_id');
switch_to_blog($site);
try {
    global $wpdb;
    uop_assert($wpdb->prefix!==$wpdb->base_prefix,'Subsite prefix was not selected');
    uop_assert(Installer::lock_name()!==$lock,'Migration lock must be site specific');
    Bootstrap::deactivate();
    Bootstrap::activate();
    uop_assert(Installer::health()['status']==='good','Subsite schema verification failed');
    $seed=get_option('uop_default_organization_id');
    Bootstrap::deactivate(); Bootstrap::activate();
    uop_assert($seed===get_option('uop_default_organization_id'),'Reactivation duplicated seed');
    $db=new WpdbConnection($wpdb); $state=new MigrationState($db,$wpdb->options);
    $tx=new TransactionManager($db,static function(){},static function(){});
    wp_cache_set('uop_db_version','stale','options');
    $tx->run(function()use($state,$home){$state->write('uop_db_version',1); switch_to_blog($home);});
    uop_assert(get_current_blog_id()===$home,'Cache cleanup changed caller site');
    restore_current_blog();
    wp_cache_get('uop_db_version','options',false,$found);
    uop_assert(!$found,'Commit did not invalidate owning subsite cache');
    uop_assert(get_option('uop_db_version')==='1','Subsite version mismatch');
} finally { restore_current_blog(); Bootstrap::deactivate(); }
uop_assert(get_current_blog_id()===$home,'Main site was not restored');
uop_assert($homeSeed===get_option('uop_default_organization_id'),'Main site seed changed');
uop_assert(Installer::health()['status']==='good','Main site schema changed');
echo "PASS: multisite subsite prefix, independent locks/schema/state, reactivation and site-scoped cache cleanup.\n";
