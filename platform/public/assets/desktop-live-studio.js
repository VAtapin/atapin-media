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

  const initialize = root => {
    if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true';
    const form = root.querySelector('[data-live-form]');
    const empty = root.querySelector('[data-live-editor-empty]');
    const events = root.querySelector('[data-live-events]');
    const listStatus = root.querySelector('[data-live-list-status]');
    const count = root.querySelector('[data-live-count]');
    const feedback = root.querySelector('[data-live-feedback]');
    const ingest = root.querySelector('[data-live-ingest]');
    const rotateWrap = root.querySelector('[data-live-rotate-wrap]');
    const preview = root.querySelector('[data-live-preview]');
    const posterFile = root.querySelector('[data-live-poster-file]');
    const posterPreview = root.querySelector('[data-live-poster-preview]');
    const posterImage = root.querySelector('[data-live-poster-image]');
    const posterEmpty = root.querySelector('[data-live-poster-empty]');
    const posterStatus = root.querySelector('[data-live-poster-status]');
    let current = null;

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
      return payload.data;
    };
    const renderList = items => {
      count.textContent = String(items.length);
      if (!items.length) {
        events.innerHTML = `<p class="desktop-live-list-empty">${escapeHtml(labels().empty)}</p>`;
        return;
      }
      events.innerHTML = items.map(item => `<button class="desktop-live-event${current && current.id === item.id ? ' is-active' : ''}" type="button" data-live-event="${item.id}"><span class="desktop-live-event-dot status-${escapeHtml(item.status)}"></span><span><strong>${escapeHtml(item.title)}</strong><small>${escapeHtml(displayDate(item.starts_at))} · ${escapeHtml(labels()['status_'+item.status] || item.status)}</small></span><span class="desktop-live-event-arrow">→</span></button>`).join('');
    };
    const showIngest = data => {
      const value = data.ingest;
      ingest.hidden = !value || !value.configured;
      if (!value || !value.configured) return;
      const parsed = new URL(value.url);
      root.querySelector('[data-live-server]').textContent = `${parsed.protocol}//${parsed.host}${parsed.pathname}`;
      root.querySelector('[data-live-url]').textContent = value.url;
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
      posterStatus.textContent = '';
      posterStatus.classList.remove('is-error');
      posterFile.value = '';
    };
    const fillForm = data => {
      current = data;
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
      setFeedback('');
      try { fillForm(await request(`${root.dataset.apiBase}/${id}`)); }
      catch (error) { setFeedback(error.message || labels().load_error, true); }
    };
    const load = async () => {
      listStatus.hidden = false;
      try {
        const data = await request(root.dataset.apiIndex);
        window.liveStudioEvents = data;
        listStatus.hidden = true;
        renderList(data);
        if (data[0]) await loadEvent(data[0].id);
        else newEvent();
      } catch (error) {
        listStatus.textContent = error.message || labels().load_error;
        setFeedback(error.message || labels().load_error, true);
        newEvent();
      }
    };
    const newEvent = () => { setFeedback(''); showIngest({}); fillForm({}); };
    root.querySelector('[data-live-new]').addEventListener('click', newEvent);
    root.querySelector('[data-live-copy]').addEventListener('click', async event => {
      const url = root.querySelector('[data-live-url]').textContent;
      if (!url) return;
      try { await navigator.clipboard.writeText(url); event.currentTarget.textContent = labels().copied; setTimeout(() => { event.currentTarget.textContent = labels().copy; }, 1800); }
      catch { setFeedback(labels().copy, true); }
    });
    posterFile.addEventListener('change', () => {
      const file = posterFile.files?.[0];
      if (!file) return;
      if (!file.type.startsWith('image/')) {
        posterFile.value = '';
        posterStatus.textContent = labels().poster_upload_error;
        posterStatus.classList.add('is-error');
        return;
      }
      posterImage.src = URL.createObjectURL(file);
      posterPreview.hidden = false;
      posterEmpty.hidden = true;
      posterStatus.textContent = labels().poster_uploaded;
      posterStatus.classList.remove('is-error');
    });
    preview.addEventListener('click', () => window.open(preview.dataset.url, '_blank', 'noopener'));
    root.querySelector('[data-live-help]').addEventListener('click', () => document.querySelector('[data-open-app="help-live-studio"]')?.click());
    events.addEventListener('click', event => { const button = event.target.closest('[data-live-event]'); if (button) loadEvent(button.dataset.liveEvent); });
    form.addEventListener('submit', async event => {
      event.preventDefault();
      setFeedback('');
      const payload = Object.fromEntries(new FormData(form).entries());
      payload.published = form.elements.published.checked;
      payload.enabled = form.elements.enabled.checked;
      payload.rotate_key = form.elements.rotate_key.checked;
      if (!payload.starts_at) payload.starts_at = null;
      const id = payload.id;
      delete payload.id;
      const method = id ? 'PATCH' : 'POST';
      try {
        const file = posterFile.files?.[0];
        delete payload.cover_media_id;
        delete payload.poster_file;
        let data = await request(id ? `${root.dataset.apiBase}/${id}` : root.dataset.apiBase, {method, body:JSON.stringify(payload)});
        if (file) {
          if (typeof window.uploadDesktopMedia !== 'function') throw new Error(labels().poster_upload_error);
          posterStatus.textContent = labels().poster_uploading;
          const mediaId = await window.uploadDesktopMedia(file, root.dataset.userId, (offset, total) => {
            posterStatus.textContent = `${labels().poster_uploading} ${Math.floor(offset / total * 100)} %`;
          });
          data = await request(`${root.dataset.apiBase}/${data.id}`, {method:'PATCH', body:JSON.stringify({
            title:data.title, body:data.body, starts_at:data.starts_at, published:data.published, enabled:data.enabled, cover_media_id:mediaId,
          })});
        }
        const index = await request(root.dataset.apiIndex);
        window.liveStudioEvents = index;
        fillForm(data);
        renderList(index);
        setFeedback(labels().saved);
      } catch (error) { setFeedback(error.message || labels().save_error, true); }
    });
    load();
  };

  window.initializeLiveStudio = initialize;
})();
