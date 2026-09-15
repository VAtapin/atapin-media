import { chromium } from '../../.local/node_modules/playwright/index.mjs';
import { fileURLToPath } from 'node:url';
import assert from 'node:assert/strict';

const browser=await chromium.launch({headless:true,...(process.env.BROWSER_CHANNEL?{channel:process.env.BROWSER_CHANNEL}:{})});
try {
  const page=await browser.newPage();
  await page.setContent('<html lang="de"><head><meta name="csrf-token" content="browser-token"></head><body><div id="editor-details"></div></body></html>');
  await page.evaluate(() => {
    window.desktopImportLabels={
      import_versions:'Gespeicherte Import-Versionen',import_versions_hint:'Originaltexte der Importe.',
      import_version_delete:'Diese Import-Version löschen',
      import_version_delete_confirm:'Nur diese gespeicherte Import-Version löschen?',
      import_version_deleted:'Import-Version gelöscht.',
      import_version_save_first:'Bitte aktuelle Änderungen zuerst speichern.',
      load_error:'Laden fehlgeschlagen',
    };
    window.confirm=()=>true;
    window.__requests=[];
    window.fetch=(url,options={})=>{
      window.__requests.push({url:String(url),method:options.method||'GET',body:options.body&&JSON.parse(options.body)});
      return new Promise(resolve=>setTimeout(()=>resolve({ok:true,json:async()=>({title:'Old copy',body:'Outdated body',metadata:{}})}),options.method==='DELETE'?0:80));
    };
  });
  await page.addScriptTag({path:fileURLToPath(new URL('../public/assets/desktop-import-versions.js',import.meta.url))});
  await page.evaluate(() => document.querySelector('#editor-details').dispatchEvent(new CustomEvent('content-selected',{
    bubbles:true,detail:{import_versions:[
      {title:'Copy 1',url:'/desktop/content/1/imports/1',delete_url:'/desktop/content/1/imports/1',created_at:'2026-09-15T10:00:00Z'},
      {title:'Copy 2',url:'/desktop/content/1/imports/2',delete_url:'/desktop/content/1/imports/2',created_at:'2026-09-16T10:00:00Z'},
    ]},
  })));
  const panel=page.locator('#editor-details > details');
  await panel.locator('summary').click();
  assert.equal(await panel.locator('[data-import-version-delete]').count(),2);
  assert.equal(await panel.locator('.content-import-version-row > small').count(),2);

  await page.locator('#editor-details').evaluate(node => {node.dataset.dirty='true';});
  await panel.locator('[data-import-version-delete]').first().click();
  await panel.getByText('Bitte aktuelle Änderungen zuerst speichern.',{exact:true}).waitFor();
  assert.equal(await page.evaluate(() => window.__requests.length),0);

  await page.locator('#editor-details').evaluate(node => {delete node.dataset.dirty;});
  await panel.locator('[data-import-version]').first().click();
  await panel.locator('[data-import-version-delete]').first().click();
  await panel.getByText('Import-Version gelöscht.',{exact:true}).waitFor();
  assert.equal(await panel.locator('.content-import-version-row').count(),1);
  await page.waitForTimeout(100);
  assert.equal(await panel.locator('[data-import-version-body]').textContent(),'');
  const requests=await page.evaluate(() => window.__requests);
  assert.deepEqual(requests[1],{url:'/desktop/content/1/imports/1',method:'DELETE',body:{confirmation:'DELETE'}});
  await panel.locator('[data-import-version-delete]').click();
  await panel.getByText('Import-Version gelöscht.',{exact:true}).waitFor();
  assert.equal(await panel.locator('.content-import-version-row').count(),0);
  console.log('Import-version controls passed.');
} finally { await browser.close(); }
