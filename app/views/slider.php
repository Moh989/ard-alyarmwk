<section class="showcase showcase--<?=e($variant)?> <?=count($slides)===1?'showcase--single':''?>" id="<?=$sliderId?>" data-showcase aria-label="<?=e($sliderLabel)?>" aria-roledescription="<?=t('عارض شرائح','carousel')?>">
<div class="showcase-stage" data-slide-stage>
<?php foreach($slides as $i=>$slide):$m=media((int)$slide['image_id']);$hasVideo=!empty($slide['video']);$cover=$m&&((int)$m['id']===(int)setting('profile_cover_id'));?>
<article class="showcase-slide <?=$hasVideo?'showcase-slide--video':''?> <?=!empty($slide['custom'])?'showcase-slide--custom':''?> <?=$cover?'showcase-slide--cover':''?> <?=$cover&&$m['width']>$m['height']?'showcase-slide--landscape-cover':''?> <?=!$m?'showcase-slide--graphic':''?>" id="<?=$sliderId?>-slide-<?=$i?>" data-slide role="group" aria-roledescription="<?=t('شريحة','slide')?>" aria-label="<?=e(($i+1).t(' من ',' of ').count($slides).': '.$slide['title'])?>">
<?php if($hasVideo):require ROOT.'/app/views/slider-video.php';else:?>
<div class="showcase-image"><?php if($m):?><?=picture((int)$slide['image_id'],'',($priority&&$i===0))?><?php else:?><div class="showcase-drawing" aria-hidden="true"><i></i><i></i><i></i><span></span></div><?php endif;?></div>
<div class="showcase-topline" aria-hidden="true"><span class="showcase-mark"><i></i><i></i><i></i></span><span dir="ltr"><?=e(setting('short_name_en'))?></span></div>
<span class="showcase-ordinal" dir="ltr" aria-hidden="true"><?=sprintf('%02d',$i+1)?></span>
<div class="showcase-copy"><p class="showcase-tag"><?=e($slide['tag'])?></p><h2><?=e($slide['title'])?></h2><?php if($slide['text']):?><p class="showcase-description" dir="auto"><?=e($slide['text'])?></p><?php endif;?><?php if($slide['href']):?><a class="showcase-link" href="<?=e($slide['href'])?>" aria-label="<?=e(t('استكشف: ','Explore: ').$slide['title'])?>"><span><?=t('اكتشف المزيد','Explore more')?></span><span class="arrow" aria-hidden="true">↗</span></a><?php endif;?></div>
<?php if($m):?><span class="showcase-photo-note"><?=e($m['classification']==='project'?t('صورة من المشروع','Project photograph'):($cover?t('الغلاف الأصلي','Original cover'):t('صورة توضيحية','Illustrative image')))?></span><?php endif;?>
<?php endif;?>
</article>
<?php endforeach;?>
</div>
<?php if(count($slides)>1):?>
<div class="showcase-controls" data-slide-controls hidden>
<div class="showcase-pagination" role="group" aria-label="<?=t('اختيار الشريحة','Choose a slide')?>"><?php foreach($slides as $i=>$slide):?><button type="button" class="showcase-tab" data-slide-to="<?=$i?>" aria-controls="<?=$sliderId?>-slide-<?=$i?>" aria-label="<?=e(t('عرض الشريحة ','Show slide ').($i+1).': '.$slide['title'])?>" aria-current="<?=$i===0?'true':'false'?>"><span dir="ltr"><?=sprintf('%02d',$i+1)?></span><span class="showcase-tab-title"><?=e($slide['title'])?></span></button><?php endforeach;?></div>
<div class="showcase-arrows"><?php if(array_filter($slides,fn($s)=>!empty($s['video']))):?><button type="button" class="showcase-autoplay" data-slide-autoplay aria-label="<?=t('إيقاف التنقل التلقائي','Pause automatic slide changes')?>" data-auto-pause="<?=t('إيقاف التنقل التلقائي','Pause automatic slide changes')?>" data-auto-play="<?=t('تشغيل التنقل التلقائي','Start automatic slide changes')?>"><svg data-auto-pause-icon viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M7 5h3v14H7zm7 0h3v14h-3z"/></svg><svg data-auto-play-icon viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true" hidden><path d="m9 5 11 7-11 7z"/></svg></button><?php endif;?><button type="button" data-slide-prev aria-label="<?=t('الشريحة السابقة','Previous slide')?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path d="M19 12H5m6-6-6 6 6 6"/></svg></button><button type="button" data-slide-next aria-label="<?=t('الشريحة التالية','Next slide')?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></button></div>
</div>
<noscript><p class="showcase-fallback"><?=t('اسحب أفقياً لتصفح الشرائح، أو استخدم روابطها المباشرة.','Scroll horizontally to browse the slides, or use their direct links.')?></p></noscript>
<?php endif;?>
<p class="sr-only" data-slide-status role="status" aria-live="polite" aria-atomic="true"></p>
</section>
