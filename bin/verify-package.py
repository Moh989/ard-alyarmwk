"""Install and smoke-test the exact archive in its own disposable MySQL database."""
import hashlib,json,os,secrets,socket,subprocess,tempfile,time,urllib.request,urllib.error,zipfile
from pathlib import Path
root=Path(__file__).resolve().parents[1]
archive=root/'delivery/ard-alyarmwk-source.zip'
database='ard_package_qa_'+secrets.token_hex(6)
create_php=r'''
$root=$argv[1];$name=$argv[2];$file=$argv[3];$base=$argv[4];
$admin=parse_ini_file($root.'/storage/mysql-root.cnf',true)['client'];
$pdo=new PDO('mysql:host='.$admin['host'].';port='.$admin['port'].';charset=utf8mb4',$admin['user'],$admin['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$config=require $root.'/config/local.php';$config['db_dsn']='mysql:host='.$admin['host'].';port='.$admin['port'].';dbname='.$name.';charset=utf8mb4';$config['db_user']=$admin['user'];$config['db_password']=$admin['password'];$config['base_url']=$base;
$f=fopen($file,'x');chmod($file,0600);fwrite($f,'<?php return '.var_export($config,true).';');fclose($f);
'''
drop_php=r'''
$admin=parse_ini_file($argv[1].'/storage/mysql-root.cnf',true)['client'];$pdo=new PDO('mysql:host='.$admin['host'].';port='.$admin['port'].';charset=utf8mb4',$admin['user'],$admin['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$name=$argv[2];$pdo->exec("DROP DATABASE IF EXISTS `$name`");
'''
with zipfile.ZipFile(archive) as z:
    assert z.testzip() is None
    names=z.namelist()
    assert not any(any(secret in name for secret in ['config/local.php','initial-admin.txt','mysql-root.cnf','test-snapshot','Comp 1.mp4']) for name in names)
    with tempfile.TemporaryDirectory(prefix='ard-package-check-') as directory:
        z.extractall(directory);project=Path(directory)/'ard-alyarmwk'
        profile=json.loads((project/'database/profile-seed.json').read_text())
        web=json.loads((project/'database/profile-web.json').read_text())
        for asset in [profile['pdf'],*profile['covers'].values(),web['web']]:
            assert hashlib.sha256((project/'public'/asset['path'].lstrip('/')).read_bytes()).hexdigest()==asset['sha256']
        manifest=json.loads((project/'database/video-seed.json').read_text())
        for asset in manifest['measurements'].values():
            data=(project/'public'/asset['path'].lstrip('/')).read_bytes()
            assert len(data)==asset['bytes']
            if 'sha256' in asset:assert hashlib.sha256(data).hexdigest()==asset['sha256']
        with socket.socket() as probe:
            probe.bind(('127.0.0.1',0));port=probe.getsockname()[1]
        config=Path(directory)/'private.php';base=f'http://127.0.0.1:{port}';env=os.environ|{'ARD_CONFIG':str(config)};server=None
        try:
            subprocess.run(['php','-r',create_php,str(root),database,str(config),base],check=True,capture_output=True)
            for command in ['bin/migrate.php','bin/seed.php','bin/install-profile-web.php']:
                subprocess.run(['php',command],cwd=project,env=env,check=True,capture_output=True)
            current=json.loads(subprocess.check_output(['php','-r',"require 'app/bootstrap.php';echo json_encode(['film'=>video_assets((int)setting('home_video_id')),'profile'=>profile_display_id(),'original'=>(int)setting('profile_id')]);"],cwd=project,env=env));film=current['film']
            assert current['profile']!=current['original']
            server=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t','public','public/router.php'],cwd=project,env=env,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
            def fetch(path,headers=None,method='GET'):
                try:
                    with urllib.request.urlopen(urllib.request.Request(base+path,headers=headers or {},method=method),timeout=15) as response:
                        return response.status,response.read(),dict(response.headers)
                except urllib.error.HTTPError as error:return error.code,error.read(),dict(error.headers)
            for attempt in range(50):
                try:fetch('/ar/');break
                except urllib.error.URLError:time.sleep(.1)
            routes=['/ar/','/en/','/ar/about','/en/sectors/digital-solutions','/ar/sectors/energy','/ar/profile','/ar/contact','/ar/privacy','/admin/login','/pdf-viewer?lang=ar','/assets/site.css','/assets/site.js','/assets/slider.js','/assets/theme.js','/assets/theme.css','/assets/admin-sliders.css','/admin/sliders','/admin/slider?type=pages&id=1&locale=ar',f"/video-viewer?id={film['id']}&lang=ar"]
            statuses={}
            for route in routes:
                code,data,headers=fetch(route);statuses[route]=code;assert code==200,(route,code)
                if route.startswith('/admin/slider'):
                    assert b'name="password"' in data, 'Slider administration must require authentication'
                if route in ['/ar/','/en/']:
                    assert b'data-slide-autoplay' in data and b'data-showcase-video' in data and b'class="nav-sectors"' in data and b'data-theme-toggle' in data
                    assert data.split(b'class="nav-sector-list">')[1].split(b'</ul>')[0].count(b'<li>')==8
            for key in ['desktop','mobile']:
                code,data,headers=fetch('/media/'+str(film[key]['id']),{'Range':'bytes=0-4095'})
                assert code==206 and len(data)==4096 and b'moov' in data[:128]
            code,data,headers=fetch('/media/'+str(current['profile']),method='HEAD');assert code==200 and int(headers['Content-Length'])==web['web']['bytes']
            code,data,headers=fetch('/media/'+str(current['profile']),{'Range':'bytes=0-63'});assert code==206 and data.startswith(b'%PDF-')
            code,data,headers=fetch('/media/'+str(current['original']),method='HEAD');assert code==200 and int(headers['Content-Length'])==profile['pdf']['bytes']
            assert fetch(manifest['assets']['desktop']['path'])[0]==404
            assert fetch(web['web']['path'])[0]==404
            assert fetch('/config/local.php')[0]==404
            assert fetch('/ar/not-a-real-page')[0]==404
            result={'archive_integrity':'passed','archive_sha256':hashlib.sha256(archive.read_bytes()).hexdigest(),'isolated_fresh_install':'passed; temporary database and config removed','extracted_copy_http':statuses,'desktop_mobile_ranges':'206, 4096 bytes; faststart metadata present','profile_web_bytes':web['web']['bytes'],'profile_original_bytes':profile['pdf']['bytes'],'all_asset_checksums':'passed','sector_dropdown':'8 links in each language','light_dark_theme_assets':'present with controls in both language headers','direct_video_and_web_pdf_asset_access':404,'secrets_in_archive':False,'slider_administration':'new routes protected; standalone editor and stylesheet included','real_hosting_or_smtp_tested':False}
        finally:
            if server:server.terminate();server.wait(timeout=10)
            subprocess.run(['php','-r',drop_php,str(root),database],check=True,capture_output=True)
(root/'delivery/PACKAGE-VERIFICATION.json').write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n')
print(json.dumps(result,ensure_ascii=False,indent=2))
