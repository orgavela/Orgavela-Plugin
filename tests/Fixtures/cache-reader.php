<?php
if (PHP_SAPI!=='cli' || getenv('UOP_TEST_ALLOW_DATABASE')!=='1') exit(1);
require dirname(__DIR__).'/integration-bootstrap.php';
require __DIR__.'/PersistentOptionCache.php';
$GLOBALS['wp_object_cache']=new PersistentOptionCache($argv[1]);
wp_using_ext_object_cache(true);
\UOP\Infrastructure\Database\MigrationState::register_option_reads();
$old=get_option('uop_db_version');
wp_cache_set('uop_db_version',$old,'options');
echo json_encode(['old'=>$old])."\n"; fflush(STDOUT);
if (trim((string)fgets(STDIN))!=='continue') exit(2);
// Simulate a reader that started before commit and publishes its old result late.
wp_cache_set('uop_db_version',$old,'options');
echo json_encode(['current'=>get_option('uop_db_version')])."\n";
