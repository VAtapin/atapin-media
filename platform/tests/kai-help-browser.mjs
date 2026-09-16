import { chromium } from '../../.local/node_modules/playwright/index.mjs';
import { spawn } from 'node:child_process';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';

assert.equal(process.env.APP_ENV, 'testing');
assert.match(process.env.DB_DATABASE || '', /desktop-workspaces/);
const base = 'http://127.0.0.1:8808';
const server = spawn(process.env.PHP_BINARY || 'php', ['-S', '127.0.0.1:8808', '-t', '.', '../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'], { cwd: 'public', env: { ...process.env, APP_URL: base }, stdio: 'pipe' });
let output = '', browser;
server.stdout.on('data', data => output += data);
server.stderr.on('data', data => output += data);

try {
  let ready = false;
  for (let i = 0; i < 60; i++) {
    try { const response = await fetch(base + '/login'); await response.text(); if (response.ok) { ready = true; break; } } catch {}
    await new Promise(resolve => setTimeout(resolve, 200));
  }
  assert(ready, output);
  browser = await chromium.launch({ headless: true, ...(process.env.BROWSER_CHANNEL ? { channel: process.env.BROWSER_CHANNEL } : {}) });
  const page = await browser.newPage({ viewport: { width: 1672, height: 941 } });
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.goto(base + '/login');
  await page.locator('[name=email]').fill('workspace@example.test');
  await page.locator('[name=password]').fill('test-only-password');
  await page.locator('form button').click();
  await page.waitForURL('**/desktop');
  const trigger = page.locator('[data-ai-chat]');
  const popup = page.locator('[data-ai-global-dialog]');

  await trigger.click();
  await popup.waitFor({ state: 'visible' });
  assert.equal(await popup.evaluate(node => node.matches(':modal')), false);
  let box = await popup.boundingBox();
  assert(box && box.width <= 390 && box.x >= 1200 && box.y > 100);
  await fs.mkdir('tests/artifacts', { recursive: true });
  await page.screenshot({ path: 'tests/artifacts/kai-help-desktop.png' });
  await popup.locator('textarea').fill('Wo finde ich Bücher?');
  await trigger.click();
  assert.equal(await popup.evaluate(node => node.open), false);

  await trigger.click();
  await page.locator('body').click({ position: { x: 600, y: 150 } });
  assert.equal(await popup.evaluate(node => node.open), false);
  await trigger.click();
  await popup.locator('.ai-dashboard-help-close').click();
  assert.equal(await popup.evaluate(node => node.open), false);

  await page.setViewportSize({ width: 390, height: 844 });
  await trigger.click();
  await popup.waitFor({ state: 'visible' });
  box = await popup.boundingBox();
  assert(box && box.x >= 0 && box.x + box.width <= 390 && box.y + box.height <= 844);
  await page.screenshot({ path: 'tests/artifacts/kai-help-mobile.png' });
  assert.deepEqual(errors, []);
  console.log('KAI help popup: desktop/mobile placement, non-modal display, trigger/outside/X close passed.');
} finally {
  if (browser) await browser.close();
  server.kill();
}
