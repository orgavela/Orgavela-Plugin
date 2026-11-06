<?php
if(!function_exists('orgavela_process_stop_groups'))require __DIR__.'/backup_process_safety.php';
/** Pure bounded filesystem helpers; no database or network access. */
final class OrgaVelaInventoryRejection extends RuntimeException {
    private ?string $privateRelativeFile;
    public function __construct(string $code, ?string $privateRelativeFile=null) {
        parent::__construct($code);
        $this->privateRelativeFile=$privateRelativeFile;
    }
    public function privateRelativeFile(): ?string { return $this->privateRelativeFile; }
}
/** One source of truth: the same exact omission for probe and production TAR. */
function orgavela_capture_tar_args(string $root): array {
    return ['tar','--create','--format=ustar','--gzip','--file','-',
        '--exclude=./.maintenance',
        '--directory',$root,'.'];
}

function orgavela_capture_inventory(string $root): array {
    $result = [];
    $total = 0;
    $walk = static function(string $dir) use (&$walk, &$result, &$total, $root): void {
        foreach (new DirectoryIterator($dir) as $entry) {
            if ($entry->isDot()) continue;
            $path = $entry->getPathname();
            $relative = substr($path, strlen($root) + 1);
            if ($relative === '.maintenance') continue;
            // Exact original fail-closed checks; only fixed non-identifying error categories differ.
            if (str_contains($relative, "\n") || str_contains($relative, "\r") || str_contains($relative, "\\")) throw new OrgaVelaInventoryRejection('inventory_invalid_name');
            if (count($result) >= 50000) throw new OrgaVelaInventoryRejection('inventory_entry_limit');
            if ($entry->isLink()) throw new OrgaVelaInventoryRejection('inventory_symlink');
            if ($entry->isDir()) { $result[$relative . '/'] = 'directory'; $walk($path); }
            elseif ($entry->isFile()) {
                $total += $entry->getSize();
                if ($total > 1073741824) throw new OrgaVelaInventoryRejection('inventory_total_size');
                $hash = hash_file('sha256', $path);
                if ($hash === false) throw new OrgaVelaInventoryRejection('inventory_unreadable_file',$relative);
                $result[$relative] = $hash;
            } else throw new OrgaVelaInventoryRejection('inventory_unsupported_entry');
        }
    };
    $walk($root); ksort($result, SORT_STRING);
    return $result;
}

function orgavela_remove_owned_maintenance(string $path, string $hash): bool {
    if (!file_exists($path)) return true;
    if (is_link($path) || !hash_equals($hash, (string)hash_file('sha256', $path))) return false;
    return unlink($path);
}

function orgavela_client_value(string $value): string {
    if (str_contains($value, "\0")) throw new RuntimeException('invalid client value');
    return '"' . strtr($value, ['\\'=>'\\\\', '"'=>'\\"', "\n"=>'\\n', "\r"=>'\\r', "\t"=>'\\t']) . '"';
}

function orgavela_tar_header(string $name, int $size): string {
    if (!in_array($name,['database.sql.gz','wordpress-files.tar.gz','capture-evidence.json'],true) || $size<0 || $size>134217728) throw new RuntimeException('invalid tar member');
    $header=str_pad($name,100,"\0").sprintf('%07o',0600)."\0".sprintf('%07o',0)."\0".sprintf('%07o',0)."\0".
        sprintf('%011o',$size)."\0".sprintf('%011o',time())."\0".str_repeat(' ',8).'0'.str_repeat("\0",100).
        "ustar\00000".str_repeat("\0",32+32+8+8+155+12);
    if (strlen($header)!==512) throw new RuntimeException('invalid tar header');
    $sum=array_sum(unpack('C*',$header));
    return substr_replace($header,sprintf('%06o',$sum)."\0 ",148,8);
}

function orgavela_lock_capture_tables(mysqli $db, array $tables): bool {
    // InnoDB table locks require autocommit=0 and innodb_table_locks=1.
    if (!$db->query('SET SESSION lock_wait_timeout=2, innodb_lock_wait_timeout=2') || !$db->autocommit(false)) return false;
    $r=$db->query('SELECT @@innodb_table_locks');
    if (!$r || (int)$r->fetch_row()[0]!==1) return false;
    return (bool)$db->query('LOCK TABLES '.implode(',',array_map(static fn($t)=>'`'.$t.'` READ',$tables)).' WAIT 2');
}

