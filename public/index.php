<?php
require __DIR__.'/../app/bootstrap.php';
security_headers();
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)??'/');
if(str_starts_with($path,'/admin')){require ROOT.'/app/admin.php';exit;}
if(preg_match('~^/media/(\d+)$~',$path,$match)){require ROOT.'/app/media.php';serve_media((int)$match[1]);exit;}
if($path==='/video-viewer'){require ROOT.'/app/views/video-viewer.php';exit;}
if($path==='/pdf-viewer'){require ROOT.'/app/views/pdf-viewer.php';exit;}
if($path==='/robots.txt'){header('Content-Type: text/plain');echo "User-agent: *\nDisallow: /admin\nDisallow: /media/\n".(config('env')==='local'?"Disallow: /\n":'')."Sitemap: ".absolute('/sitemap.xml');exit;}
if($path==='/sitemap.xml'){require ROOT.'/app/sitemap.php';exit;}
if($path==='/')redirect('/ar/',302);
if(!preg_match('~^/(ar|en)(?:/(.*))?$~',$path,$match)){$GLOBALS['lang']='ar';$route='404';}
else{$GLOBALS['lang']=$match[1];$route=trim($match[2]??'','/');}
$parts=explode('/',$route);$type='pages';$slug=$route===''?'home':$route;
if(in_array($parts[0],['sectors','projects'],true)&&count($parts)===2){$type=$parts[0];$slug=$parts[1];}
$preview=getstr('preview')==='1'&&is_admin();
$row=$route==='404'?null:record($type,$slug,null,$preview);
$sectors=records('sectors');$projects=records('projects');
if($type==='pages'&&$slug==='projects'&&!$projects&&!$preview)$row=null;
if(!$row){$preview=false;http_response_code(404);$route='404';$row=['title'=>t('يبدو أنك وصلت إلى طريق مختلف.','Looks like you took a different path.'),'summary'=>t('الصفحة التي تبحث عنها غير موجودة أو لم تعد منشورة.','The page you are looking for is unavailable or no longer published.'),'image_id'=>null,'body'=>[],'seo_title'=>t('الصفحة غير موجودة','Page not found'),'seo_description'=>''];}
$errors=[];$old=[];
if($route==='contact'){start_session();require ROOT.'/app/contact.php';if($_SERVER['REQUEST_METHOD']==='POST')handle_contact();$_SESSION['form_started']??=time();}
elseif($_SERVER['REQUEST_METHOD']!=='GET'&&$_SERVER['REQUEST_METHOD']!=='HEAD'){http_response_code(405);header('Allow: GET, HEAD');exit;}
header('Cache-Control: '.(($route==='contact'||$preview)?'private, no-store':'public, max-age=0, must-revalidate'));
if($preview)header('X-Robots-Tag: noindex, nofollow');
require ROOT.'/app/views/layout.php';
