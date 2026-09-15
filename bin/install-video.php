<?php
require_once __DIR__.'/../app/bootstrap.php';
if(PHP_SAPI!=='cli')exit;
$file=ROOT.'/database/video-seed.json';if(!is_file($file))exit("Prepare the video assets first.\n");
$data=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);$ids=[];
db()->beginTransaction();
try{
 foreach($data['assets'] as $key=>$asset){
  $path=$asset['path'];if(!preg_match('~^/assets/(video|images)/[a-zA-Z0-9.-]+$~D',$path))throw new RuntimeException('Invalid video asset path.');
  $file=ROOT.'/public'.$path;if(!is_file($file))throw new RuntimeException('Missing video asset.');
  $hash=hash_file('sha256',$file);if($hash!==$data['measurements'][$key]['sha256'])throw new RuntimeException('Video checksum does not match manifest.');
  $id=query('SELECT id FROM media WHERE path=?',[$path])->fetchColumn();
  if(!$id){query('INSERT INTO media(path,original_name,mime,size_bytes,width,height,alt_ar,alt_en,source,classification) VALUES(?,?,?,?,?,?,?,?,?,?)',[$path,basename($file),$asset['mime'],filesize($file),$asset['width'],$asset['height'],$asset['alt_ar'],$asset['alt_en'],$asset['source'],$asset['classification']]);$id=(int)db()->lastInsertId();}
  $ids[$key]=(int)$id;
 }
 $variants=setting('video_variants',[]);$variants[$ids['desktop']]=['mobile_id'=>$ids['mobile'],'poster_id'=>$ids['poster'],'duration'=>$data['duration'],'version'=>substr($data['measurements']['desktop']['sha256'],0,16)];set_setting('video_variants',$variants);
 if(in_array('--activate',$argv??[],true)||!query("SELECT setting_key FROM settings WHERE setting_key='home_video_id'")->fetchColumn())set_setting('home_video_id',$ids['desktop']);
 db()->commit();echo 'Prepared film registered. Desktop media #'.$ids['desktop'].'. No page text or existing slides were replaced.'.PHP_EOL;
}catch(Throwable $e){db()->rollBack();throw $e;}
