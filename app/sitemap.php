<?php
header('Content-Type: application/xml; charset=utf-8');header('Cache-Control: public, max-age=300');
echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">';
foreach(['ar','en'] as $locale){foreach(['pages','sectors','projects'] as $type){foreach(records($type,$locale) as $r){if($type==='pages'&&$r['slug']==='projects'&&!records('projects',$locale))continue;$path=$type==='pages'?($r['slug']==='home'?'':$r['slug']):$type.'/'.$r['slug'];echo '<url><loc>'.e(absolute(url($path,$locale))).'</loc><lastmod>'.date('c',strtotime($r['updated_at'])).'</lastmod>';foreach(['ar','en'] as $lc)if(record($type,$r['slug'],$lc))echo '<xhtml:link rel="alternate" hreflang="'.$lc.'" href="'.e(absolute(url($path,$lc))).'"/>';echo '</url>';}}}
echo '</urlset>';
