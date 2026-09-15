import {chromium,webkit,expect} from '@playwright/test';
import fs from 'node:fs/promises';
import AxeBuilder from '@axe-core/playwright';
const BASE='http://127.0.0.1:8088',results=[];
const slugs=['general-contracting','general-trading','information-technology','general-services','communications','energy','digital-solutions','industrial-investments'];
await fs.mkdir('tests/screenshots/navigation',{recursive:true});
for(const engine of ['chrome','webkit']){
 const browser=engine==='chrome'?await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true}):await webkit.launch();
 try{
  for(const lang of ['ar','en'])for(const width of [375,768,1440]){
   const context=await browser.newContext({viewport:{width,height:1000},reducedMotion:'reduce'}),page=await context.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));await page.goto(`${BASE}/${lang}/`);await page.evaluate(()=>document.fonts.ready);
   if(width===375)await page.locator('.menu-toggle').click();
   const disclosure=page.locator('.nav-sectors'),toggle=page.locator('.nav-sectors-toggle');await toggle.focus();await page.keyboard.press('Enter');await expect(disclosure).toHaveAttribute('open','');await expect(page.locator('.nav-sector-list a')).toHaveCount(8);
   expect(await page.locator('.nav-sector-list a').evaluateAll(as=>as.map(a=>a.pathname.split('/').at(-1)))).toEqual(slugs);
   for(const link of await page.locator('.nav-sector-list a').all())await expect(link).toBeVisible();
   expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
   const panel=await page.locator('.nav-sector-panel').boundingBox();expect(panel.x).toBeGreaterThanOrEqual(0);expect(panel.x+panel.width).toBeLessThanOrEqual(width);
   const axe=await new AxeBuilder({page}).include('#main-nav').withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();expect(axe.violations).toEqual([]);
   await page.screenshot({path:`tests/screenshots/navigation/${engine}-${lang}-${width}.png`});
   await page.keyboard.press(engine==='webkit'?'Alt+Tab':'Tab');await expect(page.locator('.nav-sector-overview')).toBeFocused();await page.keyboard.press('Escape');await expect(disclosure).not.toHaveAttribute('open');await expect(toggle).toBeFocused();
   if(width===375){await expect(page.locator('.menu-toggle')).toHaveAttribute('aria-expanded','true');await page.keyboard.press('Escape');await expect(page.locator('.menu-toggle')).toHaveAttribute('aria-expanded','false');await page.locator('.menu-toggle').click();}
   await toggle.click();await page.locator(`.nav-sector-list a[href="/${lang}/sectors/digital-solutions"]`).click();await expect(page).toHaveURL(`${BASE}/${lang}/sectors/digital-solutions`);await expect(page.locator('h1')).not.toBeEmpty();
   if(width===375)await page.locator('.menu-toggle').click();await toggle.click();await expect(page.locator('.nav-sector-list a[aria-current=page]')).toHaveAttribute('href',`/${lang}/sectors/digital-solutions`);
   if(width!==375){await page.mouse.click(10,800);await expect(disclosure).not.toHaveAttribute('open');await toggle.click();await page.locator('.language-switch a').focus();await expect(disclosure).not.toHaveAttribute('open');}
   expect(errors).toEqual([]);results.push({engine,lang,width,passed:true});await context.close();
  }
  const context=await browser.newContext({javaScriptEnabled:false,viewport:{width:375,height:1000}}),page=await context.newPage();await page.goto(BASE+'/ar/');await page.locator('.nav-sectors-toggle').click();await page.locator('.nav-sector-list a[href="/ar/sectors/energy"]').click();await expect(page.locator('h1')).toHaveText('مشاريع الطاقة');await context.close();results.push({engine,noJavaScript:true,passed:true});
 }catch(e){results.push({engine,passed:false,error:String(e)});console.error(e);process.exitCode=1;}
 finally{await browser.close();}
}
await fs.writeFile('tests/navigation-results.json',JSON.stringify(results,null,2));console.log(JSON.stringify(results,null,2));
