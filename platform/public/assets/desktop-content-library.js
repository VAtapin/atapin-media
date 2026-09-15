(() => {
  const escape = value => String(value ?? '').replace(/[&<>'"]/g, ch => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#39;', '"':'&quot;' }[ch]));
  const formatDuration = value => {
    const seconds = Number(value);
    if (!Number.isFinite(seconds) || seconds < 0) return '';
    const total = Math.round(seconds);
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60).toString().padStart(2, '0');
    const rest = (total % 60).toString().padStart(2, '0');
    return hours ? `${hours}:${minutes}:${rest}` : `${Number(minutes)}:${rest}`;
  };
  const formatBytes = value => {
    const bytes = Number(value);
    if (!Number.isFinite(bytes) || bytes < 0) return '';
    const units = ['B', 'KB', 'MB', 'GB']; let size = bytes; let unit = 0;
    while (size >= 1024 && unit < units.length - 1) { size /= 1024; unit++; }
    return `${new Intl.NumberFormat('de-DE', {maximumFractionDigits: unit ? 1 : 0}).format(size)} ${units[unit]}`;
  };
  const openContentEditor = (section, url = '', mode = 'detail') => window.openDesktopProgram?.(section || 'videos', {
    forceNew: true,
    contentEditor: true,
    contentEditorMode: mode,
    ...(url ? {contentUrl: url} : {}),
  });
  window.openContentEditor = openContentEditor;
  window.initializeContentLibrary = root => {
    if (!root || root.dataset.initialized) return;
    root.dataset.initialized = 'true';
    const text = window.desktopImportLabels || {};
    const form = root.querySelector('[data-content-filter]');
    const list = root.querySelector('[data-content-list]');
    const details = root.querySelector('[data-content-details]');
    const summary = root.querySelector('[data-content-summary]');
    const pagination = root.querySelector('[data-content-pagination]');
    const section = root.dataset.section || '';
    const editorMode = root.dataset.contentEditor === 'true';
    const heading = root.querySelector('[data-content-heading]');
    const intro = root.querySelector('[data-content-intro]');
    const viewButtons = [...root.querySelectorAll('[data-content-view]')];
    const sectionKinds={videos:['video','short','playlist'],podcast:['video','short'],posts:['post'],community:['poll','comment','live_chat']};
    let allContent=!section;
    let currentPayload = null;
    const viewKey = `atapin.content.view.${section || 'all'}`;
    let view = 'cards';
    try { view = localStorage.getItem(viewKey) || 'cards'; } catch (_) {}
    if (section === 'videos') {
      if (heading) heading.textContent = heading.dataset.videoHeading || 'Videos';
      if (intro) intro.textContent = intro.dataset.videoIntro || '';
    }
    const scopeToggle=document.createElement('button');
    scopeToggle.type='button'; scopeToggle.className='desktop-button'; scopeToggle.dataset.contentScope='';
    if(section && !editorMode){
      (root.querySelector('[data-content-advanced-fields]') || form).append(scopeToggle);
      const updateScope=()=>{
        const allowed=sectionKinds[section]||[];
        form.querySelectorAll('[name="kind"] option').forEach(option=>{
          option.hidden=!allContent&&Boolean(option.value)&&!allowed.includes(option.value);
          option.disabled=option.hidden;
        });
        const kind=form.querySelector('[name="kind"]');
        if(kind.selectedOptions[0]?.disabled)kind.value='';
        scopeToggle.textContent=text[allContent?'section_only':'all_content'];
      };
      scopeToggle.addEventListener('click',()=>{allContent=!allContent;updateScope();load();});
      updateScope();
    }
    let page = 1;
    let controller;
    let playlistUrl = null;
    let detailGeneration = 0;
    let currentPlaylist = false;
    const publicBadge = (active, activeText, inactiveText, icon) => {
      const label = active ? activeText : inactiveText;
      return `<span class="content-list-badge ${active ? 'is-active' : 'is-inactive'}" title="${escape(label)}" aria-label="${escape(label)}"><span class="content-list-badge-icon" aria-hidden="true">${icon}</span><span>${escape(label)}</span></span>`;
    };
    const providerCode = provider => ({youtube:'YT',facebook:'FB',instagram:'IG',telegram:'TG',x:'X',linkedin:'LI'})[provider] || String(provider || '').slice(0,2).toUpperCase();
    const externalBadges = item => (item.external_publications || []).map(publication => {
      const provider = text[`provider_${publication.provider}`] || publication.provider;
      const current = publication.remote_status || publication.status || '';
      const label = text[current === 'hidden' || current === 'private' ? 'external_hidden' : current === 'failed' ? 'external_failed' : current === 'queued' || current === 'processing' ? 'external_queued' : 'external_published'] || current;
      return `<span class="content-list-badge is-external" title="${escape(provider + ': ' + label)}" aria-label="${escape(provider + ': ' + label)}"><span class="content-list-badge-icon" aria-hidden="true">${escape(providerCode(publication.provider))}</span><span>${escape(provider)}</span></span>`;
    }).join('');
    const get = async url => {
      const response = await fetch(url, { credentials:'same-origin', headers:{Accept:'application/json'} });
      if (!response.ok) throw new Error(text.load_error);
      return response.json();
    };
    const playable = item => item.video_url ? `<button type="button" class="content-video-play" data-content-play data-video-url="${escape(item.video_url)}" aria-label="${escape(text.video_play || 'Play video')}">▶</button>` : '';
    const videoVisual = (item, className) => `<span class="${className}" data-content-preview>${item.cover_url ? `<img src="${escape(item.cover_url)}" alt="" loading="lazy">` : `<span aria-hidden="true">${escape(text[`kind_${item.kind}`] || 'Video')}</span>`}${playable(item)}</span>`;
    const renderPayload = (data, playlist = false) => {
      currentPayload = data;
      currentPlaylist = playlist;
      page = data.meta.current_page;
      summary.textContent = `${data.meta.total} ${text.records}`;
      const renderList = () => data.data.length ? data.data.map(item => {
        const homepage = ['video','short'].includes(item.kind) ? publicBadge(Boolean(item.public_homepage), text.public_homepage_active, text.public_homepage_inactive, '⌂') : '';
        const publication = publicBadge(Boolean(item.public_published), text.public_visible, text.publication_unpublished, '✓');
        const external = externalBadges(item);
        const duration = item.video_duration ? formatDuration(item.video_duration) : '';
        const bytes = item.video_bytes ? formatBytes(item.video_bytes) : '';
        const technical = [duration, bytes].filter(Boolean).join(' · ');
        if (section === 'videos' && view === 'cards') return `<li><article class="content-video-card" data-content-detail="${escape(item.detail_url)}">${videoVisual(item, 'content-video-card-visual')}<button type="button" class="content-video-card-open" data-content-detail="${escape(item.detail_url)}"><span class="content-video-card-body"><strong>${escape(item.title)}</strong><small>${escape(text[`kind_${item.kind}`] || item.kind)}${item.source ? ` · ${escape(item.source)}` : ''}</small>${item.body ? `<span class="content-video-card-description">${escape(item.body)}</span>` : ''}<span class="content-list-badges">${publication}${external}${homepage}</span><span class="content-video-card-meta">${technical ? `${escape(technical)} · ` : ''}${escape(text[item.status] || item.status)}</span></span></button></article></li>`;
        if (section === 'videos') return `<li><article class="media-library-item content-video-list-item" data-content-detail="${escape(item.detail_url)}">${videoVisual(item, 'content-video-list-visual')}<button type="button" class="content-video-list-open" data-content-detail="${escape(item.detail_url)}"><span class="media-library-item-main"><strong>${escape(item.title)}</strong><small>${escape(text[`kind_${item.kind}`] || item.kind)} · ${escape(item.source)}${technical ? ` · ${escape(technical)}` : ''} · ${escape(item.body)}</small><span class="content-list-badges">${publication}${external}${homepage}</span></span></button><span class="media-library-status">${escape(text[item.status] || item.status)}</span></article></li>`;
        return `<li><button type="button" class="media-library-item" data-content-detail="${escape(item.detail_url)}"><span class="media-library-item-main"><strong>${escape(item.title)}</strong><small>${escape(text[`kind_${item.kind}`] || item.kind)} · ${escape(item.source)} · ${escape(item.body)}</small><span class="content-list-badges">${publication}${external}${homepage}</span></span><span class="media-library-status">${escape(text[item.status] || item.status)}</span></button></li>`;
      }).join('') : `<li class="media-library-empty">${escape(text.empty)}</li>`;
      list.innerHTML = section === 'videos' && view === 'cards' ? `<div class="content-video-card-grid">${renderList()}</div>` : renderList();
      root.dispatchEvent(new CustomEvent('content-list-loaded',{bubbles:true,detail:{items:data.data,playlist}}));
      pagination.innerHTML = `<button type="button" data-content-page="${page - 1}" ${page <= 1 ? 'disabled' : ''}>‹</button><span>${page} / ${data.meta.last_page}</span><button type="button" data-content-page="${page + 1}" ${page >= data.meta.last_page ? 'disabled' : ''}>›</button>`;
    };
    const load = async (number = 1) => {
      controller?.abort(); controller = new AbortController();
      const active = controller;
      const params = new URLSearchParams(new FormData(form));
      params.set('page', number);
      if (section&&!allContent) params.set('section', section); else params.delete('section');
      try {
        const deleted=params.get('trash')==='deleted';
        const playlistOption=form.querySelector('[value="playlist"]');if(playlistOption)playlistOption.disabled=deleted;
        if(deleted && params.get('kind')==='playlist'){form.querySelector('[name="kind"]').value='';params.delete('kind');}
        const playlist = params.get('kind') === 'playlist';
        form.querySelector('[name="status"]').disabled = playlist;
        const aiBatch = form.querySelector('[data-classify-batch]'); if (aiBatch) aiBatch.hidden = playlist||deleted;
        if (playlist) params.delete('kind');
        const data = await get(`${root.dataset.contentUrl}${playlist ? '/playlists' : ''}?${params}`);
        if (active.signal.aborted || !root.isConnected) return;
        renderPayload(data, playlist);
      } catch (error) { summary.textContent = error.message; }
    };
    const setView = next => {
      view = next;
      try { localStorage.setItem(viewKey, view); } catch (_) {}
      root.classList.toggle('is-content-card-view', view === 'cards');
      root.classList.toggle('is-content-list-view', view === 'list');
      viewButtons.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.contentView === view)));
      if (currentPayload) renderPayload(currentPayload, currentPlaylist);
    };
    viewButtons.forEach(button => button.addEventListener('click', () => setView(button.dataset.contentView)));
    setView(view);
    const preview = asset => {
      const url = escape(asset.preview_url);
      if (!asset.preview_url) return '';
      if (asset.kind === 'image') return `<img class="media-library-preview image" src="${url}" alt="" loading="lazy">`;
      if (asset.kind === 'video' || asset.kind === 'audio') return `<${asset.kind} class="media-library-preview ${asset.kind}" controls preload="metadata" src="${url}"></${asset.kind}>`;
      if (asset.mime === 'application/pdf') return `<iframe class="media-library-preview pdf" src="${url}" title="PDF" sandbox></iframe>`;
      return '';
    };
    const playInline = control => {
      const visual = control.closest('[data-content-preview]');
      const url = control.dataset.videoUrl;
      if (!visual || !url || visual.classList.contains('is-playing')) return;
      const video = document.createElement('video');
      video.className = 'content-video-inline-player';
      video.controls = true;
      video.autoplay = true;
      video.playsInline = true;
      video.preload = 'metadata';
      video.src = url;
      video.setAttribute('aria-label', text.video_play || 'Video');
      visual.replaceChildren(video);
      visual.classList.add('is-playing');
      video.play().catch(() => {});
    };
    const selectContent = async event => {
      const playControl = event.target.closest?.('[data-content-play]');
      if (playControl) {
        event.preventDefault();
        event.stopPropagation();
        playInline(playControl);
        return;
      }
      const url = event.detail?.url || event.target.closest('[data-content-detail]')?.dataset.contentDetail;
      if (!url) return;
      if (!editorMode) {
        openContentEditor(section, url);
        return;
      }
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
        const externalPublicationDetails = (item.external_publications || []).length ? `<section class="content-external-publications"><h4>${escape(text.external_publications || 'External publications')}</h4>${(item.external_publications || []).map(publication => `<p><strong>${escape(text[`provider_${publication.provider}`] || publication.provider)}</strong> · ${escape(text[publication.remote_status || publication.status] || publication.remote_status || publication.status || '')}${publication.external_url ? ` · <a href="${escape(publication.external_url)}" target="_blank" rel="noopener">${escape(text.open_external || 'Open')}</a>` : ''}</p>`).join('')}</section>` : '';
        details.innerHTML = `${item.kind !== 'playlist' && playlistUrl ? `<button type="button" class="desktop-button" data-content-detail="${escape(playlistUrl)}">← ${escape(text.playlist_back)}</button>` : ''}<h3>${escape(item.title)}</h3><p>${escape(text[`kind_${item.kind}`])} · ${escape(text.external_origin)} ${escape(text[`source_${item.source}`] || item.source)}</p>${externalPublicationDetails}<p class="content-location-note">${escape(item.kind === 'playlist' ? text.playlist_local : text.local_record)}${['video','short'].includes(item.kind) ? `<br>${escape(item.has_local_video ? text.local_file : text.no_local_video)}` : ''}</p>${item.external_url ? external(item.external_url) : ''}<p class="content-original-text">${escape(item.body)}</p>${item.author ? `<p>${escape(item.author)}</p>` : ''}${item.parent_source_id ? `<p>${escape(text.parent)}: ${escape(item.parent_source_id)}</p>` : ''}${item.poll ? `<pre class="content-original-text">${escape(JSON.stringify(item.poll,null,2))}</pre>` : ''}${item.assets.length ? `<h4>${escape(text.related_files)}</h4>` : ''}${item.assets.map(asset => `<p>${escape(asset.title)}</p>${preview(asset)}${asset.download_url ? `<a class="media-library-download" href="${escape(asset.download_url)}">${escape(text.download_original || text.files)}</a>` : `<small>${escape(text.unavailable)}</small>`}`).join('')}<p><small>${escape(text.private)}</small></p>`;
        details.dataset.recordId = item.id;
        if(item.poll) {
          details.querySelector('pre')?.remove();
          details.insertAdjacentHTML('beforeend',`<h4>${escape(text.kind_poll)}</h4><ol>${(item.poll.options||[]).map(option=>`<li>${escape(option.text)}${option.is_correct?` — ${escape(text.takeout_correct_answer)}`:''}${option.explanation?`<p>${escape(option.explanation)}</p>`:''}</li>`).join('')}</ol>`);
        }
        if(item.references?.length)details.insertAdjacentHTML('beforeend',`<h4>${escape(text.takeout_references)}</h4>${item.references.map(reference=>reference.detail_url?`<button type="button" class="desktop-button" data-content-detail="${escape(reference.detail_url)}">${escape(reference.text||text.open_content)}</button>`:`<p><small>${escape(reference.text)}${reference.missing?` — ${escape(text.missing_content)}`:''}</small></p>`).join('')}`);
        if(item.takeout_data&&Object.keys(item.takeout_data).length)details.insertAdjacentHTML('beforeend',`<details class="media-inspector"><summary>${escape(text.takeout_original_data)}</summary>${item.archive_data?`<p>${escape(text.takeout_archive_private)}</p>`:''}<pre class="content-original-text">${escape(JSON.stringify(item.takeout_data,null,2))}</pre></details>`);
        details.dataset.currentUrl=url;
        if (item.items) details.insertAdjacentHTML('beforeend', `<p>${escape(text.playlist_hint)}</p><ol class="content-playlist-items">${item.items.map(member=>`<li value="${escape(member.position)}"><span class="content-playlist-title">${escape(member.title||member.source_id||text.unavailable)}</span><small>${escape(member.has_local_video ? text.local_file : member.detail_url ? text.metadata_only : text.missing_content)}</small>${member.detail_url ? `<button type="button" class="desktop-button" data-content-detail="${escape(member.detail_url)}">${escape(text.open_content)}</button>` : ''}${member.external_url ? external(member.external_url) : ''}</li>`).join('')}</ol>${item.previous_url ? `<button type="button" class="desktop-button" data-content-detail="${escape(item.previous_url)}">‹</button>` : ''}${item.next_url ? `<button type="button" class="desktop-button" data-content-detail="${escape(item.next_url)}">›</button>` : ''}`);
        if (root.dataset.canEdit === 'true' && item.kind !== 'playlist' && !item.trashed) window.appendContentAssignment?.(details, 'record', item);
        details.dispatchEvent(new CustomEvent(item.trashed?'content-trashed-selected':'content-selected', { bubbles: true, detail: item }));
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
    root.classList.toggle('is-content-editor', editorMode);
    if (editorMode) {
      const mode = root.dataset.contentEditorMode || 'detail';
      if (mode === 'new' || mode === 'series') {
        root.querySelector(mode === 'series' ? '[data-content-series]' : '[data-content-new]')?.click();
      } else if (root.dataset.contentEditorUrl) {
        root.dispatchEvent(new CustomEvent('local-content-open', {detail:{url:root.dataset.contentEditorUrl}}));
      }
    } else load();
  };
})();
