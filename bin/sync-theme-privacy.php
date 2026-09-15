<?php
require __DIR__.'/../app/bootstrap.php';
if(PHP_SAPI!=='cli')exit;
$notes=['ar'=>'يُحفظ اختيار النمط النهاري أو الليلي في متصفحك لتطبيقه عند عودتك، ولا يُرسل هذا الاختيار إلى الخادم. يمكنك تغيير الاختيار من زر النمط أو مسحه ضمن بيانات الموقع في متصفحك.','en'=>'Your light or dark mode preference is saved in your browser for future visits and is not sent to the server. You can change it using the theme button or remove it by clearing this site’s browser data.'];
$changed=0;db()->beginTransaction();
foreach($notes as $locale=>$note){
    $row=record('pages','privacy',$locale,true);if(!$row)continue;$body=$row['body'];$updated=false;
    foreach($body['sections']??[] as $i=>$section)if(($section['key']??'')==='security'&&!str_contains($section['text'],$note)){$body['sections'][$i]['text']=rtrim($section['text']).' '.$note;$updated=true;}
    if($updated){query("UPDATE translations SET body=? WHERE entity_type='pages' AND entity_id=? AND locale=?",[json_encode_safe($body),$row['id'],$locale]);audit('privacy.theme_note',$locale);$changed++;}
}
db()->commit();echo "Theme storage note added to $changed privacy translations. Approval states unchanged.\n";
