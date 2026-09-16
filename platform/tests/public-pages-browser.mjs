import { chromium } from '../../.local/node_modules/playwright/index.mjs';
import { spawn } from 'node:child_process';
import fs from 'node:fs/promises';
import assert from 'node:assert/strict';
import http from 'node:http';

const base='http://127.0.0.1:8795';
const server=spawn(process.env.PHP_BINARY||'php',['-S','127.0.0.1:8795','-t','.','../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'],{cwd:'public',stdio:'pipe',env:{...process.env,APP_URL:base}});
let output='',browser;
server.stdout.on('data',data=>output+=data);server.stderr.on('data',data=>output+=data);
const detail=JSON.parse(process.env.PUBLIC_DETAIL_ROUTES||'{}');
const routes=process.env.PUBLIC_ROUTES?JSON.parse(process.env.PUBLIC_ROUTES):['/','/themen','/videos',detail.videos||'/videos/vorschau','/beitraege',detail.beitraege||'/beitraege/vorschau','/buecher',detail.buecher||'/buecher/vorschau','/live','/podcast','/community'];
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
      if(route==='/'&&await page.locator('[data-public-book-shelf]').count()){
        const bar=page.locator('[data-public-book-shelf]'),categories=bar.locator('[data-shelf-category]');
        await bar.scrollIntoViewIfNeeded();
        const dimensions=await bar.evaluate(element=>({height:element.getBoundingClientRect().height,border:parseFloat(getComputedStyle(element).borderTopWidth)}));
        assert(dimensions.height>=190&&dimensions.height<=(width>800?290:230)&&dimensions.border===0,`Home shelf did not use the shorter image: ${JSON.stringify(dimensions)}`);
        assert.equal(await bar.locator('.public-taxonomy-crumbs').count(),0,'Old home taxonomy strip is gone');
        const emptyShelf=process.env.PUBLIC_EXPECT_EMPTY_SHELF==='1';
        assert.equal(await categories.count(),emptyShelf?1:10,'Home keeps all active categories in the data');
        await page.waitForFunction(()=>{const image=document.querySelector('.public-book-shelf-background');return image?.complete&&image.naturalWidth>0;});
        assert.equal(await bar.locator('.public-book-shelf-background').evaluate(image=>image.naturalHeight),374,'New shelf PNG is not loaded');
        const visible=bar.locator('[data-shelf-category]:visible'),hidden=bar.locator('[data-shelf-category][hidden]');
        assert(await visible.count()>0,'At least one category fits on the shelf');
        assert.equal(await bar.locator('[data-shelf-all]').isVisible(),await hidden.count()>0,'See all appears only when categories overflow');
        if(await hidden.count()>0)assert.equal(new URL(await bar.locator('[data-shelf-all]').getAttribute('href'),'http://127.0.0.1:8795').pathname,'/themen');
        if(emptyShelf){
          assert.equal(await bar.locator('.public-book-shelf-book').count(),0,'Empty category has no fake books');
          for(const item of ['[data-shelf-plant-left]','[data-shelf-globe]','[data-shelf-plant-right]']){
            assert(await bar.locator(item).isVisible(),`Empty category should show ${item}`);
          }
          const globeWidth=await bar.locator('[data-shelf-globe]').evaluate(image=>image.getBoundingClientRect().width);
          assert(globeWidth>=(width>800?150:85),`Empty shelf globe remained too small: ${globeWidth}`);
        } else {
          assert.equal(await visible.first().locator('.public-book-shelf-book').count(),2,'Initial category has two dynamic books');
          const categoryLink=visible.first().locator('.public-book-shelf-category-name');
          assert.equal(new URL(await categoryLink.getAttribute('href'),base).pathname,'/themen','Category plaque does not link to category directory');
          assert.equal(new URL(await categoryLink.getAttribute('href'),base).searchParams.get('category'),'glaube-leben','Category plaque links to the wrong category');
          for(const link of await visible.locator('.public-book-shelf-book').evaluateAll(nodes=>nodes.map(node=>new URL(node.href).pathname+new URL(node.href).search))){
            assert.match(link,/^\/(videos|beitraege|buecher)\?taxonomy=/,'Topic books must use existing section-specific routes');
          }
          const book=visible.locator('.public-book-shelf-book').first();
          const floor=await bar.evaluate(element=>{
            const image=element.querySelector('.public-book-shelf-background').getBoundingClientRect();
            const book=element.querySelector('.public-book-shelf-book').getBoundingClientRect();
            return {shelfFloor:image.top+image.height*315/374,bookBottom:book.bottom};
          });
          assert(Math.abs(floor.shelfFloor-floor.bookBottom)<=16,`Book slides away from the shelf floor at ${width}: ${JSON.stringify(floor)}`);
          await book.hover();
          await page.waitForTimeout(250);
          assert.match(await book.evaluate(element=>getComputedStyle(element).transform),/^matrix3d\(/,'Book does not pull forward with perspective on hover');
          const rotation=await book.evaluate(element=>{const matrix=new DOMMatrixReadOnly(getComputedStyle(element).transform);return [matrix.m12,matrix.m13,matrix.m21,matrix.m23,matrix.m31,matrix.m32];});
          assert(rotation.every(value=>Math.abs(value)<0.001),`Book must pull forward without rotating: ${rotation}`);
          assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,'Pulled-forward book causes horizontal overflow');
          await bar.screenshot({path:`tests/artifacts/public-shelf-hover-${width}.png`});
          const spacing=await bar.evaluate(element=>{
            const books=element.querySelector('[data-shelf-categories]');
            const decor=element.querySelector('[data-shelf-decor]');
            return {booksRight:books.getBoundingClientRect().right,decorLeft:decor.getBoundingClientRect().left,decorVisible:[...decor.children].some(item=>!item.hidden)};
          });
          assert(!spacing.decorVisible||spacing.decorLeft>=spacing.booksRight-1,`Books overlap decorations: ${JSON.stringify(spacing)}`);
          const expected=new URL(await book.getAttribute('href'),'http://127.0.0.1:8795');
          await book.click();
          await page.waitForURL(expected.href);
          assert.equal(new URL(page.url()).searchParams.has('taxonomy'),true,'Clicking a book opens its topic');
          await page.goto('http://127.0.0.1:8795/');
        }
      }
      if(route==='/themen'){
        const shelves=page.locator('[data-book-cabinet] [data-public-book-shelf]');
        assert.equal(await shelves.count(),10,'The cabinet shows one shelf for every category');
        assert.equal(await page.locator('[data-book-cabinet] [data-shelf-category]').count(),10,'Each cabinet shelf has exactly one category');
        assert.equal(await page.locator('[data-book-cabinet] .public-book-shelf-book').count(),47,'The cabinet keeps all published topic books');
        assert.equal(await shelves.first().locator('[data-shelf-all]').isVisible(),false,'A one-category shelf never needs See all');
        const decorations=await page.locator('[data-book-cabinet] [data-public-book-shelf]').evaluateAll(nodes=>nodes.map(node=>{
          const visible=[...node.querySelectorAll('[data-shelf-globe],[data-shelf-plant-right],[data-shelf-plant-left]')].filter(item=>!item.hidden);
          const image=node.querySelector('.public-book-shelf-background').getBoundingClientRect();
          return {
            shown:visible.length,
            booksRight:node.querySelector('[data-shelf-categories]').getBoundingClientRect().right,
            decorLeft:node.querySelector('[data-shelf-decor]').getBoundingClientRect().left,
            floor:image.top+image.height*315/374,
            visibleBottom:visible[0]?.getBoundingClientRect().bottom,
          };
        }));
        assert(decorations.every(item=>item.shown<=1),'Cabinet decorations must be distributed across different shelves');
        assert(decorations.every(item=>!item.shown||item.decorLeft>=item.booksRight-1),`Cabinet books overlap decoration: ${JSON.stringify(decorations)}`);
        assert(decorations.every(item=>!item.shown||Math.abs(item.floor-item.visibleBottom)<=16),`Cabinet decorations slide off the shelf floor: ${JSON.stringify(decorations)}`);
        const globe=shelves.locator('[data-shelf-globe]:visible');
        if(await globe.count())assert(await globe.first().evaluate(image=>image.getBoundingClientRect().width)>=(width>800?150:85),'Cabinet globe remained too small');
        const categoryLink=shelves.first().locator('.public-book-shelf-category-name');
        const category=new URL(await categoryLink.getAttribute('href'),base);
        assert.equal(category.pathname,'/themen','Cabinet category plaque is not a link');
        assert.equal(category.searchParams.get('category'),'glaube-leben');
        await categoryLink.click();
        await page.waitForURL(category.href);
        assert.equal(await page.locator('[data-book-cabinet] [data-public-book-shelf]').count(),1,'Category link does not open its own shelf');
        assert.equal(await page.locator('[data-book-cabinet] [data-shelf-category]').count(),10,'Category detail keeps all category navigation plaques');
        assert.equal(await page.locator('[data-book-cabinet] .public-book-shelf-book').count(),2,'Category detail shows only its topic books');
        await page.goto(base+'/themen');
      }
      if(route==='/beitraege'&&await page.locator('[data-public-book-shelf]').count()){
        const shelf=page.locator('[data-public-book-shelf]');
        assert.equal(await shelf.locator('[data-shelf-category]').count(),10,'Beiträge shelf uses all categories');
        assert.equal(await page.locator('[data-public-taxonomy]').count(),0,'Beiträge does not duplicate taxonomy navigation');
        assert.equal(new URL(await shelf.locator('[data-shelf-category]').nth(1).locator('.public-book-shelf-category-name').getAttribute('href'),base).searchParams.get('taxonomy'),'kategorie-2','Beiträge category plaque does not filter the category');
        await page.goto('http://127.0.0.1:8795/beitraege?taxonomy=kategorie-2');
        assert.equal(await page.locator('[data-public-book-shelf] [data-shelf-category]:visible').first().locator('.public-book-shelf-category-name').textContent(),'Kategorie 2');
        assert.equal(await page.locator('[data-public-book-shelf] [data-shelf-category]:visible').first().locator('.public-book-shelf-book').count(),5,'Filtered category keeps its five topic books');
        const openBook=page.locator('[data-public-book-shelf] .public-book-shelf-book').first();
        const openLink=await openBook.getAttribute('href');
        await page.goto(new URL(openLink,base).href);
        assert.equal(await page.locator(`[data-public-book-shelf] .public-book-shelf-book[href="${openLink}"]`).count(),0,'Opened topic book must not remain on the shelf');
        assert(await page.locator('[data-public-book-shelf] .public-book-shelf-book').count()>0,'Other topic books remain on the shelf');
        assert.equal(await page.locator('[data-public-book-shelf] [data-shelf-category]').count(),10,'Other categories remain available from a topic');
        await page.goto('http://127.0.0.1:8795/beitraege');
      }
      if(['/videos','/buecher'].includes(route)&&await page.locator('[data-public-taxonomy]').count()){
        const bar=page.locator('[data-public-taxonomy]');
        await bar.scrollIntoViewIfNeeded();
        const dimensions=await bar.evaluate(element=>({height:element.getBoundingClientRect().height,border:parseFloat(getComputedStyle(element).borderTopWidth)}));
        assert(dimensions.height<=42&&dimensions.border<=2,`${route}: taxonomy is not a thin strip: ${JSON.stringify(dimensions)}`);
        const crumbs=bar.locator('.public-taxonomy-crumb');
        await crumbs.first().locator('summary').click();
        assert(await crumbs.first().locator('.public-taxonomy-menu').isVisible(),`${route}: categories menu opens`);
        await crumbs.first().locator('summary').click();
        await crumbs.last().locator('summary').click();
        assert(await crumbs.last().locator('.public-taxonomy-menu').isVisible(),`${route}: topics menu opens`);
        for(const link of await bar.locator('.public-taxonomy-menu a').evaluateAll(nodes=>nodes.map(node=>new URL(node.href).pathname+new URL(node.href).search))){
          assert(link.startsWith(`${route}?taxonomy=`),`${route}: taxonomy link escaped the current content section: ${link}`);
        }
        await crumbs.last().locator('summary').click();
      }
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
