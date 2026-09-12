(() => {
  const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const optionText=option=>typeof option==='string'?option:(option.text||option.label||'');
  document.addEventListener('content-selected',async event=>{
    const item=event.detail,host=event.target,t=window.desktopImportLabels||{};
    if(!['video','short','post'].includes(item.kind))return;
    const section=document.createElement('section');section.className='media-inspector';host.insertBefore(section,host.querySelector('.content-assignment'));
    let page=1;
    const load=async()=>{
      try {
        const reply=await fetch(`/desktop/content/${encodeURIComponent(item.id)}/children?page=${page}`,{credentials:'same-origin',headers:{Accept:'application/json'}});
        if(!reply.ok)throw new Error(t.load_error);const data=await reply.json();if(!section.isConnected)return;
        section.innerHTML=`<h4>${esc(t.composite_children)} (${data.meta.total})</h4>${data.data.map(child=>child.kind==='poll'?`<div><strong>${esc(t.kind_poll)}</strong><ul>${(child.poll?.options||child.poll?.choices||[]).map(option=>`<li>${esc(optionText(option))}</li>`).join('')}</ul></div>`:`<article><small>${esc(child.author||t.kind_comment)}</small><p class="content-original-text">${esc(child.body)}</p></article>`).join('')}<div class="media-library-pagination"><button class="desktop-button" type="button" data-prev ${page<=1?'disabled':''}>‹</button><span>${page}/${data.meta.last_page}</span><button class="desktop-button" type="button" data-next ${page>=data.meta.last_page?'disabled':''}>›</button></div>`;
        section.querySelector('[data-prev]').onclick=()=>{page--;load();};section.querySelector('[data-next]').onclick=()=>{page++;load();};
      }catch(e){if(section.isConnected)section.textContent=e.message;}
    };await load();
  });
})();
