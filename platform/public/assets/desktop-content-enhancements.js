(() => {
  const labels=()=>window.desktopImportLabels||{};
  const refreshers=new WeakMap();
  const automaticDescriptionQueue=new WeakSet();
  document.addEventListener('content-list-loaded',event=>{
    const root=event.target.closest?.('[data-content-library]');
    if(!root||automaticDescriptionQueue.has(root)||event.detail?.playlist||root.dataset.canEdit!=='true'||root.querySelector('[name=trash]')?.value==='deleted')return;
    automaticDescriptionQueue.add(root);
    fetch('/desktop/content/short-descriptions',{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({missing:true})}).then(()=>refreshers.get(root.querySelector('[data-content-details]'))?.()).catch(()=>{});
  });
  document.addEventListener('content-enhancements-queued',event=>refreshers.get(event.target)?.());
  document.addEventListener('content-selected',event=>{
    const item=event.detail,details=event.target,root=details.closest('[data-content-library]');
    if(!root||root.dataset.canEdit!=='true'||root.dataset.canMediaEdit!=='true'||item.archive_data||!['video','short','post'].includes(item.kind))return;
    const panel=document.createElement('section');panel.className='media-inspector';panel.dataset.mediaPreparation='';
    const heading=document.createElement('h4');heading.textContent=labels().media_preparation;panel.append(heading);
    for(const operation of ['frame','ai_cover',...(item.public_section==='podcast'?['podcast']:[])]){
      if(operation==='frame'&&!item.has_local_video)continue;
      const row=document.createElement('div'),button=document.createElement('button'),state=document.createElement('small');button.type='button';button.className='desktop-button';button.dataset.prepareMedia=operation;button.textContent=labels()[operation==='frame'?'frame_cover':operation==='podcast'?'prepare_podcast':'ai_cover'];
      state.textContent=labels()['enhancement_'+(item.media_jobs?.[operation]?.state||'')]||'';row.append(button,state);panel.append(row);
      button.addEventListener('click',async()=>{
        if(details.dataset.dirty==='true'){state.textContent=labels().save_before_ai;return;}if(button.disabled)return;button.disabled=true;
        try{
          const response=await fetch('/desktop/content/'+item.id+'/prepare-media',{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},body:JSON.stringify({operation})});
          const data=await response.json();if(!response.ok)throw new Error(data.message||labels().load_error);state.textContent=labels().enhancement_queued;poll();
        }catch(error){state.textContent=error.message;}finally{button.disabled=false;}
      });
    }
    const hint=document.createElement('small');hint.textContent=labels().ai_cover_hint;panel.append(hint);details.append(panel);
    let timer,attempts=0;
    const poll=()=>{clearTimeout(timer);timer=setTimeout(async()=>{
      if(!panel.isConnected||details.dataset.recordId!==String(item.id)||++attempts>240)return;
      try{
        const response=await fetch(details.dataset.currentUrl,{headers:{Accept:'application/json'}});if(!response.ok)return;const current=await response.json();
        let pending=['queued','running'].includes(current.short_description_job),done=false;
        for(const row of panel.querySelectorAll('[data-prepare-media]')){
          const state=current.media_jobs?.[row.dataset.prepareMedia]?.state;if(state){const node=row.nextElementSibling,next=labels()['enhancement_'+state]||state;done||=node.textContent!==next&&['completed','superseded'].includes(state);node.textContent=next;pending||=['queued','running'].includes(state);}
        }
        const short=details.querySelector('[name=short_description]');if(short&&details.dataset.dirty!=='true'&&current.short_description_job==='completed')short.value=current.short_description||'';
        if(done&&details.dataset.dirty!=='true')root.dispatchEvent(new CustomEvent('local-content-open',{detail:{url:details.dataset.currentUrl}}));else if(pending)poll();
      }catch{poll();}
    },5000);};
    if(Object.values(item.media_jobs||{}).some(job=>['queued','running'].includes(job.state))||item.short_description_job==='queued')poll();
    refreshers.set(details,poll);
  });
  document.addEventListener('click',async event=>{
    const button=event.target.closest('[data-short-descriptions-missing]');if(!button||button.disabled)return;
    const root=button.closest('[data-content-library]'),message=root.querySelector('[data-content-summary]');
    if(root.querySelector('[data-content-details]')?.dataset.dirty==='true'){message.textContent=labels().save_before_ai;return;}
    button.disabled=true;
    try{
      const response=await fetch('/desktop/content/short-descriptions',{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},body:JSON.stringify({missing:true})});
      const result=await response.json();if(!response.ok)throw new Error(result.message||labels().load_error);
      message.textContent=labels().ai_queued+': '+result.count+' / '+result.requests;
    }catch(error){message.textContent=error.message;}finally{button.disabled=false;}
  });
})();
