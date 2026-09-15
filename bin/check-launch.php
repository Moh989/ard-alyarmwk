<?php
require __DIR__.'/../app/bootstrap.php';
if(PHP_SAPI!=='cli')exit;
$checks=[];$add=function(string $name,bool $ok,string $detail='')use(&$checks){$checks[]=['check'=>$name,'passed'=>$ok,'detail'=>$detail];};
$add('PHP 8.2+',PHP_VERSION_ID>=80200,'Tested locally on PHP 8.5.7.');
foreach(['pdo_mysql','mbstring','fileinfo','gd','openssl','zip'] as $extension)$add('Extension '.$extension,extension_loaded($extension));
$parts=parse_url((string)config('base_url'));$host=$parts['host']??'';
$add('Production configuration',config('env')==='production');
$add('Real HTTPS base URL',($parts['scheme']??'')==='https'&&filter_var(config('base_url'),FILTER_VALIDATE_URL)!==false&&!in_array($host,['','localhost','127.0.0.1','example.com','example.org'],true)&&!preg_match('/\.(test|localhost|invalid|example)$/',$host)&&empty($parts['user'])&&empty($parts['pass'])&&empty($parts['query'])&&empty($parts['fragment'])&&in_array($parts['path']??'',['','/'],true),'Checks the configured URL only; DNS and TLS must be tested on the hosting server.');
$add('Secure session cookies',config('session_secure')===true);
$add('Approved content required',config('require_approved')===true);
$add('Application key',strlen((string)config('app_key'))>=32);
$add('Composer dependencies',is_file(ROOT.'/vendor/autoload.php'));
try{
    query('SELECT 1');$add('Database connection',true);$add('Administrator created',(int)query('SELECT COUNT(*) FROM admins')->fetchColumn()>0);
    foreach(['ar','en'] as $lc){$missing=[];foreach(['pages','sectors'] as $type)foreach(records($type,$lc,true) as $r){if($type==='pages'&&$r['slug']==='projects'&&!(int)query('SELECT COUNT(*) FROM projects')->fetchColumn())continue;if($r['status']==='published'&&($r['translation_status']!=='published'||$r['approval']!=='approved'))$missing[]=$type.'/'.$r['slug'];}$add('Content approval '.$lc,!$missing,implode(', ',$missing));}
    foreach(['home','about','sectors','profile','contact','privacy'] as $slug)foreach(['ar','en'] as $lc){$r=record('pages',$slug,$lc,true);$add('Required page '.$lc.'/'.$slug,$r&&$r['status']==='published'&&$r['translation_status']==='published'&&$r['approval']==='approved');}
    foreach(['ar','en'] as $lc){$ready=array_filter(records('sectors',$lc,true),fn($r)=>$r['status']==='published'&&$r['translation_status']==='published'&&$r['approval']==='approved');$add('Eight approved sectors '.$lc,count($ready)>=8);}
    $missing=[];foreach(query('SELECT * FROM media') as $m)if(!is_file(media_file($m)))$missing[]=(int)$m['id'];$add('Media files present',!$missing,'Missing IDs: '.implode(',',$missing));
    $add('Optimised profile linked',profile_display_id()!==(int)setting('profile_id'),'Run php bin/install-profile-web.php after copying the release files.');
}catch(Throwable $e){$add('Database/content checks',false,'Could not complete; consult private server logs.');}
$smtp=config('smtp',[]);$valid=config('mail_transport')==='smtp'&&!empty($smtp['host'])&&(int)($smtp['port']??0)>0&&(int)$smtp['port']<=65535&&in_array($smtp['encryption']??'',['tls','smtps'],true)&&!empty($smtp['username'])&&!empty($smtp['password'])&&filter_var($smtp['from']??'',FILTER_VALIDATE_EMAIL)&&filter_var($smtp['to']??'',FILTER_VALIDATE_EMAIL);
$add('SMTP configuration complete',(bool)$valid,'No email is sent by this check. Verify actual delivery after configuration.');
foreach(['storage','storage/uploads','storage/logs','storage/mail'] as $dir)$add('Writable '.$dir,is_dir(ROOT.'/'.$dir)&&is_writable(ROOT.'/'.$dir));
$ok=!in_array(false,array_column($checks,'passed'),true);$result=['automated_checks_passed'=>$ok,'checks'=>$checks,'manual_checks'=>['Confirm company approval of both languages, contact details and asset rights.','Verify hosting document root, HTTPS, cron, real mail delivery and backup schedule.','Check public pages and administration on the final domain.']];
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;exit($ok?0:1);
