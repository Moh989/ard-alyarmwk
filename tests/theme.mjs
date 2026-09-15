import {chromium,webkit,expect} from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import fs from 'node:fs/promises';
const BASE='http://127.0.0.1:8088',results=[],violations=[],errors=[];
const pass=(name,details={})=>{results.push({name,passed:true,...details});console.log('PASS '+name)};
const background=page=>page.evaluate(()=>getComputedStyle(document.body).backgroundColor);
const audit=async(page,path)=>{const r=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();violations.push(...r.violations.map(v=>({path,id:v.id,nodes:v.nodes.map(n=>({target:n.target,summary:n.failureSummary}))})));};
await fs.mkdir('tests/screenshots/theme',{recursive:true});
for(const engine of ['chrome','webkit']){
 const browser=engine==='chrome'?await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'}):await webkit.launch();
 try{
  for(const lang of ['ar','en'])for(const width of [375,768,1440]){
   const c=await browser.newContext({viewport:{width,height:1000},colorScheme:'light',reducedMotion:'reduce'}),p=await c.newPage();p.on('pageerror',e=>errors.push(e.message));await p.goto(`${BASE}/${lang}/`);await p.evaluate(()=>document.fonts.ready);
   const toggle=p.locator('[data-theme-toggle]');await expect(toggle).toBeVisible();await expect(p.locator('html')).toHaveAttribute('data-theme','light');
   for(const theme of ['light','dark']){
    if(theme==='dark'){await toggle.focus();await p.keyboard.press('Enter');}
    await expect(p.locator('html')).toHaveAttribute('data-theme',theme);expect(await background(p)).toBe(theme==='dark'?'rgb(18, 24, 39)':'rgb(246, 246, 242)');
    expect(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
    const box=await toggle.boundingBox();expect(box.width).toBeGreaterThanOrEqual(40);expect(box.height).toBeGreaterThanOrEqual(44);expect(box.x).toBeGreaterThanOrEqual(0);expect(box.x+box.width).toBeLessThanOrEqual(width);
    await expect(toggle).toHaveAttribute('aria-label',lang==='ar'?(theme==='dark'?'تفعيل النمط النهاري':'تفعيل النمط الليلي'):(theme==='dark'?'Switch to light mode':'Switch to dark mode'));
    await expect(p.locator('.brand-logo').first()).toHaveCSS('filter','none');
    await p.screenshot({path:`tests/screenshots/theme/${engine}-${lang}-${width}-${theme}.png`});
    if(width===375)await p.locator('.menu-toggle').click();await p.locator('.nav-sectors-toggle').click();await expect(p.locator('.nav-sector-list a')).toHaveCount(8);
    if(engine==='chrome')await audit(p,`nav/${lang}/${width}/${theme}`);
    await p.screenshot({path:`tests/screenshots/theme/nav-${engine}-${lang}-${width}-${theme}.png`});await p.keyboard.press('Escape');if(width===375)await p.keyboard.press('Escape');
   }
   await c.close();
  }
  pass(engine+': both themes, RTL/LTR, keyboard toggle and sector dropdown fit 375/768/1440');
  const c=await browser.newContext({colorScheme:'light',reducedMotion:'reduce'}),p=await c.newPage();p.on('pageerror',e=>errors.push(e.message));await p.goto(BASE+'/ar/');
  await p.emulateMedia({colorScheme:'dark'});await expect(p.locator('html')).toHaveAttribute('data-theme','dark');
  await p.locator('[data-theme-toggle]').click();await expect(p.locator('html')).toHaveAttribute('data-theme','light');expect(await p.evaluate(()=>localStorage.getItem('ard-theme'))).toBe('light');
  await p.emulateMedia({colorScheme:'light'});await p.emulateMedia({colorScheme:'dark'});await expect(p.locator('html')).toHaveAttribute('data-theme','light');await p.reload();await expect(p.locator('html')).toHaveAttribute('data-theme','light');
  await p.locator('.language-switch a').click();await expect(p.locator('html')).toHaveAttribute('lang','en');await expect(p.locator('html')).toHaveAttribute('data-theme','light');
  const other=await c.newPage();await other.goto(BASE+'/en/about');await p.locator('[data-theme-toggle]').click();await expect(other.locator('html')).toHaveAttribute('data-theme','dark');
  await p.goto(BASE+'/en/profile');await p.locator('iframe').scrollIntoViewIfNeeded();const frame=p.frameLocator('iframe');await expect(frame.locator('html')).toHaveAttribute('data-theme','dark');await expect(frame.locator('#pdf-canvas')).toHaveAttribute('data-rendered','1',{timeout:60000});await expect(frame.locator('#pdf-canvas')).toHaveCSS('filter','none');await expect(frame.locator('#pdf-canvas')).toHaveCSS('background-color','rgb(255, 255, 255)');
  await p.locator('[data-theme-toggle]').click();await expect(frame.locator('html')).toHaveAttribute('data-theme','light');await expect(other.locator('html')).toHaveAttribute('data-theme','light');
  await p.goto(BASE+'/ar/contact');await p.locator('#field-name').fill('اختبار المظهر Test');await p.locator('#field-message').fill('تغيير النمط يحافظ على النص المكتوب في النموذج.');let posts=0;p.on('request',r=>{if(r.method()==='POST')posts++;});await p.locator('[data-theme-toggle]').click();await expect(p.locator('#field-name')).toHaveValue('اختبار المظهر Test');expect(posts).toBe(0);
  pass(engine+': system preference, manual override, reload, language switch, tabs, embedded PDF and unsent form values work');
  await c.close();
  const blocked=await browser.newContext({colorScheme:'dark'});await blocked.addInitScript(()=>Object.defineProperty(window,'localStorage',{get(){throw new DOMException('Blocked','SecurityError')}}));const bp=await blocked.newPage();bp.on('pageerror',e=>errors.push(e.message));await bp.goto(BASE+'/ar/');await bp.locator('[data-theme-toggle]').click();await expect(bp.locator('html')).toHaveAttribute('data-theme','light');await blocked.close();
  const nojs=await browser.newContext({javaScriptEnabled:false,colorScheme:'dark',viewport:{width:375,height:1000}}),np=await nojs.newPage();await np.goto(BASE+'/ar/');expect(await background(np)).toBe('rgb(18, 24, 39)');await expect(np.locator('[data-theme-toggle]')).toBeHidden();await np.locator('.nav-sectors-toggle').click();await np.locator('.nav-sector-list a[href="/ar/sectors/energy"]').click();await expect(np.locator('h1')).toHaveText('مشاريع الطاقة');await nojs.close();
  pass(engine+': storage denial and JavaScript-disabled system theme remain usable');
  if(engine==='chrome'){
   const c=await browser.newContext({colorScheme:'dark',reducedMotion:'reduce'}),p=await c.newPage();
   for(const theme of ['light','dark'])for(const lang of ['ar','en'])for(const route of ['about','sectors','sectors/digital-solutions','profile','contact','privacy','missing']){
    await p.goto(`${BASE}/${lang}/${route}`);if(await p.locator('html').getAttribute('data-theme')!==theme)await p.locator('[data-theme-toggle]').click();await p.evaluate(()=>document.fonts.ready);await audit(p,`${lang}/${route}/${theme}`);
   }
   await p.goto(BASE+'/ar/');await p.locator('.profile-book').scrollIntoViewIfNeeded();await p.locator('.profile-book img').evaluate(img=>img.decode());await p.locator('.profile-banner').screenshot({path:'tests/screenshots/theme/profile-banner-dark.png'});
   await p.goto(BASE+'/admin/login');const auth=await fs.readFile('storage/initial-admin.txt','utf8');await p.locator('#email').fill(auth.match(/Admin created: (.+)/)[1]);await p.locator('#password').fill(auth.match(/Password: (.+)/)[1]);await p.locator('form button').click();await expect(p.locator('h1')).toContainText('مرحباً');
   for(const route of ['/admin','/admin/settings','/admin/messages','/admin/media','/admin/edit?type=pages&id=1']){await p.goto(BASE+route);await expect(p.locator('html')).toHaveAttribute('data-theme','dark');await audit(p,route+'/dark');}
   await p.setViewportSize({width:375,height:1000});await p.goto(BASE+'/admin');expect(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);await p.screenshot({path:'tests/screenshots/theme/admin-dark-mobile.png',fullPage:true});
   await p.goto(BASE+'/ar/');await p.emulateMedia({media:'print'});await expect(p.locator('[data-theme-toggle]')).toBeHidden();expect(await background(p)).toBe('rgb(255, 255, 255)');await c.close();
   pass('Chrome: public pages in both themes/languages, authenticated administration and light print layout checked');
  }
 }catch(e){console.error(e);results.push({name:engine+' failure',passed:false,error:String(e)});process.exitCode=1;}
 finally{await browser.close();}
}
if(errors.length||violations.length)process.exitCode=1;
await fs.writeFile('tests/theme-results.json',JSON.stringify({results,violations,errors},null,2));console.log(JSON.stringify({groups:results.length,violations,errors},null,2));
