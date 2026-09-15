import {chromium} from '@playwright/test';
import fs from 'node:fs/promises';
const browser=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
await fs.mkdir('tests/screenshots/sliders',{recursive:true});
for(const width of [1440,375,768]){
 const page=await browser.newPage({viewport:{width,height:1000},reducedMotion:'reduce'});
 for(const [lang,route] of [['ar',''],['en',''],['ar','about'],['ar','sectors/energy'],['ar','profile'],['ar','contact'],['ar','privacy']]){
  await page.goto('http://127.0.0.1:8088/'+lang+'/'+route,{waitUntil:'networkidle'});await page.evaluate(()=>document.fonts.ready);
  const key=lang+'-'+(route.replaceAll('/','-')||'home')+'-'+width;
  await page.screenshot({path:'tests/screenshots/sliders/'+key+'.png',fullPage:width<1440});
  await page.locator('[data-showcase]').screenshot({path:'tests/screenshots/sliders/'+key+'-component.png'});
 }
 await page.close();
}
await browser.close();
