<?php
declare(strict_types=1);
const ROOT = __DIR__ . '/..';
$configFile = getenv('ARD_CONFIG') ?: ROOT . '/config/local.php';
if (!is_file($configFile)) { if(PHP_SAPI==='cli'){fwrite(STDERR,"Missing private configuration. See README.md.\n");exit(1);}http_response_code(503); exit('يرجى إكمال إعداد الموقع. See README.md.'); }
$GLOBALS['config'] = require $configFile;
if (strlen((string)config('app_key')) < 32) { if(PHP_SAPI==='cli'){fwrite(STDERR,"Site configuration incomplete: generate an application key.\n");exit(1);}http_response_code(503); exit('Site configuration incomplete.'); }
date_default_timezone_set('Asia/Baghdad');
ini_set('display_errors', '0'); ini_set('log_errors', '1');
if (!is_dir(ROOT . '/storage/logs')) { @mkdir(ROOT . '/storage/logs', 0700, true); }
ini_set('error_log', ROOT . '/storage/logs/php.log');
set_exception_handler(function(Throwable $e): void {
    error_log(get_class($e) . ': ' . $e->getMessage());
    http_response_code(503);
    if (PHP_SAPI === 'cli') { fwrite(STDERR,"Operation failed. See storage/logs/php.log\n"); exit(1); }
    echo '<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>الخدمة غير متاحة مؤقتاً</title><link rel="stylesheet" href="/assets/site.css"><main class="container error-page"><h1>الخدمة غير متاحة مؤقتاً</h1><p>يرجى المحاولة لاحقاً. لم يتم تأكيد استلام أي رسالة.</p><a href="/ar/">العودة للرئيسية</a></main></html>';
});
function config(string $key, mixed $default = null): mixed { return $GLOBALS['config'][$key] ?? $default; }
function db(): PDO {
    static $pdo;
    return $pdo ??= new PDO(config('db_dsn'),config('db_user'),config('db_password'),[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
}
function query(string $sql, array $params=[]): PDOStatement { $s=db()->prepare($sql); $s->execute($params); return $s; }
function e(mixed $s): string { return htmlspecialchars((string)($s??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function input(string $key, string $default=''): string { return is_string($_POST[$key]??null) ? trim($_POST[$key]) : $default; }
function getstr(string $key, string $default=''): string { return is_string($_GET[$key]??null) ? trim($_GET[$key]) : $default; }
function json_encode_safe(mixed $v): string { return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR); }
function redirect(string $url, int $code=303): never { header('Location: '.$url,true,$code); exit; }
function start_session(): void {
    if(session_status()===PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');ini_set('session.gc_maxlifetime','1800');
    session_name('ard_session');session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>(bool)config('session_secure'),'httponly'=>true,'samesite'=>'Lax']);session_start();
}
function csrf_token(): string { start_session(); return $_SESSION['csrf']??=bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.e(csrf_token()).'">'; }
function verify_csrf(): void { start_session();if(!isset($_SESSION['csrf'])||!hash_equals($_SESSION['csrf'],input('csrf'))){http_response_code(419);exit('انتهت صلاحية النموذج. أعد تحميل الصفحة وحاول مجدداً. / Reload the page and try again.');} }
function security_headers(): void {
    $GLOBALS['csp_nonce']=base64_encode(random_bytes(18));
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-".$GLOBALS['csp_nonce']."'; style-src 'self'; img-src 'self' data:; font-src 'self'; media-src 'self'; frame-src 'self'; object-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'self'");
    header('X-Content-Type-Options: nosniff');header('X-Frame-Options: SAMEORIGIN');header('Referrer-Policy: strict-origin-when-cross-origin');header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if(config('session_secure'))header('Strict-Transport-Security: max-age=31536000');
}
function setting(string $key,mixed $default=''): mixed {
    static $values;
    if($values===null){$values=[];foreach(query('SELECT setting_key,value FROM settings') as $r)$values[$r['setting_key']]=json_decode($r['value'],true);}
    return $values[$key]??$default;
}
function set_setting(string $key,mixed $v): void { query('INSERT INTO settings(setting_key,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[$key,json_encode_safe($v)]); }
function lang(): string { return $GLOBALS['lang']??'ar'; }
function t(string $ar,string $en): string { return lang()==='ar'?$ar:$en; }
function url(string $path='',?string $locale=null): string { return '/'.($locale??lang()).'/'.ltrim($path,'/'); }
function absolute(string $path): string { return rtrim(config('base_url'),'/').'/'.ltrim($path,'/'); }
function full_name(): string { return setting('legal_name_'.lang()); }
function content_table(string $type): string { if(!in_array($type,['pages','sectors','projects'],true))throw new InvalidArgumentException('Invalid type');return $type; }
function records(string $type,?string $locale=null,bool $all=false): array {
    $type=content_table($type);$locale??=lang();
    $where=$all?'':" AND c.status='published' AND t.status='published'".(config('require_approved')?" AND t.approval='approved'":'');
    $rows=query("SELECT c.*, t.title,t.summary,t.body,t.seo_title,t.seo_description,t.approval,t.source_ref,t.status AS translation_status FROM $type c JOIN translations t ON t.entity_id=c.id AND t.entity_type=? AND t.locale=? WHERE 1=1 $where ORDER BY c.sort_order,c.id",[$type,$locale])->fetchAll();
    foreach($rows as &$r)$r['body']=json_decode($r['body'],true);return $rows;
}
function record(string $type,string $slug,?string $locale=null,bool $all=false): ?array { foreach(records($type,$locale,$all) as $r)if($r['slug']===$slug)return $r;return null; }
function media(?int $id): ?array { if(!$id)return null;static $cache=[];return $cache[$id]??=$id?query('SELECT * FROM media WHERE id=?',[$id])->fetch()?:null:null; }
function media_file(array $m): string { return str_starts_with($m['path'],'/assets/')?ROOT.'/public'.$m['path']:ROOT.'/storage/uploads/'.basename($m['path']); }
function media_size(?int $id): int { $m=media($id);if(!$m)return 0;$file=media_file($m);return is_file($file)?(int)filesize($file):0; }
function media_url(?int $id): string {
    $m=media($id);if(!$m)return '';
    // PDFs use the range-enabled endpoint, including bundled files replaced outside the CMS.
    if($m['mime']==='application/pdf'){$file=media_file($m);$revision=is_file($file)?filemtime($file).'-'.filesize($file):'missing';return '/media/'.$m['id'].'?v='.$revision;}
    return str_starts_with($m['path'],'/assets/')?$m['path']:'/media/'.$m['id'];
}
function picture(?int $id,string $class='',bool $priority=false,?string $alt=null): string {
    $m=media($id);if(!$m)return ''; $src=media_url($id);$srcset='';
    if(str_ends_with($src,'-1280.webp'))$srcset=' srcset="'.e(str_replace('-1280.webp','-640.webp',$src)).' 640w, '.e($src).' 1280w" sizes="(max-width: 700px) 100vw, 60vw"';
    return '<img class="'.e($class).'" src="'.e($src).'"'.$srcset.' width="'.(int)($m['width']?:1280).'" height="'.(int)($m['height']?:850).'" alt="'.e($alt??$m['alt_'.lang()]).'" '.($priority?'fetchpriority="high" loading="eager"':'loading="lazy"').' decoding="async">';
}
function is_admin(): bool {
    start_session();if(!isset($_SESSION['admin_id']))return false;
    if(time()-($_SESSION['last_seen']??0)>1800||time()-($_SESSION['login_at']??0)>28800){unset($_SESSION['admin_id']);return false;}
    $_SESSION['last_seen']=time();return true;
}
function require_admin(): void { if(!is_admin())redirect('/admin/login'); }
function rate_limit(string $scope,int $limit,int $seconds): bool {
    $bucket=hash_hmac('sha256',$scope,config('app_key'));$now=time();
    query('INSERT INTO rate_limits(bucket,attempts,expires_at) VALUES(?,1,?) ON DUPLICATE KEY UPDATE attempts=IF(expires_at<=?,1,attempts+1),expires_at=IF(expires_at<=?,VALUES(expires_at),expires_at)',[$bucket,$now+$seconds,$now,$now]);
    return (int)query('SELECT attempts FROM rate_limits WHERE bucket=?',[$bucket])->fetchColumn()<=$limit;
}
function ip_key(): string { return $_SERVER['REMOTE_ADDR']??'cli'; }
function audit(string $action,string $entity=''): void { query('INSERT INTO audit_log(admin_id,action,entity) VALUES(?,?,?)',[$_SESSION['admin_id']??null,$action,$entity]); }
function sections(array $row): array { $s=$row['body']['sections']??[];usort($s,fn($a,$b)=>($a['order']??0)<=>($b['order']??0));return array_values(array_filter($s,fn($s)=>($s['visible']??true))); }
function paragraphs(string $text): string { return implode('',array_map(fn($p)=>'<p>'.nl2br(e($p)).'</p>',preg_split('/\n\s*\n/',trim($text))?:[])); }

require_once ROOT.'/app/video.php';
require_once ROOT.'/app/profile.php';
