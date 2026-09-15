(function () {
  const labels = () => window.desktopPublishingLabels || {};
  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
  const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;' }[character]));
  const date = value => value ? new Date(value).toLocaleString() : '—';
  const providerLabel = provider => labels()[`provider_${provider}`] || String(provider || '').replaceAll('_', ' ');
  const status = item => item.remote_status || item.status || 'unknown';

  window.initializePublishing = function (root) {
    if (!root || root.dataset.ready) return;
    root.dataset.ready = 'true';
    const statusList = root.querySelector('[data-publishing-status-list]');
    const feedback = root.querySelector('[data-publishing-feedback]');
    const summary = root.querySelector('[data-publishing-summary]');
    const filters = [...root.querySelectorAll('[data-publishing-filter]')];
    let state = { publications: [], providers: [] };
    let view = 'cards';
    let focusedRecordId = null;
    let loadGeneration = 0;

    const show = (message, error = false) => {
      feedback.textContent = message;
      feedback.hidden = !message;
      feedback.classList.toggle('is-error', error);
    };
    const request = async (url, options = {}) => {
      const response = await fetch(url, { ...options, headers: { 'Accept':'application/json', 'X-CSRF-TOKEN':csrf(), ...(options.headers || {}) } });
      const data = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(data.message || labels().error || 'Request failed');
      return data;
    };
    const query = () => {
      const params = new URLSearchParams();
      filters.forEach(input => { if (input.value) params.set(input.dataset.publishingFilter, input.value); });
      return params.toString();
    };
    const action = (item, name, text, active) => `<button type="button" class="desktop-button" data-publication-action="${name}" data-publication-id="${item.id}"${active === undefined ? '' : ` data-active="${active ? '1' : '0'}"`}>${escapeHtml(text)}</button>`;
    const visiblePublications = () => focusedRecordId === null ? state.publications : state.publications.filter(item => Number(item.record_id) === focusedRecordId);
    const renderSummary = publications => {
      const total = publications.length;
      const groups = [...new Set(publications.map(item => item.provider))].map(provider => `<span><strong>${publications.filter(item => item.provider === provider).length}</strong> ${escapeHtml(providerLabel(provider))}</span>`).join('');
      summary.innerHTML = `<span><strong>${total}</strong> ${escapeHtml(labels().publication_count || 'Publications')}</span>${groups}`;
    };
    const renderItemActions = item => {
      const actions = [];
      if (item.external_url) actions.push(`<a class="desktop-button" href="${escapeHtml(item.external_url)}" target="_blank" rel="noopener">${escapeHtml(labels().open_external || 'Open')}</a>`);
      if (item.can_deactivate) actions.push(action(item, 'visibility', labels().deactivate || 'Deactivate', false));
      if (item.can_activate) actions.push(action(item, 'visibility', labels().activate || 'Activate', true));
      if (item.status === 'failed') actions.push(action(item, 'retry', labels().retry || 'Retry'));
      if (item.can_remove) actions.push(action(item, 'remove', labels().remove_remote || 'Delete', undefined));
      return actions.join('');
    };
    const renderCards = publications => publications.map(item => `<article class="desktop-publishing-publication-card" data-publication-id="${item.id}" data-publication-record-id="${item.record_id}"><div class="desktop-publishing-publication-top"><span class="desktop-publishing-provider">${escapeHtml(providerLabel(item.provider))}</span><span class="desktop-publishing-status is-${escapeHtml(status(item))}">${escapeHtml(labels()[status(item)] || status(item))}</span></div><h3>${escapeHtml(item.title || ('#' + item.record_id))}</h3><p class="desktop-publishing-publication-meta">${escapeHtml(labels()[item.origin] || '')}${item.published_at ? ` · ${escapeHtml(labels().published_at || 'Published')}: ${escapeHtml(date(item.published_at))}` : ''}</p>${item.error ? `<p class="desktop-publishing-publication-error">${escapeHtml(item.error)}</p>` : ''}<div class="desktop-publishing-publication-actions">${renderItemActions(item)}</div></article>`).join('');
    const renderList = publications => `<div class="desktop-publishing-table-wrap"><table class="desktop-publishing-table"><thead><tr><th>${escapeHtml(labels().content || 'Content')}</th><th>${escapeHtml(labels().provider || 'Platform')}</th><th>${escapeHtml(labels().status_filter || 'Status')}</th><th>${escapeHtml(labels().published_at || 'Published')}</th><th>${escapeHtml(labels().actions || 'Actions')}</th></tr></thead><tbody>${publications.map(item => `<tr data-publication-id="${item.id}" data-publication-record-id="${item.record_id}"><td><strong>${escapeHtml(item.title || ('#' + item.record_id))}</strong>${item.error ? `<small class="desktop-publishing-publication-error">${escapeHtml(item.error)}</small>` : ''}</td><td>${escapeHtml(providerLabel(item.provider))}</td><td><span class="desktop-publishing-status is-${escapeHtml(status(item))}">${escapeHtml(labels()[status(item)] || status(item))}</span></td><td>${escapeHtml(date(item.published_at || item.last_attempt_at))}</td><td><div class="desktop-publishing-publication-actions">${renderItemActions(item)}</div></td></tr>`).join('')}</tbody></table></div>`;
    const render = () => {
      const publications=visiblePublications();
      renderSummary(publications);
      statusList.dataset.focusedRecordId=focusedRecordId===null?'':String(focusedRecordId);
      statusList.innerHTML = publications.length ? (view === 'list' ? renderList(publications) : `<div class="desktop-publishing-publication-grid">${renderCards(publications)}</div>`) : `<p class="desktop-publishing-muted">${escapeHtml(labels().no_publications || 'No external publications found.')}</p>`;
      if(focusedRecordId!==null)statusList.querySelector('[data-publication-record-id]')?.scrollIntoView({block:'nearest'});
    };
    const populateProviders = () => {
      const select = root.querySelector('[data-publishing-filter="provider"]');
      const current = select.value;
      select.innerHTML = `<option value="">${escapeHtml(labels().all_providers || 'All platforms')}</option>` + state.providers.map(provider => `<option value="${escapeHtml(provider)}">${escapeHtml(providerLabel(provider))}</option>`).join('');
      select.value = current;
    };
    const load = async () => {
      const generation=++loadGeneration;
      try {
        const nextState = await request(`${root.dataset.apiUrl}?${query()}`);
        if(generation!==loadGeneration)return;
        state = nextState;
        populateProviders();
        render();
      } catch (error) { if(generation===loadGeneration)show(error.message, true); }
    };

    root.querySelector('[data-publishing-refresh]')?.addEventListener('click', load);
    root.querySelector('[data-publishing-reset]')?.addEventListener('click', () => { focusedRecordId=null;filters.forEach(input => { input.value = ''; }); load(); });
    filters.forEach(input => input.addEventListener(input.type === 'search' ? 'input' : 'change', () => {focusedRecordId=null;load();}));
    root.querySelectorAll('[data-publishing-view]').forEach(button => button.addEventListener('click', () => { view = button.dataset.publishingView; root.querySelectorAll('[data-publishing-view]').forEach(item => item.classList.toggle('is-active', item === button)); render(); }));
    statusList.addEventListener('click', async event => {
      const button = event.target.closest('[data-publication-action]');
      if (!button) return;
      const item = state.publications.find(publication => String(publication.id) === button.dataset.publicationId);
      if (!item) return;
      try {
        if (button.dataset.publicationAction === 'remove' && !window.confirm(labels().confirm_remove_remote || 'Delete this external publication?')) return;
        if (button.dataset.publicationAction === 'visibility') await request(`/desktop/publishing/publications/${item.id}/visibility`, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({active: button.dataset.active === '1'}) });
        if (button.dataset.publicationAction === 'retry') await request(`/desktop/publishing/publications/${item.id}/retry`, { method:'POST' });
        if (button.dataset.publicationAction === 'remove') await request(`/desktop/publishing/publications/${item.id}`, { method:'DELETE', headers:{'Content-Type':'application/json'}, body:JSON.stringify({confirm:true}) });
        show(labels().queued_action || 'Operation queued.');
        await load();
      } catch (error) { show(error.message, true); }
    });
    root.addEventListener('desktop-publishing-open',event=>{
      const recordId=Number(event.detail?.recordId);if(!Number.isInteger(recordId)||recordId<1)return;
      focusedRecordId=recordId;
      const search=root.querySelector('[data-publishing-filter="search"]');if(search)search.value=String(event.detail?.title||'');
      load();
    });
    root.querySelectorAll('[data-publishing-view]').forEach(button => button.classList.toggle('is-active', button.dataset.publishingView === view));
    load();
  };
})();
