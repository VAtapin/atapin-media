import { de as t } from './lang-de.js';

const $ = id => document.getElementById(id);
const tasks = [];
let options = { chunk_size: 4194304 };
let paused = false;
let running = false;
let activeRequest = null;
let wakeLock = null;
let refreshTimer = null;
const memoryStorage = new Map();

function stored(key, value) {
  try {
    if (value === null) { localStorage.removeItem(key); memoryStorage.delete(key); return; }
    if (value !== undefined) { localStorage.setItem(key, value); memoryStorage.set(key, value); return value; }
    return localStorage.getItem(key) ?? memoryStorage.get(key);
  } catch {
    if (value === null) memoryStorage.delete(key);
    else if (value !== undefined) { memoryStorage.set(key, value); notice(t.localUnavailable); }
    return memoryStorage.get(key);
  }
}

function notice(message) { $('notice').textContent = message; $('notice').hidden = !message; }
function bytes(value) {
  value = Number(value);
  const units = ['B', 'KB', 'MB', 'GB', 'TB'];
  let index = 0;
  while (value >= 1024 && index < units.length - 1) { value /= 1024; index++; }
  return `${value.toLocaleString('de-DE', { maximumFractionDigits: index > 1 ? 1 : 0 })} ${units[index]}`;
}
function element(tag, className, text) {
  const node = document.createElement(tag);
  node.className = className;
  if (text !== undefined) node.textContent = text;
  return node;
}
async function digest(buffer) {
  return Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256', buffer)), n => n.toString(16).padStart(2, '0')).join('');
}
async function fingerprint(file, relative) {
  const head = await file.slice(0, 65536).arrayBuffer();
  const tail = await file.slice(Math.max(0, file.size - 65536)).arrayBuffer();
  const meta = new TextEncoder().encode(JSON.stringify([file.name, file.size, file.lastModified, relative]));
  const content = new Uint8Array(meta.byteLength + head.byteLength + tail.byteLength);
  content.set(meta); content.set(new Uint8Array(head), meta.byteLength); content.set(new Uint8Array(tail), meta.byteLength + head.byteLength);
  return digest(content);
}

class RequestError extends Error {
  constructor(key, status = 0) { super(t.errors[key] || t.errors.server_error); this.key = key; this.status = status; }
}

function request(action, { id, body, offset, checksum, onprogress } = {}) {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    const query = new URLSearchParams({ action });
    if (id) query.set('id', id);
    xhr.open(action === 'overview' ? 'GET' : 'POST', `api.php?${query}`);
    xhr.setRequestHeader('X-Intake-Request', '1');
    if (offset !== undefined) xhr.setRequestHeader('X-Upload-Offset', String(offset));
    if (checksum) xhr.setRequestHeader('X-Chunk-SHA256', checksum);
    if (body && !(body instanceof Blob)) {
      xhr.setRequestHeader('Content-Type', 'application/json'); body = JSON.stringify(body);
    } else if (body) xhr.setRequestHeader('Content-Type', 'application/octet-stream');
    xhr.timeout = 180000;
    if (onprogress) xhr.upload.onprogress = event => onprogress(event.loaded);
    if (action !== 'overview') activeRequest = xhr;
    function clear() { if (activeRequest === xhr) activeRequest = null; }
    xhr.onload = () => {
      clear();
      let data;
      try { data = JSON.parse(xhr.responseText); } catch { reject(new RequestError(xhr.status === 413 ? 'request_too_large' : 'server_error', xhr.status)); return; }
      if (xhr.status >= 200 && xhr.status < 300) resolve(data);
      else reject(new RequestError(data.error || 'server_error', xhr.status));
    };
    xhr.onerror = xhr.ontimeout = () => { clear(); reject(new RequestError('network')); };
    xhr.onabort = () => { clear(); reject(new DOMException('Paused', 'AbortError')); };
    xhr.send(body || null);
  });
}

