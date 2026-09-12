(() => {
  const escape = value => String(value ?? '').replace(/[&<>'"]/g, ch => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#39;', '"':'&quot;' }[ch]));
  window.initializeContentLibrary = root => {
    if (!root || root.dataset.initialized) return;
    root.dataset.initialized = 'true';
    const text = window.desktopImportLabels || {};
    const form = root.querySelector('[data-content-filter]');
    const list = root.querySelector('[data-content-list]');
    const details = root.querySelector('[data-content-details]');
    const summary = root.querySelector('[data-content-summary]');
    const pagination = root.querySelector('[data-content-pagination]');
    if (root.dataset.section && root.dataset.section !== 'videos') form.querySelector('[value="playlist"]').remove();
    let page = 1;
    let controller;
    let playlistUrl = null;
    let detailGeneration = 0;
    const get = async url => {
      const response = await fetch(url, { credentials:'same-origin', headers:{Accept:'application/json'} });
      if (!response.ok) throw new Error(text.load_error);
      return response.json();
    };
    const load = async (number = 1) => {
      controller?.abort(); controller = new AbortController();
      const active = controller;
      const params = new URLSearchParams(new FormData(form));
      params.set('page', number);
      if (root.dataset.section) params.set('section', root.dataset.section);
      try {
        const playlist = params.get('kind') === 'playlist';
        form.querySelector('[name="status"]').disabled = playlist;
        const aiBatch = form.querySelector('[data-classify-batch]'); if (aiBatch) aiBatch.hidden = playlist;
        if (playlist) params.delete('kind');
        const data = await get(`${root.dataset.contentUrl}${playlist ? '/playlists' : ''}?${params}`);
        if (active.signal.aborted || !root.isConnected) return;
        page = data.meta.current_page;
        summary.textContent = `${data.meta.total} ${text.records}`;
        list.innerHTML = data.data.length ? data.data.map(item => `<li><button type="button" class="media-library-item" data-content-detail="${escape(item.detail_url)}"><span class="media-library-item-main"><strong>${escape(item.title)}</strong><small>${escape(text[`kind_${item.kind}`] || item.kind)} · ${escape(item.source)} · ${escape(item.body)}</small></span><span class="media-library-status">${escape(text[item.status] || item.status)}</span></button></li>`).join('') : `<li class="media-library-empty">${escape(text.empty)}</li>`;
        root.dispatchEvent(new CustomEvent('content-list-loaded',{bubbles:true,detail:{items:data.data,playlist}}));
        pagination.innerHTML = `<button type="button" data-content-page="${page - 1}" ${page <= 1 ? 'disabled' : ''}>‹</button><span>${page} / ${data.meta.last_page}</span><button type="button" data-content-page="${page + 1}" ${page >= data.meta.last_page ? 'disabled' : ''}>›</button>`;
      } catch (error) { summary.textContent = error.message; }
    };
    const preview = asset => {
      const url = escape(asset.preview_url);
      if (!asset.preview_url) return '';
      if (asset.kind === 'image') return `<img class="media-library-preview image" src="${url}" alt="" loading="lazy">`;
      if (asset.kind === 'video' || asset.kind === 'audio') return `<${asset.kind} class="media-library-preview ${asset.kind}" controls preload="metadata" src="${url}"></${asset.kind}>`;
      if (asset.mime === 'application/pdf') return `<iframe class="media-library-preview pdf" src="${url}" title="PDF" sandbox></iframe>`;
      return '';
    };
    const selectContent = async event => {
      const url = event.detail?.url || event.target.closest('[data-content-detail]')?.dataset.contentDetail;
      if (!url) return;
      if (details.dataset.dirty === 'true' && !window.confirm(text.discard_edits)) return;
      delete details.dataset.dirty;
      const generation = ++detailGeneration;
      details.textContent = text.content_loading;
      delete details.dataset.recordId;
      try {
        const item = await get(url);
        if (generation !== detailGeneration || !root.isConnected) return;
        if (item.kind === 'playlist') playlistUrl = url;
        else if (event.target.closest('[data-content-list]')) playlistUrl = null;
        // Source URLs are provenance, never a playback or navigation fallback.
        const external = () => '';
        details.innerHTML = `${item.kind !== 'playlist' && playlistUrl ? `<button type="button" class="desktop-button" data-content-detail="${escape(playlistUrl)}">← ${escape(text.playlist_back)}</button>` : ''}<h3>${escape(item.title)}</h3><p>${escape(text[`kind_${item.kind}`])} · ${escape(text.external_origin)} ${escape(text[`source_${item.source}`] || item.source)}</p><p class="content-location-note">${escape(item.kind === 'playlist' ? text.playlist_local : text.local_record)}${['video','short'].includes(item.kind) ? `<br>${escape(item.has_local_video ? text.local_file : text.no_local_video)}` : ''}</p>${item.external_url ? external(item.external_url) : ''}<p class="content-original-text">${escape(item.body)}</p>${item.author ? `<p>${escape(item.author)}</p>` : ''}${item.parent_source_id ? `<p>${escape(text.parent)}: ${escape(item.parent_source_id)}</p>` : ''}${item.poll ? `<pre class="content-original-text">${escape(JSON.stringify(item.poll,null,2))}</pre>` : ''}${item.assets.length ? `<h4>${escape(text.related_files)}</h4>` : ''}${item.assets.map(asset => `<p>${escape(asset.title)}</p>${preview(asset)}${asset.download_url ? `<a class="media-library-download" href="${escape(asset.download_url)}">${escape(text.download_original || text.files)}</a>` : `<small>${escape(text.unavailable)}</small>`}`).join('')}<p><small>${escape(text.private)}</small></p>`;
        details.dataset.recordId = item.id;
        details.dataset.currentUrl=url;
        if (item.items) details.insertAdjacentHTML('beforeend', `<p>${escape(text.playlist_hint)}</p><ol class="content-playlist-items">${item.items.map(member=>`<li value="${escape(member.position)}"><span class="content-playlist-title">${escape(member.title||member.source_id||text.unavailable)}</span><small>${escape(member.has_local_video ? text.local_file : member.detail_url ? text.metadata_only : text.missing_content)}</small>${member.detail_url ? `<button type="button" class="desktop-button" data-content-detail="${escape(member.detail_url)}">${escape(text.open_content)}</button>` : ''}${member.external_url ? external(member.external_url) : ''}</li>`).join('')}</ol>${item.previous_url ? `<button type="button" class="desktop-button" data-content-detail="${escape(item.previous_url)}">‹</button>` : ''}${item.next_url ? `<button type="button" class="desktop-button" data-content-detail="${escape(item.next_url)}">›</button>` : ''}`);
        if (root.dataset.canEdit === 'true' && item.kind !== 'playlist') window.appendContentAssignment?.(details, 'record', item);
        details.dispatchEvent(new CustomEvent('content-selected', { bubbles: true, detail: item }));
      } catch (error) { if (generation === detailGeneration && root.isConnected) details.textContent = error.message; }
    };
    root.addEventListener('click', selectContent);
    root.addEventListener('local-content-open', selectContent);
    form.addEventListener('submit', event => { event.preventDefault(); load(); });
    form.addEventListener('change', () => load());
    let debounce;
    form.addEventListener('input', () => { clearTimeout(debounce); debounce = setTimeout(() => load(), 250); });
    pagination.addEventListener('click', event => { const button = event.target.closest('[data-content-page]'); if (button) load(Number(button.dataset.contentPage)); });
    const changed = () => { if (!root.isConnected) { document.removeEventListener('desktop-media-changed',changed); return; } load(page); };
    document.addEventListener('desktop-media-changed', changed);
    load();
  };
})();
