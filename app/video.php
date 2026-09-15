<?php
/** Video derivatives are prepared offline; no untrusted transcoding runs in a web request. */
function video_assets(int $id): ?array {
    if(!$id)return null;
    $pack=setting('video_variants',[])[$id]??null;$desktop=media($id);
    if(!$pack||($desktop['mime']??'')!=='video/mp4')return null;
    $poster=media((int)($pack['poster_id']??0));
    if(!$poster||!str_starts_with($poster['mime'],'image/'))return null;
    $mobile=media((int)($pack['mobile_id']??0));
    if(($mobile['mime']??'')!=='video/mp4')$mobile=$desktop;
    return ['id'=>$id,'desktop'=>$desktop,'mobile'=>$mobile,'poster'=>$poster,'duration'=>(float)($pack['duration']??0),'version'=>(string)($pack['version']??$desktop['size_bytes'])];
}
function video_media_ids(int $id): array {
    $v=video_assets($id);return $v?array_values(array_unique([$id,(int)$v['mobile']['id'],(int)$v['poster']['id']])):[];
}
function video_url(int $id,string $version=''): string {
    return '/media/'.$id.($version!==''?'?v='.rawurlencode($version):'');
}
function video_options(): array {
    $options=[''=>'بدون فيديو — صورة فقط'];
    foreach(setting('video_variants',[]) as $id=>$pack){$v=video_assets((int)$id);if($v)$options[$id]='#'.$id.' — '.$v['desktop']['original_name'];}
    return $options;
}

function video_viewer_url(int $id): string { return '/video-viewer?id='.$id.'&lang='.lang(); }
