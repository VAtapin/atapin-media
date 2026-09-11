import assert from 'node:assert/strict';

export async function login(url, password) {
  const response = await fetch(url, {
    method: 'POST', redirect: 'manual', headers: { Origin: new URL(url).origin },
    body: new URLSearchParams({ action: 'login', password }),
  });
  assert.equal(response.status, 303, await response.text());
  const cookie = response.headers.get('set-cookie');
  assert(cookie?.includes('HttpOnly'));
  assert(cookie?.includes('SameSite=Strict'));
  return cookie.split(';')[0];
}