function orgavela_dump_client_args(string $help, bool $schema): array {
    if (!str_contains($help,'--defaults-file')) throw new RuntimeException('controlled defaults unsupported');
    $args=['mariadb-dump','--defaults-file=/proc/self/fd/3','--single-transaction','--skip-lock-tables'];
    if (str_contains($help,'--max-statement-time')) $args[]='--max-statement-time='.($schema?'2':'15');
    return array_merge($args,$schema ? ['--skip-triggers','--no-data'] : ['--quick','--hex-blob','--default-character-set=utf8mb4','--skip-comments']);
}

function orgavela_capture_parse_status(array $headers): int {
    foreach($headers as $line)if(is_string($line)&&preg_match('~^HTTP/\\S+\\s+(\\d{3})\\b~',$line,$m))return(int)$m[1];
    throw new RuntimeException('origin status unavailable');
}

function orgavela_capture_origin_status(): int {
    $context=stream_context_create(['http'=>['timeout'=>5,'ignore_errors'=>true,'follow_location'=>0,
        'header'=>"Cache-Control: no-cache\r\nPragma: no-cache\r\n"]]);
    $headers=@get_headers('https://orgavela.com/?rest_route=/&orgavela_backup_probe='.bin2hex(random_bytes(12)),false,$context);
    if(!is_array($headers))throw new RuntimeException('origin status unavailable');
    return orgavela_capture_parse_status($headers);
}


function orgavela_validate_tables(array $tables, string $prefix): bool {
    if (count($tables)!==52 || count(array_unique($tables))!==52 || !preg_match('/^[A-Za-z0-9_]{1,64}$/D',$prefix)) return false;
    foreach ($tables as $table) if (!is_string($table) || !preg_match('/^[A-Za-z0-9_]+$/D',$table) || !str_starts_with($table,$prefix)) return false;
    foreach (['options','posts','users','usermeta','comments','uop_organizations','uop_persons','uop_consent_records','uop_registrations','uop_waitlist_entries'] as $name)
        if (!in_array($prefix.$name,$tables,true)) return false;
    return true;
}

/** Stream verified gzip/USTAR contents against the stable source inventory. */
function orgavela_verify_tar_inventory(string $gzip, array $inventory): void {
    $inflater=inflate_init(ZLIB_ENCODING_GZIP);
    if ($inflater===false) throw new RuntimeException('archive inflater');
    $buffer='';$state='header';$seen=[];$rootSeen=false;$remaining=0;$padding=0;$hash=null;$current='';$zeros=0;$expanded=0;
    $consume=static function() use (&$buffer,&$state,&$seen,&$rootSeen,&$remaining,&$padding,&$hash,&$current,&$zeros,$inventory): void {
        while(true) {
            if($state==='tail') {
                if(trim($buffer,"\0")!=='')throw new RuntimeException('archive trailing data');
                $buffer='';return;
            }
            if($state==='file') {
                $take=min(strlen($buffer),$remaining);
                if($take>0){hash_update($hash,substr($buffer,0,$take));$buffer=substr($buffer,$take);$remaining-=$take;}
                if($remaining>0)return;
                if(!hash_equals($inventory[$current],hash_final($hash)))throw new RuntimeException('archive file mismatch');
                $hash=null;$seen[$current]=true;$state='padding';
            }
            if($state==='padding') {
                $take=min(strlen($buffer),$padding);
                if(trim(substr($buffer,0,$take),"\0")!=='')throw new RuntimeException('archive nonzero padding');
                $buffer=substr($buffer,$take);$padding-=$take;
                if($padding>0)return;
                $state='header';
            }
            if(strlen($buffer)<512)return;
            $header=substr($buffer,0,512);$buffer=substr($buffer,512);
            if($header===str_repeat("\0",512)) {
                ++$zeros;if($zeros===2)$state='tail';continue;
            }
            if($zeros!==0)throw new RuntimeException('archive embedded end');
            $checksum=trim(substr($header,148,8),"\0 ");
            $sum=array_sum(unpack('C*',substr_replace($header,str_repeat(' ',8),148,8)));
            if(!preg_match('/^[0-7]+$/D',$checksum)||octdec($checksum)!==$sum||substr($header,257,6)!=="ustar\0")throw new RuntimeException('archive header');
            $name=rtrim(substr($header,0,100),"\0");$prefix=rtrim(substr($header,345,155),"\0");
            if($prefix!=='')$name=$prefix.'/'.$name;
            $type=$header[156];$sizeField=trim(substr($header,124,12),"\0 ");
            if(!preg_match('/^[0-7]+$/D',$sizeField))throw new RuntimeException('archive size');
            $size=octdec($sizeField);
            if($size>1073741824||!in_array($type,['0',"\0",'5'],true))throw new RuntimeException('archive type or bound');
            if(in_array($name,['.','./'],true)) {
                if($rootSeen||$type!=='5'||$size!==0)throw new RuntimeException('archive root');
                $rootSeen=true;continue;
            }
            if(str_starts_with($name,'./'))$name=substr($name,2);
            $name=rtrim($name,'/');
            if($name===''||str_starts_with($name,'/')||str_contains($name,"\\")||preg_match('/[\x00-\x1f\x7f]/',$name)||array_intersect(explode('/',$name),['','..','.']))throw new RuntimeException('archive path');
            $current=$name.($type==='5'?'/':'');
            if(!array_key_exists($current,$inventory)||isset($seen[$current]))throw new RuntimeException('archive inventory member');
            if($type==='5') {
                if($size!==0||$inventory[$current]!=='directory')throw new RuntimeException('archive directory mismatch');
                $seen[$current]=true;continue;
            }
            if(!is_string($inventory[$current])||!preg_match('/^[a-f0-9]{64}$/D',$inventory[$current]))throw new RuntimeException('archive file type mismatch');
            $hash=hash_init('sha256');$remaining=$size;$padding=(512-$size%512)%512;$state='file';
        }
    };
    for($offset=0;$offset<strlen($gzip);$offset+=1024) {
        if(inflate_get_status($inflater)===ZLIB_STREAM_END)throw new RuntimeException('archive compressed suffix');
        $plain=@inflate_add($inflater,substr($gzip,$offset,1024),ZLIB_SYNC_FLUSH);
        if($plain===false)throw new RuntimeException('archive gzip');
        $expanded+=strlen($plain);if($expanded>2147483648)throw new RuntimeException('archive expansion');
        $buffer.=$plain;$consume();
    }
    if(inflate_get_status($inflater)!==ZLIB_STREAM_END||inflate_get_read_len($inflater)!==strlen($gzip))throw new RuntimeException('archive incomplete gzip');
    $consume();
    if($state!=='tail'||count($seen)!==count($inventory))throw new RuntimeException('archive incomplete inventory');
}


