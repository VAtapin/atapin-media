(() => {
  const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const t=()=>window.desktopImportLabels||{};
  const send=async(url,data,method='POST')=>{
    const reply=await fetch(url,{method,credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},body:JSON.stringify(data)});
    const result=await reply.json();if(!reply.ok)throw new Error(Object.values(result.errors||{}).flat().join(' ')||result.message||t().load_error);return result;
  };
  const changed=()=>document.dispatchEvent(new Event('desktop-media-changed'));
  const selected=event=>{
    const item=event.detail,details=event.target,root=details.closest('[data-content-library]');
    if(!root||root.dataset.canEdit!=='true'||item.kind==='playlist')return;
    const tools=document.createElement('section');tools.className='media-inspector';tools.dataset.contentLifecycle='';
    tools.innerHTML=`<div class="media-library-toolbar-row"><button class="desktop-button" type="button" data-trash>${esc(item.trashed?t().trash_restore:t().delete_content)}</button>${!item.trashed&&root.dataset.canMediaEdit==='true'?`<button class="desktop-button" type="button" data-choose-role="cover">${esc(t().replace_cover)}</button>${['video','short'].includes(item.kind)?`<button class="desktop-button" type="button" data-choose-role="video">${esc(t().replace_video)}</button>`:''}<button class="desktop-button" type="button" data-choose-role="attachment">${esc(t().add_attachment)}</button>`:''}</div><p role="status"></p><div data-choice hidden></div>${!item.trashed&&root.dataset.canMediaEdit==='true'?`<div>${item.assets.map(asset=>`<p>${esc(asset.title)} <button class="desktop-button" type="button" data-replace-asset="${asset.id}">${esc(t().replace_attachment)}</button> <button class="desktop-button" type="button" data-detach-asset="${asset.id}">${esc(t().remove_attachment)}</button></p>`).join('')}</div>`:''}`;
    details.prepend(tools);const message=tools.querySelector('[role=status]'),choice=tools.querySelector('[data-choice]');let mode,generation=0;
    const dirty=()=>{if(details.dataset.dirty==='true'){message.textContent=t().save_before_ai;return true;}return false;};
    const reload=()=>{root.dispatchEvent(new CustomEvent('local-content-open',{detail:{url:'/desktop/content/'+item.id}}));changed();};
    const apply=async id=>{if(dirty())return;await send('/desktop/content/'+item.id+'/assets',{...mode,media_id:id});reload();};
    const search=async(page=1)=>{
      const current=++generation;
      try{
        const params=new URLSearchParams({archive:'active',page,q:choice.querySelector('[name=q]').value});if(mode.kind)params.set('kind',mode.kind);
        const reply=await fetch('/desktop/media/library?'+params,{headers:{Accept:'application/json'},credentials:'same-origin'});if(!reply.ok)throw new Error(t().load_error);const result=await reply.json();
        if(current!==generation||!tools.isConnected)return;
        choice.querySelector('[data-results]').innerHTML=result.data.map(file=>`<p><button class="desktop-button" type="button" data-use-media="${file.id}">${esc(file.title)} · ${esc(file.formatted_size)}</button></p>`).join('')+`<div class="media-library-pagination"><button class="desktop-button" type="button" data-choice-page="${page-1}" ${page<=1?'disabled':''}>‹</button><span>${page}/${result.meta.last_page}</span><button class="desktop-button" type="button" data-choice-page="${page+1}" ${page>=result.meta.last_page?'disabled':''}>›</button></div>`;
      }catch(e){message.textContent=e.message;}
    };
    const choose=(role,old=null)=>{
      if(dirty())return;const kind=role==='cover'?'image':role==='video'?'video':old?.kind;
      mode={action:role==='attachment'&&!old?'attach':'replace',role,...old?{old_media_id:old.id}:{},kind};choice.hidden=false;
      choice.innerHTML=`<p>${esc(t().replacement_hint)}</p>${root.dataset.canUpload==='true'?`<label>${esc(t().replacement_upload)}<input type="file" data-replacement-upload ${kind==='image'?'accept="image/*"':kind==='video'?'accept="video/*"':''}></label>`:''}<form><label>${esc(t().replacement_existing)}<input type="search" name="q" maxlength="120"></label><button class="desktop-button" type="submit">${esc(t().cover_find)}</button></form><div data-results></div>`;
      choice.querySelector('form').onsubmit=e=>{e.preventDefault();search();};
      choice.querySelector('[data-replacement-upload]')?.addEventListener('change',async e=>{
        const file=e.target.files[0];if(!file||dirty())return;tools.inert=true;
        try{const id=await window.uploadDesktopMedia(file,root.dataset.userId,(n,total)=>message.textContent=t().upload_running+' '+Math.floor(n/total*100)+' %');await apply(id);}
        catch(error){message.textContent=error.message;}finally{tools.inert=false;}
      });search();
    };
    tools.addEventListener('click',async event=>{
      const button=event.target.closest('button');if(!button)return;
      try{
        if(button.hasAttribute('data-trash')){
          if(dirty()||(!item.trashed&&!confirm(t().delete_content_confirm)))return;button.disabled=true;
          await send('/desktop/content/'+item.id+(item.trashed?'/restore':''),item.trashed?{}:{confirmation:'DELETE'},item.trashed?'POST':'DELETE');details.replaceChildren();changed();return;
        }
        if(button.dataset.chooseRole)return choose(button.dataset.chooseRole);
        if(button.dataset.replaceAsset)return choose('attachment',item.assets.find(a=>a.id===button.dataset.replaceAsset));
        if(button.dataset.detachAsset){if(dirty()||!confirm(t().remove_attachment_confirm))return;await send('/desktop/content/'+item.id+'/assets',{action:'detach',role:'attachment',old_media_id:button.dataset.detachAsset});return reload();}
        if(button.dataset.choicePage)return search(Number(button.dataset.choicePage));
        if(button.dataset.useMedia){if(dirty()||!confirm(t().replacement_confirm))return;button.disabled=true;await apply(button.dataset.useMedia);}
      }catch(e){message.textContent=e.message;button.disabled=false;}
    });
  };
  document.addEventListener('content-selected',selected);document.addEventListener('content-trashed-selected',selected);
  window.appendMediaLifecycle=(details,item)=>{
    if(details.closest('[data-media-library]')?.dataset.canEdit!=='true')return;
    const tools=document.createElement('section');tools.className='media-inspector';tools.innerHTML=`<button type="button" class="desktop-button">${esc(item.archived?t().trash_restore:t().delete_file)}</button><p role="status"></p>`;details.prepend(tools);
    tools.querySelector('button').onclick=async e=>{if(details.dataset.dirty==='true'){tools.querySelector('[role=status]').textContent=t().save_before_ai;return;}if(!item.archived&&!confirm(t().delete_file_confirm))return;e.target.disabled=true;try{await send('/desktop/media/organize',{ids:[item.id],archived:!item.archived},'PATCH');details.replaceChildren();document.dispatchEvent(new CustomEvent('desktop-media-changed',{detail:{fileRemoved:true}}));}catch(error){tools.querySelector('[role=status]').textContent=error.message;e.target.disabled=false;}};
  };
})();
