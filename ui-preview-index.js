(() => {
  'use strict';
  const $=id=>document.getElementById(id), series=window.UI_PREVIEW.series;
  const title={public:'Öffentliche Website',admin:'Media Desktop',reference:'Referenz'};
  const url=(id,page)=>`ui-preview.html?series=${encodeURIComponent(id)}#${encodeURIComponent(page)}`;
  const path=p=>p.split('/').map(encodeURIComponent).join('/');
  const cards=series.map(s=>{
    const el=document.createElement('article');el.className='card';el.dataset.group=s.group;
    const link=document.createElement('a');link.className='thumb';link.href=url(s.id,s.start);
    const img=document.createElement('img');img.src=path(s.pages.find(p=>p.id===s.start).file);img.alt=s.title;img.loading='lazy';img.decoding='async';link.append(img);
    const body=document.createElement('div');body.className='body';
    const meta=document.createElement('small');meta.textContent=`${title[s.group]} · ${s.pages.length} Screens${s.unmapped ? ' · Bildfolge' : ' · klickbar'}`;
    const h=document.createElement('h3');h.textContent=s.title;
    const p=document.createElement('p');p.textContent=s.description;
    const open=document.createElement('a');open.href=link.href;open.textContent=s.unmapped?'Bildfolge öffnen →':'Klickbaren Prototyp öffnen →';
    const pages=document.createElement('nav');pages.className='pages';pages.setAttribute('aria-label',`${s.title}: Bildschirme`);
    s.pages.forEach(p=>{const a=document.createElement('a');a.href=url(s.id,p.id);a.textContent=p.label;pages.append(a);});
    body.append(meta,h,p,open,pages);el.append(link,body);return el;
  });
  $('grid').append(...cards);
  const interactive=series.filter(s=>!s.unmapped);
  $('counts').textContent=`${interactive.length} klickbare Serien · ${interactive.reduce((n,s)=>n+s.pages.length,0)} verknüpfte Screens · weitere Bildfolgen und UI-Kits separat`;
  document.querySelectorAll('[data-group]').forEach(b=>b.addEventListener('click',()=>{
    document.querySelectorAll('[data-group]').forEach(n=>n.setAttribute('aria-pressed',String(n===b)));
    cards.forEach(c=>c.hidden=b.dataset.group!=='all'&&c.dataset.group!==b.dataset.group);
  }));
  for(const side of ['left','right'])for(const s of series)$(side).append(new Option(s.title,s.id));
  $('left').value='frontend-gold-a';$('right').value='frontend-gold-b';
  let initialized=false;
  const states={left:'home',right:'home'};
  function config(side){return series.find(s=>s.id===$(side).value);}
  function common(){
    const comparable=config('left').group===config('right').group&&config('left').group!=='reference';
    const list=comparable?config('left').pages.filter(p=>config('right').pages.some(q=>q.id===p.id)):[];
    $('sync').disabled=!comparable;
    const prev=$('compare-page').value;
    $('compare-page').replaceChildren(...list.map(p=>new Option(p.label,p.id)));
    if(list.some(p=>p.id===prev))$('compare-page').value=prev;
    $('compare-page').disabled=!list.length;
    return list;
  }
  function updateLink(side){$(`open-${side}`).href=url($(side).value,states[side]);}
  function load(side,page){
    const s=config(side);states[side]=s.pages.some(p=>p.id===page)?page:s.start;
    $(side==='left'?'lf':'rf').src=url(s.id,states[side]);updateLink(side);
  }
  $('compare-toggle').onclick=()=>{
    const open=$('comparison').hidden;$('comparison').hidden=!open;
    $('compare-toggle').setAttribute('aria-expanded',String(open));
    $('compare-toggle').textContent=open?'Vergleich schließen':'Zwei Designs vergleichen';
    if(open&&!initialized){common();load('left','home');load('right','home');initialized=true;}
  };
  for(const side of ['left','right'])$(side).onchange=()=>{const list=common();load(side,list[0]?.id||config(side).start);};
  $('compare-page').onchange=()=>{for(const side of ['left','right'])load(side,$('compare-page').value);};
  window.addEventListener('message',e=>{
    if(e.origin!==location.origin || !e.data || e.data.type!=='ui-preview:page')return;
    const side=e.source===$('lf').contentWindow?'left':e.source===$('rf').contentWindow?'right':null;
    if(!side || e.data.series!==config(side).id || !config(side).pages.some(p=>p.id===e.data.page))return;
    states[side]=e.data.page;updateLink(side);
    if(!$('sync').checked||$('sync').disabled)return;
    const other=side==='left'?'right':'left';
    if(!config(other).pages.some(p=>p.id===e.data.page)){
      $('compare-status').textContent='Kein entsprechender Screen im anderen Design. Dort bleibt die bisherige Seite geöffnet.';return;
    }
    states[other]=e.data.page;updateLink(other);
    $(other==='left'?'lf':'rf').contentWindow.postMessage({type:'ui-preview:navigate',page:e.data.page},location.origin==='null'?'*':location.origin);
    $('compare-page').value=e.data.page;
    $('compare-status').textContent=`Gleicher Bereich: ${config(side).pages.find(p=>p.id===e.data.page).label}`;
  });
})();
