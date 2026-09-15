import {chromium} from '../../.local/node_modules/playwright/index.mjs';
import assert from 'node:assert/strict';
import {fileURLToPath} from 'node:url';
import path from 'node:path';

const here=path.dirname(fileURLToPath(import.meta.url));
let browser;
try {
  browser=await chromium.launch({headless:true,...(process.env.BROWSER_CHANNEL?{channel:process.env.BROWSER_CHANNEL}:{})});
  const page=await browser.newPage({viewport:{width:1672,height:941}});
  const errors=[];page.on('pageerror',error=>errors.push(error.message));
  await page.setContent(`<html lang="de"><head><meta name="csrf-token" content="test"></head><body>
    <div class="os-window" data-app-id="settings"><section data-settings-app data-settings-direct data-active-section="system">
      <p data-settings-notice hidden></p><section data-settings-panel="system"><form action="/desktop/settings">
        <input name="section" type="hidden" value="system"><select name="legal_locale" data-legal-locale><option value="de">DE</option><option value="en">EN</option></select>
        <label>Über uns<textarea name="about_text" data-rich-text data-legal-editor></textarea></label>
        <label>Mission<textarea name="mission_text" data-rich-text data-legal-editor></textarea></label>
        <button type="submit">Speichern</button>
      </form></section>
    </section></div></body></html>`);
  await page.evaluate(()=>{
    document.querySelector('[data-legal-locale]').dataset.legalDocuments=JSON.stringify({
      de:{about_text:'<h2>Deutsch</h2>',mission_text:'<p>Mission DE</p>'},
      en:{about_text:'<h2>English</h2>',mission_text:'<p>Mission EN</p>'},
    });
    window.fetch=async(_url,options)=>{
      window.__richSent=Object.fromEntries(options.body);
      return Response.json({status:'saved',section:'system'});
    };
  });
  await page.addStyleTag({path:path.join(here,'../public/vendor/jodit/4.13.9/jodit.min.css')});
  await page.addScriptTag({path:path.join(here,'../public/vendor/jodit/4.13.9/jodit.min.js')});
  await page.addScriptTag({path:path.join(here,'../public/assets/desktop-rich-text.js')});
  await page.addScriptTag({path:path.join(here,'../public/assets/settings-tabs.js')});
  await page.locator('.jodit-container').first().waitFor();
  assert.equal(await page.locator('.jodit-container').count(),2);
  assert.match(await page.locator('[name="about_text"]').inputValue(),/Deutsch/);
  await page.locator('[name="legal_locale"]').selectOption('en');
  assert.match(await page.locator('[name="about_text"]').inputValue(),/English/);
  await page.locator('[name="about_text"]').evaluate(node=>window.DesktopRichText.setValue(node,'<p><strong>Edited English</strong></p>'));
  await page.locator('[name="legal_locale"]').selectOption('de');
  assert.match(await page.locator('[name="about_text"]').inputValue(),/Deutsch/);
  await page.locator('[name="legal_locale"]').selectOption('en');
  assert.match(await page.locator('[name="about_text"]').inputValue(),/Edited English/);
  await page.locator('button[type="submit"]').click();
  await page.waitForFunction(()=>Boolean(window.__richSent));
  const sent=await page.evaluate(()=>window.__richSent);
  assert.match(sent.about_text,/<strong>Edited English<\/strong>/);
  assert.deepEqual(errors,[]);
  const article=await browser.newPage({viewport:{width:1672,height:941}});
  const articleErrors=[];article.on('pageerror',error=>articleErrors.push(error.message));
  await article.setContent('<html lang="de"><head><meta name="csrf-token" content="test"></head><body><section data-content-library><div data-content-details></div></section></body></html>');
  await article.evaluate(()=>{
    window.desktopWorkspaceLabels={body:'Text',save:'Speichern',content_report:'Weitere Einstellungen',preview:'Vorschau'};
    window.desktopImportLabels={title:'Titel',body:'Text',kind:'Art',status:'Status',tags:'Tags',save:'Speichern',ai_classify:'Mit KI einordnen',target_profile:'Ziel'};
    window.fetch=async(url,options={})=>{
      if(String(url).startsWith('/desktop/lookups'))return Response.json({data:[]});
      if(String(url)==='/desktop/content/1'&&options.method==='PATCH'){
        window.__articleSent=JSON.parse(options.body);return Response.json({status:'saved'});
      }
      throw new Error('Unexpected request '+url);
    };
  });
  await article.addStyleTag({path:path.join(here,'../public/vendor/jodit/4.13.9/jodit.min.css')});
  await article.addScriptTag({path:path.join(here,'../public/vendor/jodit/4.13.9/jodit.min.js')});
  await article.addScriptTag({path:path.join(here,'../public/assets/desktop-rich-text.js')});
  await article.addScriptTag({path:path.join(here,'../public/assets/desktop-workspaces.js')});
  await article.addScriptTag({path:path.join(here,'../public/assets/desktop-editor-workflow.js')});
  await article.addScriptTag({path:path.join(here,'../public/assets/desktop-content-assignment.js')});
  await article.evaluate(()=>window.appendContentAssignment(document.querySelector('[data-content-details]'),'record',{
    id:1,title:'Artikel',body:'Alter Text',kind:'post',status:'ready',body_format:'plain',tags:[],taxonomy_term_ids:[],
  }));
  await article.locator('.jodit-container').waitFor();
  assert.equal(await article.locator('.workspace-rich').count(),0,'homemade contenteditable editor is removed');
  assert.equal(await article.locator('[name="body_format"]').inputValue(),'html');
  await article.locator('[name="body"]').evaluate(node=>window.DesktopRichText.setValue(node,'<p><strong>Formatted article</strong></p>'));
  await article.locator('.content-assignment button[type="submit"]').click();
  await article.waitForFunction(()=>Boolean(window.__articleSent));
  const articleSent=await article.evaluate(()=>window.__articleSent);
  assert.match(articleSent.body,/<strong>Formatted article<\/strong>/);
  assert.equal(articleSent.body_format,'html');
  assert.deepEqual(articleErrors,[]);
  await article.close();
  console.log('Jodit settings, article body, locale switching and formatted submit passed.');
} finally {
  if(browser)await browser.close();
}
