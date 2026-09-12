import {chromium} from '../../.local/node_modules/playwright/index.mjs';
import {spawn,execFileSync} from 'node:child_process';
import assert from 'node:assert/strict';
const php=process.env.PHP_BINARY||'php';
const server=spawn(php,['-S','127.0.0.1:8792','-t','.','../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'],{stdio:'pipe',cwd:'public'});
let output='';server.stdout.on('data',v=>output+=v);server.stderr.on('data',v=>output+=v);
let browser;
try {
 let ready=false;for(let n=0;n<30;n++){try{if((await fetch('http://127.0.0.1:8792/login',{signal:AbortSignal.timeout(1000)})).ok){ready=true;break;}}catch{}await new Promise(r=>setTimeout(r,200));}assert(ready,output);console.log('Workflow server ready.');
 browser=await chromium.launch({headless:true,...(process.env.BROWSER_CHANNEL?{channel:process.env.BROWSER_CHANNEL}:{})});
 const page=await browser.newPage({viewport:{width:1672,height:941}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto('http://127.0.0.1:8792/login');await page.locator('[name=email]').fill('test@example.com');await page.locator('[name=password]').fill('kurz5');await page.locator('form button').click();await page.waitForURL('**/desktop');console.log('Workflow logged in.');
 // Presentation fixtures only: real CSV, account data and references are covered by PHP feature tests.
 await page.locator('[data-close-all]').click();await page.locator('[data-open-app=videos]').first().click();
 const library=page.locator('.os-window[data-app-id=videos] [data-content-library]');
 await library.locator('[data-content-summary]').getByText('Inhalte').waitFor();
 await page.route('**/desktop/content/takeout-ui-fixture',route=>route.fulfill({json:{id:'takeout-ui-fixture',kind:'poll',source:'youtube',title:'Original quiz',body:'Original text',status:'unsorted',assets:[],private:true,poll:{options:[{text:'Yes',is_correct:true,explanation:'Original explanation'}]},references:[{text:'Not available',missing:true,detail_url:null}],takeout_data:{post:{text:'<script>window.takeoutExecuted=true</script>'}}}}));
 await library.evaluate(root=>root.dispatchEvent(new CustomEvent('local-content-open',{detail:{url:'/desktop/content/takeout-ui-fixture'}})));
 await library.locator('[data-content-details] li').filter({hasText:'Yes — Richtige Antwort'}).waitFor();await library.getByText('Original explanation',{exact:true}).waitFor();
 const original=library.locator('[data-content-details] details').filter({hasText:'Originaldaten aus Takeout'});await original.locator('summary').click();assert((await original.locator('pre').textContent()).includes('<script>'));assert.equal(await page.evaluate(()=>window.takeoutExecuted),undefined);
 assert.equal(await library.locator('a[href*="youtube.com"]').count(),0);
 await page.route('**/desktop/content/takeout-account-fixture',route=>route.fulfill({json:{id:'takeout-account-fixture',kind:'channel',source:'youtube',title:'Private channel',body:'',status:'unsorted',assets:[],private:true,archive_data:true,takeout_data:{channel:{title:'Original channel'}}}}));
 await library.evaluate(root=>root.dispatchEvent(new CustomEvent('local-content-open',{detail:{url:'/desktop/content/takeout-account-fixture'}})));
 await library.locator('.content-assignment [name=kind]').waitFor();assert.equal(await library.locator('.content-assignment [name=kind]').inputValue(),'channel');assert(await library.locator('[data-ai]').isHidden());
 console.log('Takeout presentation passed: readable quiz, escaped originals, retained archive kind and no paid AI/archive or YouTube links.');
 await page.locator('[data-close-all]').click();await page.locator('[data-open-app=imports]').first().click();
 // Inventory fixture tests the UI only; PHP feature tests exercise real ZIP/folder discovery and parsing.
 await page.route('**/desktop/imports/takeout',route=>route.fulfill({json:{available:true,reports:['takeout-fixture-report.zip'],batches:[
  {id:'takeout-fixture',layout:'zip',report:'takeout-fixture-report.zip',parts:Array.from({length:8},(_,i)=>({number:i+1,bytes:1024,name:`part-${i}.zip`}))},
  {id:'folder:prepared-fixture',layout:'folder',name:'prepared-fixture',parts:[],report:null},
  {id:'takeout-without-report',layout:'zip',parts:[{number:1,bytes:1024}],report:null}
 ]}}));
 const imports=page.locator('.os-window[data-app-id=imports]');await imports.locator('[name=method][value=takeout]').check();
 await imports.locator('[data-takeout-message]').getByText('Mit HTML-Dateikatalog',{exact:false}).waitFor();
 assert.equal(await imports.locator('[data-takeout-parts]').inputValue(),'8');
 await imports.locator('[data-takeout-batch]').selectOption('folder:prepared-fixture');assert(await imports.locator('[data-takeout-parts]').isDisabled());assert(await imports.locator('[data-takeout-parts]').isHidden());
 await imports.locator('[data-takeout-batch]').selectOption('takeout-without-report');assert(await imports.locator('[data-takeout-parts]').isEnabled());assert.equal(await imports.locator('[data-takeout-parts]').inputValue(),'1');
 assert((await imports.locator('[data-takeout-message]').textContent()).includes('optional'));
 const record=await page.evaluate(async()=>{
  const canvas=document.createElement('canvas');canvas.width=160;canvas.height=90;const ctx=canvas.getContext('2d');
  const stream=canvas.captureStream(10),recorder=new MediaRecorder(stream,{mimeType:'video/webm;codecs=vp8'}),chunks=[];
  recorder.ondataavailable=e=>chunks.push(e.data);const finished=new Promise(r=>recorder.onstop=r);recorder.start();
  for(let n=0;n<15;n++){ctx.fillStyle=n%2?'#102f52':'#d5a13c';ctx.fillRect(0,0,160,90);await new Promise(r=>setTimeout(r,100));}recorder.stop();await finished;stream.getTracks().forEach(track=>track.stop());
  const bytes=new Uint8Array(await new Blob(chunks,{type:'video/webm'}).arrayBuffer()),csrf=document.querySelector('meta[name=csrf-token]').content;
  const send=async(url,body,headers={})=>{const reply=await fetch(url,{method:'POST',body,headers:{Accept:'application/json','X-CSRF-TOKEN':csrf,...headers}});if(!reply.ok)throw new Error(await reply.text());return reply.json();};
  const upload=await send('/desktop/media/uploads',JSON.stringify({request_key:crypto.randomUUID(),name:'Playback fixture '+Date.now()+'.webm',size:bytes.length}),{'Content-Type':'application/json'});
  const sha=Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256',bytes)),n=>n.toString(16).padStart(2,'0')).join('');
  await send(`/desktop/media/uploads/${upload.id}/chunk`,bytes,{'Content-Type':'application/octet-stream','X-Upload-Offset':'0','X-Chunk-SHA256':sha});
  const done=await send(`/desktop/media/uploads/${upload.id}/finish`,'{}',{'Content-Type':'application/json'});return done.media_id;
 });
 const queued=page.waitForResponse(reply=>reply.url().endsWith('/imports/video-check')&&reply.request().method()==='POST');await imports.locator('[data-local-video-check]').click();const run=(await (await queued).json()).import_id;
 execFileSync(php,['tests/browser-video-audit.php',run],{env:process.env});
 const box=page.locator('dialog.import-report-dialog:not(.import-history-dialog)');await box.locator('[data-refresh]').click();
 await box.locator('[data-play-all]').waitFor({state:'visible'});
 // Filter down to the synthetic video; inspect report over the authenticated browser session.
 const item=await page.evaluate(async({run,record})=>{let n=1;for(;;){const data=await(await fetch(`/desktop/imports/${run}/report?type=video-check&page=${n}`,{headers:{Accept:'application/json'}})).json();const item=data.data.find(x=>x.subject_id===record);if(item)return item;if(n>=data.meta.last_page)throw new Error('fixture not reported');n++;}},{run,record});
 const fixturePage=await page.evaluate(async({run,id})=>{let n=1;for(;;){const data=await(await fetch(`/desktop/imports/${run}/report?page=${n}`,{headers:{Accept:'application/json'}})).json();if(data.data.some(x=>x.id===id))return n;n++;}},{run,id:item.id});
 for(let n=1;n<fixturePage;n++)await box.locator('[data-page]').last().click();
 await box.locator(`[data-check="${item.id}"]`).click();
 await box.locator('[data-items] li').filter({has:page.locator(`[data-check="${item.id}"]`)}).getByText('In diesem Browser abgespielt',{exact:false}).waitFor({timeout:25000});
 const saved=await page.evaluate(async({run,id})=>{let n=1;for(;;){const data=await(await fetch(`/desktop/imports/${run}/report?page=${n}`,{headers:{Accept:'application/json'}})).json();const item=data.data.find(x=>x.id===id);if(item)return item.metadata.browser_status;n++;}},{run,id:item.id});assert.equal(saved,'playable');
 await page.setViewportSize({width:390,height:844});assert(await box.evaluate(el=>el.getBoundingClientRect().width<=innerWidth));
 await box.locator('[data-close]').click();assert.deepEqual(errors,[]);console.log('New import workflow passed: Takeout fields, local HTTP 206, decoded WebM frame, seek, actual browser playback and persisted outcome.');
}finally{await browser?.close();server.kill();}
