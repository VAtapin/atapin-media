import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import vm from 'node:vm';

const stored = new Map(), sessions = new Map();
let finishes = 0, stopDuringChunk = null;
const response = data => new Response(JSON.stringify(data), {headers:{'Content-Type':'application/json'}});
const context = vm.createContext({crypto, AbortController, Error, Uint8Array, String, Number, Math, Promise, setTimeout,
  window:{desktopImportLabels:{upload_stopped:'Transfer stopped'}},
  document:{querySelector:() => ({content:'test-only'}), dispatchEvent:() => {}}, Event,
  localStorage:{getItem:key => stored.get(key), setItem:(key,value) => stored.set(key,value), removeItem:key => stored.delete(key)},
  fetch:async (url, options) => {
    if (url === '/desktop/media/uploads') {
      const input = JSON.parse(options.body);
      if (!sessions.has(input.request_key)) sessions.set(input.request_key, {id:input.request_key, offset:0, chunk_size:3});
      return response(sessions.get(input.request_key));
    }
    const id = url.split('/')[4], session = sessions.get(id);
    if (url.endsWith('/chunk')) {session.offset += options.body.size; stopDuringChunk?.stop(); return response(session);}
    finishes++; return response({media_id:id});
  },
});
vm.runInContext(await fs.readFile('public/assets/desktop-media-upload.js', 'utf8'), context);
const control = context.window.createDesktopUploadControl(); control.pause();
let resumed = 0;
const waiting = [control.checkpoint().then(() => resumed++), control.checkpoint().then(() => resumed++)];
await new Promise(resolve => setTimeout(resolve, 5)); assert.equal(resumed,0);
control.resume(); await Promise.all(waiting); assert.equal(resumed,2);
const file = new File(['abcdef'], 'original.txt', {lastModified:1});
const stopped = context.window.createDesktopUploadControl(); stopDuringChunk = stopped;
await assert.rejects(context.window.uploadDesktopMedia(file, 'test-user', () => {}, stopped), error => error.name === 'UploadStopped');
assert.equal(finishes,0); assert.equal(stored.size,1);
stopDuringChunk = null;
await context.window.uploadDesktopMedia(file, 'test-user', () => {}, context.window.createDesktopUploadControl());
assert.equal(finishes,1); assert.equal(stored.size,0); assert.equal(sessions.size,1);
const first = new File(['one'], 'same.txt', {lastModified:2}), second = new File(['two'], 'same.txt', {lastModified:2});
Object.defineProperty(first, 'webkitRelativePath', {value:'first/same.txt'});
Object.defineProperty(second, 'webkitRelativePath', {value:'second/same.txt'});
await Promise.all([first,second].map(item => context.window.uploadDesktopMedia(item, 'test-user')));
assert.equal(sessions.size,3);
console.log('Pause/resume for parallel workers, stop without finish, and resume the same session passed.');
