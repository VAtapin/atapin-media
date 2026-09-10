/* Dependency-free screenshot navigator; compatible with file:// and static hosting. */
(() => {
  'use strict';
  const $ = id => document.getElementById(id);
  const data = window.UI_PREVIEW;
  if (!data || !Array.isArray(data.series)) {
    $('error').hidden = false;
    $('error-message').textContent = 'ui-preview-data.js konnte nicht geladen werden.';
    return;
  }
  const aliases = {start:'home',startseite:'home',projekte:'projects',kalender:'calendar',aufgaben:'tasks',ki:'ai',beitraege:'articles',beitrag:'article',buecher:'books',buch:'book'};
  const groupNames = {public:'Öffentliche Website',admin:'Media Desktop',reference:'Referenzen'};
  let currentSeries, currentPage, loadId=0, toastTimer;
  const has = (obj,key) => Object.prototype.hasOwnProperty.call(obj,key);
  const path = p => p.split('/').map(encodeURIComponent).join('/');
  const canMessage = () => window.parent !== window;
  function tellParent() {
    if (canMessage()) parent.postMessage({type:'ui-preview:page',series:currentSeries.id,page:currentPage.id},location.origin === 'null' ? '*' : location.origin);
  }
  function toast(text) {
    clearTimeout(toastTimer); $('toast').textContent = text; $('toast').hidden = false;
    toastTimer = setTimeout(() => { $('toast').hidden = true; }, 6500);
  }
  function options() {
    for (const [group,title] of Object.entries(groupNames)) {
      const el=document.createElement('optgroup'); el.label=title;
      for (const s of data.series.filter(s=>s.group===group)) el.append(new Option(s.title,s.id));
      $('series').append(el);
    }
  }
  function pageById(id) { return currentSeries.pages.find(p=>p.id===id); }
  function syncControls() {
    $('series').value=currentSeries.id;
    $('pages').replaceChildren(...currentSeries.pages.map(p=>new Option(p.label,p.id)));
    $('pages').value=currentPage.id;
    $('counter').textContent=`${currentSeries.pages.indexOf(currentPage)+1} / ${currentSeries.pages.length}`;
    $('announce').textContent=`${currentSeries.title} – ${currentPage.label}`;
    document.title=`${currentPage.label} · ${currentSeries.title} · UI Preview`;
  }
  function renderHotspots() {
    const fragment=document.createDocumentFragment();
    for (const h of currentPage.hotspots || []) {
      if (![h.x,h.y,h.w,h.h].every(Number.isFinite)) continue;
      const target=h.target && pageById(h.target);
      const node=document.createElement(target ? 'a' : 'button');
      node.className=`hotspot${target ? '' : ' missing'}`;
      node.dataset.label=h.label || (target && target.label) || 'Vorschau';
      node.setAttribute('aria-label',node.dataset.label);
      node.title=target ? node.dataset.label : `${node.dataset.label} (nur Vorschau)`;
      Object.assign(node.style,{left:`${h.x}%`,top:`${h.y}%`,width:`${h.w}%`,height:`${h.h}%`});
      if (target) {
        node.href=`#${encodeURIComponent(target.id)}`;
        if(target.id===currentPage.id) node.setAttribute('aria-current','page');
      } else {
        node.type='button';
        node.addEventListener('click',()=>toast(h.message || `${node.dataset.label}: Dafür ist in dieser Design-Serie noch kein Bildschirm vorhanden.`));
      }
      fragment.append(node);
    }
    $('hotspots').replaceChildren(fragment);
  }
  function loadScreen() {
    const id=++loadId;
    $('error').hidden=true; $('stage').hidden=false;
    $('stage').classList.add('loading'); $('stage').setAttribute('aria-busy','true');
    $('hotspots').replaceChildren();
    const next=new Image(); next.decoding='async'; next.alt=currentPage.label;
    const file=currentPage.file;
    next.onload=()=>{
      if(id!==loadId) return;
      const img=$('screen');
      img.onload=()=>{
        if(id!==loadId) return;
        $('stage').classList.remove('loading'); $('stage').setAttribute('aria-busy','false');
        renderHotspots();
      };
      img.width=next.naturalWidth; img.height=next.naturalHeight;
      img.alt=`${currentSeries.title}: ${currentPage.label}`; img.src=next.src;
      $('stage').style.setProperty('--native-width',`${next.naturalWidth}px`);
      if(img.complete) img.onload();
    };
    next.onerror=()=>{
      if(id!==loadId) return;
      $('stage').hidden=true; $('stage').setAttribute('aria-busy','false');
      $('error').hidden=false; $('error-file').textContent=file;
      $('error-message').textContent='Dieses Originalbild fehlt oder konnte nicht geöffnet werden.';
    };
    next.src=path(file);
  }
  function route(notify=true) {
    const params=new URLSearchParams(location.search);
    const requested=params.get('series');
    currentSeries=data.series.find(s=>s.id===requested) || data.series[0];
    let raw='';
    try {raw=decodeURIComponent(location.hash.slice(1));} catch {toast('Ungültige Screen-Adresse. Der erste Screen wird angezeigt.');}
    if(has(aliases,raw)) raw=aliases[raw];
    currentPage=pageById(raw) || pageById(currentSeries.start) || currentSeries.pages[0];
    if(raw && raw!==currentPage.id) toast('Diesen Screen gibt es in der ausgewählten Serie nicht.');
    syncControls(); loadScreen();
    if(notify) tellParent();
  }
  function go(id, notify=true) {
    if(!pageById(id)) {toast('Dieser Bereich wurde in dieser Serie noch nicht gezeichnet.'); return;}
    if(currentPage.id===id) return;
    if(notify) location.hash=id;
    else {history.replaceState(null,'',`#${encodeURIComponent(id)}`);route(false);}
  }
  function step(delta) {
    const pages=currentSeries.pages;
    go(pages[(pages.indexOf(currentPage)+delta+pages.length)%pages.length].id);
  }
  function debug() {
    const enabled=document.body.classList.toggle('debug');
    $('debug').setAttribute('aria-pressed',String(enabled));
  }
  function hide() { const hidden=!$('toolbar').hidden; $('toolbar').hidden=hidden; $('restore').hidden=!hidden; }
  function zoom() { const native=$('stage').classList.toggle('native'); $('zoom').setAttribute('aria-pressed',String(native)); }
  async function fullscreen() {
    try { if(document.fullscreenElement) await document.exitFullscreen(); else if(document.documentElement.requestFullscreen) await document.documentElement.requestFullscreen(); else toast('Vollbild wird hier nicht unterstützt.'); }
    catch {toast('Vollbild ist in dieser Browser-Umgebung nicht verfügbar.');}
  }
  function gallery() {
    $('gallery-title').textContent=currentSeries.title;
    $('gallery-note').textContent=currentSeries.unmapped ? currentSeries.description : 'Einen Screen wählen oder direkt in der Vorschau auf das abgebildete Menü klicken.';
    $('thumbs').replaceChildren(...currentSeries.pages.map(p=>{
      const a=document.createElement('a'); a.href=`#${encodeURIComponent(p.id)}`;
      if(p.id===currentPage.id) a.setAttribute('aria-current','page');
      const img=document.createElement('img'); img.src=path(p.file); img.alt=p.label; img.loading='lazy';
      const span=document.createElement('span');span.textContent=p.label;
      a.append(img,span);a.addEventListener('click',()=>{$('gallery').close();});return a;
    }));
    $('gallery').showModal();
  }
  options(); route();
  $('series').addEventListener('change',()=>{
    const next=data.series.find(s=>s.id===$('series').value);
    const p=next.pages.find(p=>p.id===currentPage.id) || next.pages.find(p=>p.id===next.start);
    history.pushState(null,'',`?series=${encodeURIComponent(next.id)}#${encodeURIComponent(p.id)}`);route();
  });
  $('pages').addEventListener('change',()=>go($('pages').value));
  $('prev').onclick=()=>step(-1); $('next').onclick=()=>step(1);
  $('debug').onclick=debug; $('hide').onclick=hide; $('restore').onclick=hide;
  $('zoom').onclick=zoom; $('full').onclick=fullscreen; $('overview').onclick=gallery;
  $('help').onclick=()=>$('help-dialog').showModal(); $('retry').onclick=loadScreen;
  document.querySelectorAll('[data-close]').forEach(b=>b.onclick=()=>$(b.dataset.close).close());
  document.querySelectorAll('dialog').forEach(d=>d.addEventListener('click',e=>{if(e.target===d)d.close();}));
  window.addEventListener('hashchange',()=>{window.scrollTo(0,0);route();});
  window.addEventListener('popstate',()=>route());
  window.addEventListener('keydown',e=>{
    if(e.altKey||e.ctrlKey||e.metaKey||e.target.closest('input,textarea,select,[contenteditable=true]')||document.querySelector('dialog[open]'))return;
    const actions={ArrowLeft:()=>step(-1),ArrowRight:()=>step(1),Home:()=>go(currentSeries.start),d:debug,h:hide,z:zoom,f:fullscreen,g:gallery,'?':()=>$('help-dialog').showModal()};
    const key=e.key.length===1?e.key.toLowerCase():e.key;
    if(has(actions,key)){e.preventDefault();actions[key]();}
  });
  window.addEventListener('message',e=>{
    if(e.source!==window.parent || e.origin!==location.origin || !e.data || e.data.type!=='ui-preview:navigate')return;
    if(typeof e.data.page==='string')go(e.data.page,false);
  });
})();
