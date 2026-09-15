<?php
/** The reading copy is valid only while its selected original and source revision match. */
function profile_display_id(): int {
    $original=(int)setting('profile_id');$pack=setting('profile_web',[]);
    if(!is_array($pack)||(int)($pack['source_id']??0)!==$original)return $original;
    $source=media($original);$web=media((int)($pack['web_id']??0));
    if(!$source||!$web||$source['path']!==($pack['source_path']??'')||$web['mime']!=='application/pdf')return $original;
    $file=media_file($source);$copy=media_file($web);
    if(!is_file($file)||!is_file($copy))return $original;
    if(filesize($file)!==($pack['source_bytes']??0)||filemtime($file)!==($pack['source_mtime']??0)||filesize($copy)!==($pack['web_bytes']??0))return $original;
    return (int)$web['id'];
}
