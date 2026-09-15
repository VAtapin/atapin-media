import { chromium } from '../../.local/node_modules/playwright/index.mjs';
import assert from 'node:assert/strict';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const here = path.dirname(fileURLToPath(import.meta.url));
let browser;

try {
  browser = await chromium.launch({headless:true, ...(process.env.BROWSER_CHANNEL ? {channel:process.env.BROWSER_CHANNEL} : {})});
  const page = await browser.newPage({viewport:{width:1672,height:941}});
  const errors = [], requests = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.setContent('<meta name="csrf-token" content="test"><main id="host"></main>');
  await page.addScriptTag({content: `
    window.desktopWorkspaceLabels={new_book:'PDF-Buch hinzufügen',upload_pdf:'PDF hochladen',further_settings:'Weitere Einstellungen',save:'Speichern',cancel:'Abbrechen',close:'Schließen',refresh:'Aktualisieren',all:'Alle',choose:'Auswählen',loading:'Laden',book_view_cards:'Karten',book_view_list:'Liste',book_files:'Dateien des Buches',file:'Datei',full:'Vollversion',cover:'Cover',sample:'Leseprobe',upload:'Hochladen',title:'Titel',author:'Autor',description:'Beschreibung',contents:'Inhaltsverzeichnis',status:'Status'};
    window.desktopBookReviewLabels={};
    window.__openedWindows=0;window.openDesktopProgram=()=>{window.__openedWindows++;};
    const book={id:1,title:'Bestehendes Buch',subtitle:'Untertitel',description:'Beschreibung',author:'Autor',contents:'Kapitel 1',isbn:'123',language:'de',page_count:120,publication_date:'2026-09-01',tags:['Buch'],seo_title:'SEO',seo_description:'SEO Text',price_cents:990,currency:'EUR',status:'draft',project_id:null,taxonomy_term_ids:[],external_shop_url:'',metadata:{edition_text:'Private Ausgabe'}};
    window.fetch=async(url,options={})=>{
      const target=String(url),method=options.method||'GET';window.__requests=window.__requests||[];window.__requests.push({target,method,body:typeof options.body==='string'?JSON.parse(options.body):null});
      if(target.includes('/desktop/workspaces/books-pdf'))return new Response('<section class="desktop-workspace" data-workspace="books-pdf" data-can-moderate-reviews="false"><header><div data-workspace-actions></div></header><div data-workspace-feedback></div><form data-workspace-filters></form><div class="workspace-layout"><section data-workspace-list></section><aside class="workspace-editor" data-workspace-editor></aside></div><nav data-workspace-pager></nav></section>',{status:200,headers:{'Content-Type':'text/html'}});
      if(target.startsWith('/desktop/lookups'))return Response.json({data:[]});
      if(target==='/desktop/books/1')return Response.json({product:book,assets:[]});
      if(target.startsWith('/desktop/books?'))return Response.json({data:[book],current_page:1,last_page:1,total:1});
      if(target==='/desktop/books'&&method==='POST')return Response.json({id:2,title:'Schnelles Buch'},{status:201});
      if(target==='/desktop/books/1'&&method==='PATCH')return Response.json({id:1,status:'saved'});
      throw new Error('Unexpected request '+method+' '+target);
    };
  `});
  await page.addStyleTag({path:path.join(here,'../public/vendor/jodit/4.13.9/jodit.min.css')});
  await page.addScriptTag({path:path.join(here,'../public/vendor/jodit/4.13.9/jodit.min.js')});
  await page.addScriptTag({path:path.join(here,'../public/assets/desktop-rich-text.js')});
  await page.addScriptTag({path:path.join(here,'../public/assets/desktop-workspaces.js')});
  await page.addScriptTag({path:path.join(here,'../public/assets/desktop-catalogs.js')});
  await page.addStyleTag({path:path.join(here,'../public/assets/desktop-workspaces.css')});
  await page.evaluate(() => window.initializeDesktopWorkspace(document.querySelector('#host'),'books-pdf'));
  const root = page.locator('[data-workspace="books-pdf"]');
  await root.getByText('Bestehendes Buch',{exact:true}).waitFor();

  await root.locator('[data-workspace-actions] button').filter({hasText:'PDF-Buch hinzufügen'}).click();
  const quick = root.locator('[data-workspace-quick-create]');
  await quick.waitFor();
  assert.equal(await quick.locator('input,textarea,select').count(),1);
  assert.equal(await quick.locator('[name="title"]').getAttribute('required'),'');
  await quick.locator('[name="title"]').fill('Schnelles Buch');
  await quick.locator('button[type="submit"]').click();
  await quick.waitFor({state:'detached'});
  const create = await page.evaluate(() => window.__requests.find(request => request.target==='/desktop/books'&&request.method==='POST'));
  assert.deepEqual(create.body,{title:'Schnelles Buch',currency:'EUR',status:'draft',tags:[],price_cents:0});
  assert.equal(await page.evaluate(() => window.__openedWindows),0);

  await root.getByText('Bestehendes Buch',{exact:true}).click();
  const editor = root.locator('[data-workspace-editor]');
  const form = editor.locator('.workspace-book-form');
  await form.waitFor();
  assert.equal(await page.evaluate(() => window.__openedWindows),0);
  for(const name of ['title','author','isbn','language','page_count','price','currency','description','contents','status'])
    assert.equal(await form.locator(`:scope > label [name="${name}"]`).count(),1,`${name} must stay in the main editor`);
  const more = form.locator('[data-book-more-settings]');
  assert.equal(await more.evaluate(node => node.open),false);
  for(const name of ['subtitle','edition_text','publication_date','tags','seo_title','seo_description','project_id','external_shop_url'])
    assert.equal(await more.locator(`[name="${name}"]`).count(),1,`${name} must be under More settings`);
  assert.equal(await form.locator('[name="taxonomy_term_ids"]').count(),1,'taxonomy choice must stay in the main editor');
  await form.locator('[name="description"][data-rich-text-ready="true"]').waitFor({state:'attached'});
  assert.equal(await form.locator('.jodit-container').count(),3,'description, contents and edition text use Jodit');
  await more.locator('summary').click();
  assert((await more.locator('.jodit-container').evaluate(node=>node.getBoundingClientRect().width))>200,'edition editor becomes visible when More settings opens');
  await form.evaluate(node=>window.DesktopRichText.setValue(node.elements.description,'<p><strong>Formatierter Text</strong></p>'));
  await form.locator('button[type="submit"]').first().click();
  await page.waitForFunction(()=>window.__requests.some(request=>request.target==='/desktop/books/1'&&request.method==='PATCH'));
  const richSave=await page.evaluate(()=>window.__requests.find(request=>request.target==='/desktop/books/1'&&request.method==='PATCH'));
  assert.match(richSave.body.description,/<strong>Formatierter Text<\/strong>/);
  assert.equal((await form.evaluate(node => getComputedStyle(node).gridTemplateColumns)).trim().split(/\s+/).length,2);

  await root.locator('[data-book-pdf-intake]').click();
  await editor.locator('.workspace-pdf-intake [name="file"]').waitFor();
  assert.equal(await page.evaluate(() => window.__openedWindows),0);
  assert.deepEqual(errors,[]);
  console.log('Books quick create, inline editor, grouped settings and inline PDF intake passed.');
} finally {
  if(browser) await browser.close();
}
