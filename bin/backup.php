<?php
require __DIR__.'/../app/bootstrap.php';require ROOT.'/app/backup.php';
if(PHP_SAPI!=='cli')exit;
umask(0077);$dir=ROOT.'/storage/backups';if(!is_dir($dir))mkdir($dir,0700,true);
$path=$dir.'/backup-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'.zip';$zip=new ZipArchive();
try {
    if($zip->open($path,ZipArchive::CREATE|ZipArchive::EXCL)!==true)throw new RuntimeException('Cannot create backup.');
    chmod($path,0600);query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');db()->beginTransaction();$data=[];
    foreach(BACKUP_TABLES as $table)$data[$table]=query("SELECT * FROM `$table`")->fetchAll();
    $files=[];
    foreach($data['media'] as $m){$relative=backup_media_path($m['path']);$file=ROOT.'/'.$relative;if(!is_file($file))throw new RuntimeException('A media file is missing.');$files[$relative]=['sha256'=>hash_file('sha256',$file),'bytes'=>filesize($file)];if(!$zip->addFile($file,$relative))throw new RuntimeException('Could not archive media.');}
    $json=json_encode_safe($data);$zip->addFromString('database.json',$json);
    $zip->addFromString('manifest.json',json_encode_safe(['format'=>1,'created_utc'=>gmdate('c'),'database_sha256'=>hash('sha256',$json),'files'=>$files]));
    if(!$zip->close())throw new RuntimeException('Could not finish archive.');
    $verify=new ZipArchive();if($verify->open($path)!==true)throw new RuntimeException('Cannot verify archive.');
    foreach($files as $relative=>$info){$stream=$verify->getStream($relative);if(!$stream)throw new RuntimeException('Archive member missing.');$hash=hash_init('sha256');hash_update_stream($hash,$stream);fclose($stream);if(hash_final($hash)!==$info['sha256'])throw new RuntimeException('Media changed during backup; retry.');}$verify->close();db()->commit();
    echo $path.PHP_EOL;
} catch(Throwable $e){if(db()->inTransaction())db()->rollBack();if(is_file($path))unlink($path);fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
