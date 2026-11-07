<?php
/** Private Unix socket to required peer-bound DB TLS; no trust/hostname claim. */
function orgavela_bridge_profile(): array {
    $profile=[
        '/usr/bin/mariadb'=>'e375617e0bad12f0880e85a4f1125a68480bd7b9b4160d86e48d0ef4210c5a76',
        '/usr/bin/mariadb-dump'=>'a61d09007f25c5df6bcbdf02ff34fe13bcd86426cff4bb40efc5d216cbd74dea'];
    foreach($profile as$path=>$sha){if(!is_string($path)||!is_string($sha)||!preg_match('/^[a-f0-9]{64}$/D',$sha)||realpath($path)!==$path||!hash_equals($sha,(string)@hash_file('sha256',$path)))throw new RuntimeException('client identity');
        for($p=$path;;$p=dirname($p)){$uid=@fileowner($p);$mode=@fileperms($p);if(!is_int($uid)||!is_int($mode)||$uid===posix_geteuid()||($mode&0022)!==0||is_writable($p))throw new RuntimeException('client integrity');if($p==='/')break;}}
    if(count($profile)!==2)throw new RuntimeException('client profile');return array_keys($profile);
}
function orgavela_bridge_flags(string $work):array {if(strlen($work.'/s')>100)throw new RuntimeException('socket bound');return ['--host=localhost','--protocol=socket','--socket='.$work.'/s','--skip-ssl'];}
function orgavela_tls_bridge_source():string {return <<<'BRIDGE'
<?php
ini_set('display_errors','0');ini_set('log_errors','0');umask(0077);
function bridge_write($s,string $v):void {while($v!==''){$n=fwrite($s,$v);if(!$n)throw new RuntimeException('write');$v=substr($v,$n);}}
function bridge_read($s,int $n):string {$out='';while(strlen($out)<$n){$v=fread($s,$n-strlen($out));if($v===false||$v==='')throw new RuntimeException('read');$out.=$v;}return $out;}
function bridge_packet($s):string {$h=bridge_read($s,4);$n=ord($h[0])|(ord($h[1])<<8)|(ord($h[2])<<16);if($n<1||$n>65536)throw new RuntimeException('auth packet');return $h.bridge_read($s,$n);}
function bridge_seq(string $p,int $delta):string {$p[3]=chr((ord($p[3])+$delta)&255);return $p;}
$child=null;$server=null;$local=null;$remote=null;$socket=null;$inode=null;$pipes=[];$ok=false;$clientStarted=false;$authForwarded=false;$tlsEstablished=false;$reason=null;
try {
    [$host,$port,$fp,$socket,$args]=json_decode(base64_decode($argv[1],true),true,32,JSON_THROW_ON_ERROR);
    if(!is_string($host)||!is_int($port)||$port<1||$port>65535||!preg_match('/^[A-F0-9]{40}$/D',$fp)||!is_array($args)||is_link($socket)||file_exists($socket)||strlen($socket)>100)throw new RuntimeException('input');
    foreach(['--host=localhost','--protocol=socket','--socket='.$socket,'--skip-ssl']as$required)if(!in_array($required,$args,true))throw new RuntimeException('input');
    foreach(['--host=','--protocol=','--socket=']as$prefix){$n=0;foreach($args as$a)if(str_starts_with($a,$prefix))$n++;if($n!==1)throw new RuntimeException('input');}
    $server=stream_socket_server('unix://'.$socket,$errno,$error);if(!$server)throw new RuntimeException('listen');$inode=lstat($socket);chmod($socket,0600);
    // Child already uses the selected exact binary, but can send nothing before greeting.
    $config=stream_get_contents(STDIN,65537);if($config===false||$config===''||strlen($config)>65536)throw new RuntimeException('input');
    $config=preg_replace('/^(?:host|port)=.*\n/m','',$config);
    $child=proc_open($args,[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w'],3=>['pipe','r']],$pipes);
    if(!is_resource($child))throw new RuntimeException('client');$clientStarted=true;bridge_write($pipes[3],$config);fclose($pipes[3]);unset($pipes[3],$config);
    $local=stream_socket_accept($server,5);if(!$local)throw new RuntimeException('accept');stream_set_timeout($local,10);
    $ctx=stream_context_create(['ssl'=>['verify_peer'=>false,'verify_peer_name'=>false,'capture_peer_cert'=>true,'peer_fingerprint'=>['sha1'=>strtolower($fp)]]]);
    $remote=stream_socket_client('tcp://'.$host.':'.$port,$errno,$error,10,STREAM_CLIENT_CONNECT,$ctx);if(!$remote)throw new RuntimeException('connect');stream_set_timeout($remote,10);
    $g=bridge_packet($remote);$nul=strpos($g,"\0",5);if(ord($g[4])!==10||$nul===false)throw new RuntimeException('greeting');$low=$nul+14;
    if(strlen($g)<$low+8)throw new RuntimeException('capabilities');$caps=ord($g[$low])|(ord($g[$low+1])<<8);if(($caps&0x0800)===0)throw new RuntimeException('TLS required');
    // Unix client remains private; remote server receives no authentication before TLS.
    $g[$low+1]=chr(ord($g[$low+1])&~8);bridge_write($local,$g);
    $auth=bridge_packet($local);if(strlen($auth)<36||ord($auth[3])!==1)throw new RuntimeException('auth');$auth[5]=chr(ord($auth[5])|8);
    $request=substr($auth,4,32);bridge_write($remote,"\x20\0\0\x01".$request);
    if(stream_socket_enable_crypto($remote,true,STREAM_CRYPTO_METHOD_TLS_CLIENT)!==true)throw new RuntimeException('TLS');
    $parameters=stream_context_get_params($remote);$cert=$parameters['options']['ssl']['peer_certificate']??null;
    if(!$cert||!hash_equals($fp,strtoupper((string)openssl_x509_fingerprint($cert,'sha1'))))throw new RuntimeException('peer');
    $metadata=stream_get_meta_data($remote);if(empty($metadata['crypto']['cipher_name'])||!str_starts_with($metadata['crypto']['protocol']??'','TLS'))throw new RuntimeException('cipher');
    $tlsEstablished=true;$authForwarded=true;bridge_write($remote,bridge_seq($auth,1));unset($auth,$request,$cert,$parameters);
    // SSLRequest adds one handshake packet; translate only authentication sequences.
    $deadline=microtime(true)+15;
    while(true){if(microtime(true)>$deadline)throw new RuntimeException('auth deadline');$read=[$local,$remote];$w=null;$e=null;if(!stream_select($read,$w,$e,1))continue;
        foreach($read as$s){$p=bridge_packet($s);if($s===$remote){$end=ord($p[4])===0||ord($p[4])===255;bridge_write($local,bridge_seq($p,-1));if($end)break 2;}else bridge_write($remote,bridge_seq($p,1));}}
    foreach($pipes as$s)stream_set_blocking($s,false);stream_set_blocking($local,false);stream_set_blocking($remote,false);
    $deadline=microtime(true)+80;$exit=null;
    while(true){if(microtime(true)>$deadline)throw new RuntimeException('deadline');$state=proc_get_status($child);if(!$state['running']&&$exit===null)$exit=$state['exitcode'];
        $read=[];foreach([$local,$remote,$pipes[1],$pipes[2]]as$s)if(is_resource($s)&&!feof($s))$read[]=$s;
        if($exit!==null&&feof($pipes[1])&&feof($pipes[2]))break;
        if(!$read){usleep(10000);continue;}$w=null;$e=null;if(!stream_select($read,$w,$e,0,100000))continue;
        foreach($read as$s){$data=fread($s,65536);if($data===false)throw new RuntimeException('relay');if($data===''){if(($s===$local||$s===$remote)&&feof($s))@stream_socket_shutdown($s===$local?$remote:$local,STREAM_SHUT_WR);continue;}
            if($s===$pipes[1])bridge_write(STDOUT,$data);elseif($s===$pipes[2])bridge_write(STDERR,$data);else { $to=$s===$local?$remote:$local;stream_set_blocking($to,true);bridge_write($to,$data);stream_set_blocking($to,false);}}
    }
    $ok=$exit===0;
}catch(Throwable $e){if(is_resource($child)&&isset($pipes[2])){stream_set_blocking($pipes[2],false);fwrite(STDERR,stream_get_contents($pipes[2],16384));}$reason=$e->getMessage();$allowed=['input','listen','client','accept','connect','greeting','capabilities','TLS required','auth','TLS','peer','cipher','auth deadline','deadline','relay','read','write'];$reason=in_array($reason,$allowed,true)?str_replace(' ','_',strtolower($reason)):'internal';fwrite(STDERR,"ORGAVELA_TLS_BRIDGE_REFUSED=".$reason."\n");}
finally {
    if(($argv[2]??null)==='audit-plaintext-refusal'){$audit=fopen('php://fd/4','w');fwrite($audit,json_encode(['client_started'=>$clientStarted,'authentication_forwarded'=>$authForwarded,'tls_established'=>$tlsEstablished,'failure'=>$reason],JSON_THROW_ON_ERROR));fclose($audit);}
    if(is_resource($child)){$state=proc_get_status($child);if($state['running'])proc_terminate($child,9);foreach($pipes as$p)if(is_resource($p))fclose($p);proc_close($child);}
    foreach([$local,$remote,$server]as$s)if(is_resource($s))fclose($s);
    clearstatcache(true,(string)$socket);if($inode&&$socket&&!is_link($socket)&&is_array($now=@lstat($socket))&&$now['dev']===$inode['dev']&&$now['ino']===$inode['ino'])unlink($socket);
}
exit($ok?0:1);
BRIDGE;
}
