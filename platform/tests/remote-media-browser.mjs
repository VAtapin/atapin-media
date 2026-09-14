import { chromium } from '../../.local/node_modules/playwright/index.mjs';
import assert from 'node:assert/strict';

// Read-only check: no application records, uploads or files are created.
const url = new URL(process.argv[2]);
assert.equal(url.protocol, 'https:');
const browser = await chromium.launch({headless:true, ...(process.env.BROWSER_CHANNEL ? {channel:process.env.BROWSER_CHANNEL} : {})});
try {
  const page = await browser.newPage();
  const responses = [];
  page.on('response', response => { if(response.url() === url.href) responses.push(response); });
  await page.goto(url.origin + '/');
  const started = Date.now();
  await page.evaluate(src => {
    const video = document.createElement('video');
    video.id = 'playback-check'; video.controls = true; video.muted = true;
    video.src = src; document.body.replaceChildren(video);
    video.play().catch(error => {video.dataset.failure = error.message;});
  }, url.href);
  await page.waitForFunction(() => {
    const video = document.querySelector('#playback-check');
    if(video.error || video.dataset.failure) throw new Error(video.error?.message || video.dataset.failure);
    return video.videoWidth > 0 && video.currentTime > 0.2;
  }, null, {timeout:10000});
  const startMs = Date.now() - started;
  const decoded = await page.locator('video').evaluate(video => ({width:video.videoWidth,height:video.videoHeight,duration:video.duration}));
  assert.equal(decoded.width, 640); assert.equal(decoded.height, 360);
  assert(Math.abs(decoded.duration - 4) < 0.2);
  await page.locator('video').evaluate(video => {video.pause();video.currentTime = 3;});
  await page.waitForFunction(() => {const v=document.querySelector('video');return !v.seeking && Math.abs(v.currentTime-3)<0.1 && v.readyState>=2;}, null, {timeout:10000});
  await page.locator('video').evaluate(video => video.play());
  await page.waitForFunction(() => document.querySelector('video').currentTime>3.2, null, {timeout:5000});
  assert(responses.length>0);
  assert(responses.every(response => [200,206].includes(response.status()) && !response.request().redirectedFrom()));
  console.log(JSON.stringify({url:url.href,start_ms:startMs,...decoded,statuses:responses.map(response=>response.status()),seek_and_resume:'passed'}));
} finally {
  await browser.close();
}
