<?php
// Restore into an EMPTY, migrated database only; existing databases are never cleared.
require __DIR__.'/../app/bootstrap.php';require ROOT.'/app/backup.php';
if(PHP_SAPI!=='cli')exit;
umask(0077);$file=$argv[1]??'';$confirm=$argv[2]??'';$created=[];$zip=new ZipArchive();
try {
    if($confirm!=='--confirm-database='.query('SELECT DATABASE()')->fetchColumn())throw new RuntimeException('Specify backup path and --confirm-database=the_empty_target_database.');
    if(!is_file($file)||$zip->open($file)!==true)throw new RuntimeException('Cannot open backup.');
    $manifest=json_decode($zip->getFromName('manifest.json')?:'',true,512,JSON_THROW_ON_ERROR);$json=$zip->getFromName('database.json');
    if(($manifest['format']??0)!==1||!is_string($json)||hash('sha256',$json)!==$manifest['database_sha256'])throw new RuntimeException('Invalid backup manifest.');
    $data=json_decode($json,true,512,JSON_THROW_ON_ERROR);
    if(array_keys($data)!==BACKUP_TABLES)throw new RuntimeException('Backup table mismatch.');
    foreach(BACKUP_TABLES as $table)if((int)query("SELECT COUNT(*) FROM `$table`")->fetchColumn())throw new RuntimeException('Target database must be empty. Existing records were preserved.');
    $expected=[];foreach($data['media'] as $m)$expected[backup_media_path($m['path'])]=true;
    $a=array_keys($expected);$b=array_keys($manifest['files']);sort($a);sort($b);if($a!==$b)throw new RuntimeException('Media manifest mismatch.');
    foreach($manifest['files'] as $relative=>$info){
        $stream=$zip->getStream($relative);if(!$stream)throw new RuntimeException('Missing archived media.');$hash=hash_init('sha256');$bytes=hash_update_stream($hash,$stream);fclose($stream);
        if($bytes!==$info['bytes']||hash_final($hash)!==$info['sha256'])throw new RuntimeException('Media checksum mismatch.');
        $target=ROOT.'/'.$relative;if(is_file($target)){if(hash_file('sha256',$target)!==$info['sha256'])throw new RuntimeException('Existing media differs; use a clean release directory.');continue;}
        if(!is_dir(dirname($target)))mkdir(dirname($target),0700,true);$out=fopen($target,'x');if(!$out)throw new RuntimeException('Cannot create restored media.');$created[]=$target;$in=$zip->getStream($relative);stream_copy_to_stream($in,$out);fclose($in);fclose($out);chmod($target,str_starts_with($relative,'public/')?0644:0600);
    }
    db()->beginTransaction();
    foreach(BACKUP_TABLES as $table){$columns=array_column(query("SHOW COLUMNS FROM `$table`")->fetchAll(),'Field');foreach($data[$table] as $row){if(array_keys($row)!==$columns)throw new RuntimeException('Schema version mismatch.');$names=implode(',',array_map(fn($c)=>'`'.$c.'`',$columns));query("INSERT INTO `$table` ($names) VALUES (".implode(',',array_fill(0,count($row),'?')).')',array_values($row));}}
    // File timestamps may change during recovery. Only rebind a verified prepared copy.
    $pack=setting('profile_web',[]);if($pack){$source=media((int)($pack['source_id']??0));if($source&&hash_file('sha256',media_file($source))===($pack['source_sha256']??'')){$pack['source_mtime']=filemtime(media_file($source));set_setting('profile_web',$pack);}else{set_setting('profile_web',[]);}}
    db()->commit();$zip->close();echo "Database and media restored into the empty target. Configuration and release code must be supplied separately.\n";
}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();foreach($created as $path)if(is_file($path))unlink($path);fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
