import { chromium } from '../../.local/node_modules/playwright/index.mjs';
import assert from 'node:assert/strict';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const here = path.dirname(fileURLToPath(import.meta.url));
let browser;

try {
  browser = await chromium.launch({headless: true, ...(process.env.BROWSER_CHANNEL ? {channel: process.env.BROWSER_CHANNEL} : {})});
  const page = await browser.newPage({viewport: {width: 1672, height: 941}});
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.setContent(`
    <meta name="csrf-token" content="test">
    <section class="desktop-content-library is-podcast-workspace" data-content-library data-section="podcast" data-can-edit="true" data-can-media-edit="true" data-can-upload="true" data-user-id="1">
      <button type="button" data-content-new>Neue Episode</button>
      <div class="media-library-layout"><div class="media-library-list"></div><div class="media-library-details" data-content-details></div></div>
    </section>
  `);
  await page.addScriptTag({content: `
    window.desktopImportLabels = {
      podcast_new_episode:'Neue Podcast-Episode',podcast_eyebrow:'Podcast · Episode',podcast_back:'Zur Episodenliste',
      podcast_description:'Beschreibung',podcast_media:'Audio-/Video-Datei',podcast_cover:'Cover',podcast_publish_date:'Veröffentlichungsdatum',
      podcast_format:'Format',podcast_format_audio:'Audio',podcast_format_video:'Video-Podcast',podcast_more:'Weitere Einstellungen',
      podcast_website:'Website-Zuordnung',podcast_editorial:'Episodendaten',podcast_technical:'Technische Daten',podcast_materials:'Materialien',
      podcast_ai:'KI-Assistent',podcast_ai_classify:'Episode einordnen',podcast_ai_summary:'Kurztext erstellen',
      podcast_ai_proposal:'Text / SEO vorschlagen',podcast_ai_structure:'Beschreibung strukturieren',podcast_ai_cover:'Cover erstellen',
      podcast_no_file:'Noch keine Datei verknüpft.',podcast_media_hint:'Datei auswählen',podcast_cover_hint:'Cover auswählen',save:'Speichern',
      project_id:'Projekt',taxonomy_term_ids:'Kategorien',title:'Titel',status:'Status',source:'Quelle',source_manual:'Manuell',
      public_published:'Veröffentlichen',public_published_hint:'Hinweis',episode_number:'Episodennummer',season:'Staffel',author:'Autor',guest:'Gast',
      external_podcast_url:'Externe Adresse',transcript:'Transkript',seo_title:'SEO-Titel',seo_description:'SEO-Beschreibung'
    };
    let separateWindows = 0;
    window.openDesktopProgram = () => { separateWindows++; };
    const el=(tag,text,cls)=>{const node=document.createElement(tag);if(text!==undefined)node.textContent=text;if(cls)node.className=cls;return node;};
    const field=(name,type='text',value='',options=[])=>{const label=el('label',name);let input;if(type==='select'){input=el('select');for(const option of options){const pair=Array.isArray(option)?option:[option,option];input.add(new Option(pair[1],pair[0]));}}else if(type==='textarea'){input=el('textarea');}else{input=el('input');input.type=type;}input.name=name;input.value=value||'';label.append(input);return label;};
    window.DesktopWorkspaces={t:key=>key,el,field,lookup:async()=>{},button:(key,fn)=>{const b=el('button',key);b.type='button';b.onclick=fn;return b;},request:async()=>({}),open:()=>{}};
    window.__separateWindows=()=>separateWindows;
  `});
  await page.addScriptTag({path: path.join(here, '../public/assets/desktop-editor-workflow.js')});
  await page.locator('[data-content-new]').click();
  const editor = page.locator('[data-podcast-episode]');
  await editor.waitFor();
  assert.equal(await page.evaluate(() => window.__separateWindows()), 0);
  for (const name of ['title','body','podcast_file','cover_file','public_published_at','status','podcast_format'])
    assert.equal(await editor.locator(`[name="${name}"]`).count(), 1, `${name} must be a primary episode field`);
  assert.equal(await editor.locator('[name="kind"]').count(), 0, 'Generic content type must not be shown');
  assert.equal(await editor.locator('[data-podcast-more]').evaluate(node => node.open), false);
  assert.equal(await editor.locator('.podcast-ai-menu').count(), 1);
  assert.equal(await editor.locator('.podcast-ai-menu button').count(), 5);
  await editor.locator('[name="podcast_format"]').selectOption('video');
  assert.equal(await editor.locator('[name="podcast_file"]').getAttribute('accept'), 'video/*');

  await page.addStyleTag({path: path.join(here, '../public/assets/desktop-media-library.css')});
  let tracks = await page.locator('.media-library-layout').evaluate(node => getComputedStyle(node).gridTemplateColumns);
  assert.equal(tracks.trim().split(/\s+/).length, 2, 'Podcast uses two columns at desktop width');
  await page.setViewportSize({width: 600, height: 800});
  tracks = await page.locator('.media-library-layout').evaluate(node => getComputedStyle(node).gridTemplateColumns);
  assert.equal(tracks.trim().split(/\s+/).length, 1, 'Podcast collapses to one column');

  await page.setViewportSize({width: 1672, height: 941});
  await page.setContent(`<section style="width:1500px"><div class="desktop-live-studio"><header class="desktop-live-studio-head"></header><div class="desktop-live-studio-grid"><aside class="desktop-live-events"></aside><main class="desktop-live-editor"><div class="desktop-live-poster"><div></div><div class="desktop-live-poster-drop"><div class="desktop-live-poster-preview"></div></div></div></main></div></div></section>`);
  await page.addStyleTag({path: path.join(here, '../public/assets/desktop-live-studio.css')});
  const live = await page.evaluate(() => ({
    head: document.querySelector('.desktop-live-studio-head').getBoundingClientRect().width,
    grid: getComputedStyle(document.querySelector('.desktop-live-studio-grid')).gridTemplateColumns,
    preview: document.querySelector('.desktop-live-poster-preview').getBoundingClientRect().toJSON(),
  }));
  assert(live.head > 1400, 'Live Studio header must use the available width');
  assert.equal(live.grid.trim().split(/\s+/).length, 2, 'Live Studio uses two columns at desktop width');
  assert.equal(Math.round(live.preview.width), 88);
  assert.equal(Math.round(live.preview.height), 56);
  await page.setViewportSize({width: 800, height: 800});
  tracks = await page.locator('.desktop-live-studio-grid').evaluate(node => getComputedStyle(node).gridTemplateColumns);
  assert.equal(tracks.trim().split(/\s+/).length, 1, 'Live Studio collapses to one column');
  assert.deepEqual(errors, []);
  console.log('Podcast inline editor and adaptive Live Studio layout passed.');
} finally {
  if (browser) await browser.close();
}
