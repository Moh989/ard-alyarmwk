<?php
function media_is_public(int $id): bool {
    if(profile_display_id()===$id)return true;
    foreach(['logo_id','profile_id','profile_cover_id','about_image_id'] as $key)if((int)setting($key)===$id)return true;
    if(in_array($id,video_media_ids((int)setting('home_video_id')),true)&&(record('pages','home','ar')||record('pages','home','en')))return true;
    foreach(['ar','en'] as $lc)foreach(['pages','sectors','projects'] as $type)foreach(records($type,$lc) as $r){
        if((int)$r['image_id']===$id)return true;
        if($type==='projects'&&in_array($id,json_decode($r['gallery']??'[]',true)??[],true))return true;
        foreach($r['body']['slider']??[] as $slide){
            if(($slide['status']??'draft')==='published'&&!empty($slide['title'])&&in_array($id,video_media_ids((int)($slide['video_id']??0)),true)){
                if($type!=='projects'||(media((int)$slide['video_id'])['classification']??'')==='project')return true;
            }
            if(($slide['status']??'draft')!=='published'||empty($slide['title'])||(int)($slide['image_id']??0)!==$id)continue;
            if($type==='projects'&&(media($id)['classification']??'')!=='project')continue;
            return true;
        }
    }
    return false;
}
function serve_media(int $id): void {
    $m=media($id);if(!$m||(!media_is_public($id)&&!is_admin())){http_response_code(404);exit;}
    $file=media_file($m);
    if(!is_file($file)){http_response_code(404);exit;}
    if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
    header('Content-Type: '.$m['mime']);header('X-Content-Type-Options: nosniff');header('Content-Disposition: '.(getstr('download')==='1'?'attachment':'inline').'; filename="'.($m['mime']==='application/pdf'?'company-profile.pdf':basename($file)).'"');
    // Native browser video documents need their own origin to read the same MP4 stream.
    header($m['mime']==='video/mp4'
        ? "Content-Security-Policy: sandbox allow-same-origin; default-src 'none'; media-src 'self'; style-src 'unsafe-inline'"
        : "Content-Security-Policy: sandbox; default-src 'none'; style-src 'unsafe-inline'");
    header('Cache-Control: '.(media_is_public($id)?'public, max-age=86400':'private, no-store'));
    $size=filesize($file);$start=0;$end=$size-1;header('Accept-Ranges: bytes');
    if(isset($_SERVER['HTTP_RANGE'])){if(!preg_match('/^bytes=(\d*)-(\d*)$/',$_SERVER['HTTP_RANGE'],$r)||($r[1]===''&&$r[2]==='')){http_response_code(416);header('Content-Range: bytes */'.$size);exit;}if($r[1]===''){$start=max(0,$size-(int)$r[2]);}else{$start=(int)$r[1];if($r[2]!=='')$end=min($end,(int)$r[2]);}if($start>$end||$start>=$size){http_response_code(416);header('Content-Range: bytes */'.$size);exit;}http_response_code(206);header("Content-Range: bytes $start-$end/$size");}
    header('Content-Length: '.($end-$start+1));if($_SERVER['REQUEST_METHOD']==='HEAD')return;
    $f=fopen($file,'rb');fseek($f,$start);$left=$end-$start+1;while($left>0&&!feof($f)){ $chunk=fread($f,min(65536,$left));echo $chunk;$left-=strlen($chunk);}fclose($f);
}
function upload_media(): int {
    $f=$_FILES['file']??null;if(!$f||$f['error']!==UPLOAD_ERR_OK)throw new InvalidArgumentException('تعذر رفع الملف. تحقق من الحجم وإعدادات الخادم.');
    $class=input('classification','illustration');if(!in_array($class,['identity','illustration','project','document'],true))throw new InvalidArgumentException('تصنيف غير صحيح.');
    $name=basename($f['name']);$ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));$mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $types=['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp','pdf'=>'application/pdf'];
    if(!isset($types[$ext])||$types[$ext]!==$mime)throw new InvalidArgumentException('الأنواع المقبولة: PNG وJPEG وWebP وPDF، مع تطابق النوع والامتداد.');
    if($f['size']>($mime==='application/pdf'?40:8)*1048576)throw new InvalidArgumentException('الحجم الأقصى 8 MB للصورة و40 MB للملف PDF.');
    if($mime==='application/pdf'&&$class!=='document')throw new InvalidArgumentException('ملف PDF يجب أن يصنف كمستند.');
    $altAr=input('alt_ar');$altEn=input('alt_en');$source=input('source');
    if(mb_strlen($altAr)>500||mb_strlen($altEn)>500||mb_strlen($source)>4000)throw new InvalidArgumentException('أحد الحقول أطول من المسموح.');
    if($source===''||($mime!=='application/pdf'&&($altAr===''||$altEn==='')))throw new InvalidArgumentException('أضف مصدر الملف والنص البديل باللغتين للصور.');
    $dir=ROOT.'/storage/uploads';if(!is_dir($dir))mkdir($dir,0700,true);$base=bin2hex(random_bytes(20));$width=$height=null;
    if($mime==='application/pdf'){
        $data=file_get_contents($f['tmp_name']);if(!str_starts_with($data,'%PDF-')||!str_contains(substr($data,-4096),'%%EOF'))throw new InvalidArgumentException('ترويسة أو نهاية ملف PDF غير صحيحة.');
        $file=$base.'.pdf';if(!move_uploaded_file($f['tmp_name'],$dir.'/'.$file))throw new RuntimeException('Cannot move upload');
    }else{
        $dim=@getimagesize($f['tmp_name']);if(!$dim||$dim[0]*$dim[1]>25000000||$dim[0]>10000||$dim[1]>10000)throw new InvalidArgumentException('أبعاد الصورة غير مقبولة. الحد الأقصى 25 مليون بكسل.');
        $im=@imagecreatefromstring(file_get_contents($f['tmp_name']));if(!$im)throw new InvalidArgumentException('تعذر قراءة الصورة.');
        if($class==='identity'){$file=$base.'.png';$mime='image/png';imagesavealpha($im,true);if(!imagepng($im,$dir.'/'.$file))throw new RuntimeException('Image write failed');$width=$dim[0];$height=$dim[1];}
        else{$width=min(1920,$dim[0]);$height=(int)round($dim[1]*$width/$dim[0]);$scaled=imagescale($im,$width,$height);$file=$base.'.webp';$mime='image/webp';if(!imagewebp($scaled,$dir.'/'.$file,85))throw new RuntimeException('Image write failed');imagedestroy($scaled);}imagedestroy($im);
    }
    chmod($dir.'/'.$file,0600);
    try{query('INSERT INTO media(path,original_name,mime,size_bytes,width,height,alt_ar,alt_en,source,classification) VALUES(?,?,?,?,?,?,?,?,?,?)',[$file,$name,$mime,filesize($dir.'/'.$file),$width,$height,$altAr,$altEn,$source,$class]);}catch(Throwable $e){unlink($dir.'/'.$file);throw $e;}
    $id=(int)db()->lastInsertId();audit('media.upload',(string)$id);return $id;
}
