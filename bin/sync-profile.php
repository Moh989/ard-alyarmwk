<?php
require __DIR__.'/../app/bootstrap.php';
if(PHP_SAPI!=='cli')exit;
$manifest=json_decode(file_get_contents(ROOT.'/database/profile-seed.json'),true,512,JSON_THROW_ON_ERROR);
$pdf=media((int)setting('profile_id'));$cover=$manifest['covers']['1280'];
try{
    if(!$pdf||$pdf['path']!==$manifest['pdf']['path'])throw new RuntimeException('The selected PDF differs from the prepared profile; no settings changed.');
    foreach([$manifest['pdf'],...array_values($manifest['covers'])] as $asset){
        if(!preg_match('~^/assets/[a-zA-Z0-9/.-]+$~D',$asset['path'])||str_contains($asset['path'],'..'))throw new RuntimeException('Invalid asset path.');
        $file=ROOT.'/public'.$asset['path'];
        if(!is_file($file)||hash_file('sha256',$file)!==$asset['sha256'])throw new RuntimeException('Profile asset checksum mismatch; prepare the current cover first.');
    }
    db()->beginTransaction();
    query('UPDATE media SET size_bytes=?,source=? WHERE id=?',[$manifest['pdf']['bytes'],$manifest['source'],$pdf['id']]);
    $id=query('SELECT id FROM media WHERE path=?',[$cover['path']])->fetchColumn();
    if(!$id){
        query('INSERT INTO media(path,original_name,mime,size_bytes,width,height,alt_ar,alt_en,source,classification) VALUES(?,?,?,?,?,?,?,?,?,?)',[$cover['path'],basename($cover['path']),'image/webp',$cover['bytes'],$cover['width'],$cover['height'],'الغلاف الأفقي للملف التعريفي الحالي لشركة ارض اليرموك','Landscape cover of the current ARD ALYARMWK company profile',$manifest['source'].' Cover extracted from page 1.','document']);
        $id=(int)db()->lastInsertId();
    }
    if(in_array('--activate',$argv,true))set_setting('profile_cover_id',(int)$id);
    db()->commit();echo 'Current PDF metadata synchronized; cover #'.$id.' registered'.(in_array('--activate',$argv,true)?' and selected':'').'. Original PDF unchanged.'.PHP_EOL;
}catch(Throwable $error){if(db()->inTransaction())db()->rollBack();fwrite(STDERR,$error->getMessage().PHP_EOL);exit(1);}
