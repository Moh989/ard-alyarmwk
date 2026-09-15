<?php
// Creates only random isolated QA databases, restores a private backup, and removes its fixtures.
if(PHP_SAPI!=='cli')exit;
$root=dirname(__DIR__);$admin=parse_ini_file($root.'/storage/mysql-root.cnf',true)['client'];$server=new PDO('mysql:host='.$admin['host'].';port='.$admin['port'].';charset=utf8mb4',$admin['user'],$admin['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$names=[];$configs=[];$archives=[];$results=[];$upload=$root.'/storage/uploads/'.bin2hex(random_bytes(20)).'.webp';
function commandQA(string $config,array $args,bool $success=true): string {
    global $root;$p=proc_open(array_merge([PHP_BINARY],$args),[1=>['pipe','w'],2=>['pipe','w']],$pipes,$root,array_merge(getenv(),['ARD_CONFIG'=>$config]));$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($p);
    if(($code===0)!==$success)throw new RuntimeException('Unexpected command result: '.$args[0]);return trim($out);
}
try {
    foreach(['source','target'] as $key){$name='ard_backup_qa_'.bin2hex(random_bytes(6));$server->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$names[$key]=$name;$c=require $root.'/config/local.php';$c['db_dsn']='mysql:host='.$admin['host'].';port='.$admin['port'].';dbname='.$name.';charset=utf8mb4';$c['db_user']=$admin['user'];$c['db_password']=$admin['password'];$path=$root.'/storage/'.$name.'.php';$f=fopen($path,'x');chmod($path,0600);fwrite($f,'<?php return '.var_export($c,true).';');fclose($f);$configs[$key]=$path;$dbs[$key]=new PDO($c['db_dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);commandQA($path,['bin/migrate.php']);}
    commandQA($configs['source'],['bin/seed.php']);$source=$dbs['source'];
    $q=$source->prepare('INSERT INTO admins(email,name,password_hash) VALUES(?,?,?)');$q->execute(['backup-test@example.test','Backup QA',password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT)]);
    $source->exec("INSERT INTO contact_messages(reference,locale,name,email,message,fingerprint) VALUES('BACKUPQA0001','ar','اختبار الاستعادة','qa@example.test','رسالة اختبار محلية في قاعدة معزولة',REPEAT('a',64))");
    copy($root.'/public/assets/images/energy-640.webp',$upload);$sha=hash_file('sha256',$upload);$q=$source->prepare('INSERT INTO media(path,original_name,mime,size_bytes,source) VALUES(?,?,?,?,?)');$q->execute([basename($upload),'qa.webp','image/webp',filesize($upload),'Isolated backup QA fixture']);
    $archive=commandQA($configs['source'],['bin/backup.php']);$archives[]=$archive;
    if((fileperms($archive)&0777)!==0600)throw new RuntimeException('Backup permissions too broad.');
    unlink($upload);commandQA($configs['target'],['bin/restore.php',$archive,'--confirm-database='.$names['target']]);
    foreach(['pages','sectors','translations','settings','media','admins','contact_messages'] as $table)if($source->query("SELECT COUNT(*) FROM `$table`")->fetchColumn()!=$dbs['target']->query("SELECT COUNT(*) FROM `$table`")->fetchColumn())throw new RuntimeException('Restored count differs: '.$table);
    if(hash_file('sha256',$upload)!==$sha||$source->query('SELECT password_hash FROM admins')->fetchColumn()!==$dbs['target']->query('SELECT password_hash FROM admins')->fetchColumn())throw new RuntimeException('Restored media/admin mismatch.');
    $results[]=['name'=>'Private full backup restores content, admin password hash, contact message and uploaded media into an isolated empty database','passed'=>true];
    commandQA($configs['target'],['bin/restore.php',$archive,'--confirm-database='.$names['target']],false);commandQA($configs['target'],['bin/restore.php',$archive,'--confirm-database=wrong'],false);
    if($dbs['target']->query('SELECT COUNT(*) FROM contact_messages')->fetchColumn()!=1)throw new RuntimeException('Existing data changed.');
    $results[]=['name'=>'Restore rejects a nonempty database and wrong confirmation without deleting existing data','passed'=>true];
}catch(Throwable $e){$results[]=['name'=>'Failure','passed'=>false,'error'=>$e->getMessage()];$failed=true;}
finally {foreach($names as $name)$server->exec("DROP DATABASE `$name`");foreach([...$configs,...$archives,$upload] as $file)if(is_file($file))unlink($file);file_put_contents($root.'/tests/backup-restore-results.json',json_encode($results,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));}
echo json_encode($results,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;exit(!empty($failed)?1:0);
