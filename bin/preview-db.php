<?php
// Local launcher diagnostics: never initialise a schema or change credentials.
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/app/bootstrap.php';
$mode=$argv[1]??'check';
$dsn=(string)config('db_dsn');$parts=[];
if(str_starts_with($dsn,'mysql:'))foreach(explode(';',substr($dsn,6)) as $part){$pair=explode('=',$part,2);if(count($pair)===2)$parts[$pair[0]]=$pair[1];}
if($mode==='host'){echo $parts['host']??'localhost';exit;}
if($mode==='port'){$port=$parts['port']??'3306';if(!ctype_digit($port)||(int)$port<1||(int)$port>65535)exit(1);echo $port;exit;}
if($mode==='database'){$name=$parts['dbname']??'';if(!preg_match('/^[a-zA-Z0-9_]+$/D',$name))exit(1);echo $name;exit;}
if($mode==='environment'){echo config('env');exit;}
if(!in_array($mode,['check','report'],true))exit(1);
try{
    db()->query('SELECT id FROM pages LIMIT 1');
    if($mode==='report')echo "اتصال قاعدة الموقع ناجح.\n";
}catch(PDOException $e){
    if($mode==='report'){
        $code=(int)($e->errorInfo[1]??0);
        $message=match($code){
            1045=>'الخادم يرفض حساب قاعدة الموقع. تحقق من الخادم والمنفذ وبيانات الاتصال في إعداد PHP الخاص بالموقع.',
            1049,1146=>'قاعدة الموقع أو جداولها غير موجودة على هذا الخادم. استعد القاعدة المعتمدة؛ لا تُنشأ بيانات بديلة تلقائياً.',
            default=>'تعذر الاتصال بقاعدة الموقع. راجع إعداد الاتصال وسجل MySQL المحلي.',
        };
        fwrite(STDERR,$message.PHP_EOL);
    }
    exit(1);
}
