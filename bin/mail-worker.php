<?php
require __DIR__.'/../app/bootstrap.php';
if(PHP_SAPI!=='cli')exit;
if(config('mail_transport')==='disabled')exit("Notifications disabled. Messages remain in the database.\n");
$lock=fopen(ROOT.'/storage/mail-worker.lock','c');if(!flock($lock,LOCK_EX|LOCK_NB))exit("Another worker is active.\n");
foreach(query("SELECT id,reference FROM contact_messages WHERE email_status='pending' ORDER BY id LIMIT 50") as $row){
 try{
  $body="New website enquiry: ".$row['reference']."\n".absolute('/admin/message?id='.$row['id'])."\nOpen the admin inbox to review it.";
  if(config('mail_transport')==='log'){if(!is_dir(ROOT.'/storage/mail'))mkdir(ROOT.'/storage/mail',0700,true);$file=ROOT.'/storage/mail/'.$row['reference'].'.txt';file_put_contents($file,$body);chmod($file,0600);$status='logged';}
  elseif(config('mail_transport')==='smtp'){
   require_once ROOT.'/vendor/autoload.php';$smtp=config('smtp');foreach(['host','from','to'] as $key)if(empty($smtp[$key]))throw new RuntimeException('SMTP configuration missing');
   $mail=new PHPMailer\PHPMailer\PHPMailer(true);$mail->isSMTP();$mail->Host=$smtp['host'];$mail->Port=(int)$smtp['port'];$mail->SMTPAuth=!empty($smtp['username']);$mail->Username=$smtp['username'];$mail->Password=$smtp['password'];$mail->SMTPSecure=$smtp['encryption']==='smtps'?PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS:PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;$mail->Timeout=15;$mail->CharSet='UTF-8';$mail->setFrom($smtp['from'],'ARD ALYARMWK CO.');$mail->addAddress($smtp['to']);$mail->Subject='Website enquiry '.$row['reference'];$mail->Body=$body;$mail->send();$status='sent';
  }else throw new RuntimeException('Unknown transport');
  query('UPDATE contact_messages SET email_status=? WHERE id=?',[$status,$row['id']]);echo $row['reference']." $status\n";
 }catch(Throwable $e){query("UPDATE contact_messages SET email_status='failed' WHERE id=?",[$row['id']]);error_log('Mail delivery failed for '.$row['reference'].': '.$e->getMessage());echo $row['reference']." failed\n";}
}
query('DELETE FROM rate_limits WHERE expires_at<?',[time()-86400]);
