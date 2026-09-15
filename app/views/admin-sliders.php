<?php
$sliderGroups=['pages'=>'سلايدرات الصفحات','sectors'=>'سلايدرات القطاعات','projects'=>'سلايدرات المشاريع'];
$sliderTranslations=[];
foreach(query('SELECT entity_type,entity_id,locale,title,body,status,approval FROM translations') as $tr)$sliderTranslations[$tr['entity_type']][$tr['entity_id']][$tr['locale']]=$tr;
?>
<div class="admin-title"><div><p class="eyebrow">الصور · الفيديو · المحتوى</p><h1>إدارة السلايدرات</h1><p>لكل صفحة سلايدر مستقل. اختر الصفحة واللغة لبدء التعديل.</p></div><a class="button button-outline" href="/admin/media">مكتبة الوسائط ↗</a></div>
<nav class="admin-tabs slider-group-tabs" aria-label="مجموعات السلايدرات"><?php foreach($sliderGroups as $type=>$label):?><a href="#sliders-<?=$type?>"><?=$label?></a><?php endforeach;?></nav>
<?php foreach($sliderGroups as $type=>$label):$entities=query("SELECT * FROM $type ORDER BY sort_order,id")->fetchAll();?>
<section class="slider-admin-group" id="sliders-<?=$type?>" aria-labelledby="heading-<?=$type?>"><div class="slider-group-heading"><h2 id="heading-<?=$type?>"><?=$label?></h2><span class="badge"><?=count($entities)?> سلايدر</span></div>
<?php if(!$entities):?><div class="admin-card"><p>تظهر هنا سلايدرات المشاريع بعد إضافة سجلاتها. تبقى المسودات متاحة للإدارة والمعاينة الخاصة.</p><a class="text-link" href="/admin/list?type=projects">إدارة المشاريع ↗</a></div><?php endif;?>
<div class="slider-admin-list"><?php foreach($entities as $entity):$trs=$sliderTranslations[$type][$entity['id']]??[];$title=admin_slider_label($type,$entity,$trs['ar']['title']??'');$editUrl=admin_slider_url($type,(int)$entity['id']);?>
<article class="slider-admin-row" data-slider-record="<?=e($type.'/'.$entity['slug'])?>">
<div class="slider-row-identity"><a class="slider-row-thumb" href="<?=e($editUrl)?>" tabindex="-1" aria-hidden="true"><?php if($entity['image_id']):?><?=picture((int)$entity['image_id'],'',false,'')?><?php else:?><span aria-hidden="true">▥</span><?php endif;?></a><div><h3><a href="<?=e($editUrl)?>"><?=e($title)?></a></h3><p><span dir="ltr"><?=e($entity['slug'])?></span><?php if($entity['status']==='draft'):?> · صفحة مسودة<?php elseif($type==='pages'&&$entity['slug']==='projects'&&!records('projects','ar')):?> · تظهر عند نشر مشاريع<?php endif;?></p></div></div>
<div class="slider-language-actions"><?php foreach(['ar'=>'العربية','en'=>'English'] as $locale=>$language):$tr=$trs[$locale]??null;$state=admin_slider_state($tr?json_decode($tr['body'],true):[]);?>
<div class="slider-language-action"><a class="slider-edit-link" href="<?=e(admin_slider_url($type,(int)$entity['id'],$locale))?>" aria-label="<?=e('تحرير '.$title.' — '.$language)?>"><span lang="<?=$locale?>"><?=$language?></span><span aria-hidden="true">↗</span></a><span class="admin-hint"><?php if(!$tr):?>محتوى اللغة لم يُضف<?php else:?><?=$state['custom']?'مخصص · '.$state['published'].' منشورة':'افتراضي من المحتوى'?><?=$state['draft']?' · '.$state['draft'].' مسودة':''?><?php endif;?></span></div>
<?php endforeach;?></div></article><?php endforeach;?></div></section><?php endforeach;?>
<p class="admin-hint">سلايدر صفحة 404 يتبع القطاعات المنشورة تلقائياً. حالة نشر الصفحة واعتماد كل لغة تُداران من محرر محتواها.</p>