async function resilient(action, args, task) {
  for (let attempt = 0; ; attempt++) {
    if (paused) throw new DOMException('Paused', 'AbortError');
    try { return await request(action, args); }
    catch (error) {
      if (error.name === 'AbortError' || paused) throw new DOMException('Paused', 'AbortError');
      const retryable = error.status === 0 || error.status >= 500 && error.status !== 507 || error.key === 'upload_busy' || error.key === 'checksum_mismatch';
      if (!retryable || attempt >= 4) throw error;
      task.message = `${t.retrying} (${attempt + 1}/4)`; renderTask(task);
      await new Promise(resolve => setTimeout(resolve, Math.min(16000, 1000 * 2 ** attempt)));
    }
  }
}

function renderTask(task) {
  const percent = task.file.size ? Math.min(100, Math.floor(task.sent / task.file.size * 100)) : task.state === 'complete' ? 100 : 0;
  task.nodes.detail.textContent = `${bytes(task.file.size)}${task.relative ? ` · ${task.relative}` : ''}`;
  task.nodes.state.className = `file-state ${task.state === 'complete' ? 'success' : task.state === 'failed' ? 'error' : ''}`;
  task.nodes.state.replaceChildren(document.createTextNode(task.message || (task.state === 'uploading' ? t.progress(percent) : t[task.state])));
  if (task.state === 'failed') {
    const retry = element('button', 'text-button retry', t.retry); retry.type = 'button';
    retry.onclick = () => { task.state = 'waiting'; task.message = ''; renderTask(task); pump(); };
    task.nodes.state.append(retry);
  }
  task.nodes.progress.value = percent;
  task.nodes.progress.hidden = ['complete', 'failed'].includes(task.state);
  renderSummary();
}

function renderSummary() {
  $('queue-section').hidden = tasks.length === 0;
  const done = tasks.filter(task => task.state === 'complete').length;
  $('queue-summary').textContent = t.queueSummary(done, tasks.length, bytes(tasks.reduce((sum, task) => sum + task.sent, 0)));
  $('pause').textContent = paused ? t.resume : t.pause;
  $('pause').hidden = !tasks.some(task => !['complete', 'failed'].includes(task.state));
}

async function addFiles(entries) {
  for (const entry of entries) {
    const file = entry.file || entry;
    const relative = entry.relative || file.webkitRelativePath || '';
    if (tasks.some(task => task.state !== 'complete' && task.file.name === file.name && task.file.size === file.size && task.file.lastModified === file.lastModified && task.relative === relative)) continue;
    const node = element('li', 'queue-item');
    const symbol = element('span', 'file-symbol', file.name.includes('.') ? file.name.split('.').pop().slice(0, 4).toUpperCase() : 'FILE'); symbol.setAttribute('aria-hidden', 'true');
    const info = element('div', 'file-info');
    info.append(element('p', 'file-name', file.name));
    const detail = element('p', 'file-detail'); info.append(detail);
    const state = element('div', 'file-state');
    const progress = element('progress', 'file-progress'); progress.max = 100; progress.value = 0; progress.setAttribute('aria-label', file.name);
    node.append(symbol, info, state, progress); $('queue').append(node);
    const task = { file, relative, sent: 0, state: 'waiting', nodes: { detail, state, progress } };
    if (file.size > options.max_file_bytes) { task.state = 'failed'; task.message = t.errors.file_too_large; }
    tasks.push(task); renderTask(task);
  }
  pump();
}

async function keepAwake() {
  if (document.visibilityState === 'visible' && navigator.wakeLock && running && !paused) {
    try { wakeLock = await navigator.wakeLock.request('screen'); } catch { /* Browser/OS may decline. */ }
  }
}

