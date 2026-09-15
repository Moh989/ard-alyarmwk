import {chromium,expect} from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import fs from 'node:fs/promises';
import {execFileSync} from 'node:child_process';
if(!process.env.TEST_URL||!process.env.ARD_CONFIG||!process.env.TEST_ADMIN_CREDENTIALS)throw Error('Use php tests/isolated-browser.php admin-sliders');
const BASE=process.env.TEST_URL,results=[],errors=[],violations=[];
const state=()=>JSON.parse(execFileSync('php',['tests/admin-sliders-state.php','catalog'],{encoding:'utf8'}));
const initial=state(),home=initial.pages.find(p=>p.slug==='home'),about=initial.pages.find(p=>p.slug==='about'),sector=initial.sectors.find(p=>p.slug==='general-contracting'),energy=initial.sectors.find(p=>p.slug==='energy');
const tr=(data,type,id,lang='ar')=>data.translations.find(t=>t.entity_type===type&&Number(t.entity_id)===Number(id)&&t.locale===lang);
const unrelated=row=>{const {updated_at,body,...rest}=row;const {slider,...copy}=JSON.parse(body);return {...rest,body:copy};};
const edit=(type,id,lc='ar')=>`${BASE}/admin/slider?type=${type}&id=${id}&locale=${lc}`;
const pass=(name,details={})=>{results.push({name,passed:true,...details});console.log('PASS '+name);};
const browser=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'});
const context=await browser.newContext({viewport:{width:1440,height:1050},reducedMotion:'reduce'}),page=await context.newPage(),anon=await browser.newContext(),publicPage=await anon.newPage();
page.on('pageerror',e=>errors.push(e.message));publicPage.on('pageerror',e=>errors.push(e.message));
page.on('dialog',d=>d.accept());
const formData=()=>page.locator('.slider-editor-form').evaluate(f=>Object.fromEntries(new FormData(f)));
const post=async(fields={})=>context.request.post(page.url(),{form:{...await formData(),...fields},maxRedirects:0});
const fillSlide=async(i,title,{image=energy.image_id,video='',status='published',order=i+1}={})=>{
 const details=page.locator('.slider-fields details').nth(i);if(!await details.evaluate(d=>d.open))await details.locator('summary').click();
 await page.locator(`#slide-title-${i}`).fill(title);await page.locator(`#slide-status-${i}`).selectOption(status);await page.locator(`#slide-image-${i}`).selectOption(String(image));await page.locator(`#slide-video-${i}`).selectOption(String(video));await page.locator(`#slide-order-${i}`).fill(String(order));
};
const save=async()=>{await page.getByRole('button',{name:'حفظ السلايدر',exact:true}).click();await expect(page.locator('[role=status]')).toContainText('تم حفظ السلايدر');};
await fs.mkdir('tests/screenshots/admin-sliders',{recursive:true});
try{
 for(const path of ['/admin/sliders',`/admin/slider?type=pages&id=${home.id}&locale=ar`])expect((await anon.request.get(BASE+path,{maxRedirects:0})).status()).toBe(303);
 await page.goto(BASE+'/admin/login');const credentials=await fs.readFile(process.env.TEST_ADMIN_CREDENTIALS,'utf8');await page.locator('#email').fill(credentials.match(/Admin created: (.+)/)[1]);await page.locator('#password').fill(credentials.match(/Password: (.+)/)[1]);await page.locator('form button').click();
 await page.getByRole('link',{name:'السلايدرات',exact:true}).click();await expect(page.locator('h1')).toHaveText('إدارة السلايدرات');await expect(page.locator('#sliders-sectors .slider-admin-row')).toHaveCount(8);await expect(page.locator('#sliders-pages .slider-admin-row')).toHaveCount(initial.pages.length);await expect(page.locator('#sliders-projects')).toContainText('بعد إضافة سجلاتها');
 const links=await page.locator('.slider-edit-link').evaluateAll(a=>a.map(n=>n.getAttribute('href')));expect(new Set(links).size).toBe(30);
 for(const href of links){const r=await context.request.get(BASE+href);expect(r.status()).toBe(200);expect(await r.text()).toContain('name="action" value="save-slider"');}
 pass('Protected slider centre lists all 15 existing pages/sectors with 30 independent language editors');
 await page.goto(edit('pages',home.id));await expect(page.locator('h1')).toHaveText('سلايدر الرئيسية');await expect(page.locator('.slider-current-list li')).toHaveCount(4);await expect(page.locator('.slider-media-kind').first()).toContainText('فيديو');
 const csrf=await page.locator('.slider-editor-form [name=csrf]').inputValue();expect((await post({csrf:'invalid'})).status()).toBe(419);
 expect((await post({type:'sectors'})).status()).toBe(422);
 for(const params of ['type=unknown&id=1','type=pages&id=999999','type=pages&id=1&locale=fr','type[]=pages&id=1'])expect((await context.request.get(BASE+'/admin/slider?'+params)).status()).toBe(404);
 pass('CSRF, route identifiers and mismatched save destinations are rejected');
 await fillSlide(0,'فيلم الشركة — اختبار الإدارة',{image:'',video:initial.video});await fillSlide(1,'قطاع الطاقة — اختبار الإدارة');await page.locator('#slide-target-1').selectOption('sectors/energy');await save();
 let current=state(),saved=tr(current,'pages',home.id);expect(unrelated(saved)).toEqual(unrelated(tr(initial,'pages',home.id)));expect(tr(current,'pages',home.id,'en')).toEqual(tr(initial,'pages',home.id,'en'));expect(current.pages).toEqual(initial.pages);expect(JSON.parse(saved.body).slider[0].video_id).toBe(initial.video);expect(JSON.parse(saved.body).slider[0].image_id).toBeGreaterThan(0);
 await publicPage.goto(BASE+'/ar/');await expect(publicPage.locator('.showcase-slide--custom')).toHaveCount(2);await expect(publicPage.locator('video')).toHaveCount(1);await expect(publicPage.locator('[data-slide-autoplay]')).toHaveCount(1);await expect(publicPage.locator('.showcase-link[href="/ar/sectors/energy"]')).toHaveCount(1);
 pass('Standalone save reaches the public home slider, keeps the optimized video and leaves page content, SEO, publication and English untouched');
 await page.goto(edit('pages',about.id));await fillSlide(0,'<b>شريحة مسودة آمنة</b>',{status:'draft'});await save();await publicPage.goto(BASE+'/ar/about');await expect(publicPage.locator('.showcase-slide--custom')).toHaveCount(0);const preview=await context.newPage();await preview.goto(BASE+'/ar/about?preview=1');await expect(preview.locator('.showcase-slide--custom h2')).toHaveText('<b>شريحة مسودة آمنة</b>');await expect(preview.locator('.showcase-slide--custom h2 b')).toHaveCount(0);await preview.close();
 await fillSlide(0,'سلايدر من نحن — مخصص');await save();await publicPage.goto(BASE+'/ar/about');await expect(publicPage.locator('.showcase-slide--custom h2')).toHaveText('سلايدر من نحن — مخصص');
 expect(JSON.parse(tr(state(),'pages',home.id).body).slider).toEqual(JSON.parse(saved.body).slider);
 pass('About slider is independent; draft slides stay private and preview escapes HTML');
 await page.goto(edit('sectors',sector.id));await fillSlide(0,'المقاولات — تخصيص القطاع');await save();await publicPage.goto(BASE+'/ar/sectors/general-contracting');await expect(publicPage.locator('.showcase-slide--custom h2')).toHaveText('المقاولات — تخصيص القطاع');expect(tr(state(),'sectors',energy.id)).toEqual(tr(initial,'sectors',energy.id));
 await page.goto(edit('sectors',sector.id,'en'));await expect(page.locator('#slide-title-0')).toHaveAttribute('dir','ltr');await fillSlide(0,'Contracting — English slider');await save();await publicPage.goto(BASE+'/en/sectors/general-contracting');await expect(publicPage.locator('.showcase-slide--custom h2')).toHaveText('Contracting — English slider');expect(tr(state(),'sectors',sector.id,'en').approval).toBe('review');
 pass('Each sector and language saves independently without changing translation approval');
 await page.goto(edit('pages',home.id));const stale=await formData();execFileSync('php',['tests/admin-sliders-state.php','concurrent-copy',String(home.id)]);await fillSlide(1,'تحديث يحتفظ بنص الصفحة');await save();current=state();saved=tr(current,'pages',home.id);expect(JSON.parse(saved.body).test_preserved).toBe('Concurrent non-slider content');expect(saved.seo_description).toBe('Concurrent SEO metadata');
 let r=await context.request.post(edit('pages',home.id),{form:{...stale,'slider[1][title]':'Old overwritten value'},maxRedirects:0});expect(r.status()).toBe(422);expect(await r.text()).toContain('نافذة أخرى');expect(tr(state(),'pages',home.id)).toEqual(saved);
 pass('Transactional slider-only save preserves concurrent page edits; stale slider tabs cannot overwrite newer changes');
 await page.goto(edit('pages',home.id));
 for(const bad of [{'slider[0][title]':''},{'slider[0][target]':'javascript:alert(1)'},{'slider[0][image_id]':'999999'},{'slider[0][video_id]':'999999'},{'slider[0][order]':'-1'},{'slider[0][title][nested]':'bad'},{'slider[0][status]':'invalid'},{'slider[4][title]':'fifth'}]){r=await post(bad);expect(r.status()).toBe(422);expect(await r.text()).not.toContain('Fatal error');}
 expect(tr(state(),'pages',home.id)).toEqual(saved);pass('Malformed, incomplete, excess and unsafe slides fail server validation without partial writes');
 // Returning to automatic mode only resets this translation's slides.
 await page.goto(edit('pages',home.id));await page.getByRole('button',{name:'استعادة السلايدر الافتراضي',exact:true}).click();await expect(page.locator('[role=status]')).toContainText('تمت استعادة');await expect(page.locator('.slider-current-list li')).toHaveCount(4);await expect(page.locator('.slider-media-kind').first()).toContainText('فيديو');expect(JSON.parse(tr(state(),'pages',home.id).body).slider).toEqual([]);expect(unrelated(tr(state(),'pages',home.id))).toEqual(unrelated(saved));await publicPage.goto(BASE+'/ar/');await expect(publicPage.locator('.showcase-slide--custom')).toHaveCount(0);await expect(publicPage.locator('video')).toHaveCount(1);
 await page.goto(edit('sectors',sector.id));await page.locator('[name="slider[0][remove]"]').check();await save();expect(JSON.parse(tr(state(),'sectors',sector.id).body).slider).toEqual([]);expect(JSON.parse(tr(state(),'sectors',sector.id,'en').body).slider[0].title).toBe('Contracting — English slider');pass('Reset restores the original home film; removing a slide affects only its selected language');
 const projectId=Number(execFileSync('php',['tests/admin-sliders-state.php','draft-project'],{encoding:'utf8'}));await page.goto(BASE+'/admin/sliders');await expect(page.locator('#sliders-projects .slider-admin-row')).toHaveCount(1);await page.goto(edit('projects',projectId,'en'));await expect(page.locator('main')).toContainText('أضف محتوى هذه اللغة أولاً');await expect(page.locator('.slider-editor-form')).toHaveCount(0);await page.goto(edit('projects',projectId));await fillSlide(0,'صورة غير موثقة كمشروع');expect((await post()).status()).toBe(422);expect((await anon.request.get(BASE+'/ar/projects/qa-slider-project')).status()).toBe(404);pass('New project drafts appear automatically; missing translations and undocumented project images remain guarded');
 // Both existing edit entry points share the same validation and storage.
 await page.goto(`${BASE}/admin/edit?type=pages&id=${about.id}&locale=ar`);await expect(page.getByRole('link',{name:'فتح صفحة إدارة هذا السلايدر ↗'})).toHaveAttribute('href',`/admin/slider?type=pages&id=${about.id}&locale=ar`);await page.locator('.slider-fields details').first().locator('summary').click();await page.locator('#slide-title-0').fill('تعديل من محرر المحتوى');await page.getByRole('button',{name:'حفظ التغييرات'}).click();await expect(page.locator('[role=status]')).toContainText('تم حفظ المحتوى');await page.goto(edit('pages',about.id));await expect(page.locator('#slide-title-0')).toHaveValue('تعديل من محرر المحتوى');pass('The existing content editor remains connected to the same slider data and validation');
 for(const width of [375,768,1440])for(const theme of ['light','dark'])for(const [name,path] of [['index',BASE+'/admin/sliders'],['home-ar',edit('pages',home.id)],['sector-en',edit('sectors',sector.id,'en')]]){
  await page.setViewportSize({width,height:1050});await page.goto(path);if(await page.locator('html').getAttribute('data-theme')!==theme)await page.locator('[data-theme-toggle]').click();await page.evaluate(()=>document.fonts.ready);expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
  const a=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();violations.push(...a.violations.map(v=>({name,width,theme,id:v.id,nodes:v.nodes.map(n=>({target:n.target,summary:n.failureSummary}))})));
  await page.locator('img').evaluateAll(async imgs=>{await Promise.all(imgs.map(img=>{img.loading='eager';return img.decode().catch(()=>{});}));});
  await page.screenshot({path:`tests/screenshots/admin-sliders/${name}-${width}-${theme}.png`,fullPage:true});
 }
 pass('18 responsive layouts captured: slider centre and Arabic/English editors, light/dark, 375/768/1440');
 const nojs=await browser.newContext({javaScriptEnabled:false,reducedMotion:'reduce',storageState:await context.storageState(),viewport:{width:375,height:950}}),np=await nojs.newPage();await np.goto(BASE+'/admin/sliders');await np.locator('[data-slider-record="pages/home"] .slider-edit-link').first().click();await expect(np.locator('h1')).toHaveText('سلايدر الرئيسية');await np.locator('#slide-title-0').fill('سلايدر يعمل دون جافاسكربت');await np.locator('#slide-image-0').selectOption(String(energy.image_id));await np.locator('#slide-status-0').selectOption('published');await np.getByRole('button',{name:'حفظ السلايدر',exact:true}).focus();await np.keyboard.press('Enter');await expect(np.locator('[role=status]')).toContainText('تم حفظ السلايدر');await nojs.close();pass('Navigation, native slide controls and database save work without JavaScript');
 expect(state().messages).toBe(0);expect(errors).toEqual([]);expect(violations).toEqual([]);
}catch(e){console.error(e);results.push({name:'Failure',passed:false,error:String(e)});process.exitCode=1;}
finally{await browser.close();await fs.writeFile('tests/admin-sliders-results.json',JSON.stringify({results,errors,violations},null,2));}
