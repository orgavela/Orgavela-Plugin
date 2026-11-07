<?php
/** Exact installed client/path negative; synthetic loopback greeting only. */
if(!defined('ORGAVELA_TLS_NEGATIVE_ONLY')||ORGAVELA_TLS_NEGATIVE_ONLY!==true)exit(78);
ini_set('display_errors','0');ini_set('log_errors','0');umask(0077);
$work=null;$active=[];
$clean=static function()use(&$work,&$active):void {if(!$work)return;$result=orgavela_process_stop_groups_result($work);if($result['status']!=='complete')return;
    foreach($active as$p)if(is_resource($p)){@proc_terminate($p,9);@proc_close($p);}
    foreach(array_merge(glob($work.'/child-*.stat'),glob($work.'/child-*.stat.tmp'))as$f)@unlink($f);
    foreach(['s','bridge.php','watchdog.php','ready']as$f)@unlink($work.'/'.$f);@rmdir($work);
};register_shutdown_function($clean);
try {
    if(PHP_VERSION_ID!==80336 || (posix_getpwuid(posix_geteuid())['name']??'')!==ORGAVELA_EXPECTED_USER || realpath(ORGAVELA_EXPECTED_ROOT)!==ORGAVELA_EXPECTED_ROOT)throw new RuntimeException('binding');
    $clients=orgavela_bridge_profile();$work=dirname(ORGAVELA_EXPECTED_ROOT).'/.orgavela-capture-'.bin2hex(random_bytes(12));if(!mkdir($work,0700))throw new RuntimeException('work');
    file_put_contents($work.'/bridge.php',orgavela_tls_bridge_source());$self=orgavela_process_current(getmypid());
    file_put_contents($work.'/watchdog.php',orgavela_capture_watchdog($work,$work.'/unused-marker',hash('sha256','unused'),getmypid(),$self['start'],45,true));
    exec('/usr/bin/nohup '.escapeshellarg(PHP_BINARY).' '.escapeshellarg($work.'/watchdog.php').' > /dev/null 2>&1 < /dev/null &');
    $end=microtime(true)+2;while(!is_file($work.'/ready')&&microtime(true)<$end)usleep(10000);if(!is_file($work.'/ready'))throw new RuntimeException('watchdog');
    $results=[];
    foreach($clients as$i=>$binary){
        // A real TCP greeting with SSL capability absent; receives no authentication.
        $fake= <<<'FAKE'
$s=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);if(!$s)exit(1);echo substr(strrchr(stream_socket_get_name($s,false),':'),1)."\n";flush();
$c=stream_socket_accept($s,8);if(!$c)exit(1);stream_set_timeout($c,4);
$b="\x0a5.7.0-synthetic\0".pack('V',1).str_repeat('x',8)."\0".pack('v',0x8201).chr(45).pack('v',2).pack('v',8).chr(21).str_repeat("\0",10).str_repeat('x',12)."\0mysql_native_password\0";
fwrite($c,substr(pack('V',strlen($b)),0,3)."\0".$b);$received=fread($c,1);echo json_encode(['received_bytes'=>strlen((string)$received)]);fclose($c);fclose($s);
FAKE;
        $sp=[];$server=orgavela_process_open([PHP_BINARY,'-r',$fake],[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['file','/dev/null','w']],$sp,$work);$active[]=$server;
        stream_set_timeout($sp[1],10);$port=trim((string)fgets($sp[1]));if(!ctype_digit($port))throw new RuntimeException('fake server');
        $args=array_merge([$binary,'--defaults-file=/proc/self/fd/3'],orgavela_bridge_flags($work),$i===0?['--batch','--skip-column-names','--execute','SELECT 1']:['--single-transaction','--skip-lock-tables','--quick'],['--','synthetic']);
        $cp=[];$child=orgavela_process_open([PHP_BINARY,$work.'/bridge.php',base64_encode(json_encode(['127.0.0.1',(int)$port,str_repeat('A',40),$work.'/s',$args],JSON_THROW_ON_ERROR)),'audit-plaintext-refusal'],[0=>['pipe','r'],1=>['pipe','w'],2=>['file','/dev/null','w'],4=>['pipe','w']],$cp,$work);$active[]=$child;
        fwrite($cp[0],"[client]\nuser=synthetic\npassword=synthetic-not-source\nhost=localhost\n");fclose($cp[0]);
        stream_set_timeout($cp[1],12);stream_set_timeout($cp[4],12);$output=stream_get_contents($cp[1],4096);fclose($cp[1]);$audit=json_decode(stream_get_contents($cp[4],4096),true,16,JSON_THROW_ON_ERROR);fclose($cp[4]);$exit=proc_close($child);
        $serverProof=json_decode(stream_get_contents($sp[1],4096),true,8,JSON_THROW_ON_ERROR);fclose($sp[1]);$serverExit=proc_close($server);
        if($exit===0||$serverExit!==0||$output!==''||$audit!==['client_started'=>true,'authentication_forwarded'=>false,'tls_established'=>false,'failure'=>'tls_required']||$serverProof!==['received_bytes'=>0])throw new RuntimeException('negative unverified');
        $results[]=['kind'=>$i===0?'client':'dump','path'=>$binary,'sha256'=>hash_file('sha256',$binary),'version'=>'10.11.19','client_started'=>true,'plaintext_refused'=>true,'remote_authentication_bytes'=>0,'forwarded_output_bytes'=>0];
    }
    $clean();if(is_dir($work))throw new RuntimeException('cleanup');
    echo json_encode(['schema'=>'orgavela-exact-client-tls-negative/v1','results'=>$results,'database_login'=>false,'maintenance_created'=>false,'source_database_accessed'=>false,'snapshot_created'=>false,'own_processes_cleaned'=>true,'synthetic_loopback_only'=>true],JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){$clean();fwrite(STDERR,"EXACT_CLIENT_TLS_NEGATIVE_REFUSED\n");exit(1);}
