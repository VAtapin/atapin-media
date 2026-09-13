import { chromium } from '../../.local/node_modules/playwright/index.mjs';
import { spawn, execFileSync } from 'node:child_process';
import fs from 'node:fs/promises';
import assert from 'node:assert/strict';

const php=process.env.PHP_BINARY||'php';
const env={...process.env,APP_URL:'http://127.0.0.1:8796'};
const server=spawn(php,['-S','127.0.0.1:8796','-t','.','../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'],{cwd:'public',env,stdio:'pipe'});
let output='',browser,fixture;
server.stdout.on('data',data=>output+=data);server.stderr.on('data',data=>output+=data);
try {
  let ready=false;
  for(let i=0;i<60;i++) {
    try {if((await fetch('http://127.0.0.1:8796/up')).ok){ready=true;break;}}catch{}
    await new Promise(resolve=>setTimeout(resolve,300));
  }
  assert(ready,output);
  console.log('Static playback server ready.');
  browser=await chromium.launch({headless:true,...(process.env.BROWSER_CHANNEL?{channel:process.env.BROWSER_CHANNEL}:{})});
  const page=await browser.newPage({viewport:{width:1672,height:941}});
  await page.goto('http://127.0.0.1:8796/');
  console.log('Static playback browser ready.');
  // Make real, decodable video locally, without downloading media or requiring FFmpeg.
  const recording=await page.evaluate(async()=>{
    const mime=['video/mp4;codecs=avc1.42001E','video/webm;codecs=vp8'].find(type=>MediaRecorder.isTypeSupported(type));
    if(!mime)throw new Error('No test recording codec');
    const canvas=document.createElement('canvas');canvas.width=320;canvas.height=180;
    const context=canvas.getContext('2d'),stream=canvas.captureStream(15),recorder=new MediaRecorder(stream,{mimeType:mime}),chunks=[];
    recorder.ondataavailable=event=>chunks.push(event.data);
    const stopped=new Promise(resolve=>recorder.onstop=resolve);
    let frame=0;const draw=setInterval(()=>{context.fillStyle=frame++%2?'#123b69':'#bb851a';context.fillRect(0,0,320,180);},50);
    recorder.start();await new Promise(resolve=>setTimeout(resolve,1600));recorder.stop();await Promise.race([stopped,new Promise((_,reject)=>setTimeout(()=>reject(new Error('Test recorder timed out')),10000))]);
    clearInterval(draw);stream.getTracks().forEach(track=>track.stop());
    return {extension:mime.startsWith('video/mp4')?'mp4':'webm',bytes:Array.from(new Uint8Array(await new Blob(chunks,{type:mime}).arrayBuffer()))};
  });
  console.log(`Generated test ${recording.extension}.`);
  await fs.mkdir('tests/artifacts',{recursive:true});
  const source=`tests/artifacts/static-playback-source.${recording.extension}`;
  await fs.writeFile(source,Buffer.from(recording.bytes));
  fixture=JSON.parse(execFileSync(php,['tests/static-media-fixture.php',source],{env,encoding:'utf8'}));
  console.log('Registered generated video.');
  assert.match(fixture.url,/\/media\/[a-f0-9]{64}\.(mp4|webm)$/);
  const responses=[],errors=[];
  page.on('pageerror',error=>errors.push(error.message));
  page.on('response',response=>{if(response.url()===fixture.url)responses.push(response);});
  await page.goto('http://127.0.0.1:8796'+fixture.page);
  const video=page.locator('video').first();assert.equal(await video.getAttribute('src'),fixture.url);
  await video.evaluate(element=>{element.muted=true;return Promise.race([element.play(),new Promise((_,reject)=>setTimeout(()=>reject(new Error('Playback timed out')),10000))]);});
  await page.waitForFunction(()=>document.querySelector('video').currentTime>0.2&&document.querySelector('video').videoWidth===320,null,{timeout:10000});
  await video.evaluate(element=>element.pause());
  assert(responses.length>0);assert(responses.every(response=>!response.request().redirectedFrom()&&[200,206].includes(response.status())));
  for(const width of [1672,390]) {
    await page.setViewportSize({width,height:width===1672?941:844});
    assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
    await page.screenshot({path:`tests/artifacts/static-playback-${width}.png`,fullPage:true});
  }
  assert.deepEqual(errors,[]);
  console.log(`Static ${fixture.mime}: direct URL, no redirect, decoded frame, playback and desktop/mobile passed. Range/seek must be verified on production nginx, not the PHP development server.`);
} finally {
  await browser?.close();server.kill();
  if(fixture?.file) {
    execFileSync(php,['tests/static-media-fixture.php','cleanup'],{env});
    await fs.unlink(fixture.file);
  }
}
