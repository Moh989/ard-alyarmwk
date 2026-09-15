<?php
require __DIR__.'/../app/bootstrap.php';
if(PHP_SAPI!=='cli')exit;
$tables=['pages','sectors','projects','translations','media','settings','contact_messages','rate_limits'];$file=ROOT.'/storage/test-snapshot.json';
if(($argv[1]??'')==='save'){$snapshot=[];foreach($tables as $table)$snapshot[$table]=query("SELECT * FROM $table")->fetchAll();file_put_contents($file,json_encode_safe($snapshot));chmod($file,0600);echo "Test snapshot saved.\n";}
elseif(($argv[1]??'')==='restore'){
 $data=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);db()->beginTransaction();
 foreach(['contact_messages','translations','projects','pages','sectors','media','settings','rate_limits'] as $table)query("DELETE FROM $table");
 foreach($tables as $table)foreach($data[$table] as $r){$columns=implode(',',array_map(fn($k)=>'`'.$k.'`',array_keys($r)));query("INSERT INTO $table($columns) VALUES(".implode(',',array_fill(0,count($r),'?')).')',array_values($r));}
 db()->commit();echo "Original content restored.\n";
}
elseif(($argv[1]??'')==='messages')echo json_encode_safe(query('SELECT id,reference,name,status,email_status FROM contact_messages')->fetchAll());
