(() => {
  const escape = value => String(value ?? '').replace(/[&<>'"]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch]));
  const request = async (url, data, method = 'POST') => {
    const response = await fetch(url, {method, credentials:'same-origin', headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}, body:JSON.stringify(data)});
    const result = await response.json();
    if (!response.ok) throw new Error(result.message || window.desktopImportLabels.load_error);
    return result;
  };
  window.appendContentAssignment = (details, type, item) => {
    const text = window.desktopImportLabels;
    if(type==='record'&&item.public_section==='podcast'&&window.appendPodcastEditor){window.appendPodcastEditor(details,item);return;}
    const select = (name, values, current) => `<label>${escape(text[name])}<select name="${name}">${values.map(value => `<option value="${value}" ${value === current ? 'selected' : ''}>${escape(text[value] || text['kind_'+value] || value)}</option>`).join('')}</select></label>`;
    const form = document.createElement('form'); form.className = 'content-assignment';
    const confidence = item.classification_confidence ?? item.classification?.confidence;
    if (confidence != null) {
      const label = document.createElement('p'); label.textContent = `${text.ai_confidence}: ${Math.round(Number(confidence)*100)} %`;
      details.append(label);
    }
    if(item.public_url){
      const notice=details.querySelector('p:last-of-type small');if(notice)notice.textContent=text.public_visible;
      const link=document.createElement('a');link.href=item.public_url;link.target='_blank';link.rel='noopener';link.className='desktop-button';link.textContent=text.public_open;details.append(link);
    }
    form.innerHTML = `<label>${escape(text.title)}<input name="title" required maxlength="255" value="${escape(item.title)}"></label>${type === 'record' ? `<label>${escape(text.body)}<textarea name="body" rows="5">${escape(item.body)}</textarea></label>${select('kind',['video','short','post','poll','comment'],item.kind)}` : select('target_profile',['media_library','videos','shorts','posts'],item.target_profile)}${select('status',type==='record'?['unsorted','review','ready','needs_attention']:['unsorted','ready','needs_attention'],item.status)}<label>${escape(text.tags)}<input name="tags" value="${escape((item.tags || []).join(', '))}" placeholder="${escape(text.tags_hint)}"></label><div class="media-library-toolbar-row"><button class="media-library-primary" type="submit">${escape(text.save)}</button><button class="media-library-primary" type="button" data-ai>${escape(text.ai_classify)}</button></div><p role="status" aria-live="polite"></p>`;
    const kindField=form.querySelector('[name="kind"]');
    if(type==='record' && !item.archive_data && ['video','short','post'].includes(item.kind)) {
      const label=document.createElement('label');label.textContent=text.short_description;
      const input=document.createElement('textarea');input.name='short_description';input.maxLength=300;input.rows=2;input.value=item.short_description||'';label.append(input);
      form.querySelector('[name=body]').closest('label').after(label);
      const generate=document.createElement('button');generate.type='button';generate.className='desktop-button';generate.textContent=text.short_description_generate;
      form.querySelector('.media-library-toolbar-row').append(generate);
      if(item.short_description_job)messageState();
      function messageState(){form.querySelector('[role=status]').textContent=text['enhancement_'+item.short_description_job]||item.short_description_job;}
      generate.addEventListener('click',async()=>{
        const message=form.querySelector('[role=status]');
        if(details.dataset.dirty==='true'){message.textContent=text.save_before_ai;return;}
        generate.disabled=true;
        try{await request('/desktop/content/short-descriptions',{ids:[item.id]});message.textContent=text.ai_queued;details.dispatchEvent(new Event('content-enhancements-queued',{bubbles:true}));}
        catch(error){message.textContent=error.message;}finally{generate.disabled=false;}
      });
    }
    if(kindField&&!Array.from(kindField.options).some(option=>option.value===item.kind))kindField.add(new Option(text['kind_'+item.kind]||item.kind,item.kind,true,true));
    if(item.archive_data)form.querySelector('[data-ai]').hidden=true;
    const message = form.querySelector('[role=status]');
    if(type==='record'){
      const label=document.createElement('label');label.textContent=text.target_profile;
      const destination=document.createElement('select');destination.name='target_profile';
      for(const value of ['','media_library','videos','shorts','posts','polls','comments','podcast'])destination.append(new Option(value ? (text[value]||value) : text.keep_section,value));
      label.append(destination);form.insertBefore(label,form.querySelector('.media-library-toolbar-row'));
    }
    let publication, homepage, homepageLabel;
    if(type==='record' && !item.archive_data && ['video','short','post','poll','comment','live_chat'].includes(item.kind) && details.closest('[data-content-library]')?.querySelector('[data-can-publish]')){
      const label=document.createElement('label');publication=document.createElement('input');publication.type='checkbox';publication.checked=Boolean(item.public_published);
      label.append(publication,document.createTextNode(text.public_published));
      const hint=document.createElement('small');hint.textContent=text.public_published_hint;label.append(hint);
      form.insertBefore(label,form.querySelector('.media-library-toolbar-row'));
      if(['video','short'].includes(item.kind)){
        homepageLabel=document.createElement('label');homepageLabel.className='content-assignment-checkbox';homepage=document.createElement('input');homepage.type='checkbox';homepage.checked=Boolean(item.public_homepage);
        homepageLabel.append(homepage,document.createTextNode(text.public_homepage));
        const homepageHint=document.createElement('small');homepageHint.textContent=text.public_homepage_hint;homepageLabel.append(homepageHint);
        form.insertBefore(homepageLabel,form.querySelector('.media-library-toolbar-row'));
      }
      if(['video','short','post'].includes(item.kind)){
        const sectionLabel=document.createElement('label');sectionLabel.textContent=text.public_section;
        const section=document.createElement('select');section.name='public_section';
        const updateSection=()=>{
          const kind=kindField.value,current=section.value||item.public_section;
          const values=kind==='post'?['beitraege','podcast','community']:['video','short'].includes(kind)?['videos','podcast','live']:['community'];
          section.replaceChildren(...values.map(value=>new Option(text['public_section_'+value],value)));
          section.value=values.includes(current)?current:values[0];
        };
        updateSection();
        sectionLabel.append(section);form.insertBefore(sectionLabel,label);
        kindField.addEventListener('change',()=>{updateSection();if(homepageLabel){const eligible=['video','short'].includes(kindField.value);homepageLabel.hidden=!eligible;homepage.disabled=!eligible;if(!eligible)homepage.checked=false;}});
      }
    }
    if(type==='record')window.extendDesktopContentEditor?.(form,item,details);
    form.addEventListener('input', () => {details.dataset.dirty = 'true';});
    form.addEventListener('change', () => {details.dataset.dirty = 'true';});
    const perform = async operation => {
      const buttons = form.querySelectorAll('button'); buttons.forEach(button => {button.disabled = true;});
      try { await operation(); delete details.dataset.dirty; document.dispatchEvent(new Event('desktop-media-changed')); }
      catch (error) {message.textContent = error.message;}
      finally {buttons.forEach(button => {button.disabled = false;});}
    };
    form.addEventListener('submit', event => {
      event.preventDefault();
      const data = Object.fromEntries(new FormData(form)); data.tags = [...new Set(data.tags.split(',').map(tag => tag.trim()).filter(Boolean))];
      if(data.target_profile==='')delete data.target_profile;
      if(publication)data.public_published=publication.checked;
      if(homepage)data.public_homepage=homepage.checked;
      window.desktopEditorData?.(form,data);
      perform(async () => {await request(`/desktop/${type === 'media' ? 'media' : 'content'}/${item.id}`,data,'PATCH'); message.textContent = text.saved;});
    });
    form.querySelector('[data-ai]').addEventListener('click', () => {
      if (details.dataset.dirty === 'true') {message.textContent = text.save_before_ai; return;}
      perform(async () => {await request('/desktop/content/classify',{type,id:String(item.id)}); message.textContent = text.ai_queued;});
    });
    details.append(form);
  };
  document.addEventListener('click', async event => {
    const button = event.target.closest('[data-classify-batch]'); if (!button) return;
    const root = button.closest('[data-content-library], [data-media-library]');
    const summary = root.querySelector('[data-content-summary], [data-library-summary]');
    button.disabled = true;
    try {const result = await request('/desktop/content/classify',{type:button.dataset.classifyBatch,batch:true}); summary.textContent = `${window.desktopImportLabels.ai_queued} (${result.count})`;}
    catch(error) {summary.textContent = error.message;}
    finally {button.disabled = false;}
  });
})();
(() => {
  const labels = () => window.desktopImportLabels || {};
  const request = async (url, data) => {
    const response = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      },
      body: JSON.stringify(data),
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.message || labels().load_error || 'Request failed');
    return result;
  };
  const supported = item => ['post', 'video', 'short'].includes(item?.kind) && !item?.archive_data;
  const reviewDone = item => ['applied', 'no_change'].includes(item?.structure_review?.status) && item?.structure_review?.reviewed;

  document.addEventListener('content-selected', event => {
    const item = event.detail;
    const details = event.target.closest?.('[data-content-details]');
    if (!details || !supported(item) || item.public_section==='podcast' || details.dataset.dirty === 'true') return;
    const toolbar = details.querySelector('.content-assignment .media-library-toolbar-row');
    if (!toolbar || toolbar.querySelector('[data-structure-check]')) return;
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'desktop-button';
    button.dataset.structureCheck = '';
    button.textContent = reviewDone(item) ? labels().structure_recheck : labels().structure_check;
    const status = document.createElement('small');
    status.className = 'content-structure-status';
    status.textContent = item.structure_review?.status && labels()['structure_' + item.structure_review.status] || '';
    toolbar.append(button, status);
    button.addEventListener('click', async () => {
      if (details.dataset.dirty === 'true') {
        status.textContent = labels().save_before_ai;
        return;
      }
      button.disabled = true;
      try {
        const result = await request('/desktop/content/' + item.id + '/structure', {force: reviewDone(item)});
        status.textContent = result.status === 'skipped' ? labels().structure_skipped : labels().structure_queued;
        document.dispatchEvent(new Event('desktop-media-changed'));
      } catch (error) {
        status.textContent = error.message;
      } finally {
        button.disabled = false;
      }
    });
  });

  document.addEventListener('click', async event => {
    const button = event.target.closest('[data-structure-batch]');
    if (!button) return;
    const root = button.closest('[data-content-library]');
    const form = root?.querySelector('[data-content-filter]');
    const summary = root?.querySelector('[data-content-summary]');
    if (!root || !form || !summary) return;
    const filters = Object.fromEntries([...new FormData(form)].filter(([, value]) => value !== ''));
    if (root.dataset.section) filters.section = root.dataset.section;
    if (filters.trash === 'deleted' || filters.kind === 'playlist') {
      summary.textContent = labels().structure_batch + ': ' + (labels().trash_restore_first || '');
      return;
    }
    button.disabled = true;
    try {
      const result = await request('/desktop/content/structure/batch', {filters});
      summary.textContent = (labels().structure_batch_done || '')
        .replace(':queued', result.queued)
        .replace(':skipped', result.skipped);
      document.dispatchEvent(new Event('desktop-media-changed'));
    } catch (error) {
      summary.textContent = error.message;
    } finally {
      button.disabled = false;
    }
  });
})();
