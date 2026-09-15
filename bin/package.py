from pathlib import Path
import zipfile,json,hashlib
root=Path(__file__).resolve().parents[1]
target=root/'delivery/ard-alyarmwk-source.zip'
roots=['app','bin','config','database','docs','public','tests','vendor']
files=[]
for part in roots:
 for p in (root/part).rglob('*'):
  if not p.is_file():continue
  rel=p.relative_to(root)
  if str(rel)=='config/local.php' or 'screenshots' in rel.parts or p.name=='.DS_Store':continue
  files.append(p)
for name in ['README.md','composer.json','composer.lock','package.json','package-lock.json','.gitignore','.gitattributes','معاينة الموقع.command']:
 files.append(root/name)
files.append(root/'storage/.gitkeep')
with zipfile.ZipFile(target,'w',zipfile.ZIP_DEFLATED,compresslevel=7) as z:
 for p in sorted(files):z.write(p,'ard-alyarmwk/'+str(p.relative_to(root)))
 for d in ['storage/logs/','storage/uploads/','storage/mail/']:z.writestr('ard-alyarmwk/'+d,'')
with zipfile.ZipFile(target) as z:
 assert z.testzip() is None
 names=z.namelist()
 assert not any(any(s in n for s in ['config/local.php','initial-admin.txt','mysql-root.cnf','test-snapshot','qa-smtp-config','node_modules','.venv']) for n in names)
 assert all('ard-alyarmwk/'+p in names for p in ['public/index.php','public/assets/company-profile.pdf','public/assets/images/logo.png','database/schema.sql','database/seed.json','config/config.example.php','vendor/autoload.php','app/slider.php','app/views/slider.php','app/views/admin-slider.php','public/assets/slider.css','public/assets/slider.js','docs/SLIDERS.md','app/video.php','app/views/slider-video.php','app/views/video-viewer.php','database/video-seed.json','bin/install-video.php','bin/optimize-video.py','docs/VIDEO.md','bin/sync-profile.php','database/profile-seed.json','database/profile-web.json','app/profile.php','app/backup.php','bin/install-profile-web.php','bin/check-launch.php','bin/backup.php','bin/restore.php','docs/deployment/HANDOFF.md','docs/bilingual-review.html','config/production.example.php','app/views/theme-head.php','app/views/theme-toggle.php','public/assets/theme.js','public/assets/theme.css','bin/sync-theme-privacy.php','docs/THEMES.md','app/admin-sliders.php','app/views/admin-sliders.php','app/views/admin-slider-editor.php','public/assets/admin-sliders.css','tests/isolated-browser.php','tests/admin-sliders.mjs','tests/admin-sliders-state.php','bin/preview-db.php','bin/preview.sh','docs/LOCAL-MYSQL.md','docs/GITHUB.md','.gitattributes'])
 manifest=json.loads((root/'database/video-seed.json').read_text())
 assert all('ard-alyarmwk/public'+m['path'] in names for m in manifest['measurements'].values())
with zipfile.ZipFile(target) as z:
 profile=json.loads((root/'database/profile-seed.json').read_text())
 web=json.loads((root/'database/profile-web.json').read_text())
 for asset in [profile['pdf'],*profile['covers'].values(),web['web']]:
  data=z.read('ard-alyarmwk/public'+asset['path'])
  assert hashlib.sha256(data).hexdigest()==asset['sha256']
info={'archive':target.name,'bytes':target.stat().st_size,'sha256':hashlib.sha256(target.read_bytes()).hexdigest(),'files':len(files),'excluded':'Runtime database, secrets, administrator credentials, test data, node_modules, Python environment and bulk QA screenshots.'}
(root/'delivery/MANIFEST.json').write_text(json.dumps(info,ensure_ascii=False,indent=2))
print(json.dumps(info,ensure_ascii=False,indent=2))
