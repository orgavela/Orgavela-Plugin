<?php
/** Disposable synthetic DB fixture only; no source bindings or site data. */
require __DIR__.'/lib/backup_capture_safety.php';
mysqli_report(MYSQLI_REPORT_OFF);
$db=new mysqli(getenv('FIXTURE_DB_HOST'),'fixture',getenv('FIXTURE_PASSWORD'),'fixture');
if($db->connect_errno)exit(2);
if(($argv[1]??'')==='probe') {
    exit(orgavela_probe_write_locks($db,['fixture_table'])?0:3);
}
if(!$db->query('CREATE TABLE IF NOT EXISTS fixture_table(id INT PRIMARY KEY) ENGINE=InnoDB'))exit(4);
if(!orgavela_lock_capture_tables($db,['fixture_table']))exit(5);
$work='/state/watch';mkdir($work,0700);
$marker='/state/.maintenance';$hash=hash('sha256','owned-marker');
file_put_contents($marker,'owned-marker');
$pid=getmypid();$s=trim(file_get_contents('/proc/'.$pid.'/stat'));
$identity=explode(' ',substr($s,strrpos($s,')')+2))[19];
file_put_contents('/state/pid',(string)$pid);
file_put_contents($work.'/watchdog.php',orgavela_capture_watchdog($work,$marker,$hash,$pid,$identity,6,true));
exec('/usr/bin/nohup '.escapeshellarg(PHP_BINARY).' '.$work.'/watchdog.php > /dev/null 2>&1 < /dev/null &');
$end=microtime(true)+2;
while(!file_exists($work.'/ready')&&microtime(true)<$end)usleep(10000);
if(!file_exists($work.'/ready'))exit(6);
file_put_contents('/state/locked','ready');
// Graceful exception path mirrors capture cleanup; timeout/disconnect rely on watchdog.
if(($argv[1]??'')==='exception') {
    try {throw new OrgaVelaControlledAbort('synthetic');}
    catch(OrgaVelaControlledAbort $e) {
        $db->query('UNLOCK TABLES');$db->close();
        if(!orgavela_remove_owned_maintenance($marker,$hash))exit(7);
        unlink($work.'/ready');unlink($work.'/watchdog.php');rmdir($work);
    }
    exit(0);
}
sleep(30);exit(8);
