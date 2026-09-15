<?php
require __DIR__.'/../app/bootstrap.php';
if(PHP_SAPI!=='cli')exit;
function review_text(mixed $v): string {
    if(is_string($v))return '<p>'.nl2br(e($v)).'</p>';
    if(!is_array($v))return '';$out='';
    foreach($v as $key=>$value)if(!in_array($key,['key','order','visible','status','image_id','video_id'],true))$out.=review_text($value);
    return $out;
}
$html='<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>مراجعة محتوى ارض اليرموك</title><style>body{font:17px/1.9 system-ui,sans-serif;color:#252d50;background:#f5f5f2;margin:0}main{max-width:1300px;margin:auto;padding:35px}article{background:white;margin:28px 0;padding:24px;break-inside:avoid}table{width:100%;border-collapse:collapse;table-layout:fixed}td,th{vertical-align:top;text-align:start;border:1px solid #ddd;padding:18px;overflow-wrap:anywhere}p{margin:0 0 14px}h2{font-size:24px}small{color:#525969}@media(max-width:650px){td,th{display:block;width:auto}main{padding:12px}}@media print{body{background:white}main{padding:0}}</style><main><h1>مراجعة العربية والإنكليزية</h1><p>مراجعة تحريرية بتاريخ 13 أيلول 2026. تعرض هذه النسخة النصوص الحالية للمقارنة والاعتماد من الشركة. لم يُغيّر الاعتماد تلقائياً. الاسم الإنكليزي الرسمي محفوظ كما ورد بالمرفق. صفحة المشاريع لا تظهر لعدم وجود مشاريع موثقة.</p><p>بعد مراجعة كل صفحة وقطاع، اختر اللغة الإنكليزية في محرر الإدارة، واضبط الاعتماد إلى «معتمد» ثم احفظ. راجع النسختين على المعاينة قبل تفعيل require_approved في الإنتاج. لا تضع هذا الملف الداخلي داخل مجلد public.</p>';
foreach(['pages','sectors'] as $type)foreach(records($type,'ar',true) as $ar){
    $en=record($type,$ar['slug'],'en',true);$html.='<article><h2>'.e($ar['title']).'</h2><small>'.e($type.'/'.$ar['slug']).'</small><table><tr><th>العربية</th><th dir="ltr">English — company approval pending</th></tr><tr>';
    foreach([$ar,$en] as $i=>$row){$html.='<td lang="'.($i?'en':'ar').'" dir="'.($i?'ltr':'rtl').'">';if($row)$html.='<h3>'.nl2br(e($row['title'])).'</h3>'.review_text($row['summary']).review_text($row['body']).'<hr><strong>SEO</strong>'.review_text($row['seo_title']).review_text($row['seo_description']).'<small>'.e($row['source_ref']).'</small>';$html.='</td>';}
    $html.='</tr></table></article>';
}
$html.='</main></html>';file_put_contents(ROOT.'/docs/bilingual-review.html',$html);echo "Internal bilingual review exported.\n";
