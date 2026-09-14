import { chromium } from '../../.local/node_modules/playwright/index.mjs';
import assert from 'node:assert/strict';

// Isolate Publishing controls with mocked transport; no platform is contacted.
const browser = await chromium.launch({headless:true, ...(process.env.BROWSER_CHANNEL ? {channel:process.env.BROWSER_CHANNEL} : {})});
try {
  const page = await browser.newPage();
  const errors = [], submissions = [], outputSaves = [], removals = [];
  let outputs = [{provider:'rtmp_live',label:'Other Live',connected:true,capabilities:{live:true}}];
  page.on('pageerror', error => errors.push(error.message));
  let publications = [{id:1,record_id:1,title:'Video',provider:'youtube',status:'queued'}];
  const data = () => ({
    records:[{id:1,title:'Video',kind:'video',publishing_targets:[]},{id:2,title:'Post',kind:'post'},{id:3,title:'Live',kind:'live',publishing_targets:['website','rtmp_live']}],
    destinations:[
      {provider:'website',label:'Website',connected:true},
      {provider:'youtube',label:'YouTube',connected:true,capabilities:{video:true,live:true,post:true}},
      {provider:'instagram',label:'Instagram',connected:true,capabilities:{video:true,post:true,live:false}},
      {provider:'facebook',label:'Facebook',connected:true,revoked:true,capabilities:{video:true}},
      ...outputs,
    ],
    publications, youtube:{configured:true},
  });
  await page.route('http://publishing.test/**', async route => {
    const request = route.request();
    if (request.url().endsWith('/api')) return route.fulfill({json:data()});
    if (request.url().endsWith('/publish')) { submissions.push(request.postDataJSON()); return route.fulfill({json:{status:'queued'}}); }
    if (request.url().endsWith('/live-outputs')) { const value=request.postDataJSON(); outputSaves.push(value); outputs.push({provider:value.id,label:value.label,connected:true,capabilities:{live:true}}); return route.fulfill({json:{status:'saved'}}); }
    if (request.method()==='DELETE') { removals.push({url:request.url(),body:request.postDataJSON()}); if(request.url().includes('/live-outputs/'))outputs=outputs.filter(item=>!request.url().endsWith(item.provider)); return route.fulfill({json:{status:'queued'}}); }
    return route.fulfill({contentType:'text/html',body:`<meta name="csrf-token" content="test-only"><div id="publishing" data-api-url="/api" data-publish-url="/publish"><select data-publishing-record></select><div data-publishing-destinations></div><p data-publishing-empty></p><div data-publishing-status-list></div><p data-publishing-feedback hidden></p><p data-publishing-connection-status></p><button data-publishing-submit>Publish</button><form data-live-output-form><input name="id" required><input name="label" required><input name="url" type="password" required><button type="submit">Save</button></form><div data-live-output-list></div></div>`});
  });
  await page.goto('http://publishing.test/');
  await page.evaluate(() => { window.desktopPublishingLabels = {youtube_connected:'YouTube connected', relaying:'Live signal', published:'Published',remove_remote:'Delete on platform',confirm_remove_remote:'Delete permanently?',remove_output:'Remove destination'}; });
  await page.addScriptTag({path:'public/assets/desktop-publishing.js'});
  await page.evaluate(() => {
    const input = document.createElement('input');
    input.type = 'checkbox';
    input.dataset.removeOnUnpublish = '';
    document.querySelector('#publishing').append(input);
  });
  await page.evaluate(() => window.initializePublishing(document.querySelector('#publishing')));
  const select = page.locator('[data-publishing-record]');
  await page.waitForFunction(() => document.querySelector('[data-publishing-record]').options.length === 3);
  assert.equal(await page.locator('input[value="facebook"]').count(), 0);
  assert(!(await page.locator('input[value="website"]').isChecked()));
  assert(!(await page.locator('input[value="youtube"]').isChecked()));
  await select.selectOption('2');
  assert(!(await page.locator('input[value="youtube"]').isDisabled()));
  assert(await page.locator('input[value="youtube"]').isChecked());
  assert(await page.locator('input[value="rtmp_live"]').isDisabled());
  await page.locator('input[value="instagram"]').check();
  assert(!(await page.locator('[data-remove-on-unpublish]').isChecked()));
  await page.locator('[data-remove-on-unpublish]').check();
  publications = [{id:1,record_id:1,title:'Video',provider:'youtube',status:'published',remote_status:'relaying'}];
  await page.waitForFunction(() => document.querySelector('[data-publishing-status-list]').textContent.includes('Live signal'),null,{timeout:10000});
  assert.equal(await select.inputValue(), '2');
  assert(await page.locator('input[value="instagram"]').isChecked());
  await page.locator('[data-publishing-submit]').click();
  await page.waitForFunction(() => !document.querySelector('[data-publishing-feedback]').hidden);
  assert.deepEqual(submissions, [{record_id:'2',destinations:['website','youtube','instagram'],remove_external_on_unpublish:true}]);
  await select.selectOption('3');
  assert(!(await page.locator('[data-remove-on-unpublish]').isChecked()));
  assert(!(await page.locator('input[value="youtube"]').isDisabled()));
  assert(!(await page.locator('input[value="youtube"]').isChecked()));
  assert(await page.locator('input[value="instagram"]').isDisabled());
  assert(!(await page.locator('input[value="rtmp_live"]').isDisabled()));
  assert(await page.locator('input[value="rtmp_live"]').isChecked());
  await page.locator('[name="id"]').fill('rtmp_second');
  await page.locator('[name="label"]').fill('Second <Live>');
  await page.locator('[name="url"]').fill('rtmps://example.test/live/secret-key');
  await page.locator('[data-live-output-form] button').click();
  await page.waitForFunction(() => document.querySelector('[data-live-output-list]').textContent.includes('Second <Live>'));
  assert.equal(await page.locator('[name="url"]').inputValue(),'');
  assert.equal(outputSaves.length,1);
  assert(!(await page.locator('[data-live-output-list]').innerHTML()).includes('secret-key'));
  await page.locator('[data-remove-output="rtmp_second"]').click();
  await page.waitForFunction(() => !document.querySelector('[data-remove-output="rtmp_second"]'));
  publications=[{id:1,record_id:1,title:'Video',provider:'youtube',status:'published',remote_status:'public',can_remove:true}];
  await page.waitForSelector('[data-publishing-remove]');
  page.once('dialog',dialog=>dialog.dismiss());
  await page.locator('[data-publishing-remove]').click();
  assert.equal(removals.length,1);
  page.once('dialog',dialog=>dialog.accept());
  await page.locator('[data-publishing-remove]').click();
  await page.waitForFunction(() => !document.querySelector('[data-publishing-feedback]').classList.contains('is-error'));
  await new Promise(resolve=>setTimeout(resolve,100));
  assert.deepEqual(removals[1].body,{confirm:true});
  assert.deepEqual(errors, []);
  console.log('Publishing browser: adapted posts, Live-only RTMP controls, encrypted-output editor, explicit remote deletion, status refresh and preserved selections passed.');
} finally {
  await browser.close();
}
