import { chromium } from '../../.local/node_modules/playwright/index.mjs';
import assert from 'node:assert/strict';

// Isolate Publishing controls with mocked transport; no platform is contacted.
const browser = await chromium.launch({headless:true, ...(process.env.BROWSER_CHANNEL ? {channel:process.env.BROWSER_CHANNEL} : {})});
try {
  const page = await browser.newPage();
  const errors = [], submissions = [];
  page.on('pageerror', error => errors.push(error.message));
  let publications = [{id:1,record_id:1,title:'Video',provider:'youtube',status:'queued'}];
  const data = () => ({
    records:[{id:1,title:'Video',kind:'video'},{id:2,title:'Post',kind:'post'},{id:3,title:'Live',kind:'live'}],
    destinations:[
      {provider:'website',label:'Website',connected:true},
      {provider:'youtube',label:'YouTube',connected:true,capabilities:{video:true,live:true,post:false}},
      {provider:'instagram',label:'Instagram',connected:true,capabilities:{video:true,post:true,live:false}},
      {provider:'facebook',label:'Facebook',connected:true,revoked:true,capabilities:{video:true}},
    ],
    publications, youtube:{configured:true},
  });
  await page.route('http://publishing.test/**', async route => {
    const request = route.request();
    if (request.url().endsWith('/api')) return route.fulfill({json:data()});
    if (request.url().endsWith('/publish')) { submissions.push(request.postDataJSON()); return route.fulfill({json:{status:'queued'}}); }
    return route.fulfill({contentType:'text/html',body:`<meta name="csrf-token" content="test-only"><div id="publishing" data-api-url="/api" data-publish-url="/publish"><select data-publishing-record></select><div data-publishing-destinations></div><p data-publishing-empty></p><div data-publishing-status-list></div><p data-publishing-feedback hidden></p><p data-publishing-connection-status></p><button data-publishing-submit>Publish</button></div>`});
  });
  await page.goto('http://publishing.test/');
  await page.evaluate(() => { window.desktopPublishingLabels = {youtube_connected:'YouTube connected', relaying:'Live signal', published:'Published'}; });
  await page.addScriptTag({path:'public/assets/desktop-publishing.js'});
  await page.evaluate(() => window.initializePublishing(document.querySelector('#publishing')));
  const select = page.locator('[data-publishing-record]');
  await page.waitForFunction(() => document.querySelector('[data-publishing-record]').options.length === 3);
  assert.equal(await page.locator('input[value="facebook"]').count(), 0);
  await select.selectOption('2');
  assert(await page.locator('input[value="youtube"]').isDisabled());
  assert(!(await page.locator('input[value="youtube"]').isChecked()));
  await page.locator('input[value="instagram"]').check();
  publications = [{id:1,record_id:1,title:'Video',provider:'youtube',status:'published',remote_status:'relaying'}];
  await page.waitForFunction(() => document.querySelector('[data-publishing-status-list]').textContent.includes('Live signal'),null,{timeout:10000});
  assert.equal(await select.inputValue(), '2');
  assert(await page.locator('input[value="instagram"]').isChecked());
  await page.locator('[data-publishing-submit]').click();
  await page.waitForFunction(() => !document.querySelector('[data-publishing-feedback]').hidden);
  assert.deepEqual(submissions, [{record_id:'2',destinations:['website','instagram']}]);
  await select.selectOption('3');
  assert(!(await page.locator('input[value="youtube"]').isDisabled()));
  assert(await page.locator('input[value="instagram"]').isDisabled());
  assert.deepEqual(errors, []);
  console.log('Publishing browser: capability checkboxes, disconnected destinations, live status refresh and preserved selections passed.');
} finally {
  await browser.close();
}
