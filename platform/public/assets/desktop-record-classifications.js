(() => {
  const escape=value=>String(value??'').replace(/[&<>'"]/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch]));
  document.addEventListener('content-selected',event=>{
    const item=event.detail,details=event.target,t=window.desktopImportLabels;
    if(!item.classifications?.length)return;
    const panel=document.createElement('details');panel.className='media-inspector';panel.dataset.recordHistory='';
    panel.innerHTML=`<summary>${escape(t.ai_history)}</summary>${item.classifications.map(log=>`<p>${escape(log.provider)} · ${escape(log.model)} · ${escape(t['ai_log_'+log.status]||log.status)} ${log.confidence!==null?Math.round(Number(log.confidence)*100)+' %':''}</p>${log.proposal?`<p class="content-original-text">${escape(log.proposal.title)}<br>${escape(log.proposal.summary)}<br>${escape((log.proposal.tags||[]).join(', '))}</p>`:''}${details.closest('[data-content-library]').dataset.canEdit==='true'&&log.undo_url?`<button type="button" class="desktop-button" data-record-undo="${escape(log.undo_url)}">${escape(t.undo_ai)}</button>`:''}`).join('')}<p role="status"></p>`;
    details.append(panel);
    if(details.dataset.reopenRecordHistory===String(item.id)){panel.open=true;delete details.dataset.reopenRecordHistory;}
    panel.addEventListener('click',async event=>{
      const button=event.target.closest('[data-record-undo]');if(!button)return;
      const message=panel.querySelector('[role=status]');if(details.dataset.dirty==='true'){message.textContent=t.save_before_ai;return;}button.disabled=true;
      try{const response=await fetch(button.dataset.recordUndo,{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content}});const result=await response.json();if(!response.ok)throw new Error(result.message||t.load_error);
        details.dataset.reopenRecordHistory=String(item.id);
        details.closest('[data-content-library]').dispatchEvent(new CustomEvent('local-content-open',{detail:{url:details.dataset.currentUrl}}));document.dispatchEvent(new Event('desktop-media-changed'));
      }catch(error){message.textContent=error.message;button.disabled=false;}
    });
  });
})();
