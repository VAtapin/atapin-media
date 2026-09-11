import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createHash } from 'node:crypto';
import { PNG } from 'pngjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const manifest = JSON.parse(await fs.readFile(path.join(root, 'UI/assets/manifest.json'), 'utf8'));
const publicRoot = path.join(root, 'platform/public');
const sources = new Map();
const results = [];

for (const asset of manifest.assets.filter(asset => asset.group !== 'brand')) {
  const sourcePath = path.resolve(root, 'UI/approved', asset.source);
  if (!sourcePath.startsWith(path.join(root, 'UI/approved') + path.sep)) throw Error('Invalid source');
  if (!sources.has(sourcePath)) {
    const buffer = await fs.readFile(sourcePath);
    sources.set(sourcePath, { image: PNG.sync.read(buffer), sha256: createHash('sha256').update(buffer).digest('hex') });
  }
  const source = sources.get(sourcePath);
  const [x, y, width, height] = asset.rect;
  if (![x,y,width,height].every(Number.isInteger) || x<0 || y<0 || width<1 || height<1 || x+width>source.image.width || y+height>source.image.height) throw Error('Invalid crop: '+asset.file);
  const file = path.resolve(publicRoot, asset.file);
  if (!file.startsWith(publicRoot + path.sep) || !file.endsWith('.png')) throw Error('Invalid output');
  const png = new PNG({ width, height });
  for (let row=0; row<height; row++) {
    const start=((y+row)*source.image.width+x)*4;
    source.image.data.copy(png.data, row*width*4, start, start+width*4);
  }
  const buffer = PNG.sync.write(png);
  // Decode and compare pixel rows, so exports cannot silently resample the reference.
  const decoded = PNG.sync.read(buffer);
  if (!decoded.data.equals(png.data)) throw Error('Pixel verification failed: '+asset.file);
  await fs.mkdir(path.dirname(file), { recursive: true });
  await fs.writeFile(file, buffer);
  results.push({ ...asset, width, height, bytes: buffer.length, sourceSha256: source.sha256, sha256: createHash('sha256').update(buffer).digest('hex') });
}
await fs.writeFile(path.join(root, 'UI/assets/export-report.json'), JSON.stringify(results,null,2)+'\n');
const escape = s=>s.replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const groups = [...new Set(results.map(a=>a.group))];
const html = `<!doctype html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Approved UI — separate assets</title><style>body{font:15px system-ui;margin:32px;color:#0f2b5b;background:#fff8f5}h1{margin-bottom:8px}nav{display:flex;flex-wrap:wrap;gap:16px;margin:24px 0}a{color:#0f2b5b}section{scroll-margin-top:20px}.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:16px}figure{margin:0;background:#fff;border:1px solid #e5e7eb;padding:16px;border-radius:8px;min-width:0}.preview{height:180px;display:grid;place-items:center;overflow:auto;background:repeating-conic-gradient(#f0f2f5 0 25%,#fff 0 50%) 0/16px 16px}.preview img{max-width:100%;max-height:170px;object-fit:contain}figcaption{margin-top:12px;overflow-wrap:anywhere}small{display:block;margin-top:6px;color:#55657b}.note{color:#946200}</style><h1>Approved UI — ${results.length} separate PNG assets</h1><p>Original pixels. No resampling, redrawing or hidden full-sheet images.</p><nav>${groups.map(g=>`<a href="#${escape(g)}">${escape(g)}</a>`).join('')}</nav>${groups.map(g=>`<section id="${escape(g)}"><h2>${escape(g)}</h2><div class="grid">${results.filter(a=>a.group===g).map(a=>`<figure><a class="preview" href="../../platform/public/${escape(a.file)}"><img loading="lazy" src="../../platform/public/${escape(a.file)}" alt="${escape(a.name)}"></a><figcaption>${escape(a.name)}<small>${a.width} × ${a.height} px · ${(a.bytes/1024).toFixed(1)} KB</small><small>${escape(a.file)}</small>${a.note?`<small class="note">${escape(a.note)}</small>`:''}</figcaption></figure>`).join('')}</div></section>`).join('')}</html>`;
await fs.writeFile(path.join(root, 'UI/assets/index.html'),html);
console.log(`Exported and pixel-verified ${results.length} PNG assets from ${sources.size} original sheets (${results.reduce((n,a)=>n+a.bytes,0)} bytes).`);
