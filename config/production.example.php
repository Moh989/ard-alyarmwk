<?php
// Handoff template: recipient supplied by the owner; fill hosting and SMTP values before use.
return [
    'env' => 'production',
    'base_url' => 'https://example.com',
    'db_dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=ard_alyarmwk;charset=utf8mb4',
    'db_user' => 'ard_app',
    'db_password' => '',
    'app_key' => '', // Generate: php -r "echo bin2hex(random_bytes(32));"
    'require_approved' => true,
    'session_secure' => true,
    'mail_transport' => 'disabled', // disabled, log (local QA), smtp
    'smtp' => ['host'=>'', 'port'=>587, 'encryption'=>'tls', 'username'=>'', 'password'=>'', 'from'=>'', 'to'=>'moh.albadry89@gmail.com'],
];
