(() => {
  document.addEventListener('media-inspector-loaded',event=>{
    const {data,item}=event.detail,node=event.target,t=window.desktopImportLabels;
    const section=document.createElement('section'),heading=document.createElement('h4');heading.textContent=t.technical_data;section.append(heading);node.append(section);
    const render=data=>{
      section.querySelectorAll('dl,p').forEach(element=>element.remove());const dl=document.createElement('dl');
      for(const [key,value] of Object.entries(data.technical||{})){const dt=document.createElement('dt'),dd=document.createElement('dd');dt.textContent=t['technical_'+key]||key;dd.textContent=String(value)+(key==='duration'?' s':'');dl.append(dt,dd);}section.append(dl);
      if(data.technical_status==='failed'){const p=document.createElement('p');p.textContent=t.probe_failed;section.append(p);}
    };render(data);
    if(!['video','audio','image'].includes(item.kind)||node.closest('[data-media-library]').dataset.canEdit!=='true')return;
    const button=document.createElement('button');button.type='button';button.className='desktop-button';button.textContent=t.probe_local;
    const refresh=document.createElement('button');refresh.type='button';refresh.className='desktop-button';refresh.textContent=t.refresh;section.append(button,refresh);
    button.addEventListener('click',async()=>{
      if(node.closest('[data-library-details]').dataset.dirty==='true')return;button.disabled=true;
      try{const response=await fetch('/desktop/media/'+item.id+'/technical',{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content}});if(!response.ok)throw new Error(t.load_error);const p=document.createElement('p');p.textContent=t.probe_queued;section.append(p);}
      catch(error){const p=document.createElement('p');p.textContent=error.message;section.append(p);}finally{button.disabled=false;}
    });
    refresh.addEventListener('click',async()=>{refresh.disabled=true;try{const response=await fetch(item.detail_url,{headers:{Accept:'application/json'}});if(!response.ok)throw new Error(t.load_error);render(await response.json());}catch(error){const p=document.createElement('p');p.textContent=error.message;section.append(p);}finally{refresh.disabled=false;}});
  });
})();
