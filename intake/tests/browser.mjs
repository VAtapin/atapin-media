import assert from 'node:assert/strict';

/** Runs only in CI when INTAKE_BROWSER_TESTS=1; the PHP endpoint is real. */
export async function checkBrowser(origin, password) {
  const { chromium } = await import('../../.local/node_modules/playwright/index.mjs');
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(origin);
    await page.locator('#password').waitFor();
    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'login fits mobile');
    await page.locator('#password').fill(password);
    const submitted = page.waitForResponse(response => response.request().method() === 'POST' && response.request().isNavigationRequest());
    await page.getByRole('button', { name: 'Archiv öffnen' }).click();
    const loginResponse = await submitted;
    assert.equal(loginResponse.status(), 303, 'same-origin browser login must succeed: ' + await loginResponse.text().catch(() => 'redirect'));
    await page.locator('#workspace').waitFor({ state: 'visible' });
    assert.equal(await page.locator('input[type=password]').count(), 0);
    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'mobile has no horizontal overflow');
    let lostResponse = false;
    await page.route('**/api.php?action=chunk*', async route => {
      if (!lostResponse) {
        lostResponse = true;
        const response = await route.fetch();
        assert(response.ok());
        await route.abort('failed'); // Server accepted bytes, browser never receives the response.
      } else await route.continue();
    });
    await page.locator('#files').setInputFiles([
      { name: 'Заметки ü <script>.txt', mimeType: 'text/plain', buffer: Buffer.from('Original notes. Kein HTML.') },
      { name: 'recording.mov', mimeType: 'video/quicktime', buffer: Buffer.alloc(4194304 + 1000, 31) },
      { name: 'empty.csv', mimeType: 'text/csv', buffer: Buffer.alloc(0) },
      { name: 'photo.png', mimeType: 'image/png', buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWnQAAAAASUVORK5CYII=', 'base64') },
    ]);
    await page.waitForFunction(() => document.querySelectorAll('#queue .file-state.success').length === 4, null, { timeout: 60000 });
    assert(lostResponse, 'lost-response retry exercised');
    assert.equal(await page.locator('#queue .file-state.error').count(), 0);
    await page.waitForFunction(() => document.querySelectorAll('#recent li').length === 4);
    assert((await page.locator('#queue').innerText()).includes('Заметки ü <script>.txt'), 'filename rendered literally');
    await page.reload();
    await page.locator('#workspace').waitFor({ state: 'visible' });
    assert.equal(await page.locator('#recent li').count(), 4, 'local device history survives reload');
    await page.setViewportSize({ width: 1440, height: 1000 });
    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'desktop has no horizontal overflow');
    assert.deepEqual(errors, [], 'no browser runtime errors');
    await page.getByRole('button', { name: 'Abmelden', exact: true }).click();
    await page.locator('#password').waitFor();
    const denied = await page.request.get(new URL('api.php?action=overview', origin.endsWith('/') ? origin : origin + '/').href, { headers: { 'X-Intake-Request': '1' } });
    assert.equal(denied.status(), 401, 'logout removes API access');
    console.log('Browser checks passed: mobile/desktop, batch of four file types, multi-chunk upload, lost-response recovery, local history, escaped names.');
  } finally {
    await browser.close();
  }
}
