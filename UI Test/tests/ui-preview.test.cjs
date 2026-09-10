const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {series}=require('../ui-preview-data.js');
test('series and page IDs are unique, starts are valid',()=>{
 assert.equal(new Set(series.map(s=>s.id)).size,series.length);
 for(const s of series){
  assert(s.pages.length);assert(s.pages.some(p=>p.id===s.start));
  assert.equal(new Set(s.pages.map(p=>p.id)).size,s.pages.length);
 }
});
test('every rectangle lies inside its screenshot and has an accessible label',()=>{
 for(const s of series)for(const p of s.pages)for(const h of p.hotspots||[]){
  assert([h.x,h.y,h.w,h.h].every(Number.isFinite),`${s.id}/${p.id}`);
  assert(h.x>=0&&h.y>=0&&h.w>0&&h.h>0&&h.x+h.w<=100.001&&h.y+h.h<=100.001,`${s.id}/${p.id}/${h.label}`);
  assert(h.label);assert(h.target||h.message);
 }
});
test('only safe relative original-image paths',()=>{
 for(const s of series)for(const p of s.pages){
  assert.match(p.file,/^UI\/ChatGPT Image 10\. Sept\. 2026, [\d_ ()]+\.png$/);
  assert(!p.file.includes('..'));assert(!p.file.includes('?'));
 }
});
test('coherent series have mapped navigation, references are identified',()=>{
 assert.equal(series.filter(s=>!s.unmapped).reduce((a,s)=>a+s.pages.length,0),34);
 for(const s of series)if(!s.unmapped)for(const p of s.pages){
  assert(p.hotspots.some(h=>s.pages.some(t=>t.id===h.target)),`${s.id}/${p.id}`);
 }
 assert(series.find(s=>s.id==='series-1955').unmapped);
});
test('all core files exist',()=>{
 for(const file of ['index.html','ui-preview.html','ui-preview.js','ui-preview-index.js','ui-preview-data.js','UI-PREVIEW.md'])assert(fs.existsSync(path.join(__dirname,'..',file)));
});

test('gallery links use the shared renderer and retain screen identifiers',()=>{
 const script=fs.readFileSync(path.join(__dirname,'..','ui-preview-index.js'),'utf8');
 assert(script.includes('ui-preview.html?series='));
 assert(script.includes('#${encodeURIComponent(page)}'));
 for(const s of series)assert(s.pages.some(p=>p.id===s.start));
});
test('import navigation has no duplicate menu rectangles',()=>{
 const p=series.find(s=>s.id==='backend-manna').pages.find(p=>p.id==='import');
 const rects=p.hotspots.filter(h=>h.x<1).map(h=>[h.x,h.y,h.w,h.h].join(','));
 assert.equal(new Set(rects).size,rects.length);
});
