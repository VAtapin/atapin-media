import { chromium } from '../../.local/node_modules/playwright/index.mjs';
import { spawn } from 'node:child_process';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';

assert.equal(process.env.APP_ENV, 'testing');
assert.match(process.env.DB_DATABASE || '', /desktop-workspaces/);
const base = 'http://127.0.0.1:8812';
const server = spawn(process.env.PHP_BINARY || 'php', ['-S', '127.0.0.1:8812', '-t', '.', '../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'], {
  cwd:'public', stdio:'pipe', env:{...process.env, APP_URL:base},
});
let output = '', browser;
server.stdout.on('data', data => output += data);
server.stderr.on('data', data => output += data);

try {
  let ready = false;
  for (let attempt = 0; attempt < 60; attempt++) {
    try { const response = await fetch(base + '/login', {headers:{Connection:'close'}}); await response.text(); if (response.ok) { ready = true; break; } } catch {}
    await new Promise(resolve => setTimeout(resolve, 300));
  }
  assert(ready, output);
  browser = await chromium.launch({headless:true, ...(process.env.BROWSER_CHANNEL ? {channel:process.env.BROWSER_CHANNEL} : {})});
  const page = await browser.newPage({viewport:{width:1672,height:941}});
  page.setDefaultTimeout(15000);
  const errors = [], failures = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('response', response => { if (response.status() >= 400) failures.push(response.url() + ':' + response.status()); });
  await page.goto(base + '/login', {waitUntil:'domcontentloaded', timeout:30000});
  await page.locator('[name=email]').fill('workspace@example.test');
  await page.locator('[name=password]').fill('test-only-password');
  await page.locator('form button').click();
  await page.waitForURL('**/desktop', {waitUntil:'domcontentloaded', timeout:30000});

  await page.evaluate(() => document.querySelector('.os-start-menu [data-open-app=settings]').click());
  const settings = page.locator('.os-window[data-app-id=settings] [data-settings-app]');
  await settings.waitFor();
  await settings.locator('[data-settings-tab=system]').click();
  assert.equal(await settings.locator('a[href*="/desktop/shop/products"]').count(), 0);
  assert.equal(await settings.locator('[data-settings-panel=system] [data-open-app=books-pdf]').count(), 0);
  await page.evaluate(() => document.querySelector('.os-start-menu [data-open-app=books-pdf]').click());

  const books = page.locator('.os-window[data-app-id=books-pdf] [data-workspace=books-pdf]');
  await books.waitFor();
  assert.equal(await books.getAttribute('data-can-moderate-reviews'), 'true');
  await books.locator('[data-book-reviews]').click();
  const dialog = books.locator('[data-book-review-dialog]');
  await dialog.waitFor();
  const card = dialog.locator('.workspace-review-card').filter({hasText:'Browser native book review'});
  await card.waitFor();
  await card.getByText('Neu', {exact:true}).waitFor();
  await card.getByRole('button', {name:'Freigeben'}).click();
  await dialog.locator('.workspace-review-card').filter({hasText:'Browser native book review'}).getByText('Freigegeben', {exact:true}).waitFor();
  await dialog.locator('[name=status]').selectOption('published');
  await dialog.locator('form button[type=submit]').click();
  await dialog.locator('.workspace-review-card').filter({hasText:'Browser native book review'}).waitFor();
  assert.equal(await page.locator('.os-window iframe').count(), 0);
  await fs.mkdir('tests/artifacts', {recursive:true});
  await page.screenshot({path:'tests/artifacts/book-reviews-desktop.png'});
  await page.setViewportSize({width:390,height:844});
  await dialog.locator('.workspace-review-card').filter({hasText:'Browser native book review'}).waitFor();
  await page.screenshot({path:'tests/artifacts/book-reviews-mobile.png'});
  assert.deepEqual(errors, []);
  assert.deepEqual(failures, []);
  console.log('Book reviews: native settings navigation, review filtering/moderation and responsive dialog passed.');
} finally {
  if (browser) await browser.close();
  server.kill();
}
