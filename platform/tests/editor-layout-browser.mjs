import { chromium } from '../../.local/node_modules/playwright/index.mjs';
import { fileURLToPath } from 'node:url';
import assert from 'node:assert/strict';

const browser=await chromium.launch({headless:true,...(process.env.BROWSER_CHANNEL?{channel:process.env.BROWSER_CHANNEL}:{})});
try {
  const page=await browser.newPage({viewport:{width:1672,height:941}});
  await page.setContent('<html lang="de"><head><meta name="csrf-token" content="browser-token"></head><body><section data-content-library><span data-can-publish></span><div id="record-details" data-content-details><details class="content-editor-secondary"><summary>Importdaten</summary><p>Alte Vorschau</p></details></div></section><div id="media-details"><details class="content-editor-secondary"><summary>Dateidaten</summary></details></div></body></html>');
  await page.addStyleTag({path:fileURLToPath(new URL('../public/assets/desktop-media-library.css',import.meta.url))});
  await page.evaluate(() => {
    window.desktopImportLabels={title:'Titel',body:'Originaltext',kind:'Inhaltstyp',status:'Status',
      tags:'Tags',tags_hint:'Tags',target_profile:'Zielbereich',public_section:'Bereich auf der Website',
      public_section_beitraege:'Beiträge',save:'Speichern',ai_classify:'Mit KI einordnen',
      ai_classify_hint:'KI ordnet Daten zu. Keine Veröffentlichung.',short_description:'Kurzbeschreibung',
      short_description_generate:'Kurztext mit KI erstellen',public_published:'Veröffentlichen',
      public_published_hint:'Nur mit Status Bereit.',keep_section:'Bereich beibehalten'};
    window.extendDesktopContentEditor=(form)=>{
      form.querySelector('[name=body]').dataset.richText='';
      const taxonomy=document.createElement('label');taxonomy.className='content-taxonomy-field';
      taxonomy.innerHTML='Themen & Kategorien<select name="taxonomy_term_ids" multiple></select>';
      form.querySelector('.media-library-toolbar-row').before(taxonomy);
      const advanced=document.createElement('details');advanced.className='editor-advanced';
      advanced.innerHTML='<summary>Weitere Einstellungen</summary>';
      form.querySelector('.media-library-toolbar-row').before(advanced);
    };
  });
  await page.addScriptTag({path:fileURLToPath(new URL('../public/assets/desktop-content-assignment.js',import.meta.url))});
  await page.evaluate(() => window.appendContentAssignment(document.querySelector('#record-details'),'record',{
    id:1,title:'Bearbeiteter Titel',body:'Aktueller Text',kind:'post',status:'review',tags:[],
    public_section:'beitraege',public_published:false,
  }));
  const record=page.locator('#record-details');
  assert.equal(await record.locator(':scope > :first-child').evaluate(node => node.tagName),'FORM');
  assert.equal(await record.locator(':scope > details.content-editor-secondary').count(),1);
  assert.equal(await record.locator(':scope > details.content-editor-secondary').evaluate(node => node.open),false);
  const form=record.locator(':scope > form.content-editor-form');
  assert.equal(await form.locator('[name=body][data-rich-text]').count(),1);
  assert.deepEqual(await form.locator('.content-editor-field-grid select').evaluateAll(nodes => nodes.map(node => node.name)),
    ['kind','status','target_profile','public_section']);
  assert.equal(await form.locator('.content-editor-field-grid + .content-taxonomy-field').count(),1);
  assert(await form.evaluate(node => node.querySelector('.media-library-toolbar-row').compareDocumentPosition(node.querySelector('.editor-advanced'))&Node.DOCUMENT_POSITION_FOLLOWING));
  assert.equal(await form.locator('[data-ai]').getAttribute('title'),'KI ordnet Daten zu. Keine Veröffentlichung.');
  assert.equal(await form.locator('.content-editor-field-grid').evaluate(node => getComputedStyle(node).gridTemplateColumns.split(' ').length),3);

  await page.setViewportSize({width:390,height:844});
  assert.equal(await form.locator('.content-editor-field-grid').evaluate(node => getComputedStyle(node).gridTemplateColumns.split(' ').length),1);
  await page.evaluate(() => window.appendContentAssignment(document.querySelector('#media-details'),'media',{
    id:2,title:'Bilddatei',target_profile:'media_library',status:'ready',tags:[],
  }));
  const media=page.locator('#media-details');
  assert.equal(await media.locator(':scope > :first-child').evaluate(node => node.tagName),'FORM');
  assert.deepEqual(await media.locator('.content-editor-field-grid select').evaluateAll(nodes => nodes.map(node => node.name)),
    ['status','target_profile']);
  await page.evaluate(() => {
    window.desktopImportLabels.editor_source_details='Quelle, Importdaten und Dateien';
    window.desktopImportLabels.replacement_search_first='Mindestens 2 Zeichen eingeben und gezielt suchen.';
    window.fetch=async url=>Response.json(String(url).includes('?')?{
      data:[],meta:{current_page:1,total:0,last_page:1},
    }:{id:3,title:'Artikel',body:'Aktueller Originaltext',kind:'post',source:'youtube',
      status:'review',tags:[],assets:[],references:[],external_publications:[],private:true});
    const root=document.createElement('section');root.id='library-fixture';root.dataset.contentLibrary='';
    Object.assign(root.dataset,{contentEditor:'true',canEdit:'true',section:'posts',contentUrl:'/desktop/content'});
    root.innerHTML='<form data-content-filter><select name="kind"><option value="post">Post</option></select><select name="status"><option value=""></option></select></form><div data-content-list></div><div data-content-details></div><div data-content-summary></div><div data-content-pagination></div>';
    document.body.append(root);
  });
  await page.addScriptTag({path:fileURLToPath(new URL('../public/assets/desktop-content-library.js',import.meta.url))});
  await page.evaluate(() => {
    const root=document.querySelector('#library-fixture');window.initializeContentLibrary(root);
    root.dispatchEvent(new CustomEvent('local-content-open',{detail:{url:'/desktop/content/3'}}));
  });
  const library=page.locator('#library-fixture [data-content-details]');
  await library.locator('form.content-editor-form').waitFor();
  assert.equal(await library.locator(':scope > p.content-original-text').count(),0);
  assert.equal(await library.locator(':scope > details.content-editor-secondary').count(),1);
  assert.equal(await library.locator(':scope > details.content-editor-secondary').evaluate(node => node.open),false);
  assert((await library.locator('form [name=body]').inputValue()).includes('Aktueller Originaltext'));
  await page.addScriptTag({path:fileURLToPath(new URL('../public/assets/desktop-content-lifecycle.js',import.meta.url))});
  await page.evaluate(() => {
    const root=document.querySelector('#library-fixture');root.dataset.canMediaEdit='true';
    root.querySelector('[data-content-details]').dispatchEvent(new CustomEvent('content-selected',{
      bubbles:true,detail:{id:3,kind:'post',assets:[{id:99,title:'Verknüpfte Datei'}]},
    }));
  });
  const lifecycle=library.locator(':scope > [data-content-lifecycle]');
  assert.equal(await lifecycle.locator('[data-replace-asset]').count(),0);
  assert.equal(await lifecycle.locator('[data-detach-asset]').count(),1);
  assert(await library.evaluate(node => node.querySelector(':scope > form').compareDocumentPosition(node.querySelector(':scope > [data-content-lifecycle]'))&Node.DOCUMENT_POSITION_FOLLOWING));
  await lifecycle.locator('[data-choose-role=attachment]').click();
  await lifecycle.locator('[data-results]').getByText('Mindestens 2 Zeichen eingeben und gezielt suchen.',{exact:true}).waitFor();
  await page.evaluate(() => {
    document.body.dataset.mediaLibrary='';document.body.dataset.canEdit='true';
    window.appendMediaLifecycle(document.querySelector('#media-details'),{id:2,archived:false});
  });
  assert(await media.evaluate(node => node.querySelector(':scope > form').compareDocumentPosition(node.querySelector(':scope > section.media-inspector'))&Node.DOCUMENT_POSITION_FOLLOWING));
  console.log('Desktop editor layout passed at desktop and mobile widths.');
} finally { await browser.close(); }
