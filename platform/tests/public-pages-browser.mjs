import { chromium } from '../../.local/node_modules/playwright/index.mjs';
import { spawn } from 'node:child_process';
import fs from 'node:fs/promises';
import assert from 'node:assert/strict';
import http from 'node:http';

const server=spawn(process.env.PHP_BINARY||'php',['-S','127.0.0.1:8795','-t','.','../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'],{cwd:'public',stdio:'pipe'});
let output='',browser;
server.stdout.on('data',data=>output+=data);server.stderr.on('data',data=>output+=data);
const detail=JSON.parse(process.env.PUBLIC_DETAIL_ROUTES||'{}');
const routes=process.env.PUBLIC_ROUTES?JSON.parse(process.env.PUBLIC_ROUTES):['/','/videos',detail.videos||'/videos/vorschau','/beitraege',detail.beitraege||'/beitraege/vorschau','/buecher',detail.buecher||'/buecher/vorschau','/live','/podcast','/community'];
try {
  let ready=false;
  for(let i=0;i<60;i++){
    const status=await new Promise(resolve=>{const request=http.get('http://127.0.0.1:8795/',response=>{response.resume();resolve(response.statusCode);});request.on('error',()=>resolve(0));request.setTimeout(1000,()=>{request.destroy();resolve(0);});});
    if(status===200){ready=true;break;}
    await new Promise(resolve=>setTimeout(resolve,500));
  }
  assert(ready,output);
  browser=await chromium.launch({headless:true,...(process.env.BROWSER_CHANNEL?{channel:process.env.BROWSER_CHANNEL}:{})});
  const page=await browser.newPage(),errors=[];
  page.on('pageerror',error=>errors.push(error.message));
  page.on('response',response=>{if(response.status()>=400)errors.push(`${response.status()} ${response.url()}`);});
  if(process.env.PUBLIC_TEST_LOGIN){
    await page.goto('http://127.0.0.1:8795/login');
    await page.locator('[name=email]').fill(process.env.PUBLIC_TEST_EMAIL);
    await page.locator('[name=password]').fill(process.env.PUBLIC_TEST_PASSWORD);
    await page.locator('form button').click();await page.waitForURL('**/desktop');
  }
  await fs.mkdir('tests/artifacts',{recursive:true});
  for(const width of [1672,390]){
    await page.setViewportSize({width,height:width===1672?941:844});
    for(const [index,route] of routes.entries()){
      await page.goto('http://127.0.0.1:8795'+route);await page.evaluate(()=>document.fonts.ready);
      if(await page.locator('[data-live-heartbeat]').count())await page.waitForFunction(()=>/^\d+$/.test(document.querySelector('[data-live-online]').textContent));
      assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,`${route}: overflow at ${width}`);
      assert(await page.locator('.public-header').isVisible(),route);
      assert.equal(await page.locator('main').count(),1,route);
      if(await page.locator('[data-public-help]').count()){
        await page.locator('[data-public-help]').click();
        assert(await page.locator('#broadcast-help').isVisible(),route);
        assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,`help overflow at ${width}`);
        await page.screenshot({path:`tests/artifacts/live-help-${width}.png`});
        await page.keyboard.press('Escape');assert.equal(await page.locator('#broadcast-help').isVisible(),false);
      }
      await page.screenshot({path:`tests/artifacts/public-page-${index+1}-${width}${process.env.PUBLIC_DETAIL_ROUTES?'-filled':''}.png`,fullPage:true});
      const tabs=page.locator('[role=tab]');
      if(await tabs.count()>1){await tabs.nth(1).click();assert.equal(await tabs.nth(1).getAttribute('aria-selected'),'true',route);}
    }
  }
  assert.deepEqual(errors,[]);
  console.log(`${routes.length} public screens: desktop/mobile, tabs, assets and JavaScript passed.`);
} finally {await browser?.close();server.kill();}
