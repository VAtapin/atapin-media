(() => {
  const W=window.DesktopWorkspaces,{t,el,field,lookup,button,request}=W;
  window.extendDesktopContentEditor=(form,item,details)=>{
    if(item.archive_data||!['video','short','post'].includes(item.kind))return;
    const section=el('details');section.append(el('summary',t('content_report')));
    const fields=[['project_id','projects'],['taxonomy_term_ids','terms'],['cover_media_id','media'],['additional_media_ids','media']];
    for(const [name,kind] of fields){const label=field(name,'select');section.append(label);const multiple=['taxonomy_term_ids','additional_media_ids'].includes(name);lookup(label,kind,item[name],multiple).catch(error=>{form.querySelector('[role=status]').textContent=error.message;});}
    for(const name of ['author','seo_title','seo_description','guest','external_podcast_url'])section.append(field(name,name==='seo_description'?'textarea':name==='external_podcast_url'?'url':'text',item[name]));
    section.append(field('transcript','textarea',item.transcript));form.querySelector('.media-library-toolbar-row').before(section);
    if(details.closest('[data-content-library]')?.querySelector('[data-can-publish]')){
      const platforms=el('details');platforms.append(el('summary',t('platform_metadata')));for(const provider of ['youtube','facebook','instagram','telegram','x']){const group=el('fieldset');group.append(el('legend',t(provider)),field(provider+'_title','text',item.platform_metadata?.[provider]?.title),field(provider+'_body','textarea',item.platform_metadata?.[provider]?.body));platforms.append(group);}section.append(platforms);
    }
    const textarea=form.querySelector('[name=body]');if(textarea&&item.kind==='post'){
      const mode=field('body_format','select',item.body_format||'plain',['plain','html']);textarea.closest('label').after(mode);const rich=el('div',undefined,'workspace-rich');rich.contentEditable='true';rich.setAttribute('role','textbox');rich.setAttribute('aria-label',t('body'));rich.setAttribute('aria-multiline','true');
      // HTML received here has already been sanitized by the server.
      if(item.body_format==='html')rich.innerHTML=item.body||'';else rich.textContent=item.body||'';
      const toolbar=el('div',undefined,'workspace-actions');for(const [tag,title] of [['strong','B'],['em','I'],['h2','H2'],['blockquote','❝']]){const b=button('formatting',()=>{const selection=window.getSelection();if(!selection.rangeCount)return;const range=selection.getRangeAt(0);if(!rich.contains(range.commonAncestorContainer))return;const wrapper=el(tag);wrapper.append(range.extractContents());range.insertNode(wrapper);rich.dispatchEvent(new Event('input',{bubbles:true}));});b.textContent=title;b.setAttribute('aria-label',t('formatting')+' '+title);b.addEventListener('mousedown',e=>e.preventDefault());toolbar.append(b);}mode.after(toolbar,rich);const toggle=()=>{const enabled=mode.querySelector('select').value==='html';rich.hidden=!enabled;toolbar.hidden=!enabled;textarea.hidden=enabled;};mode.querySelector('select').addEventListener('change',()=>{if(mode.querySelector('select').value==='plain')textarea.value=rich.textContent;else rich.textContent=textarea.value;toggle();});toggle();form._richBody=rich;
      const pdf=button('generate_pdf',async()=>{const out=form.querySelector('[role=status]');if(details.dataset.dirty==='true'){out.textContent=window.desktopImportLabels.save_before_ai;return;}try{await request('/desktop/content/'+item.id+'/pdf',{},'POST');out.textContent=t('queued');}catch(error){out.textContent=error.message;}});form.querySelector('.media-library-toolbar-row').append(pdf);
    }
  };
  window.desktopEditorData=(form,data)=>{
    for(const name of ['project_id','cover_media_id'])if(data[name]==='')data[name]=null;
    for(const name of ['taxonomy_term_ids','additional_media_ids'])if(form.elements[name]&&!form.elements[name].disabled)data[name]=[...form.elements[name].selectedOptions].map(option=>option.value);
    if(form._richBody&&data.body_format==='html')data.body=form._richBody.innerHTML;
    const platforms={};for(const provider of ['youtube','facebook','instagram','telegram','x']){const title=provider+'_title',body=provider+'_body';if(form.elements[title])platforms[provider]={title:data[title]||null,body:data[body]||null};delete data[title];delete data[body];}if(Object.keys(platforms).length)data.platform_metadata=platforms;
    return data;
  };
  document.addEventListener('click',async event=>{
    const create=event.target.closest('[data-content-new]'),series=event.target.closest('[data-content-series]');const root=(create||series)?.closest('[data-content-library]');if(!root)return;
    if(series){const details=root.querySelector('[data-content-details]');if(details.dataset.dirty==='true'&&!window.confirm(window.desktopImportLabels.discard_edits))return;const host=el('div',undefined,'workspace-inline');details.replaceChildren(host);await W.mount(host,'series');return;}
    const details=root.querySelector('[data-content-details]');if(details.dataset.dirty==='true'&&!window.confirm(window.desktopImportLabels.discard_edits))return;
    const form=el('form');form.className='content-assignment';form.append(el('h2',t('new')),field('title'),field('body','textarea'));form.elements.title.required=true;form.elements.body.maxLength=100000;const save=el('button',t('save'),'desktop-button');form.append(save);details.replaceChildren(form);form.addEventListener('submit',async e=>{e.preventDefault();save.disabled=true;try{const section=root.dataset.section;const result=await request('/desktop/content',{...formDataSafe(form),kind:section==='posts'?'post':'video',public_section:section==='posts'?'beitraege':section==='podcast'?'podcast':'videos',status:'unsorted'},'POST');document.dispatchEvent(new Event('desktop-media-changed'));delete details.dataset.dirty;root.dispatchEvent(new CustomEvent('local-content-open',{detail:{url:result.detail_url}}));}catch(error){details.append(el('p',error.message));}finally{save.disabled=false;}});
  });
  const formDataSafe=form=>Object.fromEntries(new FormData(form));
})();
