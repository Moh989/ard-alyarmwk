<?php
start_session();$GLOBALS['lang']='ar';header('Cache-Control: private, no-store');header('X-Robots-Tag: noindex, nofollow');
$adminRoute=trim(substr($path,strlen('/admin')),'/');$adminError='';$adminSuccess=$_SESSION['admin_success']??'';unset($_SESSION['admin_success']);
if($adminRoute==='login'){
 if(is_admin())redirect('/admin');
 if($_SERVER['REQUEST_METHOD']==='POST'){
  verify_csrf();$email=mb_strtolower(input('email'));$password=input('password');
  $ipAllowed=rate_limit('login-ip:'.ip_key(),15,900);$accountAllowed=rate_limit('login-email:'.$email,5,900);
  if(!$ipAllowed||!$accountAllowed){$adminError='محاولات كثيرة. حاول مجدداً بعد 15 دقيقة.';http_response_code(429);}
  else{$user=query('SELECT * FROM admins WHERE email=?',[$email])->fetch();$valid=password_verify($password,$user['password_hash']??'$2y$12$oCDKl98JRTRSkspikDw24uDXBuplPOBMIBzoCLg4j4XP0HBDx2ZWG');if($user&&$valid){session_regenerate_id(true);$_SESSION=['admin_id'=>(int)$user['id'],'login_at'=>time(),'last_seen'=>time(),'csrf'=>bin2hex(random_bytes(32))];if(password_needs_rehash($user['password_hash'],PASSWORD_DEFAULT))query('UPDATE admins SET password_hash=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$user['id']]);audit('login');redirect('/admin');}else{$adminError='البريد الإلكتروني أو كلمة المرور غير صحيحة.';http_response_code(422);}}
 }
 require ROOT.'/app/views/admin-login.php';return;
}
require_admin();
require_once ROOT.'/app/admin-sliders.php';
$sliderContext=$adminRoute==='slider'?admin_slider_context():null;
if($adminRoute==='slider'&&!$sliderContext){http_response_code(404);$adminRoute='404';}
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 try{require ROOT.'/app/admin-actions.php';}catch(InvalidArgumentException $e){$adminError=$e->getMessage();http_response_code(422);}
}
$validRoutes=['','list','edit','sliders','slider','settings','media','media-edit','messages','message','password'];if(!in_array($adminRoute,$validRoutes,true)){http_response_code(404);$adminRoute='404';}
require ROOT.'/app/views/admin-layout.php';