async function pump() {
  if (running || paused) return;
  running = true;
  await keepAwake();
  try {
    while (!paused) {
      const task = tasks.find(task => task.state === 'waiting' || task.state === 'paused');
      if (!task) break;
      try {
        task.state = 'preparing'; task.message = ''; renderTask(task);
        task.storageKey ||= 'atapin-intake-v1:' + await fingerprint(task.file, task.relative);
        task.requestKey ||= stored(task.storageKey) || crypto.randomUUID();
        stored(task.storageKey, task.requestKey);
        let result = await resilient('start', { body: { request_key: task.requestKey, name: task.file.name, size: task.file.size, modified: task.file.lastModified, relative_path: task.relative } }, task);
        task.sent = Number(result.offset);
        while (result.state === 'uploading' && task.sent < task.file.size) {
          if (paused) throw new DOMException('Paused', 'AbortError');
          task.state = 'uploading'; task.message = ''; renderTask(task);
          const start = task.sent;
          const chunk = task.file.slice(start, Math.min(start + options.chunk_size, task.file.size));
          const checksum = await digest(await chunk.arrayBuffer());
          result = await resilient('chunk', { id: result.id, body: chunk, offset: start, checksum,
            onprogress: count => { task.sent = Math.min(start + count, task.file.size); task.message = ''; renderTask(task); } }, task);
          const next = Number(result.offset);
          if (!Number.isSafeInteger(next) || next <= start || next > task.file.size) throw new RequestError('archive_inconsistent');
          task.sent = next;
        }
        task.state = 'finalizing'; task.message = ''; renderTask(task);
        if (result.state !== 'complete') result = await resilient('finish', { id: result.id }, task);
        if (result.state !== 'complete') throw new RequestError('server_error');
        task.state = 'complete'; task.sent = task.file.size; task.message = '';
        let history = [];
        try { history = JSON.parse(stored('atapin-intake-history') || '[]'); } catch { /* Reset corrupt browser history. */ }
        if (!Array.isArray(history)) history = [];
        stored('atapin-intake-history', JSON.stringify([result, ...history.filter(item => item.id !== result.id)].slice(0, 30)));
        stored(task.storageKey, null); renderTask(task);
        clearTimeout(refreshTimer); refreshTimer = setTimeout(() => refresh().catch(() => {}), 500);
      } catch (error) {
        task.state = error.name === 'AbortError' ? 'paused' : 'failed';
        task.message = error.name === 'AbortError' ? '' : error.message;
        if (error.status === 401 || error.status === 403) { paused = true; notice(t.accessExpired); }
        renderTask(task);
      }
    }
  } finally {
    running = false;
    if (wakeLock) { await wakeLock.release().catch(() => {}); wakeLock = null; }
    renderSummary();
    // Handles a quick pause/resume while the aborted request is still settling.
    if (!paused && tasks.some(task => ['waiting', 'paused'].includes(task.state))) pump();
  }
}

async function refresh() {
  const data = await request('overview');
  options = data;
  $('limit').textContent = t.fileLimit(bytes(data.max_file_bytes));
  $('categories').replaceChildren();
  let total = 0; let totalBytes = 0;
  for (const [key, [name, symbol]] of Object.entries(t.categories)) {
    const group = data.groups.find(group => group.category === key) || { count: 0, bytes: 0 };
    total += Number(group.count); totalBytes += Number(group.bytes);
    const card = element('div', 'category'); const icon = element('span', 'category-symbol', symbol); icon.setAttribute('aria-hidden', 'true');
    const info = element('div', ''); info.append(element('p', 'category-name', name), element('p', 'category-count', t.fileCount(Number(group.count))));
    card.append(icon, info); $('categories').append(card);
  }
  $('archive-total').textContent = total ? `${t.fileCount(total)} · ${bytes(totalBytes)}` : t.empty;
  let recent = [];
  try { recent = JSON.parse(stored('atapin-intake-history') || '[]'); } catch { /* Empty history. */ }
  if (!Array.isArray(recent)) recent = [];
  $('recent').replaceChildren(); $('empty').hidden = recent.length > 0;
  for (const file of recent) {
    const li = element('li', '');
    li.append(element('span', 'file-symbol', t.categories[file.category]?.[1] || '…'), element('span', 'file-name', file.name));
    const date = new Date(file.completed_at).toLocaleString('de-DE', { dateStyle: 'short', timeStyle: 'short' });
    li.append(element('span', 'recent-meta', `${bytes(file.size)} · ${date}`)); $('recent').append(li);
  }
}

