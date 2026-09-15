import {webkit,expect} from '@playwright/test';
import fs from 'node:fs/promises';
const BASE='http://127.0.0.1:8088',results=[],errors=[];
const browser=await webkit.launch({headless:true});
const pass=name=>{results.push({name,passed:true});console.log('PASS '+name)};
await fs.mkdir('tests/screenshots/webkit',{recursive:true});
try{
 for(const lang of ['ar','en'])for(const width of [375,768,1440]){
  const context=await browser.newContext({viewport:{width,height:1000},reducedMotion:'reduce'}),page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
  for(const route of ['', 'sectors/digital-solutions','profile','contact']){
   await page.goto(`${BASE}/${lang}/${route}`);await page.evaluate(()=>document.fonts.ready);expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
   await expect(page.locator('html')).toHaveAttribute('dir',lang==='ar'?'rtl':'ltr');
   if(route==='profile'){await page.locator('iframe').scrollIntoViewIfNeeded();await expect(page.frameLocator('iframe').locator('#pdf-canvas')).toHaveAttribute('data-rendered','1',{timeout:60000});await page.evaluate(()=>scrollTo(0,0));}
   if(route==='contact'){await expect(page.locator('#field-sector')).not.toHaveAttribute('required');await page.locator('#field-name').fill('شركة Test');await page.locator('#field-email').fill('qa@example.test');await expect(page.locator('#field-email')).toHaveAttribute('dir','ltr');}
   await page.screenshot({path:`tests/screenshots/webkit/${lang}-${width}-${route.replaceAll('/','-')||'home'}.png`,fullPage:true});
  }
  if(width===375){await page.goto(`${BASE}/${lang}/`);await page.locator('.menu-toggle').click();await expect(page.locator('#main-nav')).toBeVisible();await page.keyboard.press('Escape');await expect(page.locator('#main-nav')).toBeHidden();}
  await context.close();
 }
 pass('WebKit RTL/LTR home, software sector, profile and contact fit 375/768/1440 with working PDF, mixed-language fields and mobile menus');
 for(const lang of ['ar','en']){
  const context=await browser.newContext({viewport:{width:lang==='ar'?1440:375,height:1000}}),page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));await page.goto(`${BASE}/${lang}/`);await page.locator('[data-slide-stage]').scrollIntoViewIfNeeded();
  const film=page.locator('video');await expect(film).toHaveJSProperty('paused',false,{timeout:20000});await expect.poll(()=>film.evaluate(v=>Number.isFinite(v.duration)&&v.duration>0)).toBe(true);await film.evaluate(v=>v.currentTime=v.duration-.4);
  await expect(page.locator('[data-slide-to="1"]')).toHaveAttribute('aria-current','true',{timeout:10000});await expect(film).toHaveJSProperty('ended',true);await expect(page.locator('[data-slide-to="2"]')).toHaveAttribute('aria-current','true',{timeout:9000});
  await page.locator('[data-slide-autoplay]').click();await page.waitForTimeout(6500);await expect(page.locator('[data-slide-to="2"]')).toHaveAttribute('aria-current','true');await context.close();
 }
 pass('WebKit desktop Arabic and mobile English advance after real video ended, then every six seconds; Pause holds the image');
 const page=await browser.newPage();await page.goto(BASE+'/pdf-viewer?lang=ar');for(let i=1;i<=10;i++){await expect(page.locator('#pdf-canvas')).toHaveAttribute('data-rendered',String(i),{timeout:30000});if(i<10)await page.locator('#pdf-next').click();}await expect(page.locator('#pdf-next')).toBeDisabled();pass('WebKit renders all ten pages of the optimised PDF');
 expect(errors).toEqual([]);pass('No WebKit JavaScript errors');
}catch(e){results.push({name:'Failure',passed:false,error:String(e)});console.error(e);process.exitCode=1;}
finally{await browser.close();await fs.writeFile('tests/webkit-results.json',JSON.stringify({engine:'Playwright WebKit 26.6; not a physical Safari/iPhone test',results,errors},null,2));}
