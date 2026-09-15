<?php
require_once ROOT.'/app/slider.php';
$canonical=url($route==='404'?'':($slug==='home'?'':$route));
$seoTitle=trim(preg_replace('/\s+/u',' ',$row['seo_title']?:$row['title']));
$description=$row['seo_description']?:$row['summary'];
$other=lang()==='ar'?'en':'ar';$counterpart=record($type,$slug,$other,$preview);
$ogImage=$row['image_id']?media_url((int)$row['image_id']):media_url((int)setting('logo_id'));
?><!doctype html>
<html lang="<?= lang() ?>" dir="<?= lang()==='ar'?'rtl':'ltr' ?>">
<head><?php require ROOT.'/app/views/theme-head.php';?><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($seoTitle) ?> | <?= e(setting('short_name_'.lang())) ?></title>
<meta name="description" content="<?= e($description) ?>">
<?php if(config('env')==='local'||$route==='404'||$preview):?><meta name="robots" content="noindex,nofollow"><?php endif;?>
<link rel="canonical" href="<?= e(absolute($canonical)) ?>">
<?php foreach(['ar','en'] as $lc):if(record($type,$slug,$lc,$preview)):?><link rel="alternate" hreflang="<?= $lc ?>" href="<?= e(absolute(url($slug==='home'?'':$route,$lc))) ?>"><?php endif;endforeach;?>
<link rel="alternate" hreflang="x-default" href="<?= e(absolute(url($slug==='home'?'':$route,'ar'))) ?>">
<meta property="og:type" content="website"><meta property="og:locale" content="<?= lang()==='ar'?'ar_IQ':'en_US' ?>"><meta property="og:title" content="<?= e($seoTitle) ?>"><meta property="og:description" content="<?= e($description) ?>"><meta property="og:url" content="<?= e(absolute($canonical)) ?>"><meta property="og:image" content="<?= e(absolute($ogImage)) ?>"><meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="<?= e($seoTitle) ?>"><meta name="twitter:description" content="<?= e($description) ?>"><meta name="twitter:image" content="<?= e(absolute($ogImage)) ?>">
<link rel="icon" href="<?=e(media_url((int)setting('logo_id')))?>"><link rel="preload" href="/assets/fonts/IBMPlexSansArabic-Regular.woff2" as="font" type="font/woff2" crossorigin><link rel="preload" href="/assets/fonts/IBMPlexSansArabic-SemiBold.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/assets/site.css"><link rel="stylesheet" href="/assets/slider.css"><script src="/assets/site.js" defer></script><script src="/assets/slider.js" defer></script>
<script type="application/ld+json" nonce="<?= e($GLOBALS['csp_nonce']) ?>"><?= json_encode_safe(['@context'=>'https://schema.org','@type'=>'Organization','name'=>setting('short_name_'.lang()),'legalName'=>full_name(),'url'=>absolute('/'),'logo'=>absolute(media_url((int)setting('logo_id'))),'email'=>setting('email'),'telephone'=>setting('phone'),'foundingDate'=>setting('founded'),'address'=>['@type'=>'PostalAddress','streetAddress'=>setting('address_'.lang()),'addressLocality'=>'Baghdad','addressCountry'=>'IQ']]) ?></script>
<link rel="stylesheet" href="/assets/theme.css?v=<?=substr(hash_file('sha256',ROOT.'/public/assets/theme.css'),0,12)?>"></head><body class="page-<?= e($slug) ?>">
<a class="skip-link" href="#main"><?= t('انتقل إلى المحتوى','Skip to content') ?></a>
<?php if($preview):?><div class="preview-bar">معاينة خاصة — قد تتضمن مسودات غير منشورة. <a href="/admin/edit?type=<?=e($type)?>&id=<?=(int)$row['id']?>">العودة للتحرير</a></div><?php endif;?>
<header class="site-header"><div class="container header-inner">
<a class="brand" href="<?= url() ?>" aria-label="<?= t('ارض اليرموك — الرئيسية','ARD ALYARMWK CO. — Home') ?>"><?= picture((int)setting('logo_id'),'brand-logo',true) ?><span class="brand-words"><strong><?=e(setting('short_name_'.lang()))?></strong><span dir="ltr"><?= t('ARD ALYARMWK CO.','COMPANY · IRAQ') ?></span></span></a>
<button class="menu-toggle" aria-expanded="false" aria-controls="main-nav"><span class="menu-lines" aria-hidden="true"></span><span class="sr-only"><?= t('القائمة الرئيسية','Main menu') ?></span></button>
<nav class="main-nav" id="main-nav" aria-label="<?=t('التنقل الرئيسي','Main navigation')?>">
<?php foreach([''=>t('الرئيسية','Home'),'about'=>t('من نحن','About'),'sectors'=>t('قطاعاتنا','Sectors'),'projects'=>t('مشاريعنا','Projects'),'profile'=>t('الملف التعريفي','Profile'),'contact'=>t('اتصل بنا','Contact')] as $link=>$label):if($link==='projects'&&!$projects)continue;if(!record('pages',$link===''?'home':$link))continue;?>
<?php if($link==='sectors'):?>
<details class="nav-sectors" <?=($route==='sectors'||$type==='sectors')?'data-active="true"':''?>>
<summary class="nav-sectors-toggle"><?=e($label)?><svg width="12" height="12" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></summary>
<div class="nav-sector-panel"><div class="nav-sector-intro"><span class="eyebrow"><?=t('مجالات أعمالنا','OUR FIELDS OF WORK')?></span><strong><?=t('مجالات متنوّعة. رؤية واحدة.','Diverse sectors. One vision.')?></strong><a class="nav-sector-overview" href="<?=url('sectors')?>" <?=$route==='sectors'?'aria-current="page"':''?>><?=t('عرض جميع القطاعات','Explore all sectors')?><span class="arrow" aria-hidden="true">↗</span></a></div><ul class="nav-sector-list">
<?php foreach($sectors as $i=>$sector):?><li><a href="<?=url('sectors/'.$sector['slug'])?>" <?=($type==='sectors'&&$slug===$sector['slug'])?'aria-current="page"':''?>><span class="nav-sector-number" dir="ltr" aria-hidden="true"><?=sprintf('%02d',$i+1)?></span><span><?=e($sector['title'])?></span><span class="nav-sector-arrow arrow" aria-hidden="true">↗</span></a></li><?php endforeach;?>
</ul></div></details>
<?php else:?>
<a href="<?=url($link)?>" <?=($route===$link||($link==='sectors'&&$type==='sectors')||($link==='projects'&&$type==='projects'))?'aria-current="page"':''?>><?= $label ?></a>
<?php endif;endforeach; ?></nav>
<div class="header-actions"><?php require ROOT.'/app/views/theme-toggle.php';?><div class="language-switch" aria-label="<?=t('اللغة','Language')?>"><a lang="<?= $other ?>" href="<?= url($counterpart?($slug==='home'?'':$route):'',$other) ?>"><?= t('English','العربية') ?><span aria-hidden="true">◎</span></a></div><a class="button button-small header-cta" href="<?=url('contact')?>"><?= t('ناقش مشروعك معنا','Discuss your project') ?><span class="arrow" aria-hidden="true">↗</span></a></div>
</div></header>
<main id="main">
<?php if($route!=='404'&&$slug!=='home'):?><div class="container breadcrumb"><a href="<?=url()?>"><?=t('الرئيسية','Home')?></a><span aria-hidden="true">/</span><?php if($type!=='pages'):?><a href="<?=url($type)?>"><?=$type==='sectors'?t('قطاعاتنا','Sectors'):t('مشاريعنا','Projects')?></a><span aria-hidden="true">/</span><?php endif;?><span><?= e($type==='pages'?['about'=>t('من نحن','About'),'sectors'=>t('قطاعاتنا','Sectors'),'profile'=>t('الملف التعريفي','Profile'),'contact'=>t('اتصل بنا','Contact'),'privacy'=>t('الخصوصية','Privacy'),'projects'=>t('مشاريعنا','Projects')][$slug]:$row['title']) ?></span></div><?php endif;?>
<?php $template=$route==='404'?'404':($type!=='pages'?'detail':$slug); require ROOT.'/app/views/'.$template.'.php'; ?>
</main>
<footer class="site-footer"><div class="container footer-main"><div class="footer-company"><a class="brand" href="<?=url()?>"><?=picture((int)setting('logo_id'),'brand-logo')?><span class="brand-words"><strong><?=e(setting('short_name_'.lang()))?></strong><span><?=t('مجالات متعددة. رؤية واحدة.','Diverse sectors. One vision.')?></span></span></a><p class="legal-name"><?=e(full_name())?></p></div><div><h2><?=t('اكتشف الشركة','Explore')?></h2><a href="<?=url('about')?>"><?=t('من نحن','About us')?></a><a href="<?=url('sectors')?>"><?=t('قطاعاتنا','Our sectors')?></a><a href="<?=url('profile')?>"><?=t('الملف التعريفي','Company profile')?></a></div><div class="footer-contact"><h2><?=t('ابقَ على تواصل','Get in touch')?></h2><a class="ltr" href="tel:<?=e(preg_replace('/\s/','',setting('phone')))?>"><?=e(setting('phone'))?></a><a class="ltr" href="mailto:<?=e(setting('email'))?>"><?=e(setting('email'))?></a><p><?=nl2br(e(setting('address_'.lang())))?></p></div></div><div class="container footer-bottom"><span>© <?=date('Y')?> <?=e(setting('short_name_'.lang()))?>. <?=t('جميع الحقوق محفوظة.','All rights reserved.')?></span><a href="<?=url('privacy')?>"><?=t('سياسة الخصوصية','Privacy policy')?></a><a class="back-top" href="#main"><?=t('إلى الأعلى','Back to top')?> ↑</a></div></footer>
</body></html>
