import {chromium,expect} from '@playwright/test';
import fs from 'node:fs/promises';
import {execFileSync} from 'node:child_process';
const BASE='http://127.0.0.1:8088';
const browser=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
let snapshot=false;const results=[];
try{
 const context=await browser.newContext({viewport:{width:1440,height:1000},reducedMotion:'reduce'});const page=await context.newPage();
 for(const width of [375,768,1440])for(const lang of ['ar','en']){
  await page.setViewportSize({width,height:1000});await page.goto(`${BASE}/${lang}/profile`,{waitUntil:'networkidle'});await page.evaluate(()=>document.fonts.ready);await page.locator('iframe').scrollIntoViewIfNeeded();await expect(page.frameLocator('iframe').locator('#pdf-canvas')).toHaveAttribute('data-rendered','1');await page.evaluate(()=>scrollTo(0,0));await page.screenshot({path:`tests/screenshots/${lang}-profile-${width}.png`,fullPage:true});
 }
 await page.setViewportSize({width:1440,height:1000});await page.goto(BASE+'/ar/');await page.locator('[data-slide-to="2"]').click();await expect(page.locator('[data-slide-to="2"]')).toHaveAttribute('aria-current','true');await page.locator('[data-slide][aria-hidden=false] img').evaluate(img=>img.decode());await page.screenshot({path:'docs/preview/slider-home-energy-desktop.png'});await page.locator('[data-slide-to="3"]').click();await expect(page.locator('[data-slide-to="3"]')).toHaveAttribute('aria-current','true');await page.locator('[data-slide][aria-hidden=false] img').evaluate(img=>img.decode());await page.screenshot({path:'docs/preview/slider-home-digital-desktop.png'});
 execFileSync('php',['tests/db-state.php','save']);snapshot=true;
 await page.goto(BASE+'/admin/login');const c=await fs.readFile('storage/initial-admin.txt','utf8');await page.locator('#email').fill(c.match(/Admin created: (.+)/)[1]);await page.locator('#password').fill(c.match(/Password: (.+)/)[1]);await page.locator('form button').click();await expect(page.locator('h1')).toContainText('مرحباً');
 const id=execFileSync('php',['-r',`require 'app/bootstrap.php';echo record('pages','home')['id'];`],{encoding:'utf8'});
 await page.goto(`${BASE}/admin/edit?type=pages&id=${id}`);await page.locator('#slide-title-0').evaluate(el=>el.closest('details').open=true);await page.locator('#slide-title-0').fill('مسودة سلايدر للمعاينة الخاصة');await page.locator('#slide-image-0').selectOption('2');await page.getByRole('button',{name:'حفظ التغييرات'}).click();
 await page.goto(BASE+'/ar/?preview=1');await expect(page.locator('.preview-bar')).toBeVisible();await expect(page.locator('.showcase-copy h2')).toHaveText('مسودة سلايدر للمعاينة الخاصة');
 const anon=await browser.newContext();const ap=await anon.newPage();await ap.goto(BASE+'/ar/?preview=1');await expect(ap.locator('.preview-bar')).toHaveCount(0);await expect(ap.locator('[data-slide]')).toHaveCount(4);await anon.close();results.push({name:'Authenticated draft-slide preview; visitor cannot enable preview using query string',passed:true});
 // Capture empty editor slots from original state, preserving all user content.
 execFileSync('php',['tests/db-state.php','restore']);snapshot=false;
 for(const width of [375,768,1440]){
  await page.setViewportSize({width,height:1000});await page.goto(`${BASE}/admin/edit?type=pages&id=${id}`);const panel=page.locator('.admin-card').filter({has:page.getByRole('heading',{name:'سلايدر الصفحة — العربية',exact:true})});await page.locator('#slide-title-0').evaluate(el=>el.closest('details').open=true);await panel.screenshot({path:`tests/screenshots/sliders/admin-slider-${width}.png`});expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
 }
 results.push({name:'Slide editor reviewed at 375, 768 and 1440 pixels; refreshed bilingual profile screenshots and PDF rendering',passed:true});
 await context.close();
}catch(error){results.push({name:'Failure',passed:false,error:String(error)});process.exitCode=1;console.error(error)}
finally{if(snapshot)execFileSync('php',['tests/db-state.php','restore']);await browser.close();await fs.writeFile('tests/slider-final-results.json',JSON.stringify(results,null,2));console.log(JSON.stringify(results))}
