(() => {
  const labels = () => window.desktopLiveLabels || {};
  const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[character]));
  const localDate = value => {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value).slice(0, 16);
    const pad = number => String(number).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
  };
  const displayDate = value => value ? new Intl.DateTimeFormat('de-DE', { dateStyle:'medium', timeStyle:'short' }).format(new Date(value)) : '—';
  const today = () => { const date = new Date(); const pad = number => String(number).padStart(2, '0'); return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`; };

  const initialize = root => {
    if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true';
    let browserStudio=null;
    const studioModule=import('/assets/desktop-browser-studio.js?v=11').then(module=>{browserStudio=module;return module;});
    const form = root.querySelector('[data-live-form]');
    const empty = root.querySelector('[data-live-editor-empty]');
    const events = root.querySelector('[data-live-events]');
    const listStatus = root.querySelector('[data-live-list-status]');
    const count = root.querySelector('[data-live-count]');
    const currentPanel = root.querySelector('[data-live-current]');
    const currentEvents = root.querySelector('[data-live-now-events]');
    const currentCount = root.querySelector('[data-live-now-count]');
    const filter = root.querySelector('[data-live-filter]');
    const dateFilter = root.querySelector('[data-live-date]');
    const pagination = root.querySelector('[data-live-pagination]');
    const pageInfo = root.querySelector('[data-live-page-info]');
    const previousPage = root.querySelector('[data-live-page-prev]');
    const nextPage = root.querySelector('[data-live-page-next]');
    const feedback = root.querySelector('[data-live-feedback]');
    const ingest = root.querySelector('[data-live-ingest]');
    const rotateWrap = root.querySelector('[data-live-rotate-wrap]');
    const preview = root.querySelector('[data-live-preview]');
    const posterFile = root.querySelector('[data-live-poster-file]');
    const posterDrop = root.querySelector('[data-live-poster-drop]');
    const posterPreview = root.querySelector('[data-live-poster-preview]');
    const posterImage = root.querySelector('[data-live-poster-image]');
    const posterEmpty = root.querySelector('[data-live-poster-empty]');
    const posterStatus = root.querySelector('[data-live-poster-status]');
    const posterProgress = root.querySelector('[data-live-poster-progress]');
    const saveButton = form.querySelector('button[type="submit"]');
    let current = null;
    let selectionGeneration = 0;
    let selectedPosterFile = null;
    const view = {mode:'day', date:today(), page:1};
    dateFilter.value = view.date;

    const setFeedback = (message, error = false) => {
      feedback.textContent = message || '';
      feedback.hidden = !message;
      feedback.classList.toggle('is-error', error);
    };
    const request = async (url, options = {}) => {
      const response = await fetch(url, {
        credentials:'same-origin',
        headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content || '',...(options.body ? {'Content-Type':'application/json'} : {})},
        ...options,
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) {
        const first = Object.values(payload.errors || {})[0];
        throw new Error(Array.isArray(first) ? first[0] : (payload.message || labels().save_error));
      }
      return payload;
    };
    const requestData = async (url, options = {}) => (await request(url, options)).data;
    const renderList = items => {
      count.textContent = String(window.liveStudioPagination?.total || 0);
      if (!items.length) {
        events.innerHTML = `<p class="desktop-live-list-empty">${escapeHtml(labels().empty)}</p>`;
        return;
      }
      events.innerHTML = items.map(item => `<button class="desktop-live-event${current && current.id === item.id ? ' is-active' : ''}" type="button" data-live-event="${item.id}"><span class="desktop-live-event-dot status-${escapeHtml(item.status)}"></span><span><strong>${escapeHtml(item.title)}</strong><small>${escapeHtml(displayDate(item.starts_at))} · ${escapeHtml(labels()['status_'+item.status] || item.status)}</small></span><span class="desktop-live-event-arrow">→</span></button>`).join('');
    };
    const renderCurrent = items => {
      currentPanel.hidden = false;
      currentCount.textContent = String(items.length);
      currentEvents.innerHTML = items.length ? items.map(item => `<button class="desktop-live-event is-current${current && current.id === item.id ? ' is-active' : ''}" type="button" data-live-event="${item.id}"><span class="desktop-live-event-dot status-live"></span><span><strong>${escapeHtml(item.title)}</strong><small>${escapeHtml(labels().status_live)}</small></span><span class="desktop-live-event-arrow">→</span></button>`).join('') : `<p class="desktop-live-list-empty">${escapeHtml(labels().live_none)}</p>`;
    };
    const renderPagination = meta => {
      window.liveStudioPagination = meta || {};
      const lastPage = Number(meta?.last_page || 1);
      pagination.hidden = lastPage <= 1;
      pageInfo.textContent = meta?.total ? `${meta.current_page} / ${lastPage}` : '';
      previousPage.disabled = !meta?.current_page || meta.current_page <= 1;
      nextPage.disabled = !meta?.current_page || meta.current_page >= lastPage;
    };
    const showIngest = data => {
      const value = data.ingest;
      ingest.hidden = !value || !value.configured;
      if (!value || !value.configured) return;
      const parsed = new URL(value.url);
      root.querySelector('[data-live-server]').textContent = `${parsed.protocol}//${parsed.host}${parsed.pathname}`;
      root.querySelector('[data-live-url]').textContent = value.url;
    };
    const setPosterStatus = (message = '', state = 'idle') => {
      posterStatus.textContent = message;
      posterStatus.hidden = !message;
      posterStatus.classList.toggle('is-error', state === 'error');
      posterStatus.classList.toggle('is-success', state === 'success');
      posterStatus.dataset.state = state;
    };
    const setPosterProgress = value => {
      if (value === null || value === undefined) {
        posterProgress.hidden = true;
        posterProgress.value = 0;
        return;
      }
      posterProgress.hidden = false;
      posterProgress.value = Math.max(0, Math.min(100, value));
    };
    const showPoster = data => {
      form.elements.cover_media_id.value = data.cover_media_id || '';
      if (data.cover_preview_url) {
        posterImage.src = data.cover_preview_url;
        posterPreview.hidden = false;
        posterEmpty.hidden = true;
      } else {
        posterImage.removeAttribute('src');
        posterPreview.hidden = true;
        posterEmpty.hidden = false;
      }
      setPosterStatus();
      setPosterProgress(null);
      posterFile.value = '';
      selectedPosterFile = null;
    };
    const fillForm = data => {
      current = data;
      studioModule.then(module=>module.initialize(root,data)).catch(error=>setFeedback(error.message,true));
      window.initializeLiveConsole?.(root,data.id);
      empty.hidden = true;
      form.hidden = false;
      form.elements.id.value = data.id || '';
      form.elements.title.value = data.title || '';
      form.elements.body.value = data.body || '';
      form.elements.starts_at.value = localDate(data.starts_at);
      form.elements.published.checked = Boolean(data.published);
      form.elements.enabled.checked = Boolean(data.enabled);
      form.elements.rotate_key.checked = false;
      rotateWrap.hidden = !data.id;
      preview.hidden = !data.id;
      preview.dataset.url = `${root.dataset.previewBase}${data.id}`;
      showPoster(data);
      root.querySelector('[data-live-editor-eyebrow]').textContent = data.id ? labels().edit : labels().new;
      root.querySelector('[data-live-editor-title]').textContent = data.title || labels().new;
      showIngest(data);
      renderList(window.liveStudioEvents || []);
    };
    const loadEvent = async id => {
      if(browserStudio?.busy(root)){setFeedback(window.desktopLiveLabels.close_warning||'End the active broadcast or recording first.',true);return;}
      const generation = ++selectionGeneration;
      setFeedback('');
      try { const data=await requestData(`${root.dataset.apiBase}/${id}`);if(generation===selectionGeneration)fillForm(data); }
      catch (error) { if(generation===selectionGeneration)setFeedback(error.message || labels().load_error, true); }
    };
    const indexUrl = () => {
      const url = new URL(root.dataset.apiIndex, window.location.origin);
      url.searchParams.set('filter', view.mode);
      url.searchParams.set('page', String(view.page));
      if (view.mode === 'day') url.searchParams.set('date', view.date);
      return url.toString();
    };
    const applyIndex = response => {
      window.liveStudioEvents = response.data || [];
      renderCurrent(response.live || []);
      renderPagination(response.pagination || {});
      renderList(window.liveStudioEvents);
    };
    const loadIndex = async selectFirst => {
      const selectionAtStart=selectionGeneration;
      listStatus.hidden = false;
      try {
        const response = await request(indexUrl());
        applyIndex(response);
        listStatus.hidden = true;
        if (selectFirst && selectionGeneration===selectionAtStart) {
          if (response.data?.[0]) await loadEvent(response.data[0].id);
          else newEvent();
        }
        return response;
      } catch (error) {
        listStatus.textContent = error.message || labels().load_error;
        setFeedback(error.message || labels().load_error, true);
        if(selectionGeneration===selectionAtStart)newEvent();
      }
    };
    const newEvent = () => { if(browserStudio?.busy(root)){setFeedback(window.desktopLiveLabels.close_warning||'End the active broadcast or recording first.',true);return;}selectionGeneration+=1;setFeedback(''); showIngest({}); fillForm({}); };
    root.querySelector('[data-live-new]').addEventListener('click', newEvent);
    root.querySelector('[data-live-copy]').addEventListener('click', async event => {
      const url = root.querySelector('[data-live-url]').textContent;
      if (!url) return;
      try { await navigator.clipboard.writeText(url); event.currentTarget.textContent = labels().copied; setTimeout(() => { event.currentTarget.textContent = labels().copy; }, 1800); }
      catch { setFeedback(labels().copy, true); }
    });
    const selectPosterFile = file => {
      if (!file) return;
      if (!['image/jpeg', 'image/png', 'image/webp', 'image/gif'].includes(file.type)) {
        posterFile.value = '';
        selectedPosterFile = null;
        setPosterStatus(labels().poster_invalid, 'error');
        setPosterProgress(null);
        return;
      }
      if (file.size > 50 * 1024 * 1024) {
        posterFile.value = '';
        selectedPosterFile = null;
        setPosterStatus(labels().poster_source_too_large, 'error');
        setPosterProgress(null);
        return;
      }
      selectedPosterFile = file;
      posterImage.src = URL.createObjectURL(file);
      posterPreview.hidden = false;
      posterEmpty.hidden = true;
      setPosterStatus(labels().poster_selected, 'selected');
      setPosterProgress(null);
    };
    posterFile.addEventListener('change', () => selectPosterFile(posterFile.files?.[0]));
    posterDrop?.addEventListener('click', event => { if (event.target !== posterFile) posterFile.click(); });
    posterDrop?.addEventListener('keydown', event => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); posterFile.click(); } });
    posterDrop?.addEventListener('dragover', event => { event.preventDefault(); posterDrop.classList.add('is-dragging'); });
    posterDrop?.addEventListener('dragleave', event => { if (!posterDrop.contains(event.relatedTarget)) posterDrop.classList.remove('is-dragging'); });
    posterDrop?.addEventListener('drop', event => { event.preventDefault(); posterDrop.classList.remove('is-dragging'); selectPosterFile(event.dataTransfer.files?.[0]); });
    preview.addEventListener('click', () => window.open(preview.dataset.url, '_blank', 'noopener'));
    root.querySelector('[data-live-help]').addEventListener('click', () => root.closest('.os-window')?.querySelector('[data-window-action="help"]')?.click());
    root.addEventListener('desktop-live-open',event=>{const id=Number(event.detail?.id);if(Number.isInteger(id)&&id>0)loadEvent(id);});
    root.addEventListener('desktop-live-event-started',event=>{
      const data=event.detail;if(!data?.id)return;
      if(current?.id===data.id){current=data;form.elements.published.checked=Boolean(data.published);form.elements.starts_at.value=localDate(data.starts_at);}
      loadIndex(false);
    });
    events.addEventListener('click', event => { const button = event.target.closest('[data-live-event]'); if (button) loadEvent(button.dataset.liveEvent); });
    currentEvents.addEventListener('click', event => { const button = event.target.closest('[data-live-event]'); if (button) loadEvent(button.dataset.liveEvent); });
    form.addEventListener('submit', async event => {
      event.preventDefault();
      if(browserStudio?.busy(root)){setFeedback(window.desktopLiveLabels.close_warning||'End the active broadcast or recording first.',true);return;}
      setFeedback('');
      const payload = Object.fromEntries(new FormData(form).entries());
      payload.published = form.elements.published.checked;
      payload.enabled = form.elements.enabled.checked;
      payload.rotate_key = form.elements.rotate_key.checked;
      if (!payload.starts_at) payload.starts_at = null;
      const id = payload.id;
      delete payload.id;
      const method = id ? 'PATCH' : 'POST';
      const file = selectedPosterFile || posterFile.files?.[0];
      let posterUploadStarted = false;
      saveButton.disabled = true;
      try {
        delete payload.cover_media_id;
        delete payload.poster_file;
        if (file) setPosterStatus(labels().poster_preparing, 'loading');
        let data = await requestData(id ? `${root.dataset.apiBase}/${id}` : root.dataset.apiBase, {method, body:JSON.stringify(payload)});
        if (file) {
          if (typeof window.uploadDesktopMedia !== 'function') throw new Error(labels().poster_upload_error);
          posterUploadStarted = true;
          setPosterStatus(labels().poster_uploading, 'loading');
          setPosterProgress(0);
          const mediaId = await window.uploadDesktopMedia(file, root.dataset.userId, (offset, total) => {
            const percent = total ? Math.floor(offset / total * 100) : 0;
            setPosterStatus(`${labels().poster_uploading} ${percent} %`, 'loading');
            setPosterProgress(percent);
          }, null, {profile:'poster'});
          setPosterStatus(labels().poster_linking, 'loading');
          setPosterProgress(100);
          data = await requestData(`${root.dataset.apiBase}/${data.id}`, {method:'PATCH', body:JSON.stringify({
            title:data.title, body:data.body, starts_at:data.starts_at, published:data.published, enabled:data.enabled, cover_media_id:mediaId,
          })});
        }
        const index = await loadIndex(false);
        fillForm(data);
        if (index) applyIndex(index);
        if (file) setPosterStatus(labels().poster_saved, 'success');
        setFeedback(labels().saved);
      } catch (error) {
        if (posterUploadStarted || file) setPosterStatus(labels().poster_upload_error, 'error');
        setPosterProgress(null);
        setFeedback(posterUploadStarted || file ? labels().poster_upload_error : (error.message || labels().save_error), true);
      } finally {
        saveButton.disabled = false;
      }
    });
    filter.addEventListener('change', () => { view.mode = filter.value; view.page = 1; dateFilter.disabled = view.mode !== 'day'; loadIndex(true); });
    dateFilter.addEventListener('change', () => { if (!dateFilter.value) dateFilter.value = today(); view.date = dateFilter.value; view.page = 1; loadIndex(true); });
    previousPage.addEventListener('click', () => { if (view.page > 1) { view.page -= 1; loadIndex(false); } });
    nextPage.addEventListener('click', () => { const lastPage = Number(window.liveStudioPagination?.last_page || 1); if (view.page < lastPage) { view.page += 1; loadIndex(false); } });
    loadIndex(true);
  };

  window.initializeLiveStudio = initialize;
})();
