import {chromium,expect} from '@playwright/test';import fs from 'node:fs/promises';
const b=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});const result=[];
try{
 const c=await b.newContext({viewport:{width:1440,height:1000},reducedMotion:'reduce'});const p=await c.newPage();let release;const held=new Promise(resolve=>release=resolve);
 await p.route('**/assets/images/energy-*.webp',async route=>{await held;await route.continue()});
 await p.goto('http://127.0.0.1:8088/ar/',{waitUntil:'domcontentloaded'});await p.locator('[data-slide-to="2"]').click();await expect(p.locator('[data-showcase]')).toHaveAttribute('aria-busy','true');await expect(p.locator('[data-slide-to="0"]')).toHaveAttribute('aria-current','true');await expect(p.locator('[data-slide][aria-hidden=false] img')).toHaveAttribute('src',/company-film-poster/);release();await expect(p.locator('[data-slide-to="2"]')).toHaveAttribute('aria-current','true');expect(await p.locator('[data-slide][aria-hidden=false] img').evaluate(img=>img.complete&&img.naturalWidth>0)).toBe(true);await p.locator('[data-slide][aria-hidden=false] img').evaluate(img=>img.decode());await p.screenshot({path:'docs/preview/slider-home-energy-desktop.png'});
 await p.locator('[data-slide-to="3"]').click();await expect(p.locator('[data-slide-to="3"]')).toHaveAttribute('aria-current','true');await p.locator('[data-slide][aria-hidden=false] img').evaluate(img=>img.decode());await p.screenshot({path:'docs/preview/slider-home-digital-desktop.png'});
 result.push({name:'Delayed image response retains previous slide until decode, then changes image and controls together',passed:true});
 await p.locator('[data-slide-to="0"]').click();await expect(p.locator('[data-slide-to="0"]')).toHaveAttribute('aria-current','true');await p.screenshot({path:'docs/preview/home-ar-desktop.png'});
 await p.goto('http://127.0.0.1:8088/en/',{waitUntil:'networkidle'});await p.evaluate(()=>document.fonts.ready);await p.screenshot({path:'docs/preview/home-en-desktop.png'});await c.close();
}catch(e){result.push({name:'Failure',passed:false,error:String(e)});console.error(e);process.exitCode=1}
finally{await b.close();await fs.writeFile('tests/slider-loading-results.json',JSON.stringify(result,null,2));console.log(result)}
