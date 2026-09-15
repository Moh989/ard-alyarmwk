<section class="container error-page"><span class="error-number" dir="ltr">404</span><p class="eyebrow"><?=t('صفحة غير موجودة','PAGE NOT FOUND')?></p><h1><?=e($row['title'])?></h1><p><?=e($row['summary'])?></p><div class="button-row"><a class="button" href="<?=url()?>"><?=t('العودة إلى الرئيسية','Back to home')?> <span class="arrow" aria-hidden="true">↗</span></a><a class="text-link" href="<?=url('sectors')?>"><?=t('استكشف قطاعاتنا','Explore our sectors')?></a></div></section>

<div class="container page-showcase"><?php page_slider($row,'pages','404','panorama',false);?></div>
