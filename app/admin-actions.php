<?php
$action=input('action');
function admin_done(string $text,string $url): never { $_SESSION['admin_success']=$text;redirect($url); }
function bounded(string $key,int $max,bool $required=false): string { $v=input($key);if(($required&&$v==='')||mb_strlen($v)>$max)throw new InvalidArgumentException('راجع الحقل '.$key.': مطلوب أو تجاوز الحد المسموح.');return $v; }
function valid_choice(string $value,array $options): string { if(!in_array($value,$options,true))throw new InvalidArgumentException('اختيار غير صحيح.');return $value; }
function validate_image(?int $id): void { if($id&&(!media($id)||!str_starts_with(media($id)['mime'],'image/')))throw new InvalidArgumentException('اختر صورة موجودة في مكتبة الوسائط.'); }
if($action==='logout'){audit('logout');$_SESSION=[];session_destroy();setcookie(session_name(),'',time()-3600,'/','',(bool)config('session_secure'),true);redirect('/admin/login');}
if($action==='save-slider'){
 if($adminRoute!=='slider'||!$sliderContext||!$sliderContext['tr'])throw new InvalidArgumentException('احفظ محتوى هذه اللغة أولاً، ثم عد إلى إدارة سلايدرها.');
 $type=$sliderContext['type'];$id=$sliderContext['id'];$locale=$sliderContext['locale'];
 if(input('type')!==$type||input('id')!==(string)$id||input('locale')!==$locale)throw new InvalidArgumentException('وجهة الحفظ غير متطابقة. أعد تحميل الصفحة.');
 $operation=valid_choice(input('operation'),['save','reset']);
 if($operation==='save'&&input('slider_present')!=='1')throw new InvalidArgumentException('بيانات السلايدر غير مكتملة.');
 $items=$operation==='reset'?[]:admin_slider_items($_POST['slider']??[],$type);
 db()->beginTransaction();
 try{
  $latest=query('SELECT body FROM translations WHERE entity_type=? AND entity_id=? AND locale=? FOR UPDATE',[$type,$id,$locale])->fetchColumn();
  if(!$latest)throw new InvalidArgumentException('محتوى هذه اللغة غير موجود.');
  $body=json_decode($latest,true);
  if(!hash_equals(admin_slider_revision($body,$type,$id,$locale),input('slider_revision')))throw new InvalidArgumentException('تم تعديل السلايدر في نافذة أخرى. أعد تحميل الصفحة لمراجعة النسخة الأحدث قبل الحفظ.');
  $body['slider']=$items;
  query('UPDATE translations SET body=? WHERE entity_type=? AND entity_id=? AND locale=?',[json_encode_safe($body),$type,$id,$locale]);
  audit($operation==='reset'?'slider.reset':'slider.save',"$type/$id/$locale");db()->commit();
 }catch(Throwable $e){db()->rollBack();throw $e;}
 admin_done($operation==='reset'?'تمت استعادة السلايدر الافتراضي لهذه اللغة.':'تم حفظ السلايدر. تظهر الشرائح المنشورة بحسب حالة نشر الصفحة واعتماد اللغة.',admin_slider_url($type,$id,$locale));
}
if($action==='save-content'){
 $type=content_table(input('type'));$id=(int)input('id');$locale=valid_choice(input('locale'),['ar','en']);$slug=bounded('slug',100,true);
 if(!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$slug))throw new InvalidArgumentException('الرابط الثابت يقبل حروفاً إنكليزية صغيرة وأرقاماً وشرطات.');
 $existing=$id?query("SELECT * FROM $type WHERE id=?",[$id])->fetch():null;if($id&&!$existing)throw new InvalidArgumentException('المحتوى غير موجود.');
 if($type==='pages'&&(!$existing||$existing['slug']!==$slug))throw new InvalidArgumentException('روابط الصفحات الأساسية ثابتة.');
 $status=valid_choice(input('status'),['draft','published']);$translationStatus=valid_choice(input('translation_status'),['draft','published']);$approval=valid_choice(input('approval'),['review','approved']);
 $title=bounded('title',255,true);$summary=bounded('summary',4000,$translationStatus==='published');$source=bounded('source_ref',255,$translationStatus==='published');$seoTitle=bounded('seo_title',255);$seoDescription=bounded('seo_description',500);$imageId=(int)input('image_id')?:null;validate_image($imageId);
 $otherLocale=$locale==='ar'?'en':'ar';$previous=$id?query('SELECT body FROM translations WHERE entity_type=? AND entity_id=? AND locale=?',[$type,$id,$locale])->fetchColumn():null;$body=$previous?json_decode($previous,true):['sections'=>[]];
 $body['eyebrow']=bounded('eyebrow',255);$body['text']=bounded('text',20000);$body['services']=array_values(array_filter(array_map('trim',explode("\n",bounded('services',10000)))));
 foreach(['location','scope','completion_status','client'] as $key)$body[$key]=bounded($key,2000);
 $posted=$_POST['sections']??[];if(!is_array($posted)||count($posted)>30)throw new InvalidArgumentException('بيانات الأقسام غير صحيحة.');
 $allowedKeys=array_column($body['sections']??[],'key');$updatedSections=[];
 foreach($posted as $section){if(!is_array($section))throw new InvalidArgumentException('قسم غير صحيح.');foreach(['key','title','text','items','order'] as $k)if(isset($section[$k])&&!is_string($section[$k]))throw new InvalidArgumentException('قيمة قسم غير صحيحة.');$key=$section['key']??'';if(!in_array($key,$allowedKeys,true))throw new InvalidArgumentException('معرف قسم غير صحيح.');$sTitle=trim($section['title']??'');$sText=trim($section['text']??'');if(mb_strlen($sTitle)>255||mb_strlen($sText)>20000||strlen($section['items']??'')>20000)throw new InvalidArgumentException('نص القسم أطول من المسموح.');$pairs=[];foreach(explode("\n",trim($section['items']??'')) as $line){if(trim($line)==='')continue;$bits=explode('|',$line,2);$pairs[]=['title'=>trim($bits[0]),'text'=>trim($bits[1]??'')];}$updatedSections[]=['key'=>$key,'title'=>$sTitle,'text'=>$sText,'items'=>$pairs,'order'=>max(0,min(1000,(int)($section['order']??0))),'visible'=>($section['visible']??'0')==='1'];}
 if(count($updatedSections)!==count($allowedKeys))throw new InvalidArgumentException('أعد تحميل المحرر؛ بيانات الأقسام غير مكتملة.');$body['sections']=$updatedSections;
 if(input('slider_present')==='1')$body['slider']=admin_slider_items($_POST['slider']??[],$type);
 $sectorId=(int)input('sector_id')?:null;$gallery=[];
 if($type==='projects'){
  if($sectorId&&!query('SELECT id FROM sectors WHERE id=?',[$sectorId])->fetchColumn())throw new InvalidArgumentException('القطاع غير موجود.');
  foreach((array)($_POST['gallery']??[]) as $gid){$gid=(int)$gid;validate_image($gid);if($gid)$gallery[]=$gid;}$gallery=array_values(array_unique($gallery));
  if($status==='published'&&(!$imageId||!$sectorId||$approval!=='approved'||$source===''))throw new InvalidArgumentException('نشر المشروع يتطلب قطاعاً وصورة ومصدر اعتماد واعتماد الترجمة الحالية.');
  if($status==='published')foreach(array_merge([$imageId],$gallery) as $mid)if(media($mid)['classification']!=='project')throw new InvalidArgumentException('صور المشروع المنشور يجب أن تصنف كصور مشروع حقيقي مع توثيق مصدرها.');
 }
 if($status==='published'&&$type==='sectors'&&!$body['services'])throw new InvalidArgumentException('أضف نطاق الخدمات قبل نشر القطاع.');
 if($status==='published'&&$type==='sectors'&&!$imageId)throw new InvalidArgumentException('القطاع المنشور يحتاج صورة.');
 if(query("SELECT id FROM $type WHERE slug=? AND id<>?",[$slug,$id])->fetchColumn())throw new InvalidArgumentException('الرابط الثابت مستخدم بالفعل.');
 db()->beginTransaction();
 try{
  if($id){
   $latest=query('SELECT body FROM translations WHERE entity_type=? AND entity_id=? AND locale=? FOR UPDATE',[$type,$id,$locale])->fetchColumn();
   $latestBody=$latest?json_decode($latest,true):[];
   if(input('slider_present')==='1'&&!hash_equals(admin_slider_revision($latestBody,$type,$id,$locale),input('slider_revision')))throw new InvalidArgumentException('تم تعديل السلايدر في نافذة أخرى. أعد تحميل الصفحة قبل الحفظ.');
   if(input('slider_present')!=='1'&&isset($latestBody['slider']))$body['slider']=$latestBody['slider'];
  }
  if($id)query("UPDATE $type SET slug=?,status=?,sort_order=?,image_id=?,updated_at=CURRENT_TIMESTAMP WHERE id=?",[$slug,$status,max(0,min(1000,(int)input('sort_order'))),$imageId,$id]);
  else{query("INSERT INTO $type(slug,status,sort_order,image_id) VALUES(?,?,?,?)",[$slug,$status,max(0,min(1000,(int)input('sort_order'))),$imageId]);$id=(int)db()->lastInsertId();}
  if($type==='projects')query('UPDATE projects SET sector_id=?,gallery=? WHERE id=?',[$sectorId,json_encode_safe($gallery),$id]);
  query('INSERT INTO translations(entity_type,entity_id,locale,title,summary,body,seo_title,seo_description,status,approval,source_ref) VALUES(?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),summary=VALUES(summary),body=VALUES(body),seo_title=VALUES(seo_title),seo_description=VALUES(seo_description),status=VALUES(status),approval=VALUES(approval),source_ref=VALUES(source_ref)',[$type,$id,$locale,$title,$summary,json_encode_safe($body),$seoTitle,$seoDescription,$translationStatus,$approval,$source]);
  audit('content.save',"$type/$id/$locale");db()->commit();
 }catch(Throwable $e){db()->rollBack();throw $e;}
 admin_done('تم حفظ المحتوى. تظهر التغييرات في الموقع بحسب حالة النشر والاعتماد.','/admin/edit?type='.$type.'&id='.$id.'&locale='.$locale);
}
if($action==='save-settings'){
 $values=[];foreach(['short_name_ar','short_name_en','legal_name_ar','legal_name_en','address_ar','address_en'] as $key)$values[$key]=bounded($key,2000,true);
 $values['email']=bounded('email',254,true);if(!filter_var($values['email'],FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('البريد الإلكتروني غير صحيح.');
 foreach(['phone','whatsapp'] as $key){$values[$key]=bounded($key,40,$key==='phone');if($values[$key]&&!preg_match('/^\+?[0-9 ()-]{6,40}$/',$values[$key]))throw new InvalidArgumentException('رقم الهاتف غير صحيح.');}
 $values['founded']=bounded('founded',4);if($values['founded']!==''&&!preg_match('/^(19|20)\d{2}$/',$values['founded']))throw new InvalidArgumentException('سنة التأسيس غير صحيحة.');
 $values['map_url']=bounded('map_url',2000);if($values['map_url']&&(!filter_var($values['map_url'],FILTER_VALIDATE_URL)||parse_url($values['map_url'],PHP_URL_SCHEME)!=='https'))throw new InvalidArgumentException('رابط الخريطة يجب أن يبدأ بـ https.');
 foreach(['logo_id','profile_cover_id','about_image_id'] as $key){$values[$key]=(int)input($key);validate_image($values[$key]);if(!$values[$key])throw new InvalidArgumentException('اختر الصورة المطلوبة.');}
 if(media($values['logo_id'])['classification']!=='identity')throw new InvalidArgumentException('الشعار يجب أن يكون من وسائط الهوية.');
 $values['profile_id']=(int)input('profile_id');if((media($values['profile_id'])['mime']??'')!=='application/pdf')throw new InvalidArgumentException('اختر ملف PDF صالحاً.');
 $values['social_links']=[];foreach(explode("\n",bounded('social_links',10000)) as $line){if(trim($line)==='')continue;$bits=explode('|',$line,2);$label=trim($bits[0]);$u=trim($bits[1]??'');if(!$label||!filter_var($u,FILTER_VALIDATE_URL)||parse_url($u,PHP_URL_SCHEME)!=='https')throw new InvalidArgumentException('كل رابط اجتماعي بصيغة الاسم | https://...');$values['social_links'][]=['label'=>$label,'url'=>$u];}
 $values['privacy_retention_note']=bounded('privacy_retention_note',2000);
 if(isset($_POST['home_video_id'])){
  $values['home_video_id']=(int)input('home_video_id');
  if($values['home_video_id']&&!video_assets($values['home_video_id']))throw new InvalidArgumentException('اختر فيديو معداً للويب.');
 }
 db()->beginTransaction();try{foreach($values as $k=>$v)set_setting($k,$v);query("UPDATE pages SET image_id=? WHERE slug='about'",[$values['about_image_id']]);audit('settings.save');db()->commit();}catch(Throwable $e){db()->rollBack();throw $e;}admin_done('تم تحديث الهوية وبيانات الشركة.','/admin/settings');
}
if($action==='upload'){require_once ROOT.'/app/media.php';$id=upload_media();admin_done('تم رفع الملف ومعالجته. يمكن الآن اختياره في الصفحات والإعدادات.','/admin/media-edit?id='.$id);}
if($action==='save-media'){
 $id=(int)input('id');$m=media($id);if(!$m)throw new InvalidArgumentException('الملف غير موجود.');$ar=bounded('alt_ar',500,$m['mime']!=='application/pdf');$en=bounded('alt_en',500,$m['mime']!=='application/pdf');$source=bounded('source',4000,true);$class=valid_choice(input('classification'),['identity','illustration','project','document']);
 if($m['mime']==='application/pdf'&&$class!=='document')throw new InvalidArgumentException('ملف PDF يجب أن يصنف كمستند.');
 query('UPDATE media SET alt_ar=?,alt_en=?,source=?,classification=? WHERE id=?',[$ar,$en,$source,$class,$id]);audit('media.save',(string)$id);admin_done('تم تحديث وصف الوسيط ومصدره.','/admin/media-edit?id='.$id);
}
if($action==='message-status'){$id=(int)input('id');$status=valid_choice(input('status'),['new','in_progress','closed']);query('UPDATE contact_messages SET status=? WHERE id=?',[$status,$id]);audit('message.status',(string)$id);admin_done('تم تحديث حالة الرسالة.','/admin/message?id='.$id);}
if($action==='message-retry'){$id=(int)input('id');if(config('mail_transport')==='disabled')throw new InvalidArgumentException('الإشعارات معطلة في إعدادات الخادم.');query("UPDATE contact_messages SET email_status='pending' WHERE id=? AND email_status IN ('failed','disabled')",[$id]);audit('message.retry',(string)$id);admin_done('أعيدت جدولة الإشعار. ينفذه عامل البريد عند تشغيله.','/admin/message?id='.$id);}
if($action==='delete-message'){$id=(int)input('id');if(input('confirm')!=='yes')throw new InvalidArgumentException('حدد تأكيد الحذف أولاً.');query('DELETE FROM contact_messages WHERE id=?',[$id]);audit('message.delete',(string)$id);admin_done('تم حذف الرسالة نهائياً من قاعدة بيانات الموقع.','/admin/messages');}
if($action==='change-password'){
 $current=input('current_password');$new=input('new_password');$user=query('SELECT * FROM admins WHERE id=?',[$_SESSION['admin_id']])->fetch();if(!password_verify($current,$user['password_hash']))throw new InvalidArgumentException('كلمة المرور الحالية غير صحيحة.');if(strlen($new)<12||strlen($new)>200||$new!==input('confirm_password'))throw new InvalidArgumentException('أدخل كلمة مرور من 12 إلى 200 حرف وتأكيداً مطابقاً.');query('UPDATE admins SET password_hash=? WHERE id=?',[password_hash($new,PASSWORD_DEFAULT),$user['id']]);session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));audit('password.change');admin_done('تم تغيير كلمة المرور.','/admin/password');
}
throw new InvalidArgumentException('الإجراء غير معروف.');
