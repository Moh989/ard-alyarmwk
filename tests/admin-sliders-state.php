<?php
// Fixtures are forbidden against the owner's preview database.
if(PHP_SAPI!=='cli'||!getenv('ARD_CONFIG'))exit(1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!preg_match('/dbname=ard_browser_qa_[a-f0-9]+(?:;|$)/',config('db_dsn')))exit(1);
$action=$argv[1]??'';
if($action==='catalog'){
    $data=[];foreach(['pages','sectors','projects'] as $type)$data[$type]=query("SELECT * FROM $type ORDER BY sort_order,id")->fetchAll();
    $data['translations']=query('SELECT * FROM translations ORDER BY id')->fetchAll();$data['video']=(int)setting('home_video_id');
    $data['messages']=(int)query('SELECT COUNT(*) FROM contact_messages')->fetchColumn();
    echo json_encode_safe($data);
}elseif($action==='concurrent-copy'){
    $id=(int)$argv[2];$body=json_decode(query("SELECT body FROM translations WHERE entity_type='pages' AND entity_id=? AND locale='ar'",[$id])->fetchColumn(),true);
    $body['test_preserved']='Concurrent non-slider content';
    query("UPDATE translations SET body=?,seo_description=? WHERE entity_type='pages' AND entity_id=? AND locale='ar'",[json_encode_safe($body),'Concurrent SEO metadata',$id]);
}elseif($action==='draft-project'){
    query("INSERT INTO projects(slug,status,sort_order) VALUES('qa-slider-project','draft',999)");$id=(int)db()->lastInsertId();
    query("INSERT INTO translations(entity_type,entity_id,locale,title,summary,body,status,approval) VALUES('projects',?,'ar','مشروع اختبار محلي','اختبار غير منشور','{}','draft','review')",[$id]);
    echo $id;
}else exit(1);
