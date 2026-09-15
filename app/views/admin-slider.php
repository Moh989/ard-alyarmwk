<div class="admin-card admin-sections slider-fields"><h2>سلايدر الصفحة — <?=$locale==='ar'?'العربية':'English'?></h2>
<p class="admin-hint">يعرض الموقع تلقائياً شرائح من محتوى الصفحة والقطاعات المنشورة. لتخصيصها، أضف حتى أربع شرائح هنا. وجود شريحة منشورة واحدة يستبدل العرض التلقائي. الشرائح المسودة لا تظهر للزائر؛ يمكن معاينتها بعد حفظ عنوان وصورة عبر رابط المعاينة الخاصة. الصور وأوصافها البديلة من مكتبة الوسائط، واعتماد النص يتبع اعتماد هذه اللغة. إزالة جميع الشرائح المنشورة يعيد العرض التلقائي.</p>
<?php if(empty($standaloneSlider)&&$id):?><p><a class="text-link" href="<?=e(admin_slider_url($type,$id,$locale))?>">فتح صفحة إدارة هذا السلايدر ↗</a></p><?php endif;?>
<input type="hidden" name="slider_present" value="1">
<input type="hidden" name="slider_revision" value="<?=e($adminError?input('slider_revision'):admin_slider_revision($body,$type,$id,$locale))?>">
<?php
$sliderItems=$body['slider']??[];$videoOptions=video_options();
if($adminError&&is_array($_POST['slider']??null))$sliderItems=array_slice(array_values($_POST['slider']),0,4);
$linkOptions=[''=>'بدون رابط','contact#contact-form'=>'نموذج التواصل','about'=>'من نحن','sectors'=>'جميع القطاعات','profile'=>'الملف التعريفي','privacy'=>'الخصوصية'];
foreach(records('sectors','ar',true) as $sector)$linkOptions['sectors/'.$sector['slug']]=$sector['title'];
if(records('projects','ar',true))$linkOptions['projects']='المشاريع';
for($i=0;$i<4;$i++):$slide=$sliderItems[$i]??[];
if(!is_array($slide))$slide=[];
foreach($slide as $key=>$value)if(!is_scalar($value))unset($slide[$key]);
$currentLinks=$linkOptions;if(!empty($slide['target'])&&!isset($currentLinks[$slide['target']]))$currentLinks[$slide['target']]='المسار المحفوظ: '.$slide['target'];
$prefix='slider['.$i.']';?>
<details <?=($adminError&&!empty($slide['title']))||(!empty($standaloneSlider)&&$i===0)?'open':''?>><summary><?=e(($i+1).' / '.($slide['title']??'شريحة جديدة'))?></summary>
<div class="form-grid"><div class="field"><label for="slide-status-<?=$i?>">الحالة</label><select id="slide-status-<?=$i?>" name="<?=$prefix?>[status]"><option value="draft" <?=($slide['status']??'draft')==='draft'?'selected':''?>>مسودة / غير ظاهرة</option><option value="published" <?=($slide['status']??'draft')==='published'?'selected':''?>>منشورة</option></select></div><div class="field"><label for="slide-order-<?=$i?>">الترتيب</label><input id="slide-order-<?=$i?>" name="<?=$prefix?>[order]" type="number" min="0" max="1000" value="<?=e($slide['order']??($i+1))?>"></div></div>
<div class="field"><label for="slide-title-<?=$i?>">عنوان الشريحة</label><input id="slide-title-<?=$i?>" name="<?=$prefix?>[title]" dir="<?=$direction?>" maxlength="110" value="<?=e($slide['title']??'')?>"></div>
<div class="field"><label for="slide-text-<?=$i?>">وصف مختصر (اختياري)</label><textarea id="slide-text-<?=$i?>" name="<?=$prefix?>[text]" dir="<?=$direction?>" rows="3" maxlength="240"><?=e($slide['text']??'')?></textarea></div>
<div class="form-grid"><div class="field"><label for="slide-image-<?=$i?>">الصورة</label><select id="slide-image-<?=$i?>" name="<?=$prefix?>[image_id]"><?php foreach($mediaOptions as $mid=>$label):?><option value="<?=e($mid)?>" <?=(string)$mid===(string)($slide['image_id']??'')?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></div><div class="field"><label for="slide-target-<?=$i?>">وجهة الرابط (اختياري)</label><select id="slide-target-<?=$i?>" name="<?=$prefix?>[target]"><?php foreach($currentLinks as $path=>$label):?><option value="<?=e($path)?>" <?=$path===($slide['target']??'')?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></div></div>
<div class="field"><label for="slide-video-<?=$i?>">فيديو داخل الشريحة (اختياري)</label><select id="slide-video-<?=$i?>" name="<?=$prefix?>[video_id]"><?php foreach($videoOptions as $vid=>$label):?><option value="<?=e($vid)?>" <?=(string)$vid===(string)($slide['video_id']??'')?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></div><p class="admin-hint">عند اختيار فيديو، يمكنك ترك الصورة فارغة لاستخدام غلافه المجهز. يحافظ العرض على كادر الفيديو كاملاً، ويختار تلقائياً نسخة الهاتف. تضاف ملفات فيديو جديدة بعد تجهيزها بأداة التحسين المرفقة بالمشروع.</p>
<label class="slider-remove"><input type="checkbox" name="<?=$prefix?>[remove]" value="1" <?=($slide['remove']??'')==='1'?'checked':''?>> حذف هذه الشريحة عند الحفظ</label>
<p class="admin-hint">أضف مصدر النص والصورة في مراجع المحتوى والوسائط. لا يظهر رابط الوجهة إذا كانت الصفحة غير منشورة بهذه اللغة.</p>
</details><?php endfor;?></div>
