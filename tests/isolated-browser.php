<?php
// Run the mutating acceptance suite against its own disposable database and HTTP server.
if(PHP_SAPI!=='cli')exit;
$suite=$argv[1]??'browser';if(!in_array($suite,['browser','admin-sliders'],true)){fwrite(STDERR,"Unknown isolated suite.\n");exit(1);}
$root=dirname(__DIR__);$admin=parse_ini_file($root.'/storage/mysql-root.cnf',true)['client'];
$pdo=new PDO('mysql:host='.$admin['host'].';port='.$admin['port'].';charset=utf8mb4',$admin['user'],$admin['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$name='ard_browser_qa_'.bin2hex(random_bytes(6));$configFile=$root.'/storage/'.$name.'.php';$authFile=$root.'/storage/'.$name.'.txt';$server=null;$created=false;$status=1;
function runIsolated(array $command,array $env): string {
    global $root;$p=proc_open($command,[1=>['pipe','w'],2=>['pipe','w']],$pipes,$root,$env);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($p)!==0)throw new RuntimeException('Isolated test command failed: '.$command[1]);return $out;
}
try{
    $pdo->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$created=true;
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$message);if(!$socket)throw new RuntimeException('Could not reserve local port.');$address=stream_socket_get_name($socket,false);fclose($socket);
    $config=require $root.'/config/local.php';$config['db_dsn']='mysql:host='.$admin['host'].';port='.$admin['port'].';dbname='.$name.';charset=utf8mb4';$config['db_user']=$admin['user'];$config['db_password']=$admin['password'];$config['base_url']='http://'.$address;$config['env']='local';$config['mail_transport']='log';$config['require_approved']=false;$config['session_secure']=false;
    $f=fopen($configFile,'x');chmod($configFile,0600);fwrite($f,'<?php return '.var_export($config,true).';');fclose($f);
    $env=array_merge(getenv(),['ARD_CONFIG'=>$configFile,'TEST_URL'=>$config['base_url'],'TEST_ADMIN_CREDENTIALS'=>$authFile]);
    runIsolated([PHP_BINARY,'bin/migrate.php'],$env);runIsolated([PHP_BINARY,'bin/seed.php'],$env);
    $credentials=runIsolated([PHP_BINARY,'bin/create-admin.php','--email=qa-admin@example.test','--generate'],$env);
    $f=fopen($authFile,'x');chmod($authFile,0600);fwrite($f,$credentials);fclose($f);unset($credentials);
    $server=proc_open([PHP_BINARY,'-d','upload_max_filesize=40M','-d','post_max_size=42M','-S',$address,'-t','public','public/router.php'],[0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']],$pipes,$root,$env);
    $ready=false;for($i=0;$i<50;$i++){$probe=@fopen($config['base_url'].'/ar/','r');if($probe){fclose($probe);$ready=true;break;}usleep(100000);}if(!$ready)throw new RuntimeException('Isolated HTTP server did not start.');
    $p=proc_open(['node','tests/'.$suite.'.mjs'],[0=>STDIN,1=>STDOUT,2=>STDERR],$pipes,$root,$env);$status=proc_close($p);
}catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);}
finally{
    if(is_resource($server)){proc_terminate($server);proc_close($server);}
    if($created)$pdo->exec("DROP DATABASE `$name`");
    foreach([$configFile,$authFile] as $path)if(is_file($path))unlink($path);
}
exit($status);
