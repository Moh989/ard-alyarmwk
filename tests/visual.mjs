import {chromium,expect} from '@playwright/test';
import fs from 'node:fs/promises';
const browser=await chromium.launch({executablePath:process.env.CHROME_PATH||'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
const summary=[];const consoleErrors=[];
const routes=['','about','sectors','sectors/general-contracting','sectors/general-trading','sectors/information-technology','sectors/general-services','sectors/communications','sectors/energy','sectors/digital-solutions','sectors/industrial-investments','profile','contact','privacy','not-a-page'];
for(const width of [375,768,1440]){
 const context=await browser.newContext({viewport:{width,height:width===1440?1000:900},reducedMotion:'reduce'});
 const page=await context.newPage();page.on('pageerror',e=>consoleErrors.push(e.message));
 for(const lang of ['ar','en']){
  for(const route of routes){
   const path=`/${lang}/${route}`;const response=await page.goto('http://127.0.0.1:8088'+path,{waitUntil:'networkidle'});await page.evaluate(()=>document.fonts.ready);
   // Trigger lazy images before screenshots; retain ordinary document scrolling.
   await page.evaluate(async()=>{for(const img of document.images){if(img.getBoundingClientRect().width>0){img.loading='eager';await img.decode().catch(()=>{})}}});
   if(route==='profile'){await page.locator('iframe').scrollIntoViewIfNeeded();await expect(page.frameLocator('iframe').locator('#pdf-canvas')).toHaveAttribute('data-rendered','1',{timeout:60000});await page.evaluate(()=>scrollTo(0,0));}
   const metrics=await page.evaluate(()=>({width:innerWidth,scrollWidth:document.documentElement.scrollWidth,lang:document.documentElement.lang,dir:document.documentElement.dir,missing:[...document.images].filter(x=>x.getBoundingClientRect().width>0&&!x.naturalWidth).map(x=>x.src),h1:document.querySelectorAll('h1').length}));
   summary.push({width,path,status:response.status(),...metrics});
   const fname=`tests/screenshots/${lang}-${route.replaceAll('/','-')||'home'}-${width}.png`;
   await page.screenshot({path:fname,fullPage:true});
   if(route===''&&width===1440)await page.screenshot({path:`tests/screenshots/${lang}-home-viewport.png`});
  }
 }
 await context.close();console.log(`Completed ${width}px, both languages`);
}
await fs.writeFile('tests/visual-results.json',JSON.stringify({summary,consoleErrors},null,2));console.log(JSON.stringify({pages:summary.length,issues:summary.filter(x=>x.scrollWidth>x.width||x.missing.length||x.h1!==1||x.status!==(x.path.endsWith('not-a-page')?404:200)),consoleErrors},null,2));await browser.close();
