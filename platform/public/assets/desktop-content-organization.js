(() => {
  const escape=value=>String(value??'').replace(/[&<>'"]/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch]));
  const request=async(url,data=null,method='GET')=>{
    const response=await fetch(url,{method,headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},...(data?{body:JSON.stringify(data)}:{})});
    const result=await response.json();if(!response.ok)throw new Error(Object.values(result.errors||{}).flat().join(' ')||result.message);return result;
  };
  const options=(values,t)=>values.map(value=>`<option value="${value}">${escape(t[value]||value)}</option>`).join('');
  document.addEventListener('content-list-loaded',event=>{
    const root=event.target,t=window.desktopImportLabels;
    if(root.dataset.canEdit!=='true')return;
    if(!root.contentOrganization){
      const selected=new Set(),list=root.querySelector('[data-content-list]'),filter=root.querySelector('[data-content-filter]');let selecting=false,items=[];
      const toggle=document.createElement('button');toggle.type='button';toggle.className='desktop-button';toggle.textContent=t.select_records;toggle.dataset.recordSelect='';filter.append(toggle);
      const bulk=document.createElement('form');bulk.hidden=true;bulk.className='media-bulk-form';bulk.dataset.recordBulk='';
      bulk.innerHTML=`<p data-record-count></p><button type="button" data-record-page class="desktop-button">${escape(t.select_page)}</button><button type="button" data-record-clear class="desktop-button">${escape(t.clear_selection)}</button><label>${escape(t.status)}<select name="status"><option value="">—</option>${options(['unsorted','ready','needs_attention'],t)}</select></label><label>${escape(t.target_profile)}<select name="target_profile"><option value="">—</option>${options(['media_library','videos','shorts','posts','polls','comments'],t)}</select></label><label>${escape(t.tags)}<input name="add_tags" maxlength="3000"></label><button type="submit" class="desktop-button">${escape(t.save)}</button><p role="status"></p>`;
      filter.after(bulk);
      const fields=document.createElement('div');fields.className='media-bulk-fields';for(const label of bulk.querySelectorAll('label'))fields.append(label);bulk.querySelector('[type=submit]').before(fields);
      const decorate=()=>{
        list.querySelectorAll('[data-record-check]').forEach(input=>input.remove());list.classList.toggle('is-selecting',selecting);bulk.hidden=!selecting;
        if(selecting)for(const [index,row] of [...list.children].entries()){
          if(!items[index])continue;const input=document.createElement('input');input.type='checkbox';input.dataset.recordCheck=items[index].id;input.checked=selected.has(items[index].id);input.setAttribute('aria-label',items[index].title);row.prepend(input);
        }
        bulk.querySelector('[data-record-count]').textContent=selected.size+' '+t.selected_records;
      };
      toggle.addEventListener('click',()=>{selecting=!selecting;if(!selecting)selected.clear();toggle.setAttribute('aria-pressed',String(selecting));decorate();});
      list.addEventListener('change',event=>{const input=event.target.closest('[data-record-check]');if(!input)return;const id=Number(input.dataset.recordCheck);if(input.checked&&selected.size>=100){input.checked=false;bulk.querySelector('[role=status]').textContent=t.selection_limit;return;}input.checked?selected.add(id):selected.delete(id);decorate();});
      bulk.querySelector('[data-record-clear]').addEventListener('click',()=>{selected.clear();decorate();});
      bulk.querySelector('[data-record-page]').addEventListener('click',()=>{for(const item of items){if(selected.size>=100)break;selected.add(item.id);}decorate();});
      bulk.addEventListener('submit',async event=>{
        event.preventDefault();const button=bulk.querySelector('[type=submit]'),message=bulk.querySelector('[role=status]');if(button.disabled)return;
        try{
          if(!selected.size)throw new Error(t.no_selection);const values=Object.fromEntries(new FormData(bulk)),data={ids:[...selected]};
          for(const key of ['status','target_profile'])if(values[key])data[key]=values[key];const tags=values.add_tags.split(',').map(tag=>tag.trim()).filter(Boolean);if(tags.length)data.add_tags=[...new Set(tags)];
          button.disabled=true;const result=await request('/desktop/content/organize',data,'PATCH');selected.clear();bulk.reset();decorate();message.textContent=t.saved+': '+result.count;document.dispatchEvent(new Event('desktop-media-changed'));
        }catch(error){message.textContent=error.message;}finally{button.disabled=false;}
      });
      root.contentOrganization=data=>{items=data.items;const disabled=data.playlist||filter.querySelector('[name=trash]')?.value==='deleted';toggle.hidden=disabled;if(disabled){selected.clear();selecting=false;}decorate();};
    }
    root.contentOrganization(event.detail);
  });
  document.addEventListener('content-selected',event=>{
    const item=event.detail,details=event.target,t=window.desktopImportLabels;
    if(item.kind!=='playlist'||details.closest('[data-content-library]').dataset.canEdit!=='true')return;
    const panel=document.createElement('details');panel.className='media-inspector';panel.dataset.playlistEditor='';
    panel.innerHTML=`<summary>${escape(t.edit_playlist)}</summary><form data-playlist-edit><label>${escape(t.title)}<input name="title" required maxlength="255" value="${escape(item.title)}"></label><label>${escape(t.description)}<textarea name="description" maxlength="10000">${escape(item.body)}</textarea></label><button type="submit" class="desktop-button">${escape(t.save)}</button></form><ol>${item.items.map(member=>`<li value="${member.position}">${escape(member.title||member.source_id||t.unavailable)} ${['up','down','remove'].map(action=>`<button type="button" class="desktop-button" data-playlist-member="${member.id}" data-playlist-action="${action}">${escape(t['member_'+action])}</button>`).join('')}</li>`).join('')}</ol><form data-playlist-search><label>${escape(t.playlist_add)}<input name="playlist_query" maxlength="120"></label><button type="submit" class="desktop-button">${escape(t.cover_find)}</button></form><div data-playlist-results></div><p role="status"></p>`;
    details.append(panel);const base='/desktop/content/playlists/'+item.id,message=panel.querySelector('[role=status]');
    panel.querySelectorAll('form').forEach(form=>form.classList.add('content-assignment'));
    const reload=()=>{delete details.dataset.dirty;details.closest('[data-content-library]').dispatchEvent(new CustomEvent('local-content-open',{detail:{url:details.dataset.currentUrl}}));document.dispatchEvent(new Event('desktop-media-changed'));};
    panel.querySelector('[data-playlist-edit]').addEventListener('input',()=>{details.dataset.dirty='true';});
    panel.querySelector('[data-playlist-edit]').addEventListener('submit',async event=>{event.preventDefault();const button=event.target.querySelector('[type=submit]');button.disabled=true;try{await request(base,Object.fromEntries(new FormData(event.target)),'PATCH');reload();}catch(error){message.textContent=error.message;button.disabled=false;}});
    let generation=0;
    const search=async(page=1)=>{
      const current=++generation;
      try{const result=await request('/desktop/content?'+new URLSearchParams({section:'videos',q:panel.querySelector('[name=playlist_query]').value,page}));if(current!==generation||!panel.isConnected)return;
        panel.querySelector('[data-playlist-results]').innerHTML=result.data.map(record=>`<p><button type="button" class="desktop-button" data-playlist-add="${record.id}">${escape(record.title)}</button></p>`).join('')+`<button type="button" data-playlist-page="${page-1}" ${page<=1?'disabled':''}>‹</button> ${page}/${result.meta.last_page} <button type="button" data-playlist-page="${page+1}" ${page>=result.meta.last_page?'disabled':''}>›</button>`;
      }catch(error){message.textContent=error.message;}
    };
    panel.querySelector('[data-playlist-search]').addEventListener('submit',event=>{event.preventDefault();search();});
    panel.addEventListener('click',async event=>{
      const pager=event.target.closest('[data-playlist-page]');if(pager){search(Number(pager.dataset.playlistPage));return;}
      const button=event.target.closest('[data-playlist-member],[data-playlist-add]');if(!button)return;
      if(details.dataset.dirty==='true'){message.textContent=t.save_before_ai;return;}
      if(button.dataset.playlistAction==='remove'&&!confirm(t.playlist_remove_confirm))return;button.disabled=true;
      try{if(button.dataset.playlistAdd)await request(base+'/members',{record_id:Number(button.dataset.playlistAdd)},'POST');else await request(base+'/members/'+button.dataset.playlistMember,{action:button.dataset.playlistAction},'PATCH');reload();}catch(error){message.textContent=error.message;button.disabled=false;}
    });
  });
})();
