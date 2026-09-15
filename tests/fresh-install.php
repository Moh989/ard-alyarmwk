<?php
// Local integration test: creates and removes ONLY its own random database.
if(PHP_SAPI!=='cli')exit;
$root=dirname(__DIR__);$admin=parse_ini_file($root.'/storage/mysql-root.cnf',true)['client'];
$server=new PDO('mysql:host='.$admin['host'].';port='.$admin['port'].';charset=utf8mb4',$admin['user'],$admin['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$name='ard_video_qa_'.bin2hex(random_bytes(7));$file=$root.'/storage/'.$name.'.php';$created=false;$results=[];
function runTool(string $root,string $file,array $arguments,int $expected=0): string {
    $process=proc_open(array_merge([PHP_BINARY],$arguments),[1=>['pipe','w'],2=>['pipe','w']],$pipes,$root,array_merge(getenv(),['ARD_CONFIG'=>$file]));
    $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($process)!==$expected||$err!=='')throw new RuntimeException('Installation command failed; check the private PHP log.');
    return $out;
}
try{
    $server->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$created=true;
    $config=require $root.'/config/local.php';$config['db_dsn']='mysql:host='.$admin['host'].';port='.$admin['port'].';dbname='.$name.';charset=utf8mb4';$config['db_user']=$admin['user'];$config['db_password']=$admin['password'];
    $handle=fopen($file,'x');chmod($file,0600);fwrite($handle,"<?php return ".var_export($config,true).';');fclose($handle);
    runTool($root,$file,['bin/migrate.php']);runTool($root,$file,['bin/seed.php']);
    $db=new PDO($config['db_dsn'],$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $setting=fn($key)=>json_decode($db->query('SELECT value FROM settings WHERE setting_key='.$db->quote($key))->fetchColumn(),true);
    $vid=(int)$setting('home_video_id');$pack=$setting('video_variants')[$vid]??null;
    if(!$vid||!$pack||$db->query('SELECT COUNT(*) FROM media')->fetchColumn()!=14)throw new RuntimeException('Initial video registration failed.');
    if($db->query('SELECT COUNT(*) FROM projects')->fetchColumn()!=0)throw new RuntimeException('Unexpected projects.');
    runTool($root,$file,['bin/install-profile-web.php']);
    if(!$setting('profile_web'))throw new RuntimeException('Reading copy missing.');
    $results[]=['name'=>'Fresh schema and seed register bilingual content, three video assets and homepage video without fabricated projects','passed'=>true];
    $before=$db->query('SELECT COUNT(*) FROM media')->fetchColumn();runTool($root,$file,['bin/install-video.php']);
    if($before!=$db->query('SELECT COUNT(*) FROM media')->fetchColumn())throw new RuntimeException('Installer duplicated media.');
    $db->exec("UPDATE settings SET value='null' WHERE setting_key='home_video_id'");runTool($root,$file,['bin/install-video.php']);
    if($setting('home_video_id')!==null)throw new RuntimeException('Installer overwrote disabled setting.');
    runTool($root,$file,['bin/install-video.php','--activate']);if((int)$setting('home_video_id')!==$vid)throw new RuntimeException('Explicit activation failed.');
    $results[]=['name'=>'Repeated install does not duplicate assets, retains disabled home video, and explicit activation restores it','passed'=>true];
    $db->exec("UPDATE translations SET title='Preserved QA edit' WHERE entity_type='pages' AND locale='ar' AND entity_id=1");runTool($root,$file,['bin/seed.php']);
    if($db->query("SELECT title FROM translations WHERE entity_type='pages' AND locale='ar' AND entity_id=1")->fetchColumn()!=='Preserved QA edit')throw new RuntimeException('Seed replaced existing edits.');
    $results[]=['name'=>'Running the seed again preserves existing content edits','passed'=>true];
    $profile=json_decode(file_get_contents($root.'/database/profile-seed.json'),true);
    $coverId=(int)$setting('profile_cover_id');
    if($db->query('SELECT path FROM media WHERE id='.$coverId)->fetchColumn()!==$profile['covers']['1280']['path'])throw new RuntimeException('Fresh profile cover does not match manifest.');
    runTool($root,$file,['bin/sync-profile.php','--activate']);runTool($root,$file,['bin/sync-profile.php','--activate']);
    if((int)$setting('profile_cover_id')!==$coverId||$db->query('SELECT COUNT(*) FROM media')->fetchColumn()!=$before)throw new RuntimeException('Profile synchronization duplicated or replaced existing cover.');
    if((int)$db->query('SELECT size_bytes FROM media WHERE id='.(int)$setting('profile_id'))->fetchColumn()!==$profile['pdf']['bytes'])throw new RuntimeException('PDF size mismatch.');
    $results[]=['name'=>'Current landscape cover and PDF size install correctly; repeated profile synchronization is idempotent','passed'=>true];
    $binding=$setting('profile_web');$invalid=$binding;$invalid['source_mtime']--;
    $statement=$db->prepare("UPDATE settings SET value=? WHERE setting_key='profile_web'");$statement->execute([json_encode($invalid)]);
    $state=json_decode(runTool($root,$file,['-r',"require 'app/bootstrap.php';require 'app/media.php';echo json_encode([profile_display_id(),media_is_public((int)setting('profile_web')['web_id'])]);"]),true);
    if($state!==[(int)$setting('profile_id'),false])throw new RuntimeException('Stale reading copy remained public.');
    runTool($root,$file,['bin/install-profile-web.php']);
    $state=(int)runTool($root,$file,['-r',"require 'app/bootstrap.php';echo profile_display_id();"]);
    if($state!==(int)$binding['web_id'])throw new RuntimeException('Reading copy did not reactivate.');
    $results[]=['name'=>'Source revision mismatch disables the reading copy and public media access; verified reinstall restores it','passed'=>true];
    // Simulated production configuration only: no DNS lookup, HTTPS connection or mail is sent.
    $config['env']='production';$config['base_url']='https://www.ard-alyarmwk.com';$config['session_secure']=true;$config['require_approved']=true;$config['mail_transport']='smtp';
    $config['smtp']=['host'=>'smtp.example.test','port'=>587,'encryption'=>'tls','username'=>'qa@example.test','password'=>bin2hex(random_bytes(20)),'from'=>'qa@example.test','to'=>'qa@example.test'];
    file_put_contents($file,"<?php return ".var_export($config,true).';');
    $db->exec("INSERT INTO admins(email,name,password_hash) VALUES('qa@example.test','Isolated QA','unusable-test-hash')");
    $check=json_decode(runTool($root,$file,['bin/check-launch.php'],1),true);if($check['automated_checks_passed'])throw new RuntimeException('Unapproved English passed launch checks.');
    $db->exec("UPDATE translations SET approval='approved' WHERE locale='en'");
    $check=json_decode(runTool($root,$file,['bin/check-launch.php']),true);if(!$check['automated_checks_passed'])throw new RuntimeException('Complete simulated configuration failed.');
    $db->exec("UPDATE translations SET status='draft' WHERE locale='en' AND entity_type='pages' AND entity_id=1");
    $check=json_decode(runTool($root,$file,['bin/check-launch.php'],1),true);if($check['automated_checks_passed'])throw new RuntimeException('Draft homepage passed launch checks.');
    $results[]=['name'=>'Launch checks reject unapproved English and draft required pages; pass complete simulated settings without sending email','passed'=>true];
}catch(Throwable $e){$results[]=['name'=>'Failure','passed'=>false,'error'=>$e->getMessage()];$failed=true;}
finally{if(is_file($file))unlink($file);if($created)$server->exec("DROP DATABASE `$name`");file_put_contents($root.'/tests/fresh-install-results.json',json_encode($results,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));}
echo json_encode($results,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;exit(!empty($failed)?1:0);
