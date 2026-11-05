<?php
// Standalone release regression; deliberately does not require dev dependencies.
if (PHP_SAPI !== 'cli') exit(1);
$root=dirname(__DIR__);
$zip=new ZipArchive();
if ($zip->open($root.'/dist/uop-core.zip')!==true) throw new RuntimeException('Release ZIP missing');
for($i=0;$i<$zip->numFiles;$i++) {
    $name=$zip->getNameIndex($i);
    if (preg_match('~^uop-core/(bin|tests|node_modules|docs|\.github)/~',$name)) throw new RuntimeException('Development file in ZIP: '.$name);
}
if (!str_contains($zip->getFromName('uop-core/uop-core.php'),'Version: 0.1.0-alpha.2')) throw new RuntimeException('Wrong release version');
if ($zip->getFromName('uop-core/schema/manifest.json')!==file_get_contents($root.'/schema/manifest.json')) throw new RuntimeException('Packaged schema changed');
$zip->close();
$directory=sys_get_temp_dir().'/uop-http-'.bin2hex(random_bytes(8));
mkdir($directory);
copy($root.'/bin/package.php',$directory.'/package.php');
$socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
if (!$socket) throw new RuntimeException($error);
$address=stream_socket_get_name($socket,false); fclose($socket);
$server=proc_open([PHP_BINARY,'-S',$address,'-t',$directory],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
if (!is_resource($server)) throw new RuntimeException('Test HTTP server failed');
try {
    $context=stream_context_create(['http'=>['ignore_errors'=>true,'timeout'=>1]]);
    $deadline=microtime(true)+10;
    do {
        $http_response_header=[];
        $body=@file_get_contents('http://'.$address.'/package.php',false,$context);
        if ($body!==false) break;
        usleep(50000);
    } while(microtime(true)<$deadline);
    if (!str_contains($http_response_header[0]??'','403') || $body!=='') throw new RuntimeException('Packaging script did not reject HTTP before loading dependencies');
    if (array_values(array_diff(scandir($directory),['.','..','package.php']))!==[]) throw new RuntimeException('HTTP request wrote files');
    echo "PASS: ZIP excludes development scripts; source packaging endpoint returns 403 without side effects.\n";
} finally {
    proc_terminate($server); foreach($pipes as $pipe)fclose($pipe); proc_close($server);
    unlink($directory.'/package.php'); rmdir($directory);
}
