<?php
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)??'/');
if(str_contains($path,"\0")||str_contains($path,'..')||preg_match('~(^|/)\.~',$path)){http_response_code(404);exit;}
if(str_starts_with($path,'/assets/profile/')){http_response_code(404);exit;}
if(preg_match('~^/(assets/[a-zA-Z0-9_./-]+\.(css|js|mjs|png|jpg|jpeg|webp|woff2|ttf|pdf|txt|bcmap|pfb|wasm)|favicon\.png)$~',$path)&&is_file(__DIR__.$path))return false;
require __DIR__.'/index.php';
