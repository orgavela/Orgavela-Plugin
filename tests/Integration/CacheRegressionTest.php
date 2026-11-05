<?php
namespace UOP\Tests\Integration;
use PHPUnit\Framework\TestCase;
use UOP\Core\TransactionManager;
use UOP\Infrastructure\Database\{Installer,MigrationState,WpdbConnection};
final class CacheRegressionTest extends TestCase {
    public function test_commit_and_rollback_with_parallel_request_and_persistent_cache(): void {
        global $wpdb,$wp_object_cache;
        Installer::runner()->run();
        require_once dirname(__DIR__).'/Fixtures/PersistentOptionCache.php';
        $path=tempnam(sys_get_temp_dir(),'uop-cache-');
        $original=$wp_object_cache; $wasExternal=wp_using_ext_object_cache();
        $wp_object_cache=new \PersistentOptionCache($path); wp_using_ext_object_cache(true);
        $db=new WpdbConnection($wpdb); $state=new MigrationState($db,$wpdb->options);
        try {
            foreach ([true,false] as $commit) {
                $state->write('uop_db_version',1);
                wp_cache_set('uop_db_version','1','options');
                $tx=new TransactionManager($db,static function(){},static function(){});
                $child=null; $pipes=[];
                try {
                    $tx->run(function()use($state,$path,$commit,&$child,&$pipes){
                        $state->write('uop_db_version',2);
                        self::assertSame('2',get_option('uop_db_version'));
                        self::assertSame('1',wp_cache_get('uop_db_version','options'), 'Uncommitted value must not be published');
                        $child=proc_open([PHP_BINARY,dirname(__DIR__).'/Fixtures/cache-reader.php',$path],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
                        self::assertIsResource($child);
                        $line=fgets($pipes[1]);
                        self::assertSame(['old'=>'1'],json_decode($line,true),$line ?: 'Reader failed');
                        if (!$commit) throw new \RuntimeException('Rollback fixture');
                    });
                } catch (\RuntimeException $error) { self::assertFalse($commit); self::assertSame('Rollback fixture',$error->getMessage()); }
                try {
                    wp_cache_get('uop_db_version','options',false,$found);
                    self::assertFalse($found,'Completion must invalidate the shared cache');
                    $expected=$commit ? '2' : '1';
                    self::assertSame($expected,get_option('uop_db_version'));
                    fwrite($pipes[0],"continue\n"); fclose($pipes[0]);
                    self::assertSame(['current'=>$expected],json_decode(fgets($pipes[1]),true));
                    self::assertSame($expected,get_option('uop_db_version'),'Late stale cache fills must not affect migration reads');
                    fclose($pipes[1]); $errors=stream_get_contents($pipes[2]); fclose($pipes[2]);
                    self::assertSame(0,proc_close($child),$errors); $child=null;
                } finally { if (is_resource($child)) { proc_terminate($child); proc_close($child); } }
            }
        } finally {
            $state->write('uop_db_version',1);
            $wp_object_cache=$original; wp_using_ext_object_cache($wasExternal); unlink($path);
        }
    }
}
