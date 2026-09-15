<?php
$type=$sliderContext['type'];$id=$sliderContext['id'];$locale=$sliderContext['locale'];$entity=$sliderContext['entity'];$tr=$sliderContext['tr'];$body=$sliderContext['body'];$direction=$locale==='ar'?'rtl':'ltr';
$editorUrl='/admin/edit?type='.$type.'&id='.$id.'&locale='.$locale;
$selfUrl=admin_slider_url($type,$id,$locale);$previewUrl=url($sliderContext['path'],$locale).'?preview=1';
$state=admin_slider_state($body);$revision=admin_slider_revision($body,$type,$id,$locale);
if($adminError&&is_string($_POST['slider_revision']??null))$revision=$_POST['slider_revision'];
?>
<div class="admin-title"><div><p class="eyebrow">إدارة السلايدرات</p><h1><?=e($sliderContext['label'])?></h1><p>الصور والفيديو وترتيب الشرائح — <?=$locale==='ar'?'العربية':'English'?></p></div><a class="text-link" href="/admin/sliders">كل السلايدرات ←</a></div>
<nav class="admin-tabs" aria-label="لغة السلايدر"><?php foreach(['ar'=>'العربية','en'=>'English'] as $lc=>$label):?><a href="<?=e(admin_slider_url($type,$id,$lc))?>" <?=$locale===$lc?'aria-current="page"':''?> lang="<?=$lc?>"><?=$label?></a><?php endforeach;?></nav>
<?php if(!$tr):?><div class="admin-card"><h2>أضف محتوى هذه اللغة أولاً</h2><p>يحتاج السلايدر إلى صفحة محفوظة بهذه اللغة ليرتبط بها.</p><a class="button" href="<?=e($editorUrl)?>">فتح محرر المحتوى</a></div><?php return;endif;
require_once ROOT.'/app/slider.php';
$GLOBALS['lang']=$locale;
$currentSlides=slider_slides(array_merge($entity,$tr,['body'=>$body]),$type,$entity['slug']);
$GLOBALS['lang']='ar';
$mediaOptions=[''=>'بدون صورة'];foreach(query("SELECT id,original_name FROM media WHERE mime LIKE 'image/%' ORDER BY id DESC") as $m)$mediaOptions[$m['id']]='#'.$m['id'].' — '.$m['original_name'];
?>
<section class="admin-card slider-current" aria-labelledby="current-slider-heading"><div class="slider-current-heading"><div><h2 id="current-slider-heading">العرض المحفوظ حالياً</h2><p class="admin-hint"><?=$state['custom']?'شرائح مخصصة منشورة لهذه اللغة.':'شرائح افتراضية تتحدث تلقائياً من محتوى الصفحة.'?><?=$state['draft']?' لديك أيضاً '.$state['draft'].' شريحة مسودة، تظهر في المعاينة الخاصة عند اكتمال عنوانها وصورتها.':''?></p></div><a class="text-link" href="<?=e($previewUrl)?>" target="_blank" rel="noopener">معاينة الصفحة ↗</a></div>
<ol class="slider-current-list"><?php foreach($currentSlides as $i=>$slide):?><li><div class="slider-current-image"><?php if($slide['image_id']):?><?=picture((int)$slide['image_id'],'',false,'')?><?php else:?><span aria-hidden="true">▥</span><?php endif;?><span class="slider-media-kind"><?=!empty($slide['video'])?'فيديو':'شريحة'?> · <?=sprintf('%02d',$i+1)?></span></div><p dir="<?=$direction?>"><?=e($slide['title'])?></p></li><?php endforeach;?></ol>
<p class="admin-hint">النشر: <?=$entity['status']==='published'?'الصفحة منشورة':'الصفحة مسودة'?> · <?=$tr['status']==='published'?'اللغة منشورة':'اللغة مسودة'?> · <?=$tr['approval']==='approved'?'المحتوى معتمد':'المحتوى بانتظار الاعتماد'?>. <a class="text-link" href="<?=e($editorUrl)?>">تحرير محتوى الصفحة ومراجعها ↗</a></p></section>
<form method="post" action="<?=e($selfUrl)?>" class="admin-editor slider-editor-form" data-unsaved><?=csrf_field()?><input type="hidden" name="action" value="save-slider"><input type="hidden" name="operation" value="save"><input type="hidden" name="type" value="<?=$type?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="locale" value="<?=$locale?>">
<?php $standaloneSlider=true;require ROOT.'/app/views/admin-slider.php';?>
<div class="admin-actions sticky"><button class="button" type="submit">حفظ السلايدر</button><a class="button button-outline" target="_blank" rel="noopener" href="<?=e($previewUrl)?>">معاينة النسخة المحفوظة ↗</a><span class="admin-hint">الحفظ يحدّث سلايدر هذه اللغة فقط.</span></div></form>
<?php if($body['slider']??[]):?><form method="post" action="<?=e($selfUrl)?>" class="admin-card slider-reset-form"><?=csrf_field()?><input type="hidden" name="action" value="save-slider"><input type="hidden" name="operation" value="reset"><input type="hidden" name="type" value="<?=$type?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="locale" value="<?=$locale?>"><input type="hidden" name="slider_revision" value="<?=e($revision)?>"><h2>العودة إلى العرض الافتراضي</h2><p class="admin-hint">يزيل الشرائح المخصصة والمسودات لهذه اللغة، ويعيد السلايدر المستمد من المحتوى. للرئيسية يعود فيلم الشركة المختار في الإعدادات مع القطاعات.</p><button class="button button-outline" type="submit">استعادة السلايدر الافتراضي</button></form><?php endif;?>
