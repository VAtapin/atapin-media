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
  for(const width of JSON.parse(process.env.PUBLIC_WIDTHS||'[1672,390]')){
    await page.setViewportSize({width,height:width===1672?941:844});
    for(const [index,route] of routes.entries()){
      await page.goto('http://127.0.0.1:8795'+route);await page.evaluate(()=>document.fonts.ready);
      if(await page.locator('[data-live-heartbeat]').count())await page.waitForFunction(()=>/^\d+$/.test(document.querySelector('[data-live-online]').textContent));
      const overflow=await page.evaluate(()=>({ok:document.documentElement.scrollWidth<=innerWidth,offenders:Array.from(document.querySelectorAll('body *')).filter(element=>{const rect=element.getBoundingClientRect();return rect.width&&rect.right>innerWidth+0.1;}).slice(0,8).map(element=>({tag:element.tagName,class:element.className,right:element.getBoundingClientRect().right}))}));
      assert.equal(overflow.ok,true,`${route}: overflow at ${width}: ${JSON.stringify(overflow.offenders)}`);
      assert(await page.locator('.public-header').isVisible(),route);
      assert.equal(await page.locator('main').count(),1,route);
      if(route==='/'||route==='/buecher'){
        const decorated=page.locator('.public-section-cards, .public-panel-heading, .public-empty-slot');
        for(const text of await decorated.allTextContents())assert(!text.includes('→'),`${route}: decorative arrow remains`);
      }
      if(await page.locator('.public-overview-hero').count()){
        const quote=await page.evaluate(()=>{
          const feature=document.querySelector('.public-overview-feature'),side=document.querySelector('.public-hero-side-copy');
          const a=feature.getBoundingClientRect(),b=side.getBoundingClientRect();
          return {width:innerWidth,feature:{x:a.x,y:a.y,width:a.width,height:a.height},quote:{x:b.x,y:b.y,width:b.width,height:b.height},backdrop:getComputedStyle(side,'::before').backgroundImage,blur:getComputedStyle(side,'::before').filter,overlaps:Math.min(a.right,b.right)>Math.max(a.left,b.left)&&Math.min(a.bottom,b.bottom)>Math.max(a.top,b.top),fallback:!!feature.querySelector('.public-empty-slot'),fallbackBackground:feature.querySelector('.public-empty-slot')?getComputedStyle(feature.querySelector('.public-empty-slot')).backgroundImage:null};
        });
        assert.equal(quote.overlaps,false,`Home quote overlaps media at ${width}: ${JSON.stringify(quote)}`);
        assert.match(quote.backdrop,/rgba\(255, 255, 255, 0\.98\).*rgba\(255, 255, 255, 0\.88\)/,'Home quote keeps a brighter local white cloud');
        assert.equal(quote.blur,'blur(13px)');
        assert(quote.quote.x>=0&&quote.quote.x+quote.quote.width<=width,`Home quote outside viewport at ${width}`);
        if(width>1600)assert(quote.quote.x>=quote.feature.x+quote.feature.width+12,`Home quote must be to the right of media at ${width}`);
        else assert(quote.quote.y>=quote.feature.y+quote.feature.height+12,`Home quote must follow media at ${width}`);
      }
      if(await page.locator('.public-overview-hero').count()){
        const shell=await page.evaluate(()=>{
          const header=document.querySelector('.public-header'),panel=header.querySelector('.public-header-inner');
          const hero=document.querySelector('.public-overview-hero'),image=hero.querySelector('.public-overview-hero-background');
          return {
            headerBackground:getComputedStyle(header).backgroundColor,
            panelBackground:getComputedStyle(panel).backgroundColor,
            panelRadius:getComputedStyle(panel).borderBottomLeftRadius,
            panelWidth:panel.getBoundingClientRect().width,
            overlay:getComputedStyle(hero,'::after').display,
            imagePosition:getComputedStyle(image).objectPosition,
            imageLoaded:image.complete&&image.naturalWidth>0,
            textBackdrop:getComputedStyle(hero.querySelector('.public-overview-copy'),'::before').backgroundImage,
          };
        });
        assert.equal(shell.headerBackground,'rgba(0, 0, 0, 0)',route);
        assert.equal(shell.panelBackground,'rgba(255, 255, 255, 0.88)',route);
        assert.equal(shell.panelRadius,'8px',route);
        if(width===1672)assert(shell.panelWidth<width,route);
        assert.equal(shell.overlay,'none',route);
        assert.equal(shell.imagePosition,'50% 0%',route);
        assert(shell.imageLoaded,route);
        assert.match(shell.textBackdrop,/radial-gradient/,route);
        assert.match(shell.textBackdrop,/rgba\(255, 255, 255, 0\.98\).*rgba\(255, 255, 255, 0\.88\)/,`${route}: brighter local backdrop behind hero copy`);
      }
      await page.screenshot({path:`tests/artifacts/public-page-${index+1}-${width}${process.env.PUBLIC_DETAIL_ROUTES?'-filled':''}.png`,fullPage:true});
      const tabs=page.locator('[role=tab]');
      if(await tabs.count()>1){await tabs.nth(1).click();assert.equal(await tabs.nth(1).getAttribute('aria-selected'),'true',route);}
    }
  }
  assert.deepEqual(errors,[]);
  console.log(`${routes.length} public screens: desktop/mobile, tabs, assets and JavaScript passed.`);
} finally {await browser?.close();server.kill();}
