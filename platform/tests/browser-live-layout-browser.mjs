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
  ['/assets/desktop-browser-events.js', 'public/assets/desktop-browser-events.js'],
  ['/assets/desktop-browser-pip.js', 'public/assets/desktop-browser-pip.js'],
  ['/assets/desktop-podcast-recorder.js', 'public/assets/desktop-podcast-recorder.js'],
]);
const html = `<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="layout-test"><link rel="stylesheet" href="/assets/desktop-app.css"><link rel="stylesheet" href="/assets/desktop-live-studio.css"><link rel="stylesheet" href="/assets/desktop-publishing.css"><style>html,body{margin:0;height:100%;background:#edf3f8}.os-window{height:100%}.os-titlebar{box-sizing:border-box;height:44px;padding:12px;background:#102f52;color:white;font:600 14px Inter,Arial,sans-serif}.desktop-live-studio{box-sizing:border-box;height:calc(100% - 44px)}</style></head><body><div class="os-window"><div class="os-titlebar">Live Studio</div><section class="desktop-live-studio" data-live-studio data-user-id="1" data-api-base="/api/desktop/live"><div class="desktop-live-studio-grid"></div></section></div><script>window.DesktopWorkspaces={el:(tag,text,cls)=>{const node=document.createElement(tag);if(text!==undefined)node.textContent=text;if(cls)node.className=cls;return node;}};import('/assets/desktop-browser-studio.js?v=10').then(module=>module.initialize(document.querySelector('[data-live-studio]'),{id:'layout-test',title:'Ein neues Zuhause für Manna Vom Himmel',published:true,enabled:true}));</script></body></html>`;
const configuration = { available: false, browser_enabled: false, host: '' };
let rejectFirstConfig = true;
let browserStartCount = 0, quickCreateCount = 0, lastStartEvent = null, lastCreatedPayload=null;
const scheduled = [
  { id: 'today-one', title:'Erster geplanter Livestream', starts_at:new Date(Date.now()+3600000).toISOString(), status:'scheduled' },
  { id: 'today-two', title:'Zweiter geplanter Livestream', starts_at:new Date(Date.now()+7200000).toISOString(), status:'scheduled' },
];
const server = createServer(async (request, response) => {
  const path = new URL(request.url, base).pathname;
  if (path === '/') { response.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' }); response.end(html); return; }
  if (path === '/api/desktop/live' && request.method === 'GET') { response.writeHead(200, { 'Content-Type':'application/json' });response.end(JSON.stringify({data:scheduled,pagination:{last_page:1,total:scheduled.length}}));return; }
  if (path.startsWith('/api/desktop/live/') && request.method === 'GET') { const selected=scheduled.find(item=>item.id===path.split('/').pop());response.writeHead(selected?200:404,{'Content-Type':'application/json'});response.end(JSON.stringify(selected?{data:selected}:{message:'Event not found'}));return; }
  if (path === '/api/desktop/live' && request.method === 'POST') { let body='';for await(const chunk of request)body+=chunk;quickCreateCount++;const data=JSON.parse(body);lastCreatedPayload=data;response.writeHead(201,{'Content-Type':'application/json'});response.end(JSON.stringify({data:{id:'created-now',...data}}));return; }
  if (path.endsWith('/browser') && request.method === 'POST') {browserStartCount++;lastStartEvent=path.split('/').at(-2);response.writeHead(201,{'Content-Type':'application/json'});response.end(JSON.stringify({id:'mock-session',sdp:'v=0',starts_at:new Date().toISOString()}));return;}
  if (path.endsWith('/heartbeat') && request.method === 'POST') {response.writeHead(200,{'Content-Type':'application/json'});response.end(JSON.stringify({status:'live'}));return;}
  if (path.includes('/sessions/') && request.method === 'DELETE') {response.writeHead(204);response.end();return;}
  if (path === '/desktop/live-studio/server') {
    if (request.method === 'POST') {
      let body = ''; for await (const chunk of request) body += chunk;
      if (rejectFirstConfig) { rejectFirstConfig = false; response.writeHead(503, { 'Content-Type': 'application/json' }); response.end(JSON.stringify({ message: 'Konfiguration scheiterte beim Schritt „private Konfigurationsdatei schreiben“; vorherige Einstellungen wurden wiederhergestellt.' })); return; }
      const values = JSON.parse(body); configuration.browser_enabled = values.live_browser_enabled; configuration.host = values.live_browser_host;
    }
    response.writeHead(200, { 'Content-Type': 'application/json' }); response.end(JSON.stringify({ ...configuration, can_configure: true, can_disconnect: false, labels })); return;
  }
  const file = assets.get(path);
  if (file) { response.writeHead(200, { 'Content-Type': path.endsWith('.js') ? 'text/javascript' : 'text/css' }); response.end(await readFile(file)); return; }
  response.writeHead(404); response.end();
});
await new Promise(resolve => server.listen(8811, '127.0.0.1', resolve));
let browser;
try {
  browser = await chromium.launch({ headless: true, args:['--use-fake-device-for-media-stream','--use-fake-ui-for-media-stream'], ...(process.env.BROWSER_CHANNEL ? { channel: process.env.BROWSER_CHANNEL } : {}) });
  const page = await browser.newPage({ viewport: { width: 1672, height: 941 } });
  page.setDefaultTimeout(20000);
  await page.addInitScript(() => {
    window.uploadDesktopMedia=async(file,userId,onProgress,unused,options)=>{
      if(options?.profile==='poster'){window.posterUploadCount=(window.posterUploadCount||0)+1;return 'poster-media-id';}
      return 'audio-media-id';
    };
    window.RTCPeerConnection=class {
      constructor(){this.iceGatheringState='complete';this.connectionState='new';}
      addTransceiver(){return {setCodecPreferences(){}};}
      async createOffer(){return {type:'offer',sdp:'v=0\nm=video 9 UDP/TLS/RTP/SAVPF 96\nm=audio 9 UDP/TLS/RTP/SAVPF 111\n'};}
      async setLocalDescription(offer){this.localDescription=offer;}
      async setRemoteDescription(){}
      async getStats(){return new Map();}
      close(){this.connectionState='closed';}
    };
  });
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
  const eventChoice=studio.locator('[data-browser-event-choice]');
  await eventChoice.locator('option').nth(3).waitFor({state:'attached'});
  assert.match(await studio.locator('.desktop-browser-selected-event').textContent(), /Livestream auswählen/,'The editor’s first row must not be broadcast automatically');
  assert.equal(await eventChoice.locator('option').count(),4,'Two scheduled events, a placeholder and quick creation');
  await eventChoice.selectOption('today-two');
  await studio.locator('.desktop-browser-selected-event').filter({hasText:'Zweiter geplanter Livestream'}).waitFor();
  assert.match(await studio.locator('.desktop-browser-event-start-hint').textContent(), /demselben Klick gespeichert/);
  assert.equal(await studio.locator('[data-browser-event-form]').count(),0,'Browser start should not require an extra activation checkbox or save action');
  for (const name of ['Kamera & Mikrofon', 'Bild & Bildschirm', 'Audio-Aufnahme', 'Übertragung']) {
    assert(await studio.locator('legend', { hasText: name }).isVisible(), name);
  }
  const canvas = await studio.locator('[data-studio-canvas]').boundingBox();
  assert(canvas && canvas.width <= 320 && canvas.height <= 190, `Preview is too large: ${JSON.stringify(canvas)}`);
  const preview = studio.locator('.desktop-browser-preview');
  const previewAction = studio.locator('[data-studio-action=open_preview]');
  assert(await preview.locator('[data-studio-action=open_preview]').isVisible(), 'Open action should belong to the local preview');
  const previewBox = await preview.boundingBox(), previewActionBox = await previewAction.boundingBox();
  assert(previewBox && previewActionBox && previewActionBox.x + previewActionBox.width <= previewBox.x + previewBox.width + 1,
    `Open action should stay inside the preview card: ${JSON.stringify({ previewBox, previewActionBox })}`);
  await studio.locator('[data-studio-field=scene]').selectOption('screen');
  await studio.locator('[data-studio-field=pip]').check();
  const cameraInset = preview.locator('[data-pip-handle]');
  await cameraInset.waitFor({ state: 'visible' });
  const initialInset = await cameraInset.boundingBox();
  assert(initialInset, 'Camera inset editor should be visible in local preview');
  await page.mouse.move(initialInset.x + initialInset.width / 2, initialInset.y + initialInset.height / 2);
  await page.mouse.down();
  await page.mouse.move(initialInset.x + initialInset.width / 2 - 45, initialInset.y + initialInset.height / 2 - 15, { steps: 5 });
  await page.mouse.up();
  const movedInset = await cameraInset.boundingBox();
  assert(movedInset && movedInset.x < initialInset.x - 25, `Camera inset should move inside the preview: ${JSON.stringify({ initialInset, movedInset })}`);
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
  const popupInset = popup.locator('[data-pip-handle]');
  await popupInset.waitFor({ state: 'visible' });
  const popupBefore = await popupInset.boundingBox(), resizeGrip = await popupInset.locator('[data-pip-resize]').boundingBox();
  assert(popupBefore && resizeGrip, 'The separate preview should retain the camera inset editor');
  await popup.mouse.move(resizeGrip.x + resizeGrip.width / 2, resizeGrip.y + resizeGrip.height / 2);
  await popup.mouse.down();
  await popup.mouse.move(resizeGrip.x + resizeGrip.width / 2 + 35, resizeGrip.y + resizeGrip.height / 2 + 20, { steps: 5 });
  await popup.mouse.up();
  const popupResized = await popupInset.boundingBox();
  assert(popupResized && popupResized.width > popupBefore.width + 12, `Camera inset should resize in the separate preview: ${JSON.stringify({ popupBefore, popupResized })}`);
  const smallerGrip = await popupInset.locator('[data-pip-resize]').boundingBox();
  assert(smallerGrip);
  await popup.mouse.move(smallerGrip.x + smallerGrip.width / 2, smallerGrip.y + smallerGrip.height / 2);
  await popup.mouse.down();
  await popup.mouse.move(smallerGrip.x + smallerGrip.width / 2 - 12, smallerGrip.y + smallerGrip.height / 2 - 8, { steps: 5 });
  await popup.mouse.up();
  const popupReduced = await popupInset.boundingBox();
  assert(popupReduced && popupReduced.width < popupResized.width - 5 && popupReduced.width > popupBefore.width,
    `Camera inset should also shrink in the separate preview: ${JSON.stringify({ popupBefore, popupResized, popupReduced })}`);
  await popup.mouse.move(popupReduced.x + popupReduced.width / 2, popupReduced.y + popupReduced.height / 2);
  await popup.mouse.down();
  await popup.mouse.move(popupReduced.x + popupReduced.width / 2 + 40, popupReduced.y + popupReduced.height / 2 - 10, { steps: 5 });
  await popup.mouse.up();
  const popupMoved = await popupInset.boundingBox();
  assert(popupMoved && popupMoved.x > popupReduced.x + 18, `Camera inset should move in the separate preview: ${JSON.stringify({ popupReduced, popupMoved })}`);
  assert(await studio.locator('.desktop-browser-preview').isHidden(), 'Inline preview should disappear after pop-out');
  assert(await studio.locator('.desktop-browser-heading [data-studio-action=open_preview]').isVisible(), 'Reopen action should remain at the studio heading');
  assert(await studio.locator('.desktop-browser-layout').evaluate(layout => layout.classList.contains('is-detached')));
  assert.match(await studio.locator('[data-studio-action=open_preview]').textContent(), /Vorschaufenster zeigen/);
  const controlsAfter = await studio.locator('.desktop-browser-controls').boundingBox();
  assert(controlsBefore && controlsAfter && controlsAfter.width >= controlsBefore.width + 250, 'Controls should use the released preview column');
  await page.screenshot({ path: 'tests/artifacts/browser-live-layout-detached-desktop.png' });
  await popup.close();
  await studio.locator('.desktop-browser-preview').waitFor({ state: 'visible' });
  assert(await studio.locator('[data-studio-canvas]').isVisible());
  assert(await preview.locator('[data-studio-action=open_preview]').isVisible(), 'Open action should return to the local preview');
  const restoredInset = await cameraInset.boundingBox();
  assert(restoredInset && restoredInset.width > movedInset.width && restoredInset.x > movedInset.x,
    `Camera inset geometry should persist when the separate preview closes: ${JSON.stringify({ movedInset, restoredInset })}`);

  await page.screenshot({ path: 'tests/artifacts/browser-live-layout-desktop.png' });
  await page.setViewportSize({ width: 390, height: 844 });
  await studio.locator('[data-studio-canvas]').scrollIntoViewIfNeeded();
  const mobileCanvas = await studio.locator('[data-studio-canvas]').boundingBox();
  assert(mobileCanvas && mobileCanvas.width <= 390, `Mobile preview overflows: ${JSON.stringify(mobileCanvas)}`);
  assert(await preview.locator('[data-studio-action=open_preview]').isVisible(), 'Mobile open action should belong to the local preview');
  assert(await studio.evaluate(panel => panel.scrollWidth <= panel.clientWidth + 1), 'Studio has horizontal overflow');
  await page.screenshot({ path: 'tests/artifacts/browser-live-layout-mobile.png' });
  const mobilePopupPromise = page.waitForEvent('popup');
  await studio.locator('[data-studio-action=open_preview]').click();
  const mobilePopup = await mobilePopupPromise;
  assert(await studio.locator('.desktop-browser-preview').isHidden(), 'Mobile inline preview should disappear after pop-out');
  assert(await studio.locator('.desktop-browser-heading [data-studio-action=open_preview]').isVisible(), 'Mobile reopen action should remain accessible');
  assert(await studio.evaluate(panel => panel.scrollWidth <= panel.clientWidth + 1), 'Detached mobile studio has horizontal overflow');
  await page.screenshot({ path: 'tests/artifacts/browser-live-layout-detached-mobile.png' });
  await mobilePopup.close();
  await studio.locator('.desktop-browser-preview').waitFor({ state: 'visible' });
  assert(await preview.locator('[data-studio-action=open_preview]').isVisible(), 'Mobile open action should return to the preview');
  await page.setViewportSize({ width: 1672, height: 941 });
  await studio.locator('summary').click();
  await studio.locator('[name=live_browser_host]').fill('mannavomhimmel.de');
  await studio.locator('[name=live_browser_enabled]').check();
  page.on('dialog', dialog => dialog.accept());
  const apply = studio.locator('details form .desktop-button');
  await apply.click();
  const feedback = studio.locator('[data-server-config-feedback]');
  await page.waitForFunction(() => document.querySelector('[data-server-config-feedback]')?.textContent.includes('Nicht gespeichert:'));
  assert.match(await feedback.textContent(), /Nicht gespeichert:.*private Konfigurationsdatei schreiben/);
  assert.equal(await feedback.getAttribute('role'), 'alert');
  assert(await studio.locator('[name=live_browser_enabled]').isChecked(), 'Failed attempt should preserve the unsaved choice in the form');
  assert.equal(configuration.browser_enabled, false, 'Server must not persist a failed attempt');
  await apply.click();
  const result = studio.locator('[data-server-config-result]');
  await result.waitFor({ state: 'visible' });
  assert.match(await result.textContent(), /gespeichert, aber die lokale API antwortet noch nicht/);
  assert(await studio.locator('[name=live_browser_enabled]').isChecked(), 'Saved browser setting should remain checked');
  assert.equal(configuration.host, 'mannavomhimmel.de');
  configuration.available = true;
  await page.reload();
  await root.locator('[data-mode=browser]').click();
  await studio.locator('summary').click();
  await studio.locator('[name=live_browser_enabled]').waitFor();
  assert(await studio.locator('[name=live_browser_enabled]').isChecked(), 'Saved browser setting should survive a page reload');
  assert.equal(await studio.locator('[name=live_browser_host]').inputValue(), 'mannavomhimmel.de');
  assert.match(await studio.textContent(), /Server-API erreichbar/);
  await page.evaluate(async () => {
    const module = await import('/assets/desktop-browser-studio.js?v=10');
    await module.initialize(document.querySelector('[data-live-studio]'), { id: 'next-live', title: 'Nächster Livestream' });
  });
  assert.match(await studio.locator('.desktop-browser-selected-event').textContent(), /Livestream auswählen/);
  await eventChoice.selectOption('today-two');
  await studio.locator('.desktop-browser-selected-event').filter({hasText:'Zweiter geplanter Livestream'}).waitFor();
  await studio.locator('[data-studio-action=start]').click();
  assert.match(await studio.locator('.is-broadcast [role=status]').last().textContent(), /Mikrofon vorbereiten/);
  assert.equal(browserStartCount,0,'No broadcast request should be made before media is prepared');
  await eventChoice.selectOption('new');
  await studio.locator('[data-browser-quick-title]').fill('Sofortige Browser-Sendung');
  await studio.locator('[data-browser-quick-description]').fill('Beschreibung direkt im Studio');
  assert(await studio.locator('[data-browser-quick-poster]').isVisible());
  assert.equal(quickCreateCount,0,'Quick event should be saved by Start, not by changing the selector');
  await studio.locator('[data-studio-field=camera]').selectOption('none');
  await studio.locator('[data-studio-action=prepare]').click();
  await studio.locator('[role=status]').filter({hasText:'Lokale Vorschau bereit'}).waitFor();
  await studio.locator('[data-studio-field=image]').setInputFiles('public/assets/brand/owner/logo-mark.png');
  await studio.locator('[data-studio-field=active_image] option').waitFor({state:'attached'});
  await eventChoice.selectOption('today-two');
  await studio.locator('[data-studio-action=start]').click();
  await studio.locator('[role=status]').filter({hasText:'Live auf der Website'}).waitFor();
  assert.equal(lastStartEvent,'today-two','Browser start must use the explicitly chosen second event');
  assert.equal(quickCreateCount,0,'Selecting a scheduled event must not create a new one');
  const otherTab=await browser.newPage();
  await otherTab.goto('about:blank');await otherTab.bringToFront();
  await page.waitForTimeout(1500);
  assert.match(await studio.locator('.is-broadcast [role=status]').last().textContent(),/Live auf der Website/,'Switching browser tabs must not end the session');
  await page.bringToFront();await otherTab.close();
  await studio.locator('[data-studio-action=stop]').click();
  await eventChoice.selectOption('new');
  await studio.locator('[data-browser-quick-title]').fill('Sofortige Browser-Sendung');
  await studio.locator('[data-browser-quick-description]').fill('Beschreibung direkt im Studio');
  await studio.locator('[data-browser-quick-poster]').setInputFiles('public/assets/brand/owner/logo-mark.png');
  assert(await studio.locator('.desktop-browser-quick-event img').isVisible());
  const quickStart=await studio.locator('[data-studio-action=start]').boundingBox();
  assert(quickStart && quickStart.y+quickStart.height<=941,`Quick-creation Start should remain visible in the maximized desktop window: ${JSON.stringify(quickStart)}`);
  await page.screenshot({path:'tests/artifacts/browser-live-layout-quick-desktop.png'});
  const quickPopupPromise=page.waitForEvent('popup');
  await studio.locator('[data-studio-action=open_preview]').click();
  const quickPopup=await quickPopupPromise;
  assert(await studio.locator('.desktop-browser-preview').isHidden());
  assert(await studio.locator('[data-browser-quick-title]').isVisible(),'Quick fields should remain in the Studio when preview is detached');
  await quickPopup.close();
  assert(await studio.locator('[data-browser-quick-title]').isVisible());
  await page.setViewportSize({width:390,height:844});
  assert(await studio.evaluate(panel=>panel.scrollWidth<=panel.clientWidth+1),'Quick creation has mobile horizontal overflow');
  await page.screenshot({path:'tests/artifacts/browser-live-layout-quick-mobile.png'});
  await page.setViewportSize({width:1672,height:941});
  await studio.locator('[data-studio-action=start]').click();
  await studio.locator('[role=status]').filter({hasText:'Live auf der Website'}).waitFor();
  assert.equal(quickCreateCount,1,'One Start click should create the quick livestream');
  assert.equal(lastStartEvent,'created-now','The quick livestream should be the one started');
  assert.equal(lastCreatedPayload.title,'Sofortige Browser-Sendung');
  assert.equal(lastCreatedPayload.body,'Beschreibung direkt im Studio');
  assert.equal(lastCreatedPayload.cover_media_id,'poster-media-id');
  assert.equal(await page.evaluate(()=>window.posterUploadCount),1);
  assert.equal(await eventChoice.inputValue(),'created-now');
  assert(await studio.locator('.desktop-browser-quick-event').isHidden(),'The quick form should close after the new stream starts');
  await studio.locator('[data-studio-action=stop]').click();
  await studio.locator('summary').click();
  assert(await studio.locator('[name=live_browser_enabled]').isChecked(), 'Saved server setting should apply to a different livestream');
  scheduled.length=0;
  await page.reload();await root.locator('[data-mode=browser]').click();
  await studio.locator('[data-browser-event-choice]').waitFor();
  assert.equal(await studio.locator('[data-browser-event-choice]').inputValue(),'new','No upcoming events should open quick creation automatically');
  assert(await studio.locator('[data-browser-quick-title]').isVisible());
  assert.deepEqual(errors, []);
  console.log('Browser Studio explicit event choice, one-click quick stream with poster, desktop/mobile layout, preview pop-out and persistent server setting passed.');
} finally {
  if (browser) await browser.close();
  await new Promise(resolve => server.close(resolve));
}
