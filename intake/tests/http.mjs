import assert from 'node:assert/strict';
import { spawn, execFileSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, readFileSync, readdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { resolve, join } from 'node:path';
import { createServer } from 'node:net';
import { randomUUID, createHash } from 'node:crypto';
import { login } from './login.mjs';

// Real PHP HTTP server + real disk, no API mocks.
const scratch = mkdtempSync(join(tmpdir(), 'atapin-http-'));
const storage = join(scratch, 'archive'); mkdirSync(storage);
const socket = createServer();
await new Promise(resolve => socket.listen(0, '127.0.0.1', resolve));
const port = socket.address().port;
await new Promise(resolve => socket.close(resolve));
const origin = `http://127.0.0.1:${port}`;
const config = join(scratch, 'config.php');
const marker = join(scratch, 'intake-retired.json');
const env = { ...process.env, INTAKE_CONFIG: config, INTAKE_ALLOW_LOCAL_HTTP: '1', INTAKE_PLATFORM_READY: marker };
const setupOutput = execFileSync('php', ['intake/bin/setup.php', `--storage=${storage}`, `--origin=${origin}`, '--max-file-gb=1', '--quota-gb=2'], { env, encoding: 'utf8' });
const password = setupOutput.match(/Password: ([a-z0-9]+)/)[1];
assert.equal(password.length, 6, 'generated password is short');
assert(!readFileSync(config, 'utf8').includes(password), 'plaintext password is not stored');
let cookie = '';
let output = '';
const server = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', 'intake/public'], { env, stdio: ['ignore', 'pipe', 'pipe'] });
server.stdout.on('data', data => output += data); server.stderr.on('data', data => output += data);
let serverError;
server.on('error', error => serverError = error);
const sha = data => createHash('sha256').update(data).digest('hex');
async function api(action, data, extra = {}) {
  const { id, headers, ...rest } = extra;
  const response = await fetch(`${origin}/api.php?action=${action}${id ? `&id=${id}` : ''}`, {
    method: action === 'overview' ? 'GET' : 'POST',
    headers: { 'X-Intake-Request':'1', Origin: origin, Cookie: cookie, ...(Buffer.isBuffer(data) ? {} : { 'Content-Type':'application/json' }), ...headers },
    body: data === undefined ? undefined : Buffer.isBuffer(data) ? data : JSON.stringify(data), ...rest,
  });
  let body;
  try { body = await response.json(); } catch { throw new Error(`Non-JSON response ${response.status}: ${output}`); }
  return { status: response.status, body };
}
try {
  let ready = false;
  for (let attempt=0; attempt<60; attempt++) {
    if (serverError) throw serverError;
    try { if ((await fetch(origin)).ok) { ready = true; break; } } catch {}
    await new Promise(resolve => setTimeout(resolve, 100));
  }
  assert(ready, `PHP server did not start: ${output}`);
  const page = await fetch(origin);
  const html = await page.text();
  assert(html.includes('type="password"') && !html.includes('id="workspace"') && !html.includes('<?php'));
  assert(page.headers.get('content-security-policy').includes("frame-ancestors 'none'"));
  for (const action of ['overview', 'start', 'chunk', 'finish']) assert.equal((await api(action)).status, 401, 'all API actions require login');
  assert.equal((await fetch(origin, { method: 'POST', headers: { Origin: 'https://foreign.example' }, body: new URLSearchParams({ action: 'login', password }) })).status, 403);
  const wrong = await fetch(origin, { method: 'POST', headers: { Origin: origin }, body: new URLSearchParams({ action: 'login', password: 'wrong-password' }) });
  assert.equal(wrong.status, 401); assert((await wrong.text()).includes('Das Passwort stimmt nicht'));
  cookie = await login(origin, password);
  assert.equal((await api('overview')).status, 200, 'shared password enables API');
  assert.equal((await api('overview', undefined, { headers: { Cookie: cookie + '0' } })).status, 401, 'tampered cookie rejected');
  assert((await (await fetch(origin, { headers: { Cookie: cookie } })).text()).includes('id="workspace"'));
  assert.equal((await fetch(`${origin}/api.php?action=overview`)).status, 403, 'simple cross-site submissions rejected');
  assert.equal((await api('overview', undefined, { headers: { Origin: 'https://foreign.example' } })).status, 403);
  for (const path of ['/config.local.php','/../config.local.php','/catalogue.sqlite','/.staging/','/images/']) {
    assert.equal((await fetch(origin + path)).status, 404, `${path} not exposed`);
  }
  const content = Buffer.alloc(4194304 + 501, 107); content.write('Text original ü');
  const input = { request_key: randomUUID(), name: '../../original <script>.txt', size: content.length, modified: 42, relative_path: 'folder/nested/original.txt' };
  let start = await api('start', input); assert.equal(start.status, 200);
  const id = start.body.id;
  const chunk = content.subarray(0, 4194304);
  let result = await api('chunk', chunk, { id, headers: { 'X-Upload-Offset':'0', 'X-Chunk-SHA256':sha(chunk) } });
  assert.equal(result.status, 200); assert.equal(Number(result.body.offset), chunk.length);
  result = await api('chunk', chunk, { id, headers: { 'X-Upload-Offset':'0', 'X-Chunk-SHA256':sha(chunk) } });
  assert.equal(Number(result.body.offset), chunk.length, 'lost response retry does not duplicate data');
  start = await api('start', input); assert.equal(Number(start.body.offset), chunk.length, 'new page can resume');
  const tail = content.subarray(chunk.length);
  result = await api('chunk', tail, { id, headers: { 'X-Upload-Offset':String(chunk.length), 'X-Chunk-SHA256':sha(tail) } });
  assert.equal(result.status, 200);
  result = await api('finish', undefined, { id }); assert.equal(result.status, 200); assert.equal(result.body.state, 'complete');
  assert.equal((await api('finish', undefined, { id })).body.state, 'complete');
  const malicious = Buffer.from('<?php echo "must never execute";');
  start = await api('start', { request_key: randomUUID(), name: 'evil.php', size: malicious.length, modified: 0 });
  await api('chunk', malicious, { id: start.body.id, headers: { 'X-Upload-Offset':'0', 'X-Chunk-SHA256':sha(malicious) } });
  result = await api('finish', undefined, { id: start.body.id }); assert.equal(result.body.state, 'complete');
  assert.equal((await fetch(origin + '/evil.php')).status, 404, 'uploaded script is not served or executed');
  const zero = await api('start', { request_key: randomUUID(), name: 'empty.txt', size: 0, modified: 0 });
  assert.equal((await api('finish', undefined, { id: zero.body.id })).body.state, 'complete');
  function allFiles(folder) { return readdirSync(folder, { withFileTypes:true }).flatMap(item => item.isDirectory() ? allFiles(join(folder,item.name)) : [join(folder,item.name)]); }
  const manifestPath = allFiles(storage).find(file => file.endsWith('.metadata.json') && file.includes(id));
  assert(manifestPath);
  const manifest = JSON.parse(readFileSync(manifestPath));
  assert.equal(manifest.original_name,input.name);
  assert.deepEqual(readFileSync(join(storage,manifest.stored_path)),content);
  assert.equal((await fetch(origin+'/'+manifest.stored_path)).status,404);
  const overview = await api('overview'); assert(!('recent' in overview.body));
  if (process.env.INTAKE_BROWSER_TESTS === '1') {
    const { checkBrowser } = await import('./browser.mjs');
    await checkBrowser(origin, password);
  }
  const verify = execFileSync('php',['intake/bin/console.php','verify'],{env,encoding:'utf8'}); assert(verify.includes('failed: 0'));
  // Detect subsequent corruption independently from upload acceptance.
  writeFileSync(join(storage,manifest.stored_path), Buffer.alloc(content.length));
  assert.throws(() => execFileSync('php',['intake/bin/console.php','verify'],{env,stdio:'pipe'}));
  for (let attempt = 0; attempt < 5; attempt++) {
    const denied = await fetch(origin, { method: 'POST', headers: { Origin: origin }, body: new URLSearchParams({ action: 'login', password: 'wrong-password' }) });
    assert.equal(denied.status, 401);
  }
  const limited = await fetch(origin, { method: 'POST', headers: { Origin: origin }, body: new URLSearchParams({ action: 'login', password }) });
  assert.equal(limited.status, 429, 'password attempts are limited, even for correct password');
  assert.equal(limited.headers.get('retry-after'), '900');
  assert.equal((await api('overview')).status, 200, 'throttle does not block authenticated uploads');
  writeFileSync(marker, JSON.stringify({schema:'atapin-library-cutover/v1',completed_at:new Date().toISOString()}));
  const retired = await fetch(origin, {redirect:'manual'});
  assert.equal(retired.status,303); assert.equal(retired.headers.get('location'),'/desktop');
  assert.equal((await api('start',{request_key:randomUUID(),name:'blocked.txt',size:1,modified:0})).status,410);
  assert.equal((await api('overview')).status,410);
  assert(existsArchiveOriginal(), 'cutover preserves archive originals');
  function existsArchiveOriginal() { return allFiles(storage).includes(join(storage,manifest.stored_path)); }
  console.log('HTTP checks passed: shared password, API protection, signed cookie, brute-force limits, real transfer, resume, checksums, private storage and integrity verification.');
} finally {
  server.kill();
  await new Promise(resolve => { if (server.exitCode !== null) resolve(); else server.once('exit',resolve); });
  if (resolve(scratch).startsWith(resolve(tmpdir()) + '/') || resolve(scratch).startsWith(resolve(tmpdir()) + '\\')) rmSync(scratch, { recursive:true, force:true });
}
