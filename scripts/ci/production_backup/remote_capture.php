<?php
/** Manual only. Requires explicit maintenance approval in the protected runner. */
if (!defined('ORGAVELA_CAPTURE_ENTRYPOINT_ENABLED') || ORGAVELA_CAPTURE_ENTRYPOINT_ENABLED!==true) { fwrite(STDERR,"ORGAVELA_CAPTURE_DISABLED\n"); exit(78); }
ini_set('display_errors','0'); ini_set('log_errors','0'); umask(0077);
mysqli_report(MYSQLI_REPORT_OFF);
$dry=defined('ORGAVELA_CAPTURE_SAFETY_ONLY') && ORGAVELA_CAPTURE_SAFETY_ONLY===true;
$databaseOnly=defined('ORGAVELA_CAPTURE_DATABASE_ONLY') && ORGAVELA_CAPTURE_DATABASE_ONLY===true;
$filesystemOnly=defined('ORGAVELA_CAPTURE_FILESYSTEM_ONLY') && ORGAVELA_CAPTURE_FILESYSTEM_ONLY===true;
$unverifiedTls=orgavela_database_tls_unverified();$peerFingerprint=null;
$abortOnly=defined('ORGAVELA_CAPTURE_ABORT_AFTER_LOCK') && ORGAVELA_CAPTURE_ABORT_AFTER_LOCK===true;
$abortToken=defined('ORGAVELA_ABORT_TOKEN')?ORGAVELA_ABORT_TOKEN:null;
$abortVerify=defined('ORGAVELA_ABORT_VERIFY') && ORGAVELA_ABORT_VERIFY===true;
if($abortToken!==null && (!is_string($abortToken)||!preg_match('/^[a-f0-9]{32}$/D',$abortToken)))exit(78);
$sourceOnly=defined('ORGAVELA_CAPTURE_SOURCE_ONLY') && ORGAVELA_CAPTURE_SOURCE_ONLY===true;
$phase=static function(string $name): void { fwrite(STDERR,'ORGAVELA_CAPTURE_PHASE='.$name."\n"); };
$db = null; $work = null; $maintenance = null; $maintenanceHash = null;
$cleanupStatus='unknown';
$active = []; $buffers=[]; $started = microtime(true); $cleaned = false;
$cleanup = static function() use (&$db,&$work,&$maintenance,&$maintenanceHash,&$active,&$cleaned,&$buffers,&$cleanupStatus): void {
    if ($cleaned) return; $cleaned = true;
    $buffers=[];
    $cleanupStatus=$work?orgavela_process_stop_groups_result($work)['status']:'complete';
    $childrenClean=$cleanupStatus==='complete';
    foreach ($active as $p) if (is_resource($p)) { try { @proc_terminate($p, 9); @proc_close($p); } catch (Throwable $e) {} }
    if ($db instanceof mysqli) { try { @$db->query('UNLOCK TABLES'); @$db->close(); } catch (Throwable $e) {} $db=null; }
    if(!$childrenClean)return;
    $maintenanceClean=true;
    if ($maintenance && $maintenanceHash) $maintenanceClean=@orgavela_remove_owned_maintenance($maintenance,$maintenanceHash);
    // Keep watchdog metadata if normal marker cleanup failed, so it can retry.
    if(!$maintenanceClean)return;
    if ($work && is_dir($work) && !is_link($work)) {
        foreach(array_merge(glob($work.'/child-*.stat'),glob($work.'/child-*.stat.tmp')) as $file)@unlink($file);
        foreach (['database.sql.gz','wordpress-files.tar.gz','capture-evidence.json','watchdog.php','maintenance-template','ready','bridge.php','s'] as $file) @unlink($work.'/'.$file);
        @rmdir($work);
    }
};
register_shutdown_function($cleanup);
$checkTime = static function() use ($started): void { if (microtime(true)-$started > 100) throw new RuntimeException('deadline'); };
$run = static function(array $args, ?string $config, bool $compress, int $limit=60) use (&$active,$checkTime,$phase,$filesystemOnly,&$entryCount,$cleanup,&$work,&$unverifiedTls,&$host,&$port,&$peerFingerprint): string {
    $runAt=microtime(true);
    $kind=in_array('/usr/bin/openssl',$args,true)?'database_tls_peer':(basename($args[0])==='mariadb'?'database_tls_probe':($args[0]==='tar'?'filesystem_tar':(in_array('--help',$args,true)?'client_help':'database_dump')));
    if($kind!=='client_help')$phase($kind.'_started');
    $bridge=$unverifiedTls && $config!==null;
    if($bridge){if(!$work)throw new RuntimeException('bridge work absent');$args=[PHP_BINARY,$work.'/bridge.php',base64_encode(json_encode([$host,$port,$peerFingerprint,$work.'/s',$args],JSON_THROW_ON_ERROR))];}
    $spec = [0=>['file','/dev/null','r'], 1=>['pipe','w'], 2=>['pipe','w']];
    if ($config !== null) $spec[$bridge?0:3]=['pipe','r'];
    $pipes=[];
    $p=$work ? orgavela_process_open($args,$spec,$pipes,$work) : proc_open($args,$spec,$pipes); if (!is_resource($p)) throw new RuntimeException('process');
    $active[]=$p;
    if ($config !== null) { $fd=$bridge?0:3;fwrite($pipes[$fd],$config); fclose($pipes[$fd]); }
    $gz = $compress ? deflate_init(ZLIB_ENCODING_GZIP,['level'=>6]) : null;
    if ($compress && !$gz) throw new RuntimeException('gzip');
    stream_set_blocking($pipes[1],false);
    stream_set_blocking($pipes[2],false);
    $bytes=0; $output='';$errors='';
    do {
        $checkTime();
        if (microtime(true)-$runAt>$limit) {
            $phase($kind.'_deadline');
            if($filesystemOnly && $kind==='filesystem_tar'){
                // Only aggregate progress crosses SSH. Partial source bytes are discarded.
                $result=['schema'=>'orgavela-filesystem-memory-probe/v2','ustar_paths_verified'=>true,'completed'=>false,
                    'filesystem_entries'=>$entryCount,'compressed_bytes'=>strlen($output),
                    'seconds'=>round(microtime(true)-$runAt,3),'maintenance_created'=>false,
                    'table_locks_used'=>false,'file_data_logged'=>false,'data_discarded'=>true,'backup_saved'=>false];
                if(orgavela_database_tls_unverified()){$result['schema']='orgavela-filesystem-memory-probe/v3';$result=array_merge($result,['tls_verified'=>true,'server_certificate_verified'=>false,'database_tls_policy'=>'orgavela-backup-encrypted-unverified-20261107','database_tls_exception_authorization'=>'bd622f9fa9e926d7af866676af1a0224cb9d92c0','database_control_connection_tls_verified'=>true,'database_peer_continuity_enforced'=>true]);}
                unset($output,$chunk);$cleanup();echo json_encode($result,JSON_THROW_ON_ERROR)."\n";exit(0);
            }
            if($kind==='database_dump')$phase($bytes>0?'database_dump_stream_deadline':'database_dump_no_output_deadline');
            throw new RuntimeException('child deadline');
        }
        $err=stream_get_contents($pipes[2],65536);if(strlen($errors)<16384)$errors.=substr($err,0,16384-strlen($errors));
        $chunk=stream_get_contents($pipes[1],1048576);if($chunk===false)throw new RuntimeException('stream read'); $bytes+=strlen($chunk);
        if ($bytes>1073741824 || ($kind==='database_tls_peer' && $bytes>65536)) throw new RuntimeException('stream');
        if ($chunk!=='') $output.=$compress ? deflate_add($gz,$chunk,ZLIB_NO_FLUSH) : $chunk;
        if (strlen($output)>100663296) throw new RuntimeException('compressed memory limit');
        $state=proc_get_status($p); if ($state['running'] && $chunk==='') usleep(10000);
    } while ($state['running']);
    while (!feof($pipes[1])) {
        $checkTime();
        $chunk=fread($pipes[1],65536); $bytes+=strlen($chunk);
        if ($bytes>1073741824 || ($kind==='database_tls_peer' && $bytes>65536)) throw new RuntimeException('stream');
        if ($chunk!=='') $output.=$compress ? deflate_add($gz,$chunk,ZLIB_NO_FLUSH) : $chunk;
        if (strlen($output)>100663296) throw new RuntimeException('compressed memory limit');
    }
    fclose($pipes[1]);$err=stream_get_contents($pipes[2]);if(strlen($errors)<16384)$errors.=substr($err,0,16384-strlen($errors));fclose($pipes[2]);
    if ($compress) $output.=deflate_add($gz,'',ZLIB_FINISH);
    proc_close($p); if ($state['exitcode']!==0) {
        $kind='dump_process_failed';
        if (preg_match('/(?:Got error: |ERROR )(1045|1049|1142|1205|1213|1227|1969|2002|2003|2013|2026)\b/',$errors,$m)) $kind=match($m[1]){'1045'=>'dump_auth_failed','1049'=>'dump_database_missing','1205','1213'=>'dump_lock_timeout','1969'=>'dump_statement_timeout','2002','2003'=>'dump_connection_failed','2013'=>'dump_connection_lost','2026'=>orgavela_tls_failure_phase($errors),default=>'dump_permission_denied'};
        elseif (str_contains($errors,'ORGAVELA_TLS_BRIDGE_REFUSED')){$kind='database_tls_bridge_refused';if(preg_match('/ORGAVELA_TLS_BRIDGE_REFUSED=(input|listen|client|accept|connect|greeting|capabilities|tls_required|auth|tls|peer|cipher|auth_deadline|deadline|relay|read|write|internal)\b/',$errors,$m))$kind='database_tls_bridge_'.$m[1];}
        elseif (str_contains($errors,'unknown option')||str_contains($errors,'unknown variable'))$kind='dump_option_unsupported';
        $phase($kind);unset($errors);throw new RuntimeException('process failed');
    }
    return $output;
};
try {
    if($unverifiedTls && ($abortOnly||$sourceOnly))throw new RuntimeException('database exception outside backup path');
    if($abortOnly && ($dry||$sourceOnly||$databaseOnly||$filesystemOnly))throw new RuntimeException('conflicting abort mode');
    $phase('source_validating');
    if(!is_executable('/usr/bin/setsid')||!function_exists('posix_kill')||!function_exists('posix_getsid'))throw new RuntimeException('process isolation unavailable');
    if (!defined('ORGAVELA_EXPECTED_ROOT') || !defined('ORGAVELA_EXPECTED_USER')) throw new RuntimeException('binding absent');
    $root=realpath(ORGAVELA_EXPECTED_ROOT); $home=$root?dirname($root):false;
    if (!function_exists('posix_geteuid') || !function_exists('posix_getpwuid') ||
        (posix_getpwuid(posix_geteuid())['name']??'')!==ORGAVELA_EXPECTED_USER || !$root || fileowner($root)!==posix_geteuid()) throw new RuntimeException('account');
    $part='';foreach(explode('/',ORGAVELA_EXPECTED_ROOT) as $component){if($component==='')continue;$part.='/'.$component;if(is_link($part)||!is_dir($part))throw new RuntimeException('root component');}
    // Fixed allowlisted rejection categories only; no filesystem paths or PHP errors logged.
    // These checks must remain fail-closed and precede any maintenance or database lock.
    if (!$home || !$root || $root!==ORGAVELA_EXPECTED_ROOT || is_link(ORGAVELA_EXPECTED_ROOT) || !is_executable('/usr/bin/nohup') || !is_executable('/bin/kill')) {
        $phase('source_root_rejected'); throw new RuntimeException('source');
    }
    foreach (['wp-config.php','wp-load.php','index.php'] as $name) if (!is_file($root.'/'.$name) || is_link($root.'/'.$name)) {
        $phase('source_core_files_rejected'); throw new RuntimeException('scope');
    }
    if (!is_dir($root.'/wp-content/uploads')) {
        $phase('source_uploads_rejected'); throw new RuntimeException('precondition');
    }
    if (disk_free_space($home)<1048576) {
        $phase('source_disk_space_rejected'); throw new RuntimeException('precondition');
    }
    if (file_exists($root.'/.maintenance')) {
        $phase('source_maintenance_rejected'); throw new RuntimeException('precondition');
    }
    // Validate scope before ever making the site unavailable. Suppress the
    // exception text: it can include private source paths or filenames.
    try { $inventory=$abortOnly?[]:orgavela_capture_inventory($root); if(!$abortOnly)orgavela_validate_ustar_paths(array_keys($inventory)); }
    catch (Throwable $e) {
        $phase('source_inventory_rejected');
        // No user-controlled exception messages, paths, filenames or PHP warnings leave the server.
        $safeCodes=['inventory_invalid_name','inventory_entry_limit','inventory_symlink',
            'inventory_total_size','inventory_unreadable_file','inventory_unsupported_entry','inventory_ustar_path'];
        if ($e instanceof OrgaVelaInventoryRejection && in_array($e->getMessage(),$safeCodes,true)) {
            $phase($e->getMessage());
            // Only the explicitly read-only diagnostic may return a PRIVATE
            // file reference over the protected SSH stdout channel. The runner
            // must encrypt it *before* any CI log output; no path in stderr.
            if ($sourceOnly && $e->getMessage()==='inventory_unreadable_file') {
                $relative=$e->privateRelativeFile();
                if (is_string($relative) && strlen($relative)>=1 && strlen($relative)<=1024 &&
                    !str_starts_with($relative,'/') && !str_contains($relative,"\\") &&
                    !in_array('..',explode('/',$relative),true) &&
                    !str_contains($relative,"\n") && !str_contains($relative,"\r") &&
                    !str_contains($relative,"\0")) {
                    $file=$root.'/'.$relative;
                    $perms=@fileperms($file);
                    // No numeric UIDs leave the server. Use null when POSIX
                    // identity cannot be established instead of inferring it.
                    $owner=@fileowner($file);
                    $effectiveUid=function_exists('posix_geteuid')?@posix_geteuid():false;
                    $ownerMatches=(is_int($owner) && is_int($effectiveUid))
                        ?($owner===$effectiveUid):null;
                    $private=['schema'=>'orgavela-private-unreadable-file/v1',
                        'relative_file'=>$relative,'exists'=>is_file($file),
                        'readable'=>is_readable($file),
                        'mode'=>$perms===false?null:sprintf('%04o',$perms & 07777),
                        'owner_matches_process'=>$ownerMatches];
                    $json=json_encode($private,JSON_THROW_ON_ERROR);
                    if (strlen($json)<=2048) { echo $json."\n"; exit(0); }
                }
            }
        }
        throw $e;
    }
    $entryCount=count($inventory);unset($inventory);$checkTime();
    $phase('scope_validated');
    // Diagnostic-only: the exact production scope checks above completed.
    // No DB connection, table locks, site maintenance, remote files or backup.
    if ($sourceOnly) {
        $result=['schema'=>'orgavela-source-scope-preflight/v1','source_validated'=>true,
            'filesystem_entries'=>$entryCount,'database_connected'=>false,'table_locks_used'=>false,
            'maintenance_created'=>false,'remote_files_written'=>false,'backup_saved'=>false];
        $cleanup();echo json_encode($result,JSON_THROW_ON_ERROR)."\n";exit(0);
    }
    if(!$abortOnly) {
    // Query only local client capabilities; no defaults, connection or secrets.
    if($unverifiedTls){[$clientBinary,$dumpBinary]=orgavela_bridge_profile();$phase('database_client_profile_verified');}
    $help=$run([$unverifiedTls?$dumpBinary:'mariadb-dump','--no-defaults','--help'],null,false,3);
    $dumpHelp=$help;
    if(!$unverifiedTls){try{$dumpArgs=orgavela_dump_client_args($help,$dry);}catch(OrgaVelaTrustRejection $e){$phase($e->getMessage());throw $e;}}
    $statementLimitSupported=str_contains($help,'--max-statement-time');unset($help);
    if(!$unverifiedTls)$phase('dump_client_validated');
    }
    $config=orgavela_parse_config(file_get_contents($root.'/wp-config.php'));
    $host=$config['DB_HOST']; $port=3306;
    if (preg_match('/^([^:]+):([0-9]{1,5})$/D',$host,$m)) { $host=$m[1];$port=(int)$m[2]; }
    if($unverifiedTls && !$abortOnly){
        if(!is_executable('/usr/bin/openssl') || !is_executable('/usr/bin/timeout')){$phase('database_tls_peer_reader_unavailable');throw new RuntimeException('TLS peer reader unavailable');}
        if(!function_exists('openssl_x509_fingerprint')){$phase('database_tls_peer_extension_unavailable');throw new RuntimeException('TLS peer extension unavailable');}
        try{$peer=$run(['/usr/bin/timeout','-k','2','10','/usr/bin/openssl','s_client','-starttls','mysql','-connect',$host.':'.$port,'-showcerts','-no_ign_eof'],null,false,10);}catch(Throwable $e){$phase('database_tls_peer_read_failed');throw $e;}
        $phase('database_tls_peer_read_complete');
        if(!preg_match('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s',$peer,$match)){$phase('database_tls_peer_certificate_missing');throw new RuntimeException('TLS peer missing');}
        $peerFingerprint=strtoupper((string)openssl_x509_fingerprint($match[0],'sha1'));
        unset($peer,$match);$phase('database_tls_peer_fingerprint_read');
        try{orgavela_database_tls_args($peerFingerprint);$dumpArgs=[$dumpBinary,'--defaults-file=/proc/self/fd/3','--single-transaction','--skip-lock-tables','--max-statement-time='.($dry?'2':'15')];if(!$dry)$dumpArgs=array_merge($dumpArgs,['--quick','--hex-blob','--default-character-set=utf8mb4','--skip-comments']);else $dumpArgs=array_merge($dumpArgs,['--skip-triggers','--no-data']);}catch(OrgaVelaTrustRejection $e){$phase($e->getMessage());throw $e;}catch(Throwable $e){$phase('dump_option_unsupported');throw $e;}unset($dumpHelp);$phase('dump_client_validated');
        $db=mysqli_init();$db->options(MYSQLI_OPT_CONNECT_TIMEOUT,10);$db->options(MYSQLI_OPT_READ_TIMEOUT,10);$db->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT,false);
        $db->ssl_set(null,null,null,null,null);
        if(!@$db->real_connect($host,$config['DB_USER'],$config['DB_PASSWORD'],$config['DB_NAME'],$port,null,MYSQLI_CLIENT_SSL|MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT))throw new RuntimeException('encrypted control session required');
        $controlTls=$db->query("SHOW SESSION STATUS LIKE 'Ssl_cipher'");$controlRow=$controlTls?$controlTls->fetch_row():null;
        if(!$controlRow || !is_string($controlRow[1]) || $controlRow[1]==='')throw new RuntimeException('encrypted control session required');
        unset($controlTls,$controlRow);
        $phase('database_control_tls_verified');
    }else{$db=@new mysqli($host,$config['DB_USER'],$config['DB_PASSWORD'],$config['DB_NAME'],$port);}
    if ($db->connect_errno) throw new RuntimeException('database');
    $db->set_charset('utf8mb4'); $table=$config['table_prefix'].'options';
    foreach (['siteurl','home'] as $option) {
        $r=$db->query("SELECT option_value FROM `$table` WHERE option_name='$option'");
        if (!$r || $r->num_rows!==1 || rtrim((string)$r->fetch_row()[0],'/')!=='https://orgavela.com') throw new RuntimeException('domain');
    }
    $r=$db->query('SELECT TABLE_NAME,ENGINE,TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()');
    if (!$r) throw new RuntimeException('inventory'); $tables=[];
    while ($row=$r->fetch_assoc()) {
        if ($row['ENGINE']!=='InnoDB' || $row['TABLE_TYPE']!=='BASE TABLE' || !preg_match('/^[A-Za-z0-9_]+$/D',$row['TABLE_NAME'])) throw new RuntimeException('table type');
        $tables[]=$row['TABLE_NAME'];
    }
    if (!orgavela_validate_tables($tables,$config['table_prefix'])) throw new RuntimeException('table scope');
    foreach (['ROUTINES'=>'ROUTINE_SCHEMA','EVENTS'=>'EVENT_SCHEMA','TRIGGERS'=>'TRIGGER_SCHEMA'] as $kind=>$field) {
        $r=$db->query("SELECT COUNT(*) FROM information_schema.$kind WHERE $field=DATABASE()");
        if (!$r || (int)$r->fetch_row()[0]!==0) throw new RuntimeException('unsupported database objects');
    }
    $phase('database_validated');
    $startWork=static function()use(&$work,&$maintenance,&$maintenanceHash,&$content,&$watchSeconds,$home,$root,$abortToken,$dry,$phase):void {if($work)return;
    $work=$home.'/.orgavela-capture-'.($abortToken??bin2hex(random_bytes(12))); if (!mkdir($work,0700)) throw new RuntimeException('work');
    $maintenance=$root.'/.maintenance';
    $content='<?php $upgrading = '.time().'; /* OrgaVela backup '.bin2hex(random_bytes(12)).' */ ?>';
    $maintenanceHash=hash('sha256',$content);
    // Independent watchdog uses process start identity to avoid killing a reused PID.
    $pid=getmypid(); $stat=trim(file_get_contents('/proc/'.$pid.'/stat'));
    $identity=explode(' ',substr($stat,strrpos($stat,')')+2))[19];
    if (!ctype_digit($identity)) throw new RuntimeException('process identity');
    $watchdog=orgavela_capture_watchdog($work,$maintenance,$maintenanceHash,$pid,$identity,$dry?3:($abortToken?15:115),!$dry,$abortToken?$home.'/.orgavela-abort-'.$abortToken.'.watchdog.json':null);
    file_put_contents($work.'/watchdog.php',$watchdog);
    $phase('watchdog_starting');$launchAt=microtime(true);
    exec('/usr/bin/nohup '.escapeshellarg(PHP_BINARY).' '.escapeshellarg($work.'/watchdog.php').' > /dev/null 2>&1 < /dev/null &');
    $watchReady=microtime(true)+2;
    while (!is_file($work.'/ready') && microtime(true)<$watchReady) usleep(10000);
    if (!is_file($work.'/ready')) throw new RuntimeException('watchdog unavailable');
    $phase('watchdog_ready');
    $watchSeconds=round(microtime(true)-$launchAt,3);
    };
    if($unverifiedTls){
        $startWork();
        file_put_contents($work.'/bridge.php',orgavela_tls_bridge_source());
        $dumpArgs=array_merge($dumpArgs,orgavela_bridge_flags($work));
    }
    $databaseTls=false;
    if(!$abortOnly) {
        $help=$run([$unverifiedTls?$clientBinary:'mariadb','--no-defaults','--help'],null,false,3);
        if(!$unverifiedTls && (!str_contains($help,'--ssl-verify-server-cert') || !str_contains($help,'--protocol') || !str_contains($help,'--ssl-ca')))throw new RuntimeException('TLS verification unavailable');
        $client="[client]\n";
        foreach(['user'=>$config['DB_USER'],'password'=>$config['DB_PASSWORD'],'host'=>$host,'port'=>(string)$port]as$k=>$v)$client.=$k.'='.orgavela_client_value($v)."\n";
        $tls=$run(array_merge([$unverifiedTls?$clientBinary:'mariadb','--defaults-file=/proc/self/fd/3'],$unverifiedTls?orgavela_bridge_flags($work):orgavela_database_tls_args($peerFingerprint),['--connect-timeout=10','--batch','--skip-column-names','--execute',"SHOW SESSION STATUS LIKE 'Ssl_cipher'",'--',$config['DB_NAME']]),$client,false,12);
        if(!preg_match('/^Ssl_cipher\t[A-Za-z0-9_-]+$/D',trim($tls)))throw new RuntimeException('TLS not negotiated');
        unset($tls,$client,$help);$databaseTls=true;$phase('database_tls_verified');
    }
    if($filesystemOnly || $databaseOnly) {
        if(!$work){$work=$home.'/.orgavela-capture-'.bin2hex(random_bytes(12));if(!mkdir($work,0700))throw new RuntimeException('probe work');
        $self=orgavela_process_current(getmypid());
        file_put_contents($work.'/watchdog.php',orgavela_capture_watchdog($work,$work.'/unused-marker',hash('sha256','probe-no-marker'),getmypid(),$self['start'],90,true));
        exec('/usr/bin/nohup '.escapeshellarg(PHP_BINARY).' '.escapeshellarg($work.'/watchdog.php').' > /dev/null 2>&1 < /dev/null &');
        $end=microtime(true)+2;while(!is_file($work.'/ready')&&microtime(true)<$end)usleep(10000);
        if(!is_file($work.'/ready'))throw new RuntimeException('probe watchdog');}
    }
    if($filesystemOnly){
        $db->close();$db=null;$probeAt=microtime(true);
        // A discard-only diagnostic path; never changes production deadlines.
        $archive=$run(orgavela_capture_tar_args($root),null,false,60);
        $bytes=strlen($archive);unset($archive,$config);
        $result=['schema'=>'orgavela-filesystem-memory-probe/v2','ustar_paths_verified'=>true,'completed'=>true,'filesystem_entries'=>$entryCount,
            'compressed_bytes'=>$bytes,'seconds'=>round(microtime(true)-$probeAt,3),
            'maintenance_created'=>false,'table_locks_used'=>false,'file_data_logged'=>false,'data_discarded'=>true,'backup_saved'=>false];
        if($unverifiedTls){$result['schema']=str_replace('/v2','/v3',$result['schema']);$result=array_merge($result,['tls_verified'=>true,'server_certificate_verified'=>false,'database_tls_policy'=>'orgavela-backup-encrypted-unverified-20261107','database_tls_exception_authorization'=>'bd622f9fa9e926d7af866676af1a0224cb9d92c0','database_control_connection_tls_verified'=>true,'database_peer_continuity_enforced'=>true]);}
        $cleanup();echo json_encode($result,JSON_THROW_ON_ERROR)."\n";exit(0);
    }
    if($databaseOnly){
        $client="[client]\n";
        foreach(['user'=>$config['DB_USER'],'password'=>$config['DB_PASSWORD'],'host'=>$host,'port'=>(string)$port]as$k=>$v)$client.=$k.'='.orgavela_client_value($v)."\n";
        $name=$config['DB_NAME'];unset($config);$db->close();$db=null;
        $probeAt=microtime(true);$sql=$run(array_merge($dumpArgs,['--',$name]),$client,true,25);
        $bytes=strlen($sql);unset($sql,$client,$name);
        $result=['schema'=>'orgavela-database-dump-probe/v2','tls_verified'=>$databaseTls,'server_certificate_verified'=>!$unverifiedTls,'completed'=>true,'table_count'=>count($tables),
            'compressed_bytes'=>$bytes,'seconds'=>round(microtime(true)-$probeAt,3),
            'maintenance_created'=>false,'table_locks_used'=>false,'row_data_logged'=>false,'data_discarded'=>true,'backup_saved'=>false];
        if($unverifiedTls){$result['schema']=str_replace('/v2','/v3',$result['schema']);$result=array_merge($result,['tls_verified'=>true,'server_certificate_verified'=>false,'database_tls_policy'=>'orgavela-backup-encrypted-unverified-20261107','database_tls_exception_authorization'=>'bd622f9fa9e926d7af866676af1a0224cb9d92c0','database_control_connection_tls_verified'=>true,'database_peer_continuity_enforced'=>true]);}
        $cleanup();echo json_encode($result,JSON_THROW_ON_ERROR)."\n";exit(0);
    }
    if(defined('ORGAVELA_ABORT_PREFLIGHT') && ORGAVELA_ABORT_PREFLIGHT===true) {
        if(!$abortOnly || orgavela_capture_origin_status()!==200)throw new RuntimeException('preflight origin');
        if(!orgavela_lock_capture_tables($db,$tables)||!$db->query('UNLOCK TABLES')||!orgavela_probe_write_locks($db,$tables))throw new RuntimeException('preflight locks');
        $work=$home.'/.orgavela-capture-'.bin2hex(random_bytes(12));if(!mkdir($work,0700))throw new RuntimeException('preflight work');
        $fake=$root.'/.orgavela-hardlink-probe-'.bin2hex(random_bytes(12));
        $source=$work.'/maintenance-template';file_put_contents($source,'capability-only');
        if(!link($source,$fake))throw new RuntimeException('preflight hardlink');
        $probePipes=[];$active[]=orgavela_process_open(['/bin/sleep','30'],[0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']],$probePipes,$work);
        $owned=orgavela_process_stat(file_get_contents(glob($work.'/child-*.stat')[0]));
        $self=orgavela_process_current(getmypid());
        $code=orgavela_capture_watchdog($work,$fake,hash('sha256','capability-only'),getmypid(),$self['start'],1,false);
        file_put_contents($work.'/watchdog.php',$code);
        exec('/usr/bin/nohup '.escapeshellarg(PHP_BINARY).' '.escapeshellarg($work.'/watchdog.php').' > /dev/null 2>&1 < /dev/null &');
        $end=microtime(true)+5;
        do {usleep(100000);clearstatcache();}while(is_dir($work)&&microtime(true)<$end);
        if(is_dir($work)||file_exists($fake)||orgavela_process_members($owned))throw new RuntimeException('preflight watchdog');
        $cleanup();
        echo json_encode(['schema'=>'orgavela-abort-preflight/v1','runtime'=>PHP_VERSION,'posix_sessions'=>true,'proc_identity'=>true,'hardlink'=>true,'independent_watchdog'=>true,'table_scope'=>true,'table_locks'=>true,'origin_reachable'=>true,'maintenance_created'=>false,'snapshot_created'=>false],JSON_THROW_ON_ERROR)."\n";exit(0);
    }
    if($abortVerify) {
        $receipt=$home.'/.orgavela-abort-'.$abortToken.'.json';
        if(!$abortOnly || !$abortToken || is_link($receipt)||!is_file($receipt)||fileowner($receipt)!==posix_geteuid()||filesize($receipt)>32768)throw new RuntimeException('abort receipt');
        $completion=$home.'/.orgavela-abort-'.$abortToken.'.watchdog.json';
        if(is_link($completion)||!is_file($completion)||fileowner($completion)!==posix_geteuid()||filesize($completion)>1024)throw new RuntimeException('watchdog receipt');
        if(json_decode(file_get_contents($completion),true,8,JSON_THROW_ON_ERROR)!==['schema'=>'orgavela-watchdog-complete/v1','status'=>'complete'])throw new RuntimeException('watchdog incomplete');
        $proof=json_decode(file_get_contents($receipt),true,32,JSON_THROW_ON_ERROR);
        if(($proof['token']??null)!==$abortToken||($proof['work']??null)!==$home.'/.orgavela-capture-'.$abortToken)throw new RuntimeException('abort binding');
        if(is_dir($proof['work']))throw new RuntimeException('watchdog not complete');
        $parent=orgavela_process_current($proof['parent']['pid']);
        if($parent && $parent['start']===$proof['parent']['start'] && $parent['state']!=='Z')throw new RuntimeException('parent remains');
        foreach($proof['groups'] as $owned)if(orgavela_process_members($owned))throw new RuntimeException('group remains');
        if(file_exists($root.'/.maintenance')||!orgavela_probe_write_locks($db,$tables))throw new RuntimeException('abort cleanup incomplete');
        if(orgavela_capture_origin_status()!==200)throw new RuntimeException('origin not recovered');
        $cleanup();unlink($receipt);unlink($completion);
        echo json_encode(['schema'=>'orgavela-hard-disconnect/v1','watchdog_completed'=>true,'ssh_channel_killed'=>true,'own_processes_ended'=>true,'fresh_connection_write_locks'=>true,'maintenance_removed'=>true,'origin_recovered'=>true,'snapshot_created'=>false],JSON_THROW_ON_ERROR)."\n";exit(0);
    }
    $startWork();
    if ($dry) {
        if (!orgavela_lock_capture_tables($db,$tables)) throw new RuntimeException('bounded table probe');
        $phase('tables_locked');
        $client="[client]\n";
        foreach(['user'=>$config['DB_USER'],'password'=>$config['DB_PASSWORD'],'host'=>$host,'port'=>(string)$port] as $k=>$v)$client.=$k.'='.orgavela_client_value($v)."\n";
        $schema=$run(array_merge($dumpArgs,['--',$config['DB_NAME'],$table]),$client,true,4);
        unset($schema,$client,$config);
        if(!$db->query('UNLOCK TABLES'))throw new RuntimeException('probe unlock');$db->close();$db=null;
        $phase('schema_probe_complete');
        $result=['schema'=>'orgavela-capture-safety-preflight/v2','watchdog_ready'=>true,
            'watchdog_start_seconds'=>$watchSeconds,
            'maintenance_created'=>false,'database_rows_dumped'=>false,'schema_probe'=>true,'all_table_read_locks'=>true,
            'statement_time_limit_supported'=>$statementLimitSupported];
        $cleanup();echo json_encode($result,JSON_THROW_ON_ERROR)."\n";exit(0);
    }
    if (file_put_contents($work.'/maintenance-template',$content)!==strlen($content)) throw new RuntimeException('maintenance preparation');
    if (!chmod($work.'/maintenance-template',0644)) throw new RuntimeException('maintenance readability');
    // An atomic hard link cannot overwrite somebody else's maintenance file and
    // makes only the already-complete PHP marker visible to web requests.
    if (!@link($work.'/maintenance-template',$maintenance)) throw new RuntimeException('maintenance collision');
    $phase('maintenance_active');
    $maintenanceAt=microtime(true);
    if(orgavela_capture_origin_status()!==503)throw new RuntimeException('maintenance origin unverified');
    $phase('maintenance_origin_verified');usleep(2000000);
    if (!orgavela_lock_capture_tables($db,$tables)) throw new RuntimeException('read lock');
    $phase('tables_locked');
    if($abortOnly && $abortToken) {
        // Synthetic process only; no dump, TAR or encryption in this mode.
        $probePipes=[];
        $active[]=orgavela_process_open(['/bin/sleep','90'],[0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']],$probePipes,$work);
        $groups=[];foreach(glob($work.'/child-*.stat') as $file)$groups[]=orgavela_process_stat(file_get_contents($file));
        $proof=['token'=>$abortToken,'work'=>$work,'parent'=>orgavela_process_current(getmypid()),'groups'=>$groups];
        $receipt=$home.'/.orgavela-abort-'.$abortToken.'.json';
        $handle=fopen($receipt,'x');if(!$handle)throw new RuntimeException('receipt collision');
        $data=json_encode($proof,JSON_THROW_ON_ERROR);
        if(fwrite($handle,$data)!==strlen($data)||!fflush($handle))throw new RuntimeException('receipt write');fclose($handle);
        fwrite(STDOUT,"ORGAVELA_ABORT_ARMED\n");fflush(STDOUT);
        // Client kills its SSH session; independent watchdog must end this process.
        sleep(120);throw new RuntimeException('watchdog did not terminate abort');
    }
    if (defined('ORGAVELA_CAPTURE_ABORT_AFTER_LOCK') && ORGAVELA_CAPTURE_ABORT_AFTER_LOCK===true) throw new OrgaVelaControlledAbort('controlled abort');
    // Existing requests cannot commit DB writes; any still-running file writer makes this capture fail.
    usleep(500000); $before=orgavela_capture_inventory($root); $checkTime();
    $client="[client]\n";
    foreach (['user'=>$config['DB_USER'],'password'=>$config['DB_PASSWORD'],'host'=>$host,'port'=>(string)$port] as $k=>$v) $client.=$k.'='.orgavela_client_value($v)."\n";
    $buffers=[];
    $buffers['database.sql.gz']=$run(array_merge($dumpArgs,['--',$config['DB_NAME']]),$client,true);
    unset($client,$config);
    $phase('dump_complete');
    $buffers['wordpress-files.tar.gz']=$run(orgavela_capture_tar_args($root),null,false);
    $phase('files_complete');
    if (array_sum(array_map('strlen',$buffers))>134217728) throw new RuntimeException('combined memory limit');
    $after=orgavela_capture_inventory($root); $checkTime();
    if ($before!==$after) throw new RuntimeException('files changed during capture');
    orgavela_verify_tar_inventory($buffers['wordpress-files.tar.gz'],$before);
    $phase('archive_inventory_verified');
    $phase('files_verified');
    if (!$db->query('UNLOCK TABLES')) throw new RuntimeException('unlock'); $db->close();$db=null;
    if (!orgavela_remove_owned_maintenance($maintenance,$maintenanceHash)) throw new RuntimeException('maintenance cleanup');
    $phase('maintenance_removed');
    if(orgavela_capture_origin_status()!==200)throw new RuntimeException('origin recovery unverified');
    $phase('origin_recovered');
    $evidence=['schema'=>'orgavela-ssh-capture-evidence/v3','capture_mode'=>'production',
        'database_tls_verified'=>$databaseTls,'database_server_certificate_verified'=>!$unverifiedTls,
        'filesystem_consistency'=>'quiesced','database_consistency'=>'single-transaction',
        'quiescence'=>['wordpress_maintenance'=>true,'all_table_read_locks'=>true,'stable_file_hashes'=>true,'archive_matches_inventory'=>true,
            'maintenance_seconds'=>round(microtime(true)-$maintenanceAt,3),'maintenance_removed'=>true,
            'maintenance_origin_verified'=>true,'source_origin_recovered'=>true],
        'scope'=>['database'=>true,'wordpress_filesystem'=>true,'uploads'=>true,'wp_config'=>true],
        'table_count'=>count($tables),'filesystem_entries'=>count($after),
        'components'=>['database'=>['sha256'=>hash('sha256',$buffers['database.sql.gz'])],
            'wordpress'=>['sha256'=>hash('sha256',$buffers['wordpress-files.tar.gz'])]]];
    if($unverifiedTls){$evidence['schema']='orgavela-ssh-capture-evidence/v4';$evidence=array_merge($evidence,['database_tls_policy'=>'orgavela-backup-encrypted-unverified-20261107','database_tls_exception_authorization'=>'bd622f9fa9e926d7af866676af1a0224cb9d92c0','database_control_connection_tls_verified'=>true,'database_peer_continuity_enforced'=>true]);}
    $buffers['capture-evidence.json']=json_encode($evidence,JSON_THROW_ON_ERROR);
    // Capture is already complete and maintenance is off before any network transfer.
    $write=static function(string $data): void { $offset=0;while($offset<strlen($data)){$n=fwrite(STDOUT,substr($data,$offset,65536));if(!$n)throw new RuntimeException('transfer');$offset+=$n;} };
    foreach ($buffers as $name=>$data) { $size=strlen($data);$write(orgavela_tar_header($name,$size));$write($data);if($size%512)$write(str_repeat("\0",512-$size%512)); }
    $write(str_repeat("\0",1024)); unset($buffers,$data);
    $phase('stream_complete');
    $cleanup(); exit(0);
} catch (OrgaVelaControlledAbort $e) {
    $cleanup();
    try {
        if($cleanupStatus!=='complete')throw new RuntimeException('process cleanup unverified');
        clearstatcache(true,$maintenance);
        if(file_exists($maintenance))throw new RuntimeException('maintenance remains');
        // A fresh connection must acquire conflicting write locks after cleanup.
        $probe=@new mysqli($host,$config['DB_USER'],$config['DB_PASSWORD'],$config['DB_NAME'],$port);
        if($probe->connect_errno || !orgavela_probe_write_locks($probe,$tables))throw new RuntimeException('locks remain');
        $probe->close();unset($config);
        if(orgavela_capture_origin_status()!==200)throw new RuntimeException('origin not recovered');
        echo json_encode(['schema'=>'orgavela-controlled-abort/v2','own_processes_ended'=>true,'aborted_after_table_locks'=>true,
            'fresh_connection_write_locks'=>true,'maintenance_removed'=>true,'origin_recovered'=>true,
            'snapshot_created'=>false],JSON_THROW_ON_ERROR)."\n";exit(0);
    } catch(Throwable $failure) { fwrite(STDERR,"Controlled abort recovery unverified\n");exit(1); }
} catch (Throwable $e) { $cleanup(); fwrite(STDERR,"Quiesced capture failed; details suppressed\n"); exit(1); }
