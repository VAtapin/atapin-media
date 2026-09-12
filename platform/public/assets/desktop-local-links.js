(() => {
  const escape = value => String(value ?? '').replace(/[&<>'"]/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch]));
  const request = async (url,data) => {
    const response=await fetch(url,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},body:JSON.stringify(data || {})});
    const result=await response.json(); if(!response.ok) throw new Error(result.message); return result;
  };
  document.addEventListener('click', async event => {
    const repair=event.target.closest('[data-repair-local-links]');
    if(repair) {
      repair.disabled=true;
      try {await request('/desktop/content/local-links'); repair.parentElement.querySelector('[role=status]').textContent=window.desktopImportLabels.links_queued;}
      catch(error) {repair.parentElement.querySelector('[role=status]').textContent=error.message;}
      finally {repair.disabled=false;}
    }
    const usage=event.target.closest('[data-open-local-content]'); if(!usage) return;
    const media=usage.closest('[data-media-library]');
    if(media.querySelector('[data-library-content-container]').hidden) media.querySelector('[data-library-content-toggle]').click();
    media.querySelector('[data-content-library]').dispatchEvent(new CustomEvent('local-content-open',{detail:{url:usage.dataset.openLocalContent}}));
  });
  document.addEventListener('content-selected',event => {
    const item=event.detail, details=event.target, t=window.desktopImportLabels;
    if(!['video','short'].includes(item.kind) || details.closest('[data-content-library]').dataset.canEdit!=='true') return;
    const panel=document.createElement('details'); panel.className='media-inspector';
    panel.innerHTML=`<summary>${escape(t.attach_local_video)}</summary><p>${escape(t.attach_local_hint)}</p><form><input name="video_query" maxlength="120" aria-label="${escape(t.cover_search)}" placeholder="${escape(t.cover_search)}"><button type="submit" class="desktop-button">${escape(t.cover_find)}</button></form><div data-local-video-results></div><p role="status"></p>`;
    details.append(panel);
    let page=1, generation=0;
    const search=async(number=1) => {
      const current=++generation;
      try {
        const response=await fetch('/desktop/media/library?'+new URLSearchParams({kind:'video',archive:'active',q:panel.querySelector('[name=video_query]').value,page:number}),{headers:{Accept:'application/json'}});
        if(!response.ok) throw new Error(t.load_error); const result=await response.json();
        if(current!==generation || !panel.isConnected) return;
        page=number;
        panel.querySelector('[data-local-video-results]').innerHTML=result.data.map(file=>`<p><button type="button" class="desktop-button" data-attach-video="${file.id}">${escape(file.title)} · ${escape(file.formatted_size)}</button></p>`).join('')+`<button type="button" data-local-page="${page-1}" ${page<=1?'disabled':''}>‹</button> ${page} / ${result.meta.last_page} <button type="button" data-local-page="${page+1}" ${page>=result.meta.last_page?'disabled':''}>›</button>`;
      } catch(error) {panel.querySelector('[role=status]').textContent=error.message;}
    };
    panel.querySelector('form').addEventListener('submit',event=>{event.preventDefault();search();});
    panel.addEventListener('toggle',()=>{if(panel.open&&!panel.dataset.loaded){panel.dataset.loaded='true';search();}});
    panel.addEventListener('click',async event=>{
      const pager=event.target.closest('[data-local-page]'); if(pager) {search(Number(pager.dataset.localPage));return;}
      const button=event.target.closest('[data-attach-video]'); if(!button)return;
      if(details.dataset.dirty==='true'){panel.querySelector('[role=status]').textContent=t.save_before_ai;return;}
      if(!confirm(t.attach_local_confirm+'\n'+button.textContent))return;
      button.disabled=true;
      try {await request('/desktop/content/'+item.id+'/local-video',{media_id:button.dataset.attachVideo}); details.closest('[data-content-library]').dispatchEvent(new CustomEvent('local-content-open',{detail:{url:'/desktop/content/'+item.id}}));document.dispatchEvent(new Event('desktop-media-changed'));}
      catch(error){button.disabled=false;panel.querySelector('[role=status]').textContent=error.message;}
    });
  });
})();
