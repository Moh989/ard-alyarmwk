<?php
// Idempotent wording corrections only; do not approve translations or overwrite custom text.
require __DIR__.'/../app/bootstrap.php';
if(PHP_SAPI!=='cli')exit;
$review=json_decode(file_get_contents(ROOT.'/database/editorial-review.json'),true,512,JSON_THROW_ON_ERROR);
$from=array_column($review['replacements'],0);$to=array_column($review['replacements'],1);$count=0;
db()->beginTransaction();
foreach(query('SELECT * FROM translations') as $row){
    $fields=[];foreach(['summary','body','seo_description'] as $key)$fields[$key]=str_replace($from,$to,$row[$key]);
    if($fields['summary']===$row['summary']&&$fields['body']===$row['body']&&$fields['seo_description']===$row['seo_description'])continue;
    query('UPDATE translations SET summary=?,body=?,seo_description=? WHERE id=?',[$fields['summary'],$fields['body'],$fields['seo_description'],$row['id']]);
    audit('content.editorial_review',(string)$row['id']);$count++;
}
db()->commit();echo "Editorial wording updated in $count translations. Publication approvals unchanged.\n";
