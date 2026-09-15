import {chromium,expect} from '@playwright/test';
import fs from 'node:fs/promises';
import {execFileSync} from 'node:child_process';
const BASE='http://127.0.0.1:8088';
const results=[];const passed=name=>{results.push({name,passed:true});console.log('PASS '+name)};
const browser=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
let snapshot=false;
try{
 const context=await browser.newContext({viewport:{width:1440,height:1000},reducedMotion:'reduce'});
 const page=await context.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
 const routes=['','about','sectors','sectors/general-contracting','sectors/general-trading','sectors/information-technology','sectors/general-services','sectors/communications','sectors/energy','sectors/digital-solutions','sectors/industrial-investments','profile','contact','privacy','not-a-page'];
 let cases=0,totalSlides=0;
 for(const width of [375,768,1440])for(const lang of ['ar','en']){
  await page.setViewportSize({width,height:1000});
  for(const route of routes){
   await page.goto(`${BASE}/${lang}/${route}`);await page.evaluate(()=>document.fonts.ready);
   const slider=page.locator('[data-showcase]');await expect(slider).toHaveCount(1);
   const count=await slider.locator('[data-slide]').count();expect(count).toBe(route===''?4:3);totalSlides+=count;
   const heights=[];
   for(let i=0;i<count;i++){
    await slider.locator('[data-slide-to]').nth(i).click();
    const active=slider.locator('[data-slide][aria-hidden=false]');await expect(active).toHaveCount(1);await expect(active).toBeVisible();await expect(slider.locator('[data-slide-to]').nth(i)).toHaveAttribute('aria-current','true');
    await expect(slider.locator('[data-slide][inert]')).toHaveCount(count-1);
    await active.locator('img').evaluateAll(imgs=>Promise.all(imgs.map(img=>img.decode())));
    const metrics=await page.evaluate(()=>({width:innerWidth,scrollWidth:document.documentElement.scrollWidth}));expect(metrics.scrollWidth).toBeLessThanOrEqual(metrics.width);
    const boxes=await active.evaluate(el=>{const box=el.getBoundingClientRect();const copy=el.querySelector('.showcase-copy').getBoundingClientRect();return {bottom:copy.bottom<=box.bottom,top:copy.top>=box.top,height:box.height}});expect(boxes.top&&boxes.bottom).toBe(true);heights.push(boxes.height);
   }
   expect(Math.max(...heights)-Math.min(...heights)).toBeLessThanOrEqual(1);
   expect(await page.locator('h1').count()).toBe(1);cases++;
  }
 }
 passed(`${cases} page / language / viewport combinations, ${totalSlides} slides: no overflow, clipping or height changes`);
 for(const lang of ['ar','en']){
  await page.goto(`${BASE}/${lang}/`);const root=page.locator('[data-showcase]');const stage=root.locator('[data-slide-stage]');
  await root.locator('[data-slide-next]').click();await expect(root.locator('[data-slide-to="1"]')).toHaveAttribute('aria-current','true');
  await root.locator('[data-slide-prev]').click();await expect(root.locator('[data-slide-to="0"]')).toHaveAttribute('aria-current','true');
  await stage.focus();await stage.press(lang==='ar'?'ArrowLeft':'ArrowRight');await expect(root.locator('[data-slide-to="1"]')).toHaveAttribute('aria-current','true');
  await stage.press('End');await expect(root.locator('[data-slide-to="3"]')).toHaveAttribute('aria-current','true');await stage.press('Home');
  await root.locator('[data-slide-to="0"]').focus();await page.keyboard.press(lang==='ar'?'ArrowLeft':'ArrowRight');await expect(root.locator('[data-slide-to="1"]')).toBeFocused();
  expect(await root.locator('[data-slide][aria-hidden=false] .showcase-copy').evaluate(el=>getComputedStyle(el).animationName)).toBe('none');
 }
 passed('Manual arrows, numbered controls, RTL/LTR arrow keys, Home/End, focus and reduced motion');
 const mobile=await browser.newContext({viewport:{width:375,height:900},isMobile:true,hasTouch:true,reducedMotion:'reduce'});const mp=await mobile.newPage();const client=await mobile.newCDPSession(mp);
 for(const lang of ['ar','en']){
  await mp.goto(`${BASE}/${lang}/`);const stage=mp.locator('[data-slide-stage]');await stage.scrollIntoViewIfNeeded();const box=await stage.boundingBox();const x=box.x+box.width/2,y=box.y+120,dx=lang==='ar'?100:-100;
  await client.send('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:[{x,y}]});
  for(let i=1;i<=5;i++)await client.send('Input.dispatchTouchEvent',{type:'touchMove',touchPoints:[{x:x+dx*i/5,y}]});
  await client.send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});await expect(mp.locator('[data-slide-to="1"]')).toHaveAttribute('aria-current','true');
  const position=await mp.evaluate(()=>scrollY);
  await client.send('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:[{x,y:y+100}]});
  for(let i=1;i<=6;i++)await client.send('Input.dispatchTouchEvent',{type:'touchMove',touchPoints:[{x,y:y+100-i*20}]});
  await client.send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});
  await expect(mp.locator('[data-slide-to="1"]')).toHaveAttribute('aria-current','true');expect(await mp.evaluate(()=>scrollY)).toBeGreaterThan(position);
 }
 await mobile.close();passed('Real browser touch input: horizontal swipe in both directions and normal vertical scrolling');
 const nojs=await browser.newContext({javaScriptEnabled:false,viewport:{width:375,height:900}});const np=await nojs.newPage();await np.goto(BASE+'/ar/');await expect(np.locator('[data-slide]')).toHaveCount(4);await expect(np.locator('[data-slide-controls]')).toBeHidden();expect(await np.locator('[data-slide-stage]').evaluate(el=>el.scrollWidth>el.clientWidth)).toBe(true);await np.locator('[data-slide]').nth(3).locator('a').click();await expect(np.locator('h1')).toHaveText('الحلول البرمجية الرقمية والذكية');await nojs.close();passed('All slides server-rendered and direct links usable without JavaScript');
 const motion=await browser.newContext();const normal=await motion.newPage();await normal.goto(BASE+'/ar/');await normal.waitForTimeout(1600);expect(await normal.locator('[data-slide][aria-hidden=false]').count()).toBe(1);await expect(normal.locator('[data-slide-to="0"]')).toHaveAttribute('aria-current','true');await motion.close();passed('Opening film remains selected before it ends');
 expect(errors).toEqual([]);passed('No browser JavaScript errors');
 // The following checks restore every existing content row and message after exercising the CMS.
 execFileSync('php',['tests/db-state.php','save']);snapshot=true;
 await page.goto(BASE+'/admin/login');const credentials=await fs.readFile('storage/initial-admin.txt','utf8');await page.locator('#email').fill(credentials.match(/Admin created: (.+)/)[1]);await page.locator('#password').fill(credentials.match(/Password: (.+)/)[1]);await page.locator('form button').click();await expect(page.locator('h1')).toContainText('مرحباً');
 await page.goto(BASE+'/admin/media');await page.locator('#upload-file').setInputFiles('public/assets/images/energy-640.webp');await page.locator('#edit-alt_ar').fill('صورة اختبار للسلايدر');await page.locator('#edit-alt_en').fill('Slider test image');await page.locator('#edit-source').fill('Temporary local QA, removed after verification.');await page.getByRole('button',{name:'رفع الملف'}).click();await expect(page.locator('[role=status]')).toContainText('تم رفع الملف');const mid=new URL(page.url()).searchParams.get('id');const anon=await browser.newContext();expect((await anon.request.get(BASE+'/media/'+mid)).status()).toBe(404);
 const homeId=execFileSync('php',['-r',`require 'app/bootstrap.php'; echo record('pages','home')['id'];`],{encoding:'utf8'});
 const openEditor=async(locale='ar')=>{await page.goto(`${BASE}/admin/edit?type=pages&id=${homeId}&locale=${locale}`);await page.locator('#slide-title-0').evaluate(el=>el.closest('details').open=true)};
 const save=async()=>{await page.getByRole('button',{name:'حفظ التغييرات'}).click();await expect(page.locator('[role=status]')).toContainText('تم حفظ المحتوى')};
 await openEditor();await page.locator('#slide-title-0').fill('شريحة اختبار <script>alert(1)</script>');await page.locator('#slide-text-0').fill('نص مؤقت للتحقق من الإدارة فقط.');await page.locator('#slide-image-0').selectOption(mid);await page.locator('#slide-target-0').selectOption('contact#contact-form');await save();
 let ap=await anon.newPage();await ap.goto(BASE+'/ar/');expect(await ap.locator('[data-slide]').count()).toBe(4);expect((await anon.request.get(BASE+'/media/'+mid)).status()).toBe(404);passed('Slide drafts stay unpublished and uploaded slide-only media stays private');
 await openEditor();await page.locator('#slide-status-0').selectOption('published');await save();await ap.reload();await expect(ap.locator('[data-slide]')).toHaveCount(1);await expect(ap.locator('.showcase-copy h2')).toHaveText('شريحة اختبار <script>alert(1)</script>');expect(await ap.locator('[data-slide] script').count()).toBe(0);await expect(ap.locator('.showcase-description')).toBeVisible();expect((await anon.request.get(BASE+'/media/'+mid)).status()).toBe(200);await expect(ap.locator('.showcase-link')).toHaveAttribute('href','/ar/contact#contact-form');expect(await ap.locator('[data-slide-controls]').count()).toBe(0);passed('Published custom text, image and CTA persist; output escaped; one slide has no inactive controls');
 await ap.goto(BASE+'/en/');await expect(ap.locator('[data-slide]')).toHaveCount(4);passed('Arabic customisation leaves the English translation independent');
 await openEditor();await page.locator('#slide-title-1').evaluate(el=>el.closest('details').open=true);await page.locator('#slide-title-1').fill('الشريحة الثانية أولاً');await page.locator('#slide-image-1').selectOption('1');await page.locator('#slide-order-1').fill('0');await page.locator('#slide-status-1').selectOption('published');await save();await ap.goto(BASE+'/ar/');await expect(ap.locator('[data-slide]').first().locator('h2')).toHaveText('الشريحة الثانية أولاً');await expect(ap.locator('[data-slide]')).toHaveCount(2);passed('Custom slide ordering reaches public carousel');
 await openEditor();
 for(const i of [2,3]){await page.locator(`#slide-title-${i}`).evaluate(el=>el.closest('details').open=true);await page.locator(`#slide-title-${i}`).fill(('عنوان شريحة اختبار طويل للمراجعة البصرية ').repeat(3).slice(0,110));await page.locator(`#slide-text-${i}`).fill(('وصف الشريحة المخصص من لوحة التحكم. ').repeat(8).slice(0,240));await page.locator(`#slide-image-${i}`).selectOption(mid);await page.locator(`#slide-status-${i}`).selectOption('published')}
 await save();
 for(const width of [375,768,1440]){await ap.setViewportSize({width,height:1000});await ap.goto(BASE+'/ar/');await expect(ap.locator('[data-slide]')).toHaveCount(4);const heights=[];for(let i=0;i<4;i++){await ap.locator('[data-slide-to]').nth(i).click();heights.push((await ap.locator('[data-slide-stage]').boundingBox()).height);const fits=await ap.locator('[data-slide][aria-hidden=false]').evaluate(el=>{const a=el.getBoundingClientRect(),b=el.querySelector('.showcase-copy').getBoundingClientRect();return b.top>=a.top&&b.bottom<=a.bottom&&document.documentElement.scrollWidth<=innerWidth});expect(fits).toBe(true)}expect(Math.max(...heights)-Math.min(...heights)).toBeLessThanOrEqual(1)}
 passed('Four custom slides with maximum text lengths fit all three widths without content jumps');
 await openEditor();const form=await page.locator('.admin-editor').evaluate(el=>Object.fromEntries(new FormData(el).entries()));form['slider[0][target]']='javascript:alert(1)';let response=await context.request.post(page.url(),{form});expect(await response.text()).toContain('رابط الشريحة يجب أن يكون');
 form['slider[0][target]']='contact#contact-form';form['slider[0][image_id]']='10';response=await context.request.post(page.url(),{form});expect(await response.text()).toContain('اختر صورة موجودة');passed('Server rejects executable URLs and non-image media for custom slides');
 await openEditor('en');await page.locator('#slide-title-0').fill('English slider preview');await page.locator('#slide-image-0').selectOption(mid);await page.locator('#slide-status-0').selectOption('published');await save();await ap.goto(BASE+'/en/');await expect(ap.locator('.showcase-copy h2')).toHaveText('English slider preview');
 const approval=execFileSync('php',['-r',`require 'app/bootstrap.php';$GLOBALS['config']['require_approved']=true;echo record('pages','home','en')===null?'hidden':'visible';`],{encoding:'utf8'});expect(approval).toBe('hidden');passed('English slides persist separately and production content-approval filter remains enforced');
 await openEditor();for(const i of [0,1,2,3])await page.locator(`#slide-status-${i}`).evaluate(el=>{el.closest('details').open=true;el.value='draft'});await save();await ap.goto(BASE+'/ar/');await expect(ap.locator('[data-slide]')).toHaveCount(4);passed('Returning custom slides to draft restores source-backed automatic slides');
 await anon.close();await context.close();
}catch(e){results.push({name:'Failure',passed:false,error:String(e)});console.error(e);process.exitCode=1}
finally{if(snapshot)execFileSync('php',['tests/db-state.php','restore']);await browser.close();await fs.writeFile('tests/slider-results.json',JSON.stringify(results,null,2))}
