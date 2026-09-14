import { chromium } from '../../.local/node_modules/playwright/index.mjs';
import { spawn } from 'node:child_process';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
assert.equal(process.env.APP_ENV,'testing');assert.match(process.env.DB_DATABASE||'',/desktop-workspaces-live/);
assert(process.env.MEDIAMTX_VALIDATE_BIN,'Use the installed pinned MediaMTX binary for a real transport check.');
const php=process.env.PHP_BINARY||'php',base='http://127.0.0.1:8808',processes=[];let browser,logs='',mtxLogs='';
const start=(binary,args,options={})=>{const child=spawn(binary,args,{env:process.env,stdio:'pipe',...options});processes.push(child);child.stdout.on('data',r=>logs+=r);child.stderr.on('data',r=>logs+=r);return child;};
const web=port=>start(php,['-S','127.0.0.1:'+port,'-t','.','../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'],{cwd:'public',env:{...process.env,APP_URL:'http://127.0.0.1:'+port}});
try{
  web(8808);web(8809);const mtx=start(process.env.MEDIAMTX_VALIDATE_BIN,['tests/artifacts/browser-live-test.yml']);mtx.stdout.on('data',r=>mtxLogs+=r);mtx.stderr.on('data',r=>mtxLogs+=r);
  for(const port of [8808,8809]){let ready=false;for(let i=0;i<60;i++){try{if((await fetch('http://127.0.0.1:'+port+'/login')).ok){ready=true;break;}}catch{}await new Promise(resolve=>setTimeout(resolve,200));}assert(ready,logs);}
  browser=await chromium.launch({headless:true,...(process.env.BROWSER_CHANNEL?{channel:process.env.BROWSER_CHANNEL}:{}),args:['--use-fake-device-for-media-stream','--use-fake-ui-for-media-stream']});
  const page=await browser.newPage({viewport:{width:1672,height:941},acceptDownloads:true});page.setDefaultTimeout(20000);const errors=[],failures=[];page.on('pageerror',error=>errors.push(error.message));page.on('response',r=>{if(r.status()>=400)failures.push(r.url()+':'+r.status());});
  await page.goto(base+'/login');await page.locator('[name=email]').fill('studio@example.test');await page.locator('[name=password]').fill('test-only-password');await page.locator('form button').click();await page.waitForURL('**/desktop');
  await page.evaluate(()=>document.querySelector('.os-start-menu [data-open-app=live-studio]').click());
  const live=page.locator('.os-window[data-app-id=live-studio]'),studio=live.locator('[data-browser-studio]');await live.locator('[data-mode=browser]').click();await studio.locator('[data-studio-action=prepare]').waitFor();
  await studio.locator('[data-studio-action=prepare]').click();await studio.locator('[role=status]').filter({hasText:'Lokale Vorschau bereit'}).waitFor();
  await studio.locator('input[data-studio-field=image]').setInputFiles('public/assets/brand/owner/logo-mark.png');await studio.locator('select[data-studio-field=active_image] option').waitFor({state:'attached'});await studio.locator('[data-studio-field=pip]').check();await studio.locator('[data-studio-field=overlay]').fill('Browser studio • Podcast / Live');
  await studio.locator('[data-studio-action=record]').click();await studio.locator('[role=status]').filter({hasText:'Audio wird lokal aufgenommen'}).waitFor();await new Promise(resolve=>setTimeout(resolve,1500));await studio.locator('[data-studio-action=record_stop]').click();
  const downloaded=page.waitForEvent('download');await studio.locator('[data-studio-action=download]').click();const download=await downloaded,record=await fs.readFile(await download.path());assert.equal(record.subarray(0,4).toString(),'RIFF');assert.equal(record.subarray(8,12).toString(),'WAVE');assert(record.length>48000,'AudioWorklet produced actual PCM');assert.equal(record.readUInt32LE(40)+44,record.length);
  await studio.locator('[data-studio-action=upload]').click();await studio.locator('[role=status]').filter({hasText:'Audio in Media Library gespeichert'}).waitFor();
  await studio.locator('[data-studio-action=podcast_draft]').click();await studio.locator('[role=status]').filter({hasText:'Podcast-Entwurf erstellt'}).waitFor();
  page.once('dialog',dialog=>dialog.accept());const started=page.waitForResponse(r=>r.url().includes('/browser')&&r.request().method()==='POST');await studio.locator('[data-studio-action=start]').click();const response=await started;assert.equal(response.status(),201,await response.text());const {id}=await response.json();assert(id);
  await new Promise(resolve=>setTimeout(resolve,2000));
  const signal=await page.evaluate(async()=>await(await fetch('/desktop/live-studio/server',{headers:{Accept:'application/json'}})).json());assert.equal(signal.available,true);assert(signal.active.some(path=>path.name==='browser-'+id&&path.source==='webRTCSession'),'Real MediaMTX received the browser media');
  await live.locator('[data-window-action=close]').click();await studio.locator('[role=status]').filter({hasText:'Übertragung oder Aufnahme aktiv'}).waitFor();
  await studio.locator('[data-studio-action=stop]').click();await studio.locator('[role=status]').filter({hasText:'Browser-Übertragung beendet'}).waitFor();
  await studio.locator('h3').scrollIntoViewIfNeeded();await page.screenshot({path:'tests/artifacts/browser-live-desktop.png'});await page.setViewportSize({width:390,height:844});await studio.locator('h3').scrollIntoViewIfNeeded();await page.screenshot({path:'tests/artifacts/browser-live-mobile.png'});
  assert.deepEqual(errors,[]);assert.deepEqual(failures,[]);console.log('Real MediaMTX WHIP/WebRTC, local camera/microphone, image/PiP/title composition, WAV recording/download/library upload, busy-event protection and explicit stop passed desktop/mobile. Linux transcoding/Plesk/firewall were not exercised.');
}catch(error){console.error(mtxLogs);console.error(logs.slice(-1000));throw error;}finally{if(browser)await browser.close();for(const process of processes.reverse())process.kill();}
