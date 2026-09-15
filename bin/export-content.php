<?php
// Read-only, portable backup of public content and media metadata; excludes users/messages/secrets.
require __DIR__.'/../app/bootstrap.php';
if(PHP_SAPI!=='cli')exit;
$out=[];foreach(['pages','sectors','projects','translations','media','settings'] as $table)$out[$table]=query("SELECT * FROM $table")->fetchAll();
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
