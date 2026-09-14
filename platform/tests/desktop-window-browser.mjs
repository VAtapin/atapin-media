import { chromium } from '../../.local/node_modules/playwright/index.mjs';
import { spawn } from 'node:child_process';
import assert from 'node:assert/strict';

const base = 'http://127.0.0.1:8797';
const server = spawn(process.env.PHP_BINARY || 'php', ['-S','127.0.0.1:8797','-t','.','../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'], {stdio:'pipe',cwd:'public',env:{...process.env,APP_URL:base}});
let output = '', browser;
server.stdout.on('data', data => output += data);
server.stderr.on('data', data => output += data);
try {
  let ready = false;
  for (let i = 0; i < 60; i++) {
    try { if ((await fetch(`${base}/login`, {signal:AbortSignal.timeout(1000)})).ok) { ready = true; break; } } catch (_) {}
    await new Promise(resolve => setTimeout(resolve, 500));
  }
  assert(ready, output);
  browser = await chromium.launch({headless:true, ...(process.env.BROWSER_CHANNEL ? {channel:process.env.BROWSER_CHANNEL} : {})});
  const page = await browser.newPage({viewport:{width:1672,height:941}});
  page.setDefaultTimeout(10000);
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  const login = async () => {
    await page.goto(`${base}/login`);
    await page.locator('[name=email]').fill('test@example.com');
    await page.locator('[name=password]').fill('kurz5');
    await page.locator('form button').click();
    await page.waitForURL('**/desktop');
  };
  const app = id => page.locator(`.os-window[data-app-id="${id}"]`);
  await login();
  await page.locator('[data-close-all]').click();

  await page.goto(`${base}/desktop?open=community&keep=one#retained`);
  await app('community').waitFor({state:'visible'});
  const url = new URL(page.url());
  assert.equal(url.searchParams.has('open'), false, 'Deep-link instruction was not consumed');
  assert.equal(url.searchParams.get('keep'), 'one', 'Unrelated query parameter was removed');
  assert.equal(url.hash, '#retained', 'Fragment was removed');

  await app('community').locator('[data-window-action=close]').click();
  const storageKey = await page.locator('[data-desktop]').getAttribute('data-storage-key');
  assert.deepEqual(await page.evaluate(key => JSON.parse(localStorage.getItem(key)).windows, storageKey), []);
  await page.reload();
  assert.equal(await app('community').count(), 0, 'Closed Community reopened on reload');

  await page.locator('[data-start-button]').click();
  await page.locator('.os-start-menu button[type=submit]').click();
  await page.waitForURL(url => url.pathname !== '/desktop');
  await login();
  assert.equal(await app('community').count(), 0, 'Closed Community reopened after login');

  // A genuinely new deep link must still open the requested app.
  await page.goto(`${base}/desktop?open=community`);
  await app('community').waitFor({state:'visible'});
  await page.reload();
  await app('community').waitFor({state:'visible'});
  await page.locator('[data-close-all]').click();
  await page.reload();
  assert.equal(await page.locator('.os-window').count(), 0, 'Close all did not survive reload');

  // Other apps use the same contract, and unknown app IDs remain harmless.
  await page.goto(`${base}/desktop?open=videos`);
  await app('videos').waitFor({state:'visible'});
  assert.equal(new URL(page.url()).searchParams.has('open'), false);
  await page.goto(`${base}/desktop?open=unknown-app&keep=two`);
  await app('videos').waitFor({state:'visible'});
  assert.equal(await page.locator('.os-window').count(), 1, 'Unknown app altered restored windows');
  assert.equal(new URL(page.url()).searchParams.has('open'), false);
  assert.equal(new URL(page.url()).searchParams.get('keep'), 'two');
  assert.deepEqual(errors, []);
  console.log('Desktop deep links, close/reload/login, saved windows and unknown apps passed.');
} finally {
  if (browser) await browser.close();
  server.kill();
}
