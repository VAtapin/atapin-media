import {chromium} from '../../.local/node_modules/playwright/index.mjs';
import {spawn} from 'node:child_process';
import fs from 'node:fs/promises';
import assert from 'node:assert/strict';
const base='http://127.0.0.1:8798', fixture=JSON.parse(process.env.CONTENT_ENHANCEMENTS_FIXTURE);
const server=spawn(process.env.PHP_BINARY||'php',['-S','127.0.0.1:8798','-t','.','../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'],{cwd:'public',stdio:'pipe',env:{...process.env,APP_URL:base}});
let output='',browser;server.stdout.on('data',d=>output+=d);server.stderr.on('data',d=>output+=d);
try {
  let ready=false;for(let i=0;i<60;i++){try{if((await fetch(base+'/login')).ok){ready=true;break;}}catch{}await new Promise(r=>setTimeout(r,300));}assert(ready,output);
  browser=await chromium.launch({headless:true,...(process.env.BROWSER_CHANNEL?{channel:process.env.BROWSER_CHANNEL}:{})});
  let page=await browser.newPage({viewport:{width:1672,height:941}});const errors=[];page.on('pageerror',e=>errors.push(e.message));page.setDefaultTimeout(10000);
  await fs.mkdir('tests/artifacts',{recursive:true});
  for(const width of [1672,390]){
    await page.setViewportSize({width,height:width===1672?941:844});await page.goto(base);await page.evaluate(()=>document.fonts.ready);
    const geometry=await page.evaluate(()=>{const feature=document.querySelector('.public-overview-feature'),copy=feature.querySelector('.public-feature-content'),author=feature.querySelector('.public-feature-author'),play=feature.querySelector('.public-play');const f=feature.getBoundingClientRect(),c=copy.getBoundingClientRect(),a=author.getBoundingClientRect(),p=play.getBoundingClientRect();return {overflow:document.documentElement.scrollWidth>innerWidth,border:getComputedStyle(feature).borderTopColor,author:author.textContent,play:p.x+p.width/2-f.x,width:f.width,copyBottom:c.bottom,authorTop:a.top};});
    assert(!geometry.overflow,JSON.stringify(geometry));assert.match(geometry.author,/Testautor/);assert(geometry.play>geometry.width/2);assert(geometry.copyBottom<=geometry.authorTop+2,JSON.stringify(geometry));
    await page.screenshot({path:`tests/artifacts/content-enhancements-${width}.png`,fullPage:true});
  }
  await page.setViewportSize({width:1672,height:941});await page.goto(base+'/login');await page.locator('[name=email]').fill('test@example.com');await page.locator('[name=password]').fill('kurz5');await page.locator('form button').click();await page.waitForURL('**/desktop');
  await page.goto(base+'/desktop?open=settings');
  await page.locator('.os-window [data-settings-tab="media_appearance"]').click();
  const settings=page.locator('.os-window [data-settings-panel="media_appearance"]');assert(await settings.locator('[name=cover_style_prompt]').isVisible());assert(await settings.locator('[name=public_author_photo]').count());
  await page.goto(base+'/desktop?open=podcast');await page.locator('.os-window[data-app-id=podcast] [data-content-list]').getByText('Podcast Browser',{exact:true}).click();
  await page.waitForSelector('.os-window[data-app-id^="podcast-"] [data-prepare-media=podcast]');assert(await page.locator('.os-window[data-app-id^="podcast-"] [name=short_description]').isVisible());
  page=await browser.newPage({viewport:{width:1672,height:941}});page.on('pageerror',e=>errors.push(e.message));page.setDefaultTimeout(10000);
  await page.goto(base+'/login');await page.locator('[name=email]').fill('viewer@example.com');await page.locator('[name=password]').fill('kurz5');await page.locator('form button').click();await page.waitForURL('**/konto');
  await page.goto(base+fixture.url);await page.evaluate(()=>{window.testPlayer=document.querySelector('video');window.testMarker='same-document';});
  const details=page.locator('.public-full-description');assert.equal(await details.getAttribute('open'),null);await details.locator('summary').click();assert.match(await details.textContent(),/Originalbeschreibung/);await page.waitForFunction(()=>document.querySelector('.public-full-description summary').textContent==='Weniger anzeigen');
  const reaction=page.locator('form[data-public-form]').filter({has:page.locator('[name=enabled]')}).first();
  assert.equal(await reaction.count(),1);const before=await reaction.locator('[name=enabled]').inputValue();
  for(const expected of [before==='1'?'0':'1',before]){const responsePromise=page.waitForResponse(r=>r.request().method()==='POST'&&r.url().includes('/state'));await reaction.locator('button').click();const response=await responsePromise;assert.equal(response.status(),200,await response.text());await page.waitForTimeout(100);assert.equal(await reaction.locator('[name=enabled]').inputValue(),expected);}
  await page.locator('#tab-comments').click();const form=page.locator('.public-comments form');await form.locator('[name=body]').fill('Browserkommentar zur Prüfung.');await form.locator('button[type=submit]').click();await page.locator('[data-comment-list]').getByText('Browserkommentar zur Prüfung.',{exact:true}).waitFor();
  assert(await page.evaluate(()=>window.testMarker==='same-document'&&window.testPlayer===document.querySelector('video')));assert.match(await page.locator('[data-comment-list]').textContent(),/KI|geprüft|Prüfung/i);assert.equal(errors.length,0,errors.join('\n'));
  console.log('Content enhancements browser passed: desktop/mobile hero, settings, Podcast, descriptions, comments, player DOM continuity.');
} finally {await browser?.close();server.kill();}
