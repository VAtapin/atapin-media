import assert from 'node:assert/strict';
import { spawn, execFileSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, cpSync, readFileSync, writeFileSync, existsSync, statSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { createServer } from 'node:net';
import { login } from './login.mjs';

// Model the actual Plesk layout, including a separate existing main website.
const scratch = mkdtempSync(join(tmpdir(), 'atapin-subfolder-'));
const docroot = join(scratch, 'httpdocs'); mkdirSync(docroot);
cpSync('intake', join(docroot, 'intake'), { recursive: true, filter: path => !path.endsWith('config.local.php') && !path.includes('/var/') });
cpSync('upload', join(docroot, 'upload'), { recursive: true });
writeFileSync(join(docroot, 'index.html'), '<!doctype html><title>Existing site</title>Main website is unchanged');
const socket = createServer();
await new Promise(resolve => socket.listen(0, '127.0.0.1', resolve));
const port = socket.address().port;
await new Promise(resolve => socket.close(resolve));
const origin = `http://127.0.0.1:${port}`;
const url = origin + '/upload/';
const php = execFileSync('php', ['-r', 'echo PHP_BINARY;'], { encoding: 'utf8' });
const env = { ...process.env, PLESK_PHP_BIN: php, INTAKE_ALLOW_LOCAL_HTTP: '1' };
delete env.INTAKE_CONFIG; // Exercise automatic private config discovery in CLI AND HTTP.
function deploy(nextOrigin = origin) {
  const script = join(docroot, 'intake/bin/deploy-plesk.sh');
  // On GitHub runners also exercise root -> Plesk owner delegation.
  return process.env.GITHUB_ACTIONS
    ? execFileSync('sudo', ['env', `PLESK_PHP_BIN=${php}`, 'INTAKE_ALLOW_LOCAL_HTTP=1', 'bash', script, nextOrigin], { env, encoding: 'utf8' })
    : execFileSync('bash', [script, nextOrigin], { env, encoding: 'utf8' });
}
let server;
try {
  const deployed = deploy();
  assert(deployed.includes(url));
  const password = deployed.match(/Password: ([a-z0-9]+)/)[1];
  const config = join(scratch, 'private/manna-intake-config.php');
  assert(existsSync(config));
  assert(!existsSync(join(docroot, 'intake/config.local.php')));
  assert.equal(statSync(config).uid, statSync(docroot).uid, 'config belongs to site owner');
  assert.equal(statSync(config).mode & 0o777, 0o600, 'config is private');
  let output = '';
  server = spawn(php, ['-S', `127.0.0.1:${port}`, '-t', docroot], { env, stdio: ['ignore', 'pipe', 'pipe'] });
  server.stderr.on('data', data => output += data);
  for (let attempt = 0; attempt < 60; attempt++) {
    try { if ((await fetch(url)).ok) break; } catch {}
    await new Promise(resolve => setTimeout(resolve, 100));
  }
  assert((await (await fetch(url)).text()).includes('type="password"'));
  const cookie = await login(url, password);
  const response = await fetch(url, { headers: { Cookie: cookie } });
  assert.equal(response.status, 200, output);
  const html = await response.text();
  assert(html.includes('Dateien sammeln'));
  for (const match of html.matchAll(/(?:href|src)="([^\"]+\.(?:css|js|svg))"/g)) {
    assert.equal((await fetch(new URL(match[1], url))).status, 200, 'subfolder asset ' + match[1]);
  }
  assert((await (await fetch(origin)).text()).includes('Main website is unchanged'));
  assert.equal((await fetch(origin + '/upload')).url, url, 'missing trailing slash is redirected');
  for (const path of ['/upload/missing/', '/upload/missing.php']) {
    assert.equal((await fetch(origin + path)).status, 404, path);
  }
  for (const path of ['/private/manna-intake-config.php', '/private/manna-intake/catalogue.sqlite']) {
    assert(!existsSync(join(docroot, path)), 'private file is outside the document root');
    const privateResponse = await fetch(origin + path);
    // PHP 8.4's built-in server may fall back to the existing site's index.html.
    // Neither a 404 nor that exact public page exposes the private file.
    if (privateResponse.status !== 404) {
      assert.equal(privateResponse.status, 200, path);
      assert.equal(await privateResponse.text(), readFileSync(join(docroot, 'index.html'), 'utf8'), path);
    }
  }
  assert.equal((await fetch(origin + '/intake/public/api.php?action=overview', { headers: { 'X-Intake-Request': '1' } })).status, 401, 'alternate entry point also requires password');
  const overview = await fetch(url + 'api.php?action=overview', { headers: { 'X-Intake-Request': '1', Origin: origin, Cookie: cookie } });
  assert.equal(overview.status, 200, 'same-origin subfolder API works');
  if (process.env.INTAKE_BROWSER_TESTS === '1') {
    const { checkBrowser } = await import('./browser.mjs');
    await checkBrowser(url, password);
  }
  const catalogue = join(scratch, 'private/manna-intake/catalogue.sqlite');
  const before = statSync(catalogue).size;
  const storagePathBefore = readFileSync(config, 'utf8').match(/'storage_path' => '[^']+'/)[0];
  deploy('https://mannavomhimmel.de');
  assert(readFileSync(config, 'utf8').includes("'origin' => 'https://mannavomhimmel.de'"));
  assert(readFileSync(config, 'utf8').includes(storagePathBefore), 'URL update preserves archive path');
  assert.equal(statSync(catalogue).size, before, 'URL update does not recreate the catalogue');
  deploy();
  assert.equal((await fetch(url + 'api.php?action=overview', { headers: { 'X-Intake-Request': '1', Cookie: cookie } })).status, 200, 'normal deploy preserves password and login');
  execFileSync(php, [join(docroot, 'intake/bin/setup.php'), `--origin=${origin}`, '--update-origin', '--password-stdin'], { env, input: 'replacement-test-password\n' });
  assert.equal((await fetch(url + 'api.php?action=overview', { headers: { 'X-Intake-Request': '1', Cookie: cookie } })).status, 401, 'password change revokes old browser logins');
  await login(url, 'replacement-test-password');
  const protectedConfig = readFileSync(config, 'utf8');
  assert.throws(() => execFileSync(php, [join(docroot, 'intake/bin/setup.php'), `--origin=${origin}`, '--update-origin', '--password-stdin'], { env, input: 'tiny\n', stdio: ['pipe', 'pipe', 'pipe'] }));
  assert.equal(readFileSync(config, 'utf8'), protectedConfig, 'invalid password cannot change config');
  // Upgrade an earlier installation that has an archive but no password.
  const legacyConfig = protectedConfig.replace(/\s*'password_hash' => '[^']+',?/, '');
  assert.notEqual(legacyConfig, protectedConfig);
  writeFileSync(config, legacyConfig);
  assert.equal((await fetch(url + 'api.php?action=overview', { headers: { 'X-Intake-Request': '1' } })).status, 503, 'legacy config cannot silently allow public access');
  const upgraded = deploy();
  await login(url, upgraded.match(/Password: ([a-z0-9]+)/)[1]);
  assert.equal(statSync(catalogue).size, before, 'adding a password preserves the archive');
  const verify = execFileSync(php, [join(docroot, 'intake/bin/console.php'), 'verify'], { env, encoding: 'utf8' });
  assert(verify.includes('failed: 0'));
  assert.equal(readFileSync(join(docroot, 'index.html'), 'utf8'), '<!doctype html><title>Existing site</title>Main website is unchanged');
  console.log('Subfolder checks passed: existing httpdocs, root delegation, private config, /upload/ assets and API, browser uploads, repeat deployment preserves originals.');
} finally {
  if (server) {
    server.kill();
    await new Promise(resolve => { if (server.exitCode !== null) resolve(); else server.once('exit', resolve); });
  }
  rmSync(scratch, { recursive: true, force: true });
}
