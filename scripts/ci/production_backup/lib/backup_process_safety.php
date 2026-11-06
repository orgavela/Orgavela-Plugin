<?php
/** Linux ABI signals: STOP=19, CONT=18, KILL=9; no PCNTL dependency. */
/** Linux process sessions owned exclusively by this capture, no command-string interpolation. */
function orgavela_process_stat(string $stat): array {
    if(!preg_match('/^([1-9][0-9]*) \(/D',$stat,$m)||($end=strrpos($stat,')'))===false)throw new RuntimeException('process stat');
    $parts=explode(' ',trim(substr($stat,$end+1)));
    if(count($parts)<20||!ctype_digit($parts[19]))throw new RuntimeException('process identity');
    return ['pid'=>(int)$m[1],'state'=>$parts[0],'ppid'=>(int)$parts[1],'group'=>(int)$parts[2],'session'=>(int)$parts[3],'start'=>$parts[19]];
}

function orgavela_process_current(int $pid): ?array {
    $s=@file_get_contents('/proc/'.$pid.'/stat');
    return $s===false?null:orgavela_process_stat($s);
}

/** Child registers its session atomically and stops before executing any payload. */
function orgavela_process_open(array $args,array $spec,array &$pipes,string $work) {
    $record=$work.'/child-'.bin2hex(random_bytes(12)).'.stat';
    $script='umask 077; cat "/proc/$$/stat" > "$1.tmp" && mv "$1.tmp" "$1" || exit 70; kill -STOP "$$"; shift; exec "$@"';
    $process=proc_open(array_merge(['/usr/bin/setsid','/bin/sh','-c',$script,'orgavela-capture',$record],$args),$spec,$pipes);
    if(!is_resource($process))throw new RuntimeException('process session launch');
    $end=microtime(true)+2;
    do {
        $status=proc_get_status($process);
        $current=orgavela_process_current($status['pid']);
        if(is_file($record)&&$current && $current['state']==='T') {
            $owned=orgavela_process_stat(file_get_contents($record));
            if($owned['pid']!==$status['pid']||$owned['group']!==$owned['pid']||$owned['session']!==$owned['pid']||$owned['start']!==$current['start'])break;
            if(!posix_kill($owned['pid'],18))break;
            return $process;
        }
        usleep(10000);
    }while($status['running']&&microtime(true)<$end);
    @proc_terminate($process,9);orgavela_process_stop_groups($work);@proc_close($process);
    throw new RuntimeException('process session unverified');
}

/** Validated session membership remains identifiable after the PHP parent dies. */
function orgavela_process_members(array $owned): array {
    $pid=$owned['pid'];
    if($pid<2||$pid===getmypid()||$pid===posix_getsid(getmypid())||$owned['group']!==$pid||$owned['session']!==$pid)throw new RuntimeException('foreign process session');
    $leader=orgavela_process_current($pid);
    if($leader && $leader['start']!==$owned['start'])throw new RuntimeException('reused process identity');
    $members=[];
    foreach(glob('/proc/[0-9]*/stat') as $file) {
        $stat=@file_get_contents($file);if($stat===false)continue;
        $member=orgavela_process_stat($stat);
        if($member['session']!==$pid||$member['group']!==$pid||$member['state']==='Z')continue;
        if(fileowner(dirname($file))!==posix_geteuid()||(float)$member['start']<(float)$owned['start'])throw new RuntimeException('foreign process member');
        $members[]=$member;
    }
    return $members;
}

/** Kill whole sessions, including gzip grandchildren; never a caller's inherited group. */
function orgavela_process_stop_groups_result(string $work, ?callable $signal=null, float $wait=1.0): array {
    $signal=$signal??static fn($pid,$number)=>posix_kill($pid,$number);
    $unknown=false;$survivors=false;
    // A temporary registration is not a veto on already committed sessions.
    // Complete temporary identities use the same session/start/UID validation.
    $records=array_merge(glob($work.'/child-*.stat'),glob($work.'/child-*.stat.tmp'));
    if(count($records)>64)$unknown=true;
    foreach($records as $record) {
        try {
            if(is_link($record)||filesize($record)>4096)throw new RuntimeException('process record');
            $raw=file_get_contents($record);
            // Incomplete identities never authorize signals; report failure after other groups.
            $owned=orgavela_process_stat($raw);
            if(orgavela_process_members($owned)) {
                $signal(-$owned['pid'],19);
                $signal(-$owned['pid'],9);
            }
            $end=microtime(true)+$wait;
            while(orgavela_process_members($owned)&&microtime(true)<$end)usleep(10000);
            if(orgavela_process_members($owned))$survivors=true;
        }catch(Throwable $failure){$unknown=true;}
    }
    return ['status'=>$survivors?'survivors':($unknown?'unknown':'complete')];
}

function orgavela_process_stop_groups(string $work): bool {
    return orgavela_process_stop_groups_result($work)['status']==='complete';
}

function orgavela_process_helper_source(): string {
    $source=defined('ORGAVELA_PROCESS_HELPERS_BASE64')?base64_decode(ORGAVELA_PROCESS_HELPERS_BASE64,true):file_get_contents(__FILE__);
    if(!is_string($source)||!str_starts_with($source,'<?php'))throw new RuntimeException('process helper source');
    return substr($source,5);
}