final class OrgaVelaControlledAbort extends RuntimeException {}

/** Independent, bounded conflict probe; caller owns the fresh connection. */
function orgavela_probe_write_locks(mysqli $db, array $tables): bool {
    foreach($tables as $table)if(!is_string($table)||!preg_match('/^[A-Za-z0-9_]+$/D',$table))return false;
    if(!$tables || !$db->query('SET SESSION lock_wait_timeout=2, innodb_lock_wait_timeout=2') || !$db->autocommit(false))return false;
    $ok=(bool)$db->query('LOCK TABLES '.implode(',',array_map(static fn($t)=>'`'.$t.'` WRITE',$tables)).' WAIT 2');
    return $db->query('UNLOCK TABLES') && $ok;
}

/** The same independent watchdog is exercised by the synthetic abort rehearsal. */
function orgavela_capture_watchdog(string $work,string $maintenance,string $hash,int $pid,string $identity,int $seconds,bool $terminate): string {
    if($seconds<1||$seconds>115||!ctype_digit($identity)||$pid<2||!preg_match('/^[a-f0-9]{64}$/D',$hash))throw new RuntimeException('watchdog binding');
    return '<?php '.orgavela_process_helper_source().' $w='.var_export($work,true).'; file_put_contents($w."/ready","ready"); $end=microtime(true)+'.$seconds.'; while(microtime(true)<$end){clearstatcache(true,$w);if(!is_dir($w)||is_link($w))exit;usleep(100000);} if(!is_dir($w)||is_link($w))exit;
    $pid='.$pid.'; $identity='.var_export($identity,true).'; $parent=orgavela_process_current($pid);
    if('.($terminate?'true':'false').' && $parent && $parent["start"]===$identity)posix_kill($pid,19);
    usleep(100000); $stopped=orgavela_process_stop_groups($w);
    $parent=orgavela_process_current($pid);
    if('.($terminate?'true':'false').' && $parent && $parent["start"]===$identity)posix_kill($pid,9);
    if(!$stopped)exit(71);
    $p='.var_export($maintenance,true).'; $h='.var_export($hash,true).'; if(is_file($p)&&!is_link($p)&&hash_equals($h,(string)hash_file("sha256",$p)))unlink($p);
    foreach(glob($w."/child-*.stat") as $f)unlink($f);
    foreach(["database.sql.gz","wordpress-files.tar.gz","capture-evidence.json","watchdog.php","maintenance-template","ready"] as $f)@unlink($w."/".$f); @rmdir($w);';
}
