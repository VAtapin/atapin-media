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
    const select = (name, values, current) => `<label>${escape(text[name])}<select name="${name}">${values.map(value => `<option value="${value}" ${value === current ? 'selected' : ''}>${escape(text[value] || text['kind_'+value] || value)}</option>`).join('')}</select></label>`;
    const form = document.createElement('form'); form.className = 'content-assignment';
    const confidence = item.classification_confidence ?? item.classification?.confidence;
    if (confidence != null) {
      const label = document.createElement('p'); label.textContent = `${text.ai_confidence}: ${Math.round(Number(confidence)*100)} %`;
      details.append(label);
    }
    form.innerHTML = `<label>${escape(text.title)}<input name="title" required maxlength="255" value="${escape(item.title)}"></label>${type === 'record' ? `<label>${escape(text.body)}<textarea name="body" rows="5">${escape(item.body)}</textarea></label>${select('kind',['video','short','post','poll','comment'],item.kind)}` : select('target_profile',['media_library','videos','shorts','posts'],item.target_profile)}${select('status',['unsorted','ready','needs_attention'],item.status)}<label>${escape(text.tags)}<input name="tags" value="${escape((item.tags || []).join(', '))}" placeholder="${escape(text.tags_hint)}"></label><div class="media-library-toolbar-row"><button class="media-library-primary" type="submit">${escape(text.save)}</button><button class="media-library-primary" type="button" data-ai>${escape(text.ai_classify)}</button></div><p role="status" aria-live="polite"></p>`;
    const message = form.querySelector('[role=status]');
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
