import assert from 'node:assert/strict';
import fs from 'node:fs/promises';

export async function checkDesktopShortcuts(page, { artifacts = 'tests/artifacts', reenter } = {}) {
  await fs.mkdir(artifacts, { recursive:true });
  await page.setViewportSize({ width:1672, height:941 });
  const shortcut = id => page.locator(`.os-shortcuts [data-open-app="${id}"]`);
  const app = id => page.locator(`.os-start-menu [data-open-app="${id}"]`);
  const command = id => page.locator(`[data-shortcut-command="${id}"]`);
  const storageKey = await page.locator('[data-desktop]').getAttribute('data-storage-key');
  const saved = () => page.evaluate(key => JSON.parse(localStorage.getItem(`${key}.shortcuts`)), storageKey);
  const dragTo = async (locator, x, y, cancel = false) => {
    const box = await locator.boundingBox();
    await page.mouse.move(box.x + 30, box.y + 30);
    await page.mouse.down();
    await page.mouse.move(x, y, { steps:12 });
    if (cancel) await page.keyboard.press('Escape');
    await page.mouse.up();
  };
  const catalogue = await page.locator('.os-start-menu [data-open-app]').evaluateAll(items => items.map(item => item.dataset.openApp));
  const initialCount = await page.locator('.os-shortcut').count();
  assert.equal(catalogue.length, 19);
  await page.evaluate(() => Promise.all([...document.querySelectorAll('.os-shortcut img,.os-program-grid img')].map(image => image.decode())));
  assert(await app('videos').getAttribute('data-app-icon').then(path => path.endsWith('/desktop/manna/Videos.png')));
  await page.screenshot({ path:`${artifacts}/desktop-shortcuts.png` });

  await dragTo(shortcut('videos'), 735, 337);
  assert.equal(await page.locator('.os-window').count(), 0, 'Dragging opened an application');
  let video = await shortcut('videos').boundingBox();
  assert.equal(video.x, 705);
  assert.equal(video.y, 307);
  const custom = await saved();
  await page.reload();
  assert.equal((await shortcut('videos').boundingBox()).x, 705);
  assert.deepEqual(await saved(), custom);
  await dragTo(shortcut('videos'), 900, 450, true);
  assert.equal((await shortcut('videos').boundingBox()).x, 705, 'Escape changed the position');
  assert.equal(await page.locator('.os-window').count(), 0, 'Cancelled drag opened an application');
  await dragTo(shortcut('videos'), 1100, 933);
  assert.equal((await shortcut('videos').boundingBox()).x, 705, 'Drop on taskbar changed the position');

  await shortcut('videos').click();
  await page.locator('.os-window [data-window-action="minimize"]').click();
  await shortcut('videos').click({ button:'right' });
  await command('remove').click();
  assert.equal(await shortcut('videos').count(), 0);
  assert.equal(await page.locator('.os-window[data-app-id="videos"]').count(), 1, 'Removing shortcut closed its application');
  assert.equal(await app('videos').count(), 1);
  await page.reload();
  assert.equal(await shortcut('videos').count(), 0, 'Removed shortcut returned');
  assert.equal(await page.locator('.os-window[data-app-id="videos"]').count(), 1, 'Window restoration depended on shortcut');
  if (reenter) {
    await reenter();
    assert.equal(await shortcut('videos').count(), 0, 'Removed shortcut returned after login');
  }
  await page.locator('[data-start-button]').click();
  await app('videos').click();
  assert(await page.locator('.os-window[data-app-id="videos"]').isVisible());
  await page.locator('[data-close-all]').click();
  assert.equal(await shortcut('videos').count(), 0);
  assert(await saved(), 'Close all cleared shortcut preferences');

  await page.locator('[data-start-button]').click();
  await dragTo(app('videos'), 1000, 340);
  assert.equal(await shortcut('videos').count(), 1);
  assert.equal((await shortcut('videos').boundingBox()).x, 952);
  assert.equal(await page.locator('.os-window').count(), 0);
  await page.locator('[data-start-button]').click();
  await dragTo(app('videos'), 800, 420);
  assert.equal(await shortcut('videos').count(), 1, 'Duplicate shortcut created');
  assert.equal(await app('videos').count(), 1);

  await page.mouse.click(1300, 630, { button:'right' });
  await command('add').click();
  await command('add-projects').click();
  assert.equal(await shortcut('projects').count(), 1);
  assert.equal((await shortcut('projects').boundingBox()).x, 1300);
  await shortcut('projects').click();
  assert.equal(await page.locator('.os-window[data-app-id="projects"]').count(), 1, 'New shortcut does not launch');
  await page.locator('[data-close-all]').click();

  await shortcut('projects').focus();
  await page.keyboard.press('Shift+F10');
  await page.keyboard.press('Escape');
  assert.equal(await page.locator('[data-shortcut-menu]').isVisible(), false);
  assert(await shortcut('projects').evaluate(element => element === document.activeElement));
  await page.mouse.click(1665, 885, { button:'right' });
  await command('add').click();
  const menu = await page.locator('[data-shortcut-menu]').boundingBox();
  assert(menu.x >= 0 && menu.x + menu.width <= 1672 && menu.y + menu.height <= 895, 'Menu escaped desktop');
  await page.screenshot({ path:`${artifacts}/desktop-shortcut-menu.png` });
  await page.keyboard.press('Escape');
  const desktopSaved = await saved();
  await page.setViewportSize({ width:390, height:844 });
  await page.reload();
  const mobileBox = await shortcut('videos').boundingBox();
  assert(mobileBox.x >= 0 && mobileBox.x + mobileBox.width <= 390);
  assert(mobileBox.y >= 0 && mobileBox.y + mobileBox.height <= 794);
  assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
  await page.screenshot({ path:`${artifacts}/desktop-shortcuts-mobile.png` });
  await page.locator('[data-start-button]').click();
  await page.screenshot({ path:`${artifacts}/desktop-start-mobile.png` });
  await page.keyboard.press('Escape');
  await page.setViewportSize({ width:1672, height:941 });
  await page.waitForFunction(() => document.querySelector('.os-shortcut[data-open-app="projects"]').offsetLeft === 1300);
  assert.deepEqual(await saved(), desktopSaved, 'Resize overwrote preferred positions');
  assert.equal((await shortcut('projects').boundingBox()).x, 1300);

  // Corrupt or stale saved references must never become applications or executable markup.
  await page.evaluate(key => localStorage.setItem(`${key}.shortcuts`, JSON.stringify({ version:1, shortcuts:[null, { appId:'unknown', x:0, y:0 }, { appId:'videos', x:'invalid', y:0 }] })), storageKey);
  await page.reload();
  assert.equal(await page.locator('.os-shortcut').count(), 0);
  assert.equal(await app('videos').count(), 1);
  await page.evaluate(key => localStorage.setItem(`${key}.shortcuts`, '{broken'), storageKey);
  await page.reload();
  assert.equal(await page.locator('.os-shortcut').count(), initialCount);

  // Removing every shortcut is a valid desktop, not a request to restore defaults.
  for (const id of await page.locator('.os-shortcut').evaluateAll(items => items.map(item => item.dataset.openApp))) {
    await shortcut(id).click({ button:'right' });
    await command('remove').click();
  }
  await page.reload();
  assert.equal(await page.locator('.os-shortcut').count(), 0);
  assert.deepEqual(await page.locator('.os-start-menu [data-open-app]').evaluateAll(items => items.map(item => item.dataset.openApp)), catalogue);
  await page.locator('.os-shortcuts').focus();
  await page.keyboard.press('Shift+F10');
  await page.keyboard.press('Enter');
  await command('add-videos').click();
  assert.equal(await shortcut('videos').count(), 1);

  await page.evaluate(key => { localStorage.removeItem(`${key}.shortcuts`); localStorage.removeItem(key); }, storageKey);
  await page.reload();
  assert.equal(await page.locator('.os-shortcut').count(), initialCount);
  console.log('Desktop shortcuts: drag, cancel, persistence, removal, Start catalogue, menus, keyboard and mobile OK');
}
