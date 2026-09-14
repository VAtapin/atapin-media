import { chromium } from '../../.local/node_modules/playwright/index.mjs';
import { spawn } from 'node:child_process';
import fs from 'node:fs/promises';
import assert from 'node:assert/strict';
import http from 'node:http';

const server=spawn(process.env.PHP_BINARY||'php',['-S','127.0.0.1:8794','-t','.','../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'],{cwd:'public',stdio:'pipe'});
let output='',browser;server.stdout.on('data',data=>output+=data);server.stderr.on('data',data=>output+=data);
try {
  let ready=false;
  for(let i=0;i<60;i++){
    const status=await new Promise(resolve=>{
      const request=http.get('http://127.0.0.1:8794/',response=>{response.resume();resolve(response.statusCode);});
      request.on('error',()=>resolve(0));
      request.setTimeout(1000,()=>{request.destroy();resolve(0);});
    });
    if(status===200){ready=true;break;}
    await new Promise(resolve=>setTimeout(resolve,500));
  }
  assert(ready,output);
  browser=await chromium.launch({headless:true,...(process.env.BROWSER_CHANNEL?{channel:process.env.BROWSER_CHANNEL}:{})});
  const page=await browser.newPage({viewport:{width:1672,height:941}}),errors=[];
  page.on('pageerror',error=>errors.push(error.message));
  page.on('response',response=>{if(response.status()>=400)errors.push(`${response.status()} ${response.url()}`);});
  await page.goto('http://127.0.0.1:8794/');await page.evaluate(()=>document.fonts.ready);
  assert.equal(await page.locator('.public-section-cards>a').count(),6);
  assert.equal((await page.locator('.public-header').boundingBox()).height,66);
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
  await fs.mkdir('tests/artifacts',{recursive:true});
  await page.screenshot({path:'tests/artifacts/public-home-desktop.png',fullPage:true});
  await page.setViewportSize({width:390,height:844});
  await page.locator('.public-menu-button').click();
  assert.equal(await page.locator('.public-menu-button').getAttribute('aria-expanded'),'true');
  await page.locator('.public-menu-button').click();
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
  await page.screenshot({path:'tests/artifacts/public-home-mobile.png',fullPage:true});
  await page.goto('http://127.0.0.1:8794/videos');await page.locator('#catalog-q').fill('No match');await page.locator('.public-catalog-search button').click();
  assert(page.url().includes('q=No'));
  assert.deepEqual(errors,[]);
  console.log('Public home: desktop, mobile, navigation and search passed.');
} finally {await browser?.close();server.kill();}
