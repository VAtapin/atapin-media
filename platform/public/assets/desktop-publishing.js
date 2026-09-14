(function () {
  const labels = () => window.desktopPublishingLabels || {};
  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
  const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;' }[character]));
  const date = value => value ? new Date(value).toLocaleString() : '—';

  window.initializePublishing = function (root) {
    if (!root || root.dataset.ready) return;
    root.dataset.ready = 'true';
    const recordSelect = root.querySelector('[data-publishing-record]');
    const destinations = root.querySelector('[data-publishing-destinations]');
    const statusList = root.querySelector('[data-publishing-status-list]');
    const feedback = root.querySelector('[data-publishing-feedback]');
    let state = { records: [], destinations: [], publications: [] };

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
    const selectedRecord = () => state.records.find(record => String(record.id) === recordSelect.value);
    const compatible = (destination, record) => !record || destination.provider === 'website' || destination.capabilities?.[record.kind] === true;

    const renderDestinations = () => {
      const record = selectedRecord();
      destinations.innerHTML = state.destinations.filter(destination => destination.connected && !destination.revoked).map(destination => {
        const supported = compatible(destination, record);
        return `<label class="desktop-publishing-destination"><input type="checkbox" value="${escapeHtml(destination.provider)}" ${supported && (destination.provider === 'website' || destination.provider === 'youtube') ? 'checked' : ''} ${supported ? '' : 'disabled'}><span><strong>${escapeHtml(destination.label)}</strong><small>${escapeHtml(destination.public_url || '')}${supported ? '' : ` · ${escapeHtml(labels().unsupported || 'Unavailable for this content')}`}</small></span></label>`;
      }).join('') || `<p class="desktop-publishing-muted">${escapeHtml(labels().youtube_not_connected || '')}</p>`;
    };
    const renderRecords = () => {
      recordSelect.innerHTML = state.records.map(record => `<option value="${escapeHtml(record.id)}">${escapeHtml(record.title)} · ${escapeHtml(labels()[record.kind] || record.kind)}</option>`).join('');
      root.querySelector('[data-publishing-empty]').hidden = state.records.length > 0;
      recordSelect.disabled = !state.records.length;
      renderDestinations();
    };
    const renderStatus = () => {
      statusList.innerHTML = state.publications.length ? state.publications.map(item => `<article class="desktop-publishing-status-item"><div><strong>${escapeHtml(item.title || ('#' + item.record_id))}</strong><span>${escapeHtml(item.provider)} · ${escapeHtml(labels()[item.status] || item.status)}${item.remote_status && item.remote_status !== item.status ? ` · ${escapeHtml(labels()[item.remote_status] || item.remote_status)}` : ''}</span></div><small>${escapeHtml(item.error || '')}${item.published_at ? ` · ${escapeHtml(labels().published_at || 'Published')}: ${escapeHtml(date(item.published_at))}` : ''}${item.last_attempt_at ? ` · ${escapeHtml(labels().last_attempt || 'Last attempt')}: ${escapeHtml(date(item.last_attempt_at))}` : ''}${item.attempts ? ` · ${escapeHtml(labels().attempts || 'Attempts')}: ${item.attempts}` : ''}</small>${item.status === 'failed' ? `<button type="button" class="desktop-button" data-publishing-retry="${escapeHtml(item.id)}">${escapeHtml(labels().retry || 'Retry')}</button>` : ''}</article>`).join('') : `<p class="desktop-publishing-muted">${escapeHtml(labels().no_content || '')}</p>`;
    };
    const load = async () => {
      state = await request(root.dataset.apiUrl);
      renderRecords();
      renderStatus();
      const connection = root.querySelector('[data-publishing-connection-status]');
      if (connection) connection.textContent = state.youtube?.configured ? labels().youtube_connected : (labels().youtube_not_connected || 'YouTube not connected');
    };

    recordSelect.addEventListener('change', renderDestinations);
    root.querySelector('[data-publishing-submit]')?.addEventListener('click', async () => {
      const selected = [...destinations.querySelectorAll('input:checked:not(:disabled)')].map(input => input.value);
      if (!selected.length) return show(labels().no_destination || 'Select a destination.', true);
      try { await request(root.dataset.publishUrl, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ record_id:recordSelect.value, destinations:selected }) }); show(labels().saved || 'Queued.'); await load(); } catch (error) { show(error.message, true); }
    });
    root.querySelector('[data-publishing-sync]')?.addEventListener('click', async () => {
      try { await request(root.dataset.syncUrl, { method:'POST' }); show(labels().sync_queued || 'Sync queued.'); } catch (error) { show(error.message, true); }
    });
    statusList.addEventListener('click', async event => {
      const button = event.target.closest('[data-publishing-retry]');
      if (!button) return;
      try { await request(`/desktop/publishing/publications/${button.dataset.publishingRetry}/retry`, { method:'POST' }); show(labels().saved || 'Queued.'); await load(); } catch (error) { show(error.message, true); }
    });
    const refreshStatus = async () => {
      if (!root.isConnected) return;
      try {
        const data = await request(root.dataset.apiUrl);
        state.publications = data.publications;
        renderStatus(); // Do not reset the editor's selected record or destination checkboxes.
      } catch { /* A transient refresh failure must not interrupt editing. */ }
      setTimeout(refreshStatus, 5000);
    };
    load().then(() => setTimeout(refreshStatus, 5000)).catch(error => show(error.message, true));
  };
})();
