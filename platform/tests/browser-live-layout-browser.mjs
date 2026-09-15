import { chromium } from '../../.local/node_modules/playwright/index.mjs';
import { createServer } from 'node:http';
import { spawnSync } from 'node:child_process';
import { readFile } from 'node:fs/promises';
import assert from 'node:assert/strict';

const php = process.env.PHP_BINARY || 'php';
const translations = spawnSync(php, ['-r', 'echo json_encode(require "lang/de/live-browser.php");'], { encoding: 'utf8' });
assert.equal(translations.status, 0, translations.stderr);
const labels = JSON.parse(translations.stdout);
const base = 'http://127.0.0.1:8811';
const assets = new Map([
  ['/assets/desktop-app.css', 'public/assets/desktop-app.css'],
  ['/assets/desktop-live-studio.css', 'public/assets/desktop-live-studio.css'],
  ['/assets/desktop-publishing.css', 'public/assets/desktop-publishing.css'],
  ['/assets/desktop-browser-studio.css', 'public/assets/desktop-browser-studio.css'],
  ['/assets/desktop-browser-studio.js', 'public/assets/desktop-browser-studio.js'],
]);
const html = `<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="layout-test"><link rel="stylesheet" href="/assets/desktop-app.css"><link rel="stylesheet" href="/assets/desktop-live-studio.css"><link rel="stylesheet" href="/assets/desktop-publishing.css"><style>html,body{margin:0;height:100%;background:#edf3f8}.os-window{height:100%}.os-titlebar{box-sizing:border-box;height:44px;padding:12px;background:#102f52;color:white;font:600 14px Inter,Arial,sans-serif}.desktop-live-studio{box-sizing:border-box;height:calc(100% - 44px)}</style></head><body><div class="os-window"><div class="os-titlebar">Live Studio</div><section class="desktop-live-studio" data-live-studio><div class="desktop-live-studio-grid"></div></section></div><script>window.DesktopWorkspaces={el:(tag,text,cls)=>{const node=document.createElement(tag);if(text!==undefined)node.textContent=text;if(cls)node.className=cls;return node;}};import('/assets/desktop-browser-studio.js?v=4').then(module=>module.initialize(document.querySelector('[data-live-studio]'),{id:'layout-test',title:'Ein neues Zuhause für Manna Vom Himmel'}));</script></body></html>`;
const server = createServer(async (request, response) => {
  const path = new URL(request.url, base).pathname;
  if (path === '/') { response.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' }); response.end(html); return; }
  if (path === '/desktop/live-studio/server') { response.writeHead(200, { 'Content-Type': 'application/json' }); response.end(JSON.stringify({ available: false, browser_enabled: false, can_configure: true, can_disconnect: false, host: '', labels })); return; }
  const file = assets.get(path);
  if (file) { response.writeHead(200, { 'Content-Type': path.endsWith('.js') ? 'text/javascript' : 'text/css' }); response.end(await readFile(file)); return; }
  response.writeHead(404); response.end();
});
await new Promise(resolve => server.listen(8811, '127.0.0.1', resolve));
let browser;
try {
  browser = await chromium.launch({ headless: true, ...(process.env.BROWSER_CHANNEL ? { channel: process.env.BROWSER_CHANNEL } : {}) });
  const page = await browser.newPage({ viewport: { width: 1672, height: 941 } });
  page.setDefaultTimeout(20000);
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.goto(base);
  const root = page.locator('[data-live-studio]');
  await root.locator('[data-mode=browser]').click();
  const studio = root.locator('[data-browser-studio]');
  await studio.locator('.desktop-browser-group').first().waitFor();
  const studioBox = await studio.boundingBox();
  assert(studioBox && studioBox.width >= 1672 * .9, `Studio should use the maximized window: ${JSON.stringify(studioBox)}`);
  assert.equal(await studio.locator('.desktop-browser-group').count(), 4);
  for (const name of ['Kamera & Mikrofon', 'Bild & Bildschirm', 'Audio-Aufnahme', 'Übertragung']) {
    assert(await studio.locator('legend', { hasText: name }).isVisible(), name);
  }
  const canvas = await studio.locator('[data-studio-canvas]').boundingBox();
  assert(canvas && canvas.width <= 320 && canvas.height <= 190, `Preview is too large: ${JSON.stringify(canvas)}`);
  const start = studio.locator('[data-studio-action=start]');
  assert.equal(await start.evaluate(button => getComputedStyle(button).backgroundColor), 'rgb(185, 54, 50)');
  const startBox = await start.boundingBox();
  assert(startBox && startBox.width < 180 && startBox.y + startBox.height <= 941, `Start action should be compact and visible: ${JSON.stringify(startBox)}`);

  await studio.locator('summary').click();
  const host = studio.locator('[name=live_browser_host]');
  assert(await host.isVisible());
  assert.equal(await host.inputValue(), '127.0.0.1');
  assert.match(await studio.textContent(), /ohne https:\/\/, Port oder Pfad/);
  assert.match(await studio.textContent(), /Der Hostname allein behebt diesen Status nicht/);
  assert.match(await studio.textContent(), /Browser-Übertragung ist ausgeschaltet/);
  await studio.locator('summary').click();

  const controlsBefore = await studio.locator('.desktop-browser-controls').boundingBox();
  const popupPromise = page.waitForEvent('popup');
  await studio.locator('[data-studio-action=open_preview]').click();
  const popup = await popupPromise;
  await popup.locator('video').waitFor();
  await popup.waitForFunction(() => document.querySelector('video')?.readyState >= 2);
  assert.equal(await popup.locator('video').evaluate(video => video.srcObject?.getVideoTracks().length), 1);
  assert.match(await popup.title(), /Lokale Vorschau/);
  assert(await studio.locator('.desktop-browser-preview').isHidden(), 'Inline preview should disappear after pop-out');
  assert(await studio.locator('.desktop-browser-layout').evaluate(layout => layout.classList.contains('is-detached')));
  assert.match(await studio.locator('[data-studio-action=open_preview]').textContent(), /Vorschaufenster zeigen/);
  const controlsAfter = await studio.locator('.desktop-browser-controls').boundingBox();
  assert(controlsBefore && controlsAfter && controlsAfter.width >= controlsBefore.width + 250, 'Controls should use the released preview column');
  await page.screenshot({ path: 'tests/artifacts/browser-live-layout-detached-desktop.png' });
  await popup.close();
  await studio.locator('.desktop-browser-preview').waitFor({ state: 'visible' });
  assert(await studio.locator('[data-studio-canvas]').isVisible());

  await page.screenshot({ path: 'tests/artifacts/browser-live-layout-desktop.png' });
  await page.setViewportSize({ width: 390, height: 844 });
  await studio.locator('[data-studio-canvas]').scrollIntoViewIfNeeded();
  const mobileCanvas = await studio.locator('[data-studio-canvas]').boundingBox();
  assert(mobileCanvas && mobileCanvas.width <= 390, `Mobile preview overflows: ${JSON.stringify(mobileCanvas)}`);
  assert(await studio.evaluate(panel => panel.scrollWidth <= panel.clientWidth + 1), 'Studio has horizontal overflow');
  await page.screenshot({ path: 'tests/artifacts/browser-live-layout-mobile.png' });
  const mobilePopupPromise = page.waitForEvent('popup');
  await studio.locator('[data-studio-action=open_preview]').click();
  const mobilePopup = await mobilePopupPromise;
  assert(await studio.locator('.desktop-browser-preview').isHidden(), 'Mobile inline preview should disappear after pop-out');
  assert(await studio.evaluate(panel => panel.scrollWidth <= panel.clientWidth + 1), 'Detached mobile studio has horizontal overflow');
  await page.screenshot({ path: 'tests/artifacts/browser-live-layout-detached-mobile.png' });
  await mobilePopup.close();
  await studio.locator('.desktop-browser-preview').waitFor({ state: 'visible' });
  assert.deepEqual(errors, []);
  console.log('Compact Browser Studio groups, preview, pop-out, server hostname hint and mobile layout passed.');
} finally {
  if (browser) await browser.close();
  await new Promise(resolve => server.close(resolve));
}
