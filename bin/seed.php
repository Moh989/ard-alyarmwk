<?php
require __DIR__.'/../app/bootstrap.php';
if(PHP_SAPI!=='cli')exit;
if((int)query('SELECT COUNT(*) FROM pages')->fetchColumn()>0)exit("Content already exists; seed skipped to preserve edits.\n");
$data=json_decode(file_get_contents(ROOT.'/database/seed.json'),true,512,JSON_THROW_ON_ERROR);
db()->beginTransaction();
foreach($data['settings'] as $k=>$v)set_setting($k,$v);
$ids=[];
foreach($data['media'] as $m){
 $path='/assets/images/'.$m['file'];if($m['key']==='profile')$path='/assets/company-profile.pdf';
 $file=ROOT.'/public'.$path;if(!is_file($file))throw new RuntimeException('Missing asset '.$path);
 $size=@getimagesize($file);
 query('INSERT INTO media(path,original_name,mime,size_bytes,width,height,alt_ar,alt_en,source,classification) VALUES(?,?,?,?,?,?,?,?,?,?)',[$path,basename($file),(new finfo(FILEINFO_MIME_TYPE))->file($file),filesize($file),$size[0]??null,$size[1]??null,$m['alt_ar'],$m['alt_en'],$m['source'],$m['classification']]);$ids[$m['key']]=(int)db()->lastInsertId();
}
set_setting('logo_id',$ids['logo']);set_setting('profile_id',$ids['profile']);set_setting('profile_cover_id',$ids['profile-cover']);set_setting('about_image_id',$ids['team']);
foreach($data['items'] as $r){
 $table=content_table($r['kind']);query("INSERT INTO $table(slug,status,sort_order,image_id) VALUES(?,?,?,?)",[$r['slug'],$r['status'],$r['sort_order'],$ids[$r['image']]??null]);$id=(int)db()->lastInsertId();
 foreach($r['translations'] as $locale=>$tr)query('INSERT INTO translations(entity_type,entity_id,locale,title,summary,body,seo_title,seo_description,status,approval,source_ref) VALUES(?,?,?,?,?,?,?,?,?,?,?)',[$table,$id,$locale,$tr['title'],$tr['summary'],json_encode_safe($tr['body']),$tr['seo_title'],$tr['seo_description'],$tr['status'],$tr['approval'],$tr['source_ref']]);
}
db()->commit();echo "Source-backed bilingual content installed; no projects fabricated.\n";

if(is_file(ROOT.'/database/video-seed.json'))require ROOT.'/bin/install-video.php';
if(is_file(ROOT.'/database/profile-web.json'))require ROOT.'/bin/install-profile-web.php';
