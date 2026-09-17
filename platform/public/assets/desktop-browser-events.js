// Browser Studio keeps its own deliberate event choice; the OBS editor may open the first row automatically.
export function mountBrowserEvents({root,panel,t,request,onSelect}){
  const api=root.dataset.apiBase;
  const section=document.createElement('section');section.className='desktop-browser-event-choice';
  const label=document.createElement('label');label.textContent=t('choose_event');
  const choice=document.createElement('select');choice.dataset.browserEventChoice='';label.append(choice);
  const hint=document.createElement('p');hint.className='desktop-browser-event-choice-hint';
  const quick=document.createElement('div');quick.className='desktop-browser-quick-event';quick.hidden=true;
  const field=(text,node)=>{const wrapper=document.createElement('label');wrapper.textContent=text;wrapper.append(node);quick.append(wrapper);return node;};
  const title=field(t('quick_title'),document.createElement('input'));title.maxLength=255;title.required=true;title.dataset.browserQuickTitle='';
  const body=field(t('quick_description'),document.createElement('textarea'));body.maxLength=10000;body.rows=2;body.dataset.browserQuickDescription='';
  const poster=field(t('quick_poster'),document.createElement('input'));poster.type='file';poster.accept='image/jpeg,image/png,image/webp,image/gif';poster.dataset.browserQuickPoster='';
  const preview=document.createElement('img');preview.alt='';preview.hidden=true;poster.parentElement.append(preview);
  const quickHint=document.createElement('small');quickHint.textContent=t('quick_hint');quick.append(quickHint);
  section.append(label,hint,quick);panel.insertBefore(section,panel.querySelector('.desktop-browser-layout'));
  const place=detached=>(detached?section:panel.querySelector('.desktop-browser-preview')).append(quick);
  place(false);
  let current=null,draft=null,previewUrl=null,uploadedCover=null,generation=0;
  const dispose=()=>{generation++;if(previewUrl)URL.revokeObjectURL(previewUrl);};
  const resetPreview=()=>{if(previewUrl)URL.revokeObjectURL(previewUrl);previewUrl=null;preview.hidden=true;preview.removeAttribute('src');};
  poster.onchange=()=>{
    resetPreview();uploadedCover=null;const file=poster.files?.[0];if(!file)return;
    if(!['image/jpeg','image/png','image/webp','image/gif'].includes(file.type)||file.size>50*1024*1024){poster.value='';hint.textContent=t('quick_poster_invalid');return;}
    hint.textContent='';previewUrl=URL.createObjectURL(file);preview.src=previewUrl;preview.hidden=false;
  };
  window.enhanceDesktopFileInput(poster,{profile:'poster',preview:false,userId:root.dataset.userId,onUploaded:id=>{uploadedCover=id;hint.textContent=t('quick_saved');},onError:error=>{hint.textContent=error.message;}});
  const select=async()=>{
    const own=++generation;quick.hidden=choice.value!=='new';hint.textContent='';
    if(choice.value==='new'){current=null;onSelect(null);return;}
    if(!choice.value){current=null;onSelect(null);return;}
    try{const response=await request(api+'/'+encodeURIComponent(choice.value));if(own!==generation)return;current=response.data;onSelect(current);}
    catch(error){if(own===generation){choice.value='';current=null;hint.textContent=error.message;onSelect(null);}}
  };
  choice.onchange=select;
  const refresh=async(activeId=null)=>{
    const own=++generation;const items=[];let page=1,lastPage=1;
    try{
      do{const response=await request(api+'?filter=browser_today&page='+page);items.push(...(response.data||[]));lastPage=response.pagination?.last_page||1;page++;}while(page<=lastPage);
      if(own!==generation)return;
      choice.replaceChildren(new Option(t('choose_placeholder'),''));
      for(const item of items){const time=new Intl.DateTimeFormat(document.documentElement.lang||'de',{timeStyle:'short'}).format(new Date(item.starts_at));choice.append(new Option(time+' · '+item.title,String(item.id)));}
      choice.append(new Option(t('quick_new'),'new'));
      if(activeId){
        if(!items.some(item=>String(item.id)===String(activeId)))choice.add(new Option(t('active_event'),String(activeId)),choice.options.length-1);
        choice.value=String(activeId);await select();
      }
      else if(!items.length){choice.value='new';quick.hidden=false;hint.textContent=t('no_today');onSelect(null);}
      else{choice.value='';quick.hidden=true;hint.textContent=t('choose_hint');onSelect(null);}
    }catch(error){if(own===generation){choice.replaceChildren(new Option(t('quick_new'),'new'));choice.value='new';quick.hidden=false;hint.textContent=error.message;onSelect(null);}}
  };
  const validate=()=>{
    if(choice.value==='new'){if(!title.value.trim()){title.focus();throw new Error(t('quick_title_required'));}return;}
    if(!choice.value||!current||String(current.id)!==choice.value)throw new Error(t('choose_required'));
  };
  const ensureEvent=async()=>{
    validate();
    if(poster._desktopUploader?.uploading)throw new Error(t('quick_poster_uploading'));
    if(choice.value!=='new'){
      if(!current||String(current.id)!==choice.value)throw new Error(t('choose_required'));
      return current;
    }
    const name=title.value.trim();if(!name){title.focus();throw new Error(t('quick_title_required'));}
    const cover=uploadedCover||draft?.cover_media_id||null;
    const payload={title:name,body:body.value.trim(),starts_at:null,published:false,enabled:false};
    if(cover)payload.cover_media_id=cover;
    const result=await request(draft?api+'/'+encodeURIComponent(draft.id):api,{method:draft?'PATCH':'POST',body:JSON.stringify(payload)});
    draft=result.data;current=draft;hint.textContent=t('quick_saved');onSelect(current);
    return current;
  };
  const complete=data=>{
    current=data;
    if(choice.value==='new'){
      const option=new Option(data.title,String(data.id));choice.add(option,choice.options.length-1);
      choice.value=String(data.id);quick.hidden=true;draft=null;uploadedCover=null;title.value='';body.value='';resetPreview();
    }
    hint.textContent='';onSelect(current);
  };
  const lock=value=>{choice.disabled=value;title.disabled=value;body.disabled=value;poster.disabled=value;};
  return {refresh,validate,ensureEvent,complete,lock,place,dispose,selected:()=>current,mode:()=>choice.value};
}
