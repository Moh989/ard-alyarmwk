import {chromium,expect} from '@playwright/test';import fs from 'node:fs/promises';
const b=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
try{
 const c=await b.newContext({javaScriptEnabled:false});const p=await c.newPage();await p.goto('http://127.0.0.1:8088/ar/');
 const [popup]=await Promise.all([p.waitForEvent('popup'),p.locator('.showcase-film-fallback').click()]);await popup.waitForLoadState('domcontentloaded');const video=popup.locator('video');await expect(video).toHaveJSProperty('videoWidth',1280);const box=await video.boundingBox();await video.click({position:{x:22,y:box.height-49}});await expect(video).toHaveJSProperty('paused',false);await expect.poll(()=>video.evaluate(v=>v.currentTime)).toBeGreaterThan(0);
 const result=await video.evaluate(v=>({readyState:v.readyState,width:v.videoWidth,height:v.videoHeight,controls:v.controls,paused:v.paused,currentTime:v.currentTime}));await fs.writeFile('tests/video-native-results.json',JSON.stringify(result,null,2));console.log('Native video viewer without JavaScript',result)
}finally{await b.close()}
