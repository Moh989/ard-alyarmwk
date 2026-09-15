<?php
require __DIR__.'/../app/bootstrap.php';
if(PHP_SAPI!=='cli')exit;
$reference=strtoupper(bin2hex(random_bytes(6)));$id=null;$path=ROOT.'/storage/qa-smtp-config.php';
try{
 query('INSERT INTO contact_messages(reference,locale,name,email,message,email_status,fingerprint) VALUES(?,?,?,?,?,?,?)',[$reference,'ar','اختبار فشل إشعار محلي','test@example.test','اختبار محلي لعدم فقد الرسالة عند فشل البريد.','pending',hash('sha256',random_bytes(32))]);$id=(int)db()->lastInsertId();
 $c=$GLOBALS['config'];$c['mail_transport']='smtp';$c['smtp']=['host'=>'127.0.0.1','port'=>1,'encryption'=>'tls','username'=>'','password'=>'','from'=>'test@example.test','to'=>'test@example.test'];file_put_contents($path,"<?php return ".var_export($c,true).';');chmod($path,0600);
 $command='ARD_CONFIG='.escapeshellarg($path).' '.escapeshellarg(PHP_BINARY).' '.escapeshellarg(ROOT.'/bin/mail-worker.php');exec($command,$output,$exit);
 $row=query('SELECT * FROM contact_messages WHERE id=?',[$id])->fetch();if(!$row||$row['email_status']!=='failed')throw new RuntimeException('Expected failed notification with preserved message');
 file_put_contents(ROOT.'/tests/mail-failure-results.json',json_encode_safe(['smtp_endpoint'=>'127.0.0.1:1 (local refusal only)','delivery_failed'=>true,'message_preserved'=>true,'external_email_sent'=>false]));echo "PASS SMTP failure preserves the saved message; no external mail sent.\n";
}finally{if($id)query('DELETE FROM contact_messages WHERE id=?',[$id]);if(is_file($path))unlink($path);}
