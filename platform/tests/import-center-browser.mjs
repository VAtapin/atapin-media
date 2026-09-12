import { chromium } from '../../.local/node_modules/playwright/index.mjs';
import { spawn } from 'node:child_process';
import fs from 'node:fs/promises';
import assert from 'node:assert/strict';

const server = spawn(process.env.PHP_BINARY || 'php', ['-S','127.0.0.1:8792','-t','.','../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'], {stdio:'pipe',cwd:'public'});
let output = ''; server.stdout.on('data', data => output += data); server.stderr.on('data', data => output += data);
let browser;
try {
  let ready = false;
  for (let i = 0; i < 60; i++) {
    try { if ((await fetch('http://127.0.0.1:8792/login', {signal:AbortSignal.timeout(1000)})).ok) { ready = true; break; } } catch (_) {}
    await new Promise(resolve => setTimeout(resolve, 500));
  }
  assert(ready, output);
  console.log('Local server ready.');
  browser = await chromium.launch({headless:true, ...(process.env.BROWSER_CHANNEL ? {channel:process.env.BROWSER_CHANNEL} : {})});
  const page = await browser.newPage({viewport:{width:1672,height:941}});
  console.log('Browser ready.');
  const errors = []; page.on('pageerror', error => errors.push(error.message));
  await page.goto('http://127.0.0.1:8792/login');
  console.log('Login:', page.url(), await page.title());
  await page.locator('[name=email]').fill('test@example.com'); await page.locator('[name=password]').fill('kurz5');
  await page.locator('form button').click(); await page.waitForURL('**/desktop');
  await page.locator('[data-close-all]').click();
  await page.locator('[data-open-app="media"]').first().click();
  const media = page.locator('.os-window[data-app-id="media"]');
  await media.locator('[data-media-upload-input]').setInputFiles({name:'browser-original.txt',mimeType:'text/plain',buffer:Buffer.from('Original from browser')});
  await media.locator('[data-library-list]').getByText('browser-original.txt').waitFor();
  await media.locator('[data-library-list]').getByText('browser-original.txt').click();
  await media.locator('[data-library-details] .media-library-download').waitFor();
  const assignment = media.locator('[data-library-details] .content-assignment');
  await assignment.locator('[name=title]').fill('Browser reviewed original');
  await assignment.locator('[name=tags]').fill('Browser, Original');
  await assignment.locator('[name=target_profile]').selectOption('posts');
  await assignment.locator('button[type=submit]').click();
  await media.locator('[data-library-list]').getByText('Browser reviewed original').waitFor();
  await media.locator('[data-library-grid]').click(); assert(await media.locator('[data-library-list]').evaluate(el => el.classList.contains('is-grid')));
  await media.locator('[data-library-content-toggle]').click();
  await media.locator('[data-content-summary]').getByText('Inhalte').waitFor();
  await media.locator('[data-content-list]').getByText('Browser reviewed original').first().click();
  const contentAssignment = media.locator('[data-content-details] .content-assignment');
  await contentAssignment.locator('[name=title]').fill('Browser reviewed post');
  await contentAssignment.locator('[name=body]').fill('Original post text');
  await contentAssignment.locator('[name=status]').selectOption('ready');
  await contentAssignment.locator('button[type=submit]').click();
  await media.locator('[data-content-list]').getByText('Browser reviewed post').first().waitFor();
  await fs.mkdir('tests/artifacts',{recursive:true});
  await page.screenshot({path:'tests/artifacts/import-library-desktop.png'});
  await media.locator('[data-window-action="close"]').click();
  await page.locator('[data-open-app="imports"]').first().click();
  const imports = page.locator('.os-window[data-app-id="imports"]');
  await imports.locator('[data-import-source] option[value="youtube"]').waitFor({state:'attached'});
  await imports.locator('[data-import-source]').selectOption('youtube');
  await imports.locator('[data-import-start]').click();
  await imports.locator('[data-import-message]').getByText('Import gestartet:').waitFor();
  await imports.locator('[data-import-run-list]').getByText('youtube ·').waitFor();
  await page.screenshot({path:'tests/artifacts/import-center-desktop.png'});
  await imports.locator('[data-window-action="close"]').click();
  for (const section of ['videos','posts','community']) {
    await page.locator(`[data-open-app="${section}"]`).first().click();
    const win = page.locator(`.os-window[data-app-id="${section}"]`);
    await win.locator('[data-content-summary]').getByText('Inhalte').waitFor();
    assert.equal(await win.locator('.os-window-content h1, .os-window-content h2').count(),0);
    await win.locator('[data-window-action="close"]').click();
  }
  await page.setViewportSize({width:390,height:844});
  await page.locator('[data-open-app="media"]').first().click();
  await page.locator('.os-window[data-app-id="media"] [data-library-list]').getByText('Browser reviewed original').first().waitFor();
  await page.screenshot({path:'tests/artifacts/import-library-mobile.png'});
  assert.deepEqual(errors,[]);
  console.log('Import Center browser workflow passed.');
} finally {
  await browser?.close(); server.kill();
}
