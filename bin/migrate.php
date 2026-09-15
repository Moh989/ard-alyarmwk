<?php
require __DIR__.'/../app/bootstrap.php';
if(PHP_SAPI!=='cli')exit;
db()->exec(file_get_contents(ROOT.'/database/schema.sql'));echo "Database schema installed.\n";
