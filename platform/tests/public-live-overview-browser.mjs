import {chromium} from '../../.local/node_modules/playwright/index.mjs';
import {spawn} from 'node:child_process';
import fs from 'node:fs/promises';
import assert from 'node:assert/strict';
import http from 'node:http';
const base='http://127.0.0.1:8801';
const server=spawn(process.env.PHP_BINARY||'php',['-S','127.0.0.1:8801','-t','.','../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'],{cwd:'public',stdio:'pipe',env:{...process.env,APP_URL:base}});
let browser,output='';server.stderr.on('data',d=>output+=d);
try{
  let ready=false;
  for(let i=0;i<50;i++){
    const status=await new Promise(resolve=>{const request=http.get(base+'/live',response=>{response.resume();resolve(response.statusCode);});request.on('error',()=>resolve(0));request.setTimeout(1000,()=>{request.destroy();resolve(0);});});
    if(status===200){ready=true;break;}await new Promise(r=>setTimeout(r,200));
  }
  assert(ready,output);
  browser=await chromium.launch({headless:true,...(process.env.BROWSER_CHANNEL?{channel:process.env.BROWSER_CHANNEL}:{})});
  const directory='../.local/public-live-overview-browser';await fs.mkdir(directory,{recursive:true});
  for(const viewport of [{width:1672,height:941},{width:390,height:844}]){
    const page=await browser.newPage({viewport}),errors=[];
    await page.clock.install();
    page.on('pageerror',error=>errors.push(error.message));
    let online=true;
    // UI transport fixture only: this does not prove MediaMTX playback/codec compatibility.
    await page.route('**/live/current',route=>route.fulfill({json:{live:online}}));
    await page.route('**/live/*/heartbeat',route=>route.fulfill({json:{status:online?'live':'ended',online:online?1:0,chat:[]}}));
    await page.route('**/_live/**',route=>route.fulfill(route.request().url().includes('m3u8')?{contentType:'application/vnd.apple.mpegurl',body:'#EXTM3U\n#EXT-X-STREAM-INF:BANDWIDTH=800000\nstream.m3u8'}:{contentType:'text/html',body:'<html><body style="background:#102c48;color:white;margin:0"><video controls style="width:100%;height:100%"></video></body></html>'}));
    await page.goto(base+'/live');await page.evaluate(()=>document.fonts.ready);
    if(viewport.width<800)await page.locator('.public-menu-button').click();
    await page.locator('[data-live-player] iframe').waitFor({state:'visible'});
    assert.equal(await page.locator('.public-live-overview-caption').count(),1);
    assert.equal(await page.locator('[data-live-player] iframe').count(),1);
    assert(await page.locator('[data-current-live]').isVisible());
    assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Live overview overflow');
    const box=await page.locator('[data-live-player]').boundingBox();assert(box.width>250&&box.height>200);
    if(viewport.width<800)await page.locator('.public-menu-button').click();
    await page.screenshot({path:`${directory}/live-${viewport.width}.png`,fullPage:true});
    const initialStatus=page.waitForResponse(response=>response.url().endsWith('/live/current'));
    await page.goto(base+'/');await initialStatus;
    if(viewport.width<800)await page.locator('.public-menu-button').click();
    assert(await page.locator('[data-current-live]').isVisible());
    assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Homepage overflow');
    online=false;await page.clock.fastForward(31000);
    await page.locator('[data-current-live]').waitFor({state:'hidden'});
    online=true;await page.clock.fastForward(31000);await page.locator('[data-current-live]').waitFor({state:'visible'});
    await page.goto(base+'/live');await page.locator('[data-live-player] iframe').waitFor({state:'visible'});
    online=false;await page.clock.fastForward(16000);
    await page.locator('[data-live-player] iframe').waitFor({state:'hidden'});
    assert((await page.locator('[data-live-status-label]').allTextContents()).every(label=>label==='Beendet'));
    assert.deepEqual(errors,[]);await page.close();
  }
  console.log('Live overview / homepage indicator desktop-mobile UI passed (HLS transport mocked).');
}finally{await browser?.close();server.kill();}
