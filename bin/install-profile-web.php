<?php
require_once __DIR__.'/../app/bootstrap.php';
if(PHP_SAPI!=='cli')exit;
try {
    $manifest=json_decode(file_get_contents(ROOT.'/database/profile-web.json'),true,512,JSON_THROW_ON_ERROR);
    $selected=query("SELECT value FROM settings WHERE setting_key='profile_id'")->fetchColumn();
    $source=media((int)json_decode($selected,true));
    if(!$source||$source['path']!==$manifest['source']['path'])throw new RuntimeException('Selected original differs; reading copy was not activated.');
    foreach(['source','web'] as $key){
        $asset=$manifest[$key];
        if(!preg_match('~^/assets/[a-zA-Z0-9/.-]+$~D',$asset['path'])||str_contains($asset['path'],'..'))throw new RuntimeException('Invalid asset path.');
        $file=ROOT.'/public'.$asset['path'];
        if(!is_file($file)||filesize($file)!==$asset['bytes']||hash_file('sha256',$file)!==$asset['sha256'])throw new RuntimeException('PDF checksum mismatch; prepare and review the current reading copy first.');
    }
    $web=$manifest['web'];db()->beginTransaction();
    $id=query('SELECT id FROM media WHERE path=?',[$web['path']])->fetchColumn();
    if(!$id){query('INSERT INTO media(path,original_name,mime,size_bytes,alt_ar,alt_en,source,classification) VALUES(?,?,?,?,?,?,?,?)',[$web['path'],'company-profile-web.pdf','application/pdf',$web['bytes'],'نسخة قراءة خفيفة من الملف التعريفي الرسمي','Optimised reading copy of the official company profile','Derived from original SHA256 '.$manifest['source']['sha256'].'. All 10 pages, vector text and dimensions preserved; see docs/PROFILE-WEB.md.','document']);$id=(int)db()->lastInsertId();}
    set_setting('profile_web',['source_id'=>(int)$source['id'],'source_path'=>$source['path'],'source_bytes'=>$manifest['source']['bytes'],'source_sha256'=>$manifest['source']['sha256'],'source_mtime'=>filemtime(media_file($source)),'web_id'=>(int)$id,'web_bytes'=>$web['bytes']]);
    audit('profile.web.install',(string)$id);db()->commit();echo "Verified reading copy registered and linked to the current original.\n";
}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
