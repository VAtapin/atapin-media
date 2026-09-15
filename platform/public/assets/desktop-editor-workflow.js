(() => {
  const W=window.DesktopWorkspaces,{t,el,field,lookup,button,request}=W;
  const podcastText=key=>(window.desktopImportLabels||{})[key]||t(key)||key;
  const podcastField=(name,type='text',value='',options=[],label=name)=>{const node=field(name,type,value,options);node.firstChild.textContent=podcastText(label);return node;};
  const podcastMedia=(asset,label)=>{
    const node=el('div',undefined,'podcast-current-media');node.append(el('strong',label));
    if(!asset){node.append(el('span',podcastText('podcast_no_file')));return node;}
    if(asset.preview_url&&asset.kind==='image'){const image=el('img');image.src=asset.preview_url;image.alt='';node.append(image);}
    else if(asset.preview_url&&['audio','video'].includes(asset.kind)){const preview=el(asset.kind);preview.controls=true;preview.preload='metadata';preview.src=asset.preview_url;node.append(preview);}
    const href=asset.download_url||asset.preview_url;const link=el('a',asset.title||podcastText('podcast_existing_file'),'desktop-button');link.href=href||'#';if(href){link.target='_blank';link.rel='noopener';}node.append(link);return node;
  };
  window.appendPodcastEditor=(details,source)=>{
    const item=source||{title:'',body:'',status:'unsorted',podcast_format:'audio',assets:[],taxonomy_term_ids:[]};
    const root=details.closest('[data-content-library]');if(!root)return;
    root.classList.add('has-podcast-editor');details.replaceChildren();
    const form=el('form',undefined,'content-assignment podcast-episode-form');form.dataset.podcastEpisode='';
    const heading=el('header',undefined,'podcast-editor-heading'),headingText=el('div');headingText.append(el('p',podcastText('podcast_eyebrow'),'content-library-eyebrow'),el('h2',item.id?item.title:podcastText('podcast_new_episode')));
    const close=el('button',podcastText('podcast_back'),'desktop-button');close.type='button';heading.append(headingText,close);form.append(heading);
    const format=item.podcast_format||((item.assets||[]).some(asset=>asset.kind==='video')?'video':'audio');
    const mediaAsset=(item.assets||[]).find(asset=>asset.id===(format==='audio'?item.primary_audio_id:item.primary_video_id))||(item.assets||[]).find(asset=>asset.kind===format);
    const coverAsset=(item.assets||[]).find(asset=>asset.id===item.cover_media_id)||(item.assets||[]).find(asset=>asset.kind==='image');
    const primary=el('section',undefined,'podcast-primary-fields');
    const title=podcastField('title','text',item.title,[],'title');title.classList.add('podcast-field-wide');title.querySelector('input').required=true;title.querySelector('input').maxLength=255;
    const description=podcastField('body','textarea',item.body,[],'podcast_description');description.classList.add('podcast-field-wide');description.querySelector('textarea').maxLength=1000000;
    const formatField=podcastField('podcast_format','select',format,[['audio',podcastText('podcast_format_audio')],['video',podcastText('podcast_format_video')]],'podcast_format');
    const date=podcastField('public_published_at','date',item.public_published_at,[],'podcast_publish_date');date.querySelector('input').max=new Date().toISOString().slice(0,10);date.append(el('small',podcastText('podcast_publish_date_hint')));if(!root.querySelector('[data-can-publish]'))date.querySelector('input').disabled=true;
    const statusField=podcastField('status','select',item.status||'unsorted',['unsorted','review','ready','needs_attention'],'status');
    const fileLabel=podcastField('podcast_file','file','','','podcast_media');fileLabel.classList.add('podcast-upload-field');const fileInput=fileLabel.querySelector('input');fileInput.accept=format==='audio'?'audio/*':'video/*';fileInput.disabled=root.dataset.canUpload!=='true';fileLabel.append(el('small',podcastText('podcast_media_hint')));
    const coverLabel=podcastField('cover_file','file','','','podcast_cover');coverLabel.classList.add('podcast-upload-field');const coverInput=coverLabel.querySelector('input');coverInput.accept='image/jpeg,image/png,image/webp,image/gif';coverInput.disabled=root.dataset.canUpload!=='true';coverLabel.append(el('small',podcastText('podcast_cover_hint')));
    const mediaBlock=el('div',undefined,'podcast-media-block');mediaBlock.append(podcastMedia(mediaAsset,podcastText('podcast_media')),fileLabel);
    const coverBlock=el('div',undefined,'podcast-media-block');coverBlock.append(podcastMedia(coverAsset,podcastText('podcast_cover')),coverLabel);
    primary.append(title,description,mediaBlock,coverBlock,date,statusField,formatField);form.append(primary);
    formatField.querySelector('select').addEventListener('change',event=>{fileInput.accept=event.target.value==='audio'?'audio/*':'video/*';fileInput.value='';});

    const more=el('details',undefined,'podcast-more-settings');more.dataset.podcastMore='';more.append(el('summary',podcastText('podcast_more')));const moreContent=el('div',undefined,'podcast-more-content');more.append(moreContent);form.append(more);
    const website=el('section',undefined,'podcast-settings-group');website.append(el('h3',podcastText('podcast_website')));
    const project=podcastField('project_id','select',item.project_id,[],'project_id'),terms=podcastField('taxonomy_term_ids','select',item.taxonomy_term_ids,[],'taxonomy_term_ids');website.append(project,terms);
    lookup(project,'projects',item.project_id).catch(error=>{form.querySelector('[role=status]').textContent=error.message;});lookup(terms,'terms',item.taxonomy_term_ids,true).catch(error=>{form.querySelector('[role=status]').textContent=error.message;});
    let publication=null;if(root.querySelector('[data-can-publish]')){const publish=el('label',undefined,'content-assignment-checkbox');publication=el('input');publication.type='checkbox';publication.checked=Boolean(item.public_published);publish.append(publication,document.createTextNode(podcastText('public_published')),el('small',podcastText('public_published_hint')));website.append(publish);}
    const editorial=el('section',undefined,'podcast-settings-group');editorial.append(el('h3',podcastText('podcast_editorial')),podcastField('tags','text',(item.tags||[]).join(', '),[],'tags'),podcastField('episode_number','number',item.episode_number,[],'episode_number'),podcastField('season','number',item.season,[],'season'),podcastField('author','text',item.author,[],'author'),podcastField('guest','text',item.guest,[],'guest'),podcastField('external_podcast_url','url',item.external_podcast_url,[],'external_podcast_url'),podcastField('transcript','textarea',item.transcript,[],'transcript'));
    const seo=el('section',undefined,'podcast-settings-group');seo.append(el('h3','SEO'),podcastField('seo_title','text',item.seo_title,[],'seo_title'),podcastField('seo_description','textarea',item.seo_description,[],'seo_description'));
    const technical=el('section',undefined,'podcast-settings-group');technical.append(el('h3',podcastText('podcast_technical')),el('p',`${podcastText('source')}: ${item.source||podcastText('source_manual')} · ${podcastText('podcast_format')}: ${podcastText('podcast_format_'+format)}`));
    const files=el('div');files.append(el('h4',podcastText('podcast_materials')));for(const asset of item.assets||[]){const row=el('p',`${asset.title} · ${asset.mime||asset.kind}`);files.append(row);}technical.append(files);
    const ai=el('details',undefined,'podcast-ai-menu');ai.append(el('summary',podcastText('podcast_ai')));const aiActions=el('div',undefined,'media-library-toolbar-row'),aiStatus=el('p');aiStatus.setAttribute('role','status');
    const aiAction=(label,operation)=>{const control=el('button',podcastText(label),'desktop-button');control.type='button';control.disabled=!item.id;control.addEventListener('click',async()=>{if(details.dataset.dirty==='true'){aiStatus.textContent=podcastText('save_before_ai');return;}control.disabled=true;try{await operation();aiStatus.textContent=podcastText('ai_queued');}catch(error){aiStatus.textContent=error.message;}finally{control.disabled=false;}});aiActions.append(control);};
    aiAction('podcast_ai_classify',()=>request('/desktop/content/classify',{type:'record',id:String(item.id)},'POST'));
    aiAction('podcast_ai_summary',()=>request('/desktop/content/short-descriptions',{ids:[item.id]},'POST'));
    aiAction('podcast_ai_proposal',async()=>{W.open('ai-assistant',{source_record_id:item.id,purpose:'seo',question:podcastText('seo_title')});});
    aiAction('podcast_ai_structure',()=>request('/desktop/content/'+item.id+'/structure',{},'POST'));
    if(root.dataset.canMediaEdit==='true')aiAction('podcast_ai_cover',()=>request('/desktop/content/'+item.id+'/prepare-media',{operation:'ai_cover'},'POST'));
    ai.append(aiActions,aiStatus);moreContent.append(website,editorial,seo,technical,ai);

    const toolbar=el('div',undefined,'media-library-toolbar-row podcast-save-row'),save=el('button',podcastText('save'),'media-library-primary'),message=el('p');save.type='submit';message.setAttribute('role','status');toolbar.append(save);form.append(toolbar,message);details.append(form);
    const closeEditor=()=>{root.classList.remove('has-podcast-editor');delete details.dataset.dirty;details.innerHTML=`<div class="podcast-editor-empty"><strong>${podcastText('podcast_episode')}</strong><p>${podcastText('podcast_select_episode')}</p></div>`;};
    close.addEventListener('click',()=>{if(details.dataset.dirty==='true'&&!window.confirm(podcastText('discard_edits')))return;closeEditor();});
    form.addEventListener('input',()=>{details.dataset.dirty='true';});form.addEventListener('change',()=>{details.dataset.dirty='true';});
    form.addEventListener('submit',async event=>{event.preventDefault();save.disabled=true;message.textContent='';try{
      const selectedFormat=formatField.querySelector('select').value;let mediaId=null,coverId=null;
      if(mediaAsset&&selectedFormat!==format&&!fileInput.files[0])throw new Error(podcastText('podcast_format_requires_file'));
      if(fileInput.files[0]){if(typeof window.uploadDesktopMedia!=='function')throw new Error(podcastText('podcast_upload_unavailable'));mediaId=await window.uploadDesktopMedia(fileInput.files[0],root.dataset.userId,(offset,total)=>{message.textContent=`${podcastText('podcast_uploading')} ${total?Math.floor(offset/total*100):0} %`;},null,{profile:selectedFormat==='video'?'video':'attachment'});}
      if(coverInput.files[0]){if(typeof window.uploadDesktopMedia!=='function')throw new Error(podcastText('podcast_upload_unavailable'));coverId=await window.uploadDesktopMedia(coverInput.files[0],root.dataset.userId,(offset,total)=>{message.textContent=`${podcastText('podcast_cover_uploading')} ${total?Math.floor(offset/total*100):0} %`;},null,{profile:'cover'});}
      const data=Object.fromEntries(new FormData(form));delete data.podcast_file;delete data.cover_file;data.kind='video';data.status=data.status||'unsorted';data.podcast_format=selectedFormat;data.target_profile='podcast';data.tags=(data.tags||'').split(',').map(value=>value.trim()).filter(Boolean);data.project_id=data.project_id||null;data.public_published_at=data.public_published_at||null;data.taxonomy_term_ids=[...terms.querySelector('select').selectedOptions].map(option=>option.value);if(publication)data.public_published=publication.checked;
      let id=item.id;if(id){await request('/desktop/content/'+id,{...data,...mediaId?{podcast_media_id:mediaId}:{},...coverId?{cover_media_id:coverId}:{}},'PATCH');}
      else{const created=await request('/desktop/content',{...data,public_section:'podcast',podcast_media_id:mediaId,cover_media_id:coverId},'POST');id=created.id;}
      delete details.dataset.dirty;document.dispatchEvent(new Event('desktop-media-changed'));root.dispatchEvent(new CustomEvent('local-content-open',{detail:{url:'/desktop/content/'+id}}));
    }catch(error){message.textContent=error.message;}finally{save.disabled=false;}});
    queueMicrotask(()=>{for(const node of [...details.children])if(node!==form)moreContent.append(node);});
  };
  window.extendDesktopContentEditor=(form,item,details)=>{
    if(item.archive_data||!['video','short','post'].includes(item.kind))return;
    const section=el('details');section.append(el('summary',t('content_report')));
    const taxonomy=field('taxonomy_term_ids','select');taxonomy.classList.add('content-taxonomy-field');
    taxonomy.append(el('small',t('taxonomy_editor_hint')));form.querySelector('.media-library-toolbar-row').before(taxonomy);
    lookup(taxonomy,'terms',item.taxonomy_term_ids,true).catch(error=>{form.querySelector('[role=status]').textContent=error.message;});
    if(item.preview_url){const a=el('a',t('preview'),'desktop-button');a.href=item.preview_url;a.target='_blank';a.rel='noopener';form.querySelector('.media-library-toolbar-row').append(a);}
    if(item.pending_review){const accept=button('accept_review',async()=>{const out=form.querySelector('[role=status]');if(details.dataset.dirty==='true'){out.textContent=t('save_before_action');return;}if(!window.confirm(t('confirm_review')))return;try{await request('/desktop/content/'+item.id+'/accept-review',{confirm:true},'POST');document.dispatchEvent(new Event('desktop-media-changed'));details.closest('[data-content-library]').dispatchEvent(new CustomEvent('local-content-open',{detail:{url:'/desktop/content/'+item.id}}));}catch(error){out.textContent=error.message;}});form.querySelector('.media-library-toolbar-row').append(accept);}
    section.append(field('workflow_stage','select',item.workflow_stage||'idea',['idea','script','production','review','approved']),field('slug','text',item.slug),field('locale','select',item.locale||'de',['de','en']));
    if(document.querySelector('.os-start-menu [data-open-app=ai-assistant]')){const ai=button('generate_ai',()=>{if(details.dataset.dirty==='true'){form.querySelector('[role=status]').textContent=t('save_before_action');return;}W.open('ai-assistant',{source_record_id:item.id,purpose:'seo',question:t('seo')});});form.querySelector('.media-library-toolbar-row').append(ai);}
    if(item.public_section==='podcast')section.append(field('episode_number','number',item.episode_number),field('season','number',item.season));
    const fields=[['project_id','projects'],['cover_media_id','media'],['additional_media_ids','media']];
    for(const [name,kind] of fields){const label=field(name,'select');section.append(label);const multiple=['taxonomy_term_ids','additional_media_ids'].includes(name);lookup(label,kind,item[name],multiple).catch(error=>{form.querySelector('[role=status]').textContent=error.message;});}
    for(const name of ['author','seo_title','seo_description','guest','external_podcast_url'])section.append(field(name,name==='seo_description'?'textarea':name==='external_podcast_url'?'url':'text',item[name]));
    section.append(field('transcript','textarea',item.transcript));form.querySelector('.media-library-toolbar-row').before(section);
    if(details.closest('[data-content-library]')?.querySelector('[data-can-publish]')){
      section.append(field('public_published_at','date',item.public_published_at));
      const platforms=el('details');platforms.append(el('summary',t('platform_metadata')));for(const provider of ['youtube','facebook','instagram','telegram','x']){const group=el('fieldset'),cover=field(provider+'_cover','select',null);cover.firstChild.textContent=t('platform_cover');group.append(el('legend',t(provider)),field(provider+'_title','text',item.platform_metadata?.[provider]?.title),field(provider+'_body','textarea',item.platform_metadata?.[provider]?.body),cover);const tags=field(provider+'_hashtags','text',(item.platform_metadata?.[provider]?.hashtags||[]).join(', '));tags.firstChild.textContent=t('hashtags');group.append(tags);lookup(cover,'media',item.platform_metadata?.[provider]?.cover_media_id).catch(error=>{form.querySelector('[role=status]').textContent=error.message;});platforms.append(group);}section.append(platforms);
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
    const platforms={};for(const provider of ['youtube','facebook','instagram','telegram','x']){const title=provider+'_title',body=provider+'_body',cover=provider+'_cover',tags=provider+'_hashtags';if(form.elements[title])platforms[provider]={title:data[title]||null,body:data[body]||null,cover_media_id:data[cover]||null,hashtags:(data[tags]||'').split(/[,\s]+/).filter(Boolean)};for(const key of [title,body,cover,tags])delete data[key];}if(Object.keys(platforms).length)data.platform_metadata=platforms;
    return data;
  };
  document.addEventListener('click',async event=>{
    const create=event.target.closest('[data-content-new]'),series=event.target.closest('[data-content-series]');const root=(create||series)?.closest('[data-content-library]');if(!root)return;
    if (root.dataset.contentEditor !== 'true' && !(create && root.dataset.section === 'podcast')) {
      window.openContentEditor?.(root.dataset.section, '', series ? 'series' : 'new');
      return;
    }
    if(series){const details=root.querySelector('[data-content-details]');if(details.dataset.dirty==='true'&&!window.confirm(window.desktopImportLabels.discard_edits))return;const host=el('div',undefined,'workspace-inline');details.replaceChildren(host);await W.mount(host,'series');return;}
    const details=root.querySelector('[data-content-details]');if(details.dataset.dirty==='true'&&!window.confirm(window.desktopImportLabels.discard_edits))return;
    if(root.dataset.section==='podcast'&&window.appendPodcastEditor){root.classList.add('has-podcast-editor');window.appendPodcastEditor(details,null);return;}
    const form=el('form');form.className='content-assignment';form.append(el('h2',t('new')),field('title'),field('body','textarea'));form.elements.title.required=true;form.elements.body.maxLength=100000;const save=el('button',t('save'),'desktop-button');form.append(save);details.replaceChildren(form);form.addEventListener('submit',async e=>{e.preventDefault();save.disabled=true;try{const section=root.dataset.section;const result=await request('/desktop/content',{...formDataSafe(form),kind:section==='posts'?'post':'video',public_section:section==='posts'?'beitraege':section==='podcast'?'podcast':'videos',status:'unsorted'},'POST');document.dispatchEvent(new Event('desktop-media-changed'));delete details.dataset.dirty;root.dispatchEvent(new CustomEvent('local-content-open',{detail:{url:result.detail_url}}));}catch(error){details.append(el('p',error.message));}finally{save.disabled=false;}});
  });
  const formDataSafe=form=>Object.fromEntries(new FormData(form));
})();
