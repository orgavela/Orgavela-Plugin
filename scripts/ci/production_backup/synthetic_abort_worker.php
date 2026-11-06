<?php
/** Disposable synthetic DB fixture only; no source bindings or site data. */
require __DIR__.'/lib/backup_capture_safety.php';
mysqli_report(MYSQLI_REPORT_OFF);umask(0077);
$db=new mysqli(getenv('FIXTURE_DB_HOST'),'fixture',getenv('FIXTURE_PASSWORD'),'fixture');
if($db->connect_errno)exit(2);
if(($argv[1]??'')==='probe') {
    exit(orgavela_probe_write_locks($db,['fixture_table'])?0:3);
}
if(!$db->query('CREATE TABLE IF NOT EXISTS fixture_table(id INT PRIMARY KEY) ENGINE=InnoDB'))exit(4);
if(!orgavela_lock_capture_tables($db,['fixture_table']))exit(5);
$mode=$argv[1]??'';
$childKind=str_starts_with($mode,'tar-')?'tar':(str_starts_with($mode,'dump-')?'dump':null);
if($childKind==='dump') {
    // More output than a pipe can hold; this is a real running dump, not sleep.
    if(!$db->query('UNLOCK TABLES')||!$db->query('CREATE TABLE IF NOT EXISTS dump_payload(id INT PRIMARY KEY, payload LONGBLOB) ENGINE=InnoDB')||!$db->query("REPLACE INTO dump_payload VALUES(1,REPEAT('synthetic-only',200000))")||!$db->commit()||!orgavela_lock_capture_tables($db,['fixture_table']))exit(9);
}
$work='/state/watch';mkdir($work,0700);
$marker='/state/.maintenance';$hash=hash('sha256','owned-marker');
file_put_contents($marker,'owned-marker');
$pid=getmypid();$s=trim(file_get_contents('/proc/'.$pid.'/stat'));
$identity=explode(' ',substr($s,strrpos($s,')')+2))[19];
file_put_contents('/state/pid',(string)$pid);
file_put_contents($work.'/watchdog.php',orgavela_capture_watchdog($work,$marker,$hash,$pid,$identity,6,true));
exec('/usr/bin/nohup '.escapeshellarg(PHP_BINARY).' '.$work.'/watchdog.php > /state/watchdog.log 2>&1 < /dev/null &');
$end=microtime(true)+2;
while(!file_exists($work.'/ready')&&microtime(true)<$end)usleep(10000);
if(!file_exists($work.'/ready'))exit(6);
$child=null;$pipes=[];
if($childKind) {
    $spec=[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']];
    if($childKind==='tar') {
        file_put_contents('/state/payload',random_bytes(4*1024*1024));
        $args=['tar','--gzip','-cf','-','-C','/state','payload'];
    }else {
        $args=['mariadb-dump','--defaults-file=/proc/self/fd/3','--skip-ssl','--single-transaction','--skip-lock-tables','fixture'];
        $spec[3]=['pipe','r'];
    }
    $child=orgavela_process_open($args,$spec,$pipes,$work);
    if($childKind==='dump') {
        $config="[client]\nhost=".getenv('FIXTURE_DB_HOST')."\nuser=fixture\npassword=".getenv('FIXTURE_PASSWORD')."\n";
        fwrite($pipes[3],$config);fclose($pipes[3]);
    }
    // Prove output was produced, then stop draining it so the actual tool stays active.
    stream_set_blocking($pipes[1],false);$end=microtime(true)+2;$output='';
    while($output===''&&microtime(true)<$end){$output=fread($pipes[1],1024);usleep(10000);}
    if($output===''){file_put_contents('/state/tool-error',substr(stream_get_contents($pipes[2]),0,2048));exit(10);}
    usleep(100000);
    $owned=orgavela_process_stat(file_get_contents(glob($work.'/child-*.stat')[0]));
    $members=orgavela_process_members($owned);
    if(!$members||($childKind==='tar'&&count($members)<2)||!proc_get_status($child)['running']){file_put_contents('/state/tool-error',substr(stream_get_contents($pipes[2]),0,2048));exit(11);}
    $comm=trim(file_get_contents('/proc/'.$owned['pid'].'/comm'));
    if($comm!==($childKind==='tar'?'tar':'mariadb-dump'))exit(12);
    file_put_contents('/state/children.json',json_encode(['tool'=>$comm,'members'=>$members,'output_observed'=>true],JSON_THROW_ON_ERROR));
}
file_put_contents('/state/locked','ready');
// Graceful exception path mirrors capture cleanup; timeout/disconnect rely on watchdog.
if($mode==='exception'||str_ends_with($mode,'-exception')) {
    try {throw new OrgaVelaControlledAbort('synthetic');}
    catch(OrgaVelaControlledAbort $e) {
        if(!orgavela_process_stop_groups($work))exit(13);
        if(is_resource($child)){foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($child);}
        $db->query('UNLOCK TABLES');$db->close();
        if(!orgavela_remove_owned_maintenance($marker,$hash))exit(7);
        foreach(glob($work.'/child-*.stat') as $file)unlink($file);
        unlink($work.'/ready');unlink($work.'/watchdog.php');rmdir($work);
    }
    exit(0);
}
sleep(30);exit(8);
