<?php
require __DIR__.'/../app/bootstrap.php';
if(PHP_SAPI!=='cli')exit;
$options=getopt('',['email:','name:','generate']);
$email=$options['email']??trim(readline('Admin email: '));$name=$options['name']??'مسؤول الموقع';
if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254)exit("Invalid email.\n");
if(isset($options['generate']))$password=bin2hex(random_bytes(16));
else{fwrite(STDOUT,'Password (minimum 12 characters): ');$mode=trim(shell_exec('stty -g')??'');shell_exec('stty -echo');try{$password=rtrim(fgets(STDIN),"\r\n");}finally{if($mode)shell_exec('stty '.escapeshellarg($mode));fwrite(STDOUT,"\n");}}
if(strlen($password)<12||strlen($password)>200)exit("Password must be 12–200 characters.\n");
query('INSERT INTO admins(email,name,password_hash) VALUES(?,?,?)',[$email,$name,password_hash($password,PASSWORD_DEFAULT)]);
echo "Admin created: $email\n";if(isset($options['generate']))echo "Password: $password\n";
