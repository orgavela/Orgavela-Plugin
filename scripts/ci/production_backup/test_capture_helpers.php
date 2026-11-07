<?php
/** Local fictitious fixtures only: no WordPress bootstrap, DB or network. */
require __DIR__.'/lib/backup_wp_config.php';
require __DIR__.'/lib/backup_capture_safety.php';
function check(bool $ok): void { if (!$ok) throw new RuntimeException('synthetic assertion failed'); }
function refused(callable $call): void { try { $call(); } catch (RuntimeException $e) { return; } throw new LogicException('refusal missing'); }
function remove_fixture(string $path): void {
    if (is_link($path) || is_file($path)) { unlink($path); return; }
    if (is_dir($path)) { foreach (new DirectoryIterator($path) as $entry) if (!$entry->isDot()) remove_fixture($entry->getPathname()); rmdir($path); }
}
$root=sys_get_temp_dir().'/orgavela-port-fixture-'.bin2hex(random_bytes(8));mkdir($root,0700);
try {
    $config="<?php define('DB_NAME','fixture'); define('DB_USER','fixture'); define('DB_PASSWORD','fixture'); define('DB_HOST','localhost'); \$table_prefix='fixture_'; throw new Exception('must never execute');";
    check(orgavela_parse_config($config)['table_prefix']==='fixture_');
    refused(fn()=>orgavela_parse_config($config." define('DB_NAME','other');"));
    refused(fn()=>orgavela_parse_config(str_replace("'DB_NAME','fixture'","'DB_NAME',getenv('PRIVATE')",$config)));
    refused(fn()=>orgavela_parse_config(str_replace("'fixture_';","'bad-prefix';",$config)));
    mkdir($root.'/wp-content/cache/docket-cache',0700,true);
    file_put_contents($root.'/wp-content/cache/docket-cache/fixture.txt','synthetic retained');
    file_put_contents($root.'/wp-config.php','synthetic retained config');
    $before=orgavela_capture_inventory($root);
    check(isset($before['wp-content/cache/docket-cache/fixture.txt']));
    file_put_contents($root.'/wp-config.php','changed synthetic retained config');
    check($before!==orgavela_capture_inventory($root));
    symlink($root.'/wp-config.php',$root.'/link');
    refused(fn()=>orgavela_capture_inventory($root));unlink($root.'/link');
    file_put_contents($root."/bad\nname",'synthetic');
    refused(fn()=>orgavela_capture_inventory($root));unlink($root."/bad\nname");
    $marker=$root.'/.maintenance';file_put_contents($marker,'ours');$hash=hash('sha256','ours');
    check(orgavela_remove_owned_maintenance($marker,$hash));
    file_put_contents($marker,'another-owner');check(!orgavela_remove_owned_maintenance($marker,$hash));
    check(file_get_contents($marker)==='another-owner');
    $args=orgavela_capture_tar_args($root);check(count(array_filter($args,fn($a)=>str_starts_with($a,'--exclude=')))===1);
    $proc=proc_open($args,[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $tar=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    check(proc_close($proc)===0 && $error==='');
    $archive=$root.'.tar.gz';file_put_contents($archive,$tar);
    $listing=shell_exec('tar -tzf '.escapeshellarg($archive));unlink($archive);
    check(str_contains($listing,'./wp-content/cache/docket-cache/fixture.txt') && !str_contains($listing,'./.maintenance'));
    $stable=orgavela_capture_inventory($root);
    orgavela_verify_tar_inventory($tar,$stable);
    // A->B->A during packing: equal before/after inventories do not prove TAR contents.
    file_put_contents($root.'/wp-config.php','transient different bytes');
    $proc=proc_open(orgavela_capture_tar_args($root),[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $transient=stream_get_contents($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);check(proc_close($proc)===0);
    file_put_contents($root.'/wp-config.php','changed synthetic retained config');
    check($stable===orgavela_capture_inventory($root));
    refused(fn()=>orgavela_verify_tar_inventory($transient,$stable));
    refused(fn()=>orgavela_verify_tar_inventory(substr($tar,0,-4),$stable));
    refused(fn()=>orgavela_verify_tar_inventory($tar.'suffix',$stable));
    $missing=$stable;unset($missing['wp-config.php']);refused(fn()=>orgavela_verify_tar_inventory($tar,$missing));
    $names=['options','posts','users','usermeta','comments','uop_organizations','uop_persons','uop_consent_records','uop_registrations','uop_waitlist_entries'];
    for($i=count($names);$i<52;$i++)$names[]='synthetic_'.$i;
    $tables=array_map(fn($t)=>'fixture_'.$t,$names);
    check(orgavela_validate_tables($tables,'fixture_'));
    $bad=$tables;$bad[0]='foreign_options';check(!orgavela_validate_tables($bad,'fixture_'));
    $bad=$tables;$bad[0]='fixture_missing';check(!orgavela_validate_tables($bad,'fixture_'));
    check(!orgavela_validate_tables(array_slice($tables,1),'fixture_'));
    $bad=$tables;$bad[51]=$bad[50];check(!orgavela_validate_tables($bad,'fixture_'));
    check(orgavela_capture_parse_status(['HTTP/2 503'])===503);
    refused(fn()=>orgavela_capture_parse_status(['X-Text: HTTP/2 503']));
    refused(fn()=>orgavela_client_value("bad\0value"));
    check(str_contains(orgavela_client_value("quote\"newline\n"),'\\n'));
    check(strlen(orgavela_tar_header('database.sql.gz',3))===512);
    refused(fn()=>orgavela_tar_header('../escape',3));
    $help='--defaults-file --protocol --ssl --ssl-verify-server-cert --max-statement-time';
    $dump=orgavela_dump_client_args($help,false);
    check(in_array('--ssl',$dump,true)&&in_array('--ssl-verify-server-cert',$dump,true)&&in_array('--protocol=tcp',$dump,true));
    refused(fn()=>orgavela_dump_client_args('--defaults-file --protocol',false));
    orgavela_validate_ustar_paths([str_repeat('a',128).'/',str_repeat('a',150).'/'.str_repeat('b',90)]);
    refused(fn()=>orgavela_validate_ustar_paths([str_repeat('a',101)]));
    refused(fn()=>orgavela_validate_ustar_paths([str_repeat('a',156).'/'.str_repeat('b',100)]));
    $self=orgavela_process_current(getmypid());check($self['pid']===getmypid());
    // A caller's inherited process group must never be signaled.
    refused(fn()=>orgavela_process_members(['pid'=>getmypid(),'group'=>1,'session'=>1,'start'=>$self['start']]));
    $record=$root.'/child-negative.stat';file_put_contents($record,file_get_contents('/proc/'.getmypid().'/stat'));
    check(!orgavela_process_stop_groups($root));check(posix_kill(getmypid(),0));unlink($record);
    // A corrupt early record must not veto later, fully verified groups.
    $childPipes=[];$process=orgavela_process_open(['/bin/sleep','30'],[0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']],$childPipes,$root);
    $owned=orgavela_process_stat(file_get_contents(glob($root.'/child-*.stat')[0]));
    file_put_contents($root.'/child-000-invalid.stat','partial');
    file_put_contents($root.'/child-partial.stat.tmp','partial');
    check(!orgavela_process_stop_groups($root));
    check(!orgavela_process_members($owned));proc_close($process);
    foreach(array_merge(glob($root.'/child-*.stat'),glob($root.'/child-*.stat.tmp')) as $file)unlink($file);
    // Unknown identity requires escalation and must not release maintenance.
    file_put_contents($root.'/child-invalid.stat.tmp','partial');
    foreach(['ours','foreign-owner'] as $contents) {
        file_put_contents($marker,$contents);
        $code=orgavela_capture_watchdog($root,$marker,$hash,getmypid(),$self['start'],1,false);
        $watch=proc_open([PHP_BINARY,'-r',substr($code,6)],[0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['pipe','w']],$watchPipes);
        $diagnostic=stream_get_contents($watchPipes[2]);fclose($watchPipes[2]);
        check(proc_close($watch)===71 && $diagnostic==='');
        clearstatcache(true,$marker);
        check(file_get_contents($marker)===$contents);
        check(json_decode(file_get_contents($root.'/escalation.json'),true)['status']==='unknown');
    }
    unlink($root.'/child-invalid.stat.tmp');unlink($root.'/ready');
    // Deterministic failed-delivery seam: a real verified session remains alive
    // although the signal sender claims success. This is not a real unkillable process.
    $childPipes=[];$child=orgavela_process_open(['/bin/sleep','30'],[0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']],$childPipes,$root);
    foreach(['ours','foreign-owner'] as $contents) {
        file_put_contents($marker,$contents);
        $code=orgavela_capture_watchdog($root,$marker,$hash,getmypid(),$self['start'],1,false);
        $code=str_replace('orgavela_process_stop_groups_result($w)','orgavela_process_stop_groups_result($w,static fn($pid,$signal)=>true,0.05)',$code);
        $watch=proc_open([PHP_BINARY,'-r',substr($code,6)],[0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['pipe','w']],$watchPipes);
        $diagnostic=stream_get_contents($watchPipes[2]);fclose($watchPipes[2]);
        check(proc_close($watch)===72 && $diagnostic==='');
        check(file_get_contents($marker)===$contents);
        check(json_decode(file_get_contents($root.'/escalation.json'),true)['status']==='survivors');
        check(proc_get_status($child)['running']);
    }
    check(orgavela_process_stop_groups($root));proc_close($child);
    foreach(glob($root.'/child-*.stat') as $file)unlink($file);

    echo "ORGAVELA_CAPTURE_HELPERS_SYNTHETIC=PASS\n";
} finally { remove_fixture($root); }
