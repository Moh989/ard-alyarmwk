import {chromium,expect} from '@playwright/test';
import fs from 'node:fs/promises';
import {createHash} from 'node:crypto';
const BASE='http://127.0.0.1:8088',manifest=JSON.parse(await fs.readFile('database/profile-seed.json','utf8')),web=JSON.parse(await fs.readFile('database/profile-web.json','utf8')).web,results=[];
const pass=(name,details={})=>{results.push({name,passed:true,...details});console.log('PASS '+name)};
const browser=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
await fs.mkdir('tests/screenshots/profile-current',{recursive:true});
try{
 const context=await browser.newContext({viewport:{width:1000,height:850}}),page=await context.newPage(),cdp=await context.newCDPSession(page);
 await cdp.send('Network.enable');const ids=new Set(),ranges=[];let bytes=0;
 cdp.on('Network.requestWillBeSent',event=>{if(new URL(event.request.url).pathname.startsWith('/media/'))ids.add(event.requestId)});
 cdp.on('Network.dataReceived',event=>{if(ids.has(event.requestId))bytes+=event.dataLength});
 page.on('response',response=>{if(response.status()===206)ranges.push(response.headers()['content-range'])});
 await page.goto(BASE+'/pdf-viewer?lang=ar');await expect(page.locator('#pdf-canvas')).toHaveAttribute('data-rendered','1',{timeout:60000});await page.waitForTimeout(1200);
 expect(ranges.length).toBeGreaterThan(0);expect(bytes).toBeGreaterThan(0);expect(bytes).toBeLessThan(manifest.pdf.bytes/3);
 pass('Current PDF first page loads through byte ranges without downloading the full original',{firstPageBytes:bytes,originalBytes:manifest.pdf.bytes,rangeRequests:ranges.length});
 const copy=new URL(await page.locator('body').getAttribute('data-document'),BASE).href;
 let compact=await context.request.head(copy);expect(+compact.headers()['content-length']).toBe(web.bytes);
 await page.goto(BASE+'/ar/profile');const source=new URL(await page.locator('[data-original-profile]').getAttribute('href'),BASE).href;
 let response=await context.request.head(source);expect(response.status()).toBe(200);expect(+response.headers()['content-length']).toBe(manifest.pdf.bytes);
 response=await context.request.get(source,{headers:{Range:'bytes=0-63'}});expect(response.status()).toBe(206);expect((await response.body()).subarray(0,5).toString()).toBe('%PDF-');
 expect((await context.request.get(source,{headers:{Range:'bytes=999999999-'}})).status()).toBe(416);
 const current=await fs.readFile('public'+manifest.pdf.path);expect(createHash('sha256').update(current).digest('hex')).toBe(manifest.pdf.sha256);
 pass('Current original remains byte-identical; metadata, 206 ranges and invalid-range rejection match its real size');
 await context.close();
 const visual=await browser.newContext({reducedMotion:'reduce'}),p=await visual.newPage();
 for(const lang of ['ar','en'])for(const width of [375,768,1440]){
  await p.setViewportSize({width,height:1000});await p.goto(`${BASE}/${lang}/profile`);await p.evaluate(()=>document.fonts.ready);
  await expect(p.locator('.file-meta').first()).toContainText((web.bytes/1000000).toFixed(1)+' MB');
  await expect(p.locator('[data-slide]').first().locator('img')).toHaveAttribute('src',manifest.covers['1280'].path);
  await p.locator('[data-slide]').first().locator('img').evaluate(img=>img.decode());
  expect(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
  const heights=[];for(let i=0;i<3;i++){await p.locator('[data-slide-to]').nth(i).click();heights.push((await p.locator('[data-slide-stage]').boundingBox()).height)}
  expect(Math.max(...heights)-Math.min(...heights)).toBeLessThanOrEqual(1);await p.locator('[data-slide-to="0"]').click();
  await p.locator('iframe').scrollIntoViewIfNeeded();await expect(p.frameLocator('iframe').locator('#pdf-canvas')).toHaveAttribute('data-rendered','1',{timeout:60000});await p.evaluate(()=>{document.activeElement?.blur();scrollTo(0,0)});await p.screenshot({path:`tests/screenshots/profile-current/${lang}-${width}.png`,fullPage:true});
  await p.goto(`${BASE}/${lang}/`);const cover=p.locator('.profile-book');await expect(cover).toHaveClass(/profile-book--landscape/);await expect(cover.locator('img')).toHaveAttribute('src',manifest.covers['1280'].path);await cover.scrollIntoViewIfNeeded();await p.locator('.profile-banner').screenshot({path:`tests/screenshots/profile-current/banner-${lang}-${width}.png`});
 }
 pass('Current landscape cover, accurate file size, stable profile slides and home banner fit both languages at 375/768/1440');await visual.close();
 const nojs=await browser.newContext({javaScriptEnabled:false});const np=await nojs.newPage();await np.goto(BASE+'/ar/profile');const href=await np.locator('.profile-page-hero a[download]').getAttribute('href');expect((await nojs.request.head(new URL(href,BASE).href)).status()).toBe(200);pass('Optimised PDF download remains available without JavaScript');await nojs.close();
}catch(error){console.error(error);results.push({name:'Failure',passed:false,error:String(error)});process.exitCode=1}
finally{await browser.close();await fs.writeFile('tests/profile-current-results.json',JSON.stringify(results,null,2))}