// Directory drop support (including nested and empty files). Never unpack archives.
async function walk(entry, prefix = '') {
  if (entry.isFile) {
    const file = await new Promise((resolve, reject) => entry.file(resolve, reject));
    return [{ file, relative: prefix ? prefix + file.name : '' }];
  }
  if (!entry.isDirectory) return [];
  const reader = entry.createReader(); const result = [];
  while (true) {
    const children = await new Promise((resolve, reject) => reader.readEntries(resolve, reject));
    if (!children.length) break;
    for (const child of children) result.push(...await walk(child, prefix + entry.name + '/'));
  }
  return result;
}

function installControls() {
  for (const id of ['files', 'folder']) $(id).addEventListener('change', event => { addFiles(Array.from(event.target.files)); event.target.value = ''; });
  if (!('webkitdirectory' in $('folder'))) $('folder-label').hidden = true;
  for (const name of ['dragenter', 'dragover']) $('dropzone').addEventListener(name, event => { event.preventDefault(); $('dropzone').classList.add('dragover'); });
  for (const name of ['dragleave', 'drop']) $('dropzone').addEventListener(name, event => { event.preventDefault(); $('dropzone').classList.remove('dragover'); });
  // Prevent the browser navigating away when a file is dropped outside the target.
  window.addEventListener('dragover', event => event.preventDefault());
  window.addEventListener('drop', event => event.preventDefault());
  $('dropzone').addEventListener('drop', async event => {
    const fallback = Array.from(event.dataTransfer.files);
    const entries = Array.from(event.dataTransfer.items).filter(item => item.kind === 'file').map(item => item.webkitGetAsEntry?.()).filter(Boolean);
    try {
      if (entries.length) for (const entry of entries) await addFiles(await walk(entry));
      else await addFiles(fallback);
    } catch { notice(t.dropFailed); }
  });
  $('pause').onclick = () => {
    paused = !paused;
    if (paused) activeRequest?.abort(); else pump();
    renderSummary();
  };
  $('refresh').onclick = async () => {
    $('refresh').disabled = true;
    try { await refresh(); } catch (error) { notice(error.message); }
    finally { $('refresh').disabled = false; }
  };
  window.addEventListener('beforeunload', event => {
    if (tasks.some(task => !['complete', 'failed'].includes(task.state))) { event.preventDefault(); event.returnValue = ''; }
  });
  window.addEventListener('offline', () => { $('connection').textContent = t.offline; $('connection').classList.remove('ready'); });
  window.addEventListener('online', () => { $('connection').textContent = t.ready; $('connection').classList.add('ready'); });
  document.addEventListener('visibilitychange', keepAwake);
  // Read-only companion for supported browsers; exposes only the same visible local queue.
  try {
    const registration = document.modelContext?.registerTool({ name: 'read_upload_queue', title: 'Read upload queue',
      description: 'Read the file names and transfer states currently visible on this device. Does not upload, cancel or access local files.',
      inputSchema: { type: 'object', properties: {}, additionalProperties: false },
      annotations: { readOnlyHint: true, untrustedContentHint: true },
      execute(input) { if (!input || typeof input !== 'object' || Object.keys(input).length) throw new Error('Expected an empty object'); return { paused, files: tasks.map(task => ({ name: task.file.name, bytes: task.file.size, transferred: task.sent, state: task.state })) }; },
    });
    Promise.resolve(registration).catch(() => {});
  } catch { /* Optional browser API. */ }
}

async function boot() {
  if (!globalThis.crypto?.subtle || !globalThis.crypto?.randomUUID) { notice(t.unsupported); return; }
  try {
    await refresh();
    $('workspace').hidden = false; $('connection').textContent = t.ready; $('connection').classList.add('ready'); installControls();
  } catch (error) {
    $('connection').textContent = t.unavailable; notice(error.message);
  }
}
boot();
