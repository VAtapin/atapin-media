(() => {
  const labels = { intake:'Dateien des Eigentümers', youtube:'YouTube-Archiv', upload:'Media-Library-Upload', unsorted:'Unsortiert', processing:'In Verarbeitung', ready:'Bereit', needs_attention:'Benötigt Aufmerksamkeit', failed:'Fehlgeschlagen', video:'Video', audio:'Audio', image:'Bild', document:'Dokument', other:'Datei' };
  const escape = value => String(value ?? '').replace(/[&<>'"]/g, char => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#39;', '"':'&quot;' }[char]));
  const date = value => value ? new Intl.DateTimeFormat('de-DE', { dateStyle:'medium' }).format(new Date(value)) : '–';
  const icon = kind => ({ video:'▶', audio:'♫', image:'▧', document:'▤', other:'…' }[kind] || '…');
  const preview = item => {
    if (!item.preview_url) return '';
    const url = escape(item.preview_url);
    if (item.kind === 'image') return `<img class="media-library-preview image" src="${url}" alt="">`;
    if (item.kind === 'audio') return `<audio class="media-library-preview audio" controls preload="metadata" src="${url}"></audio>`;
    if (item.kind === 'video') return `<video class="media-library-preview video" controls preload="metadata" src="${url}"></video>`;
    if (item.mime === 'application/pdf') return `<iframe class="media-library-preview pdf" src="${url}" title="PDF-Vorschau" sandbox></iframe>`;
    return '';
  };
  window.initializeMediaLibrary = root => {
    if (!root || root.dataset.initialized) return;
    root.dataset.initialized = 'true';
    const form = root.querySelector('[data-library-filter]');
    const list = root.querySelector('[data-library-list]');
    const summary = root.querySelector('[data-library-summary]');
    const details = root.querySelector('[data-library-details]');
    const pagination = root.querySelector('[data-library-pagination]');
    let selected = null;
    let current = [];
    let lastPayload = null;
    const query = page => {
      const params = new URLSearchParams(new FormData(form));
      if (page > 1) params.set('page', page);
      [...params.entries()].filter(([, value]) => !value).forEach(([key]) => params.delete(key));
      return `${root.dataset.libraryUrl}?${params}`;
    };
    const renderDetails = item => {
      if (!item) { details.innerHTML = '<p>Wähle ein Medium, um Details zu sehen.</p>'; return; }
      details.innerHTML = `${preview(item)}<span class="media-library-detail-icon" aria-hidden="true">${icon(item.kind)}</span><h3>${escape(item.title)}</h3><p>${escape(item.original_name)}</p><dl><div><dt>Typ</dt><dd>${escape(labels[item.kind] || item.kind)}</dd></div><div><dt>Quelle</dt><dd>${escape(labels[item.source] || item.source)}</dd></div><div><dt>Status</dt><dd>${escape(labels[item.status] || item.status)}</dd></div><div><dt>Größe</dt><dd>${escape(item.formatted_size)}</dd></div><div><dt>Hinzugefügt</dt><dd>${date(item.created_at)}</dd></div>${item.asset_count ? `<div><dt>Verbundene Assets</dt><dd>${item.asset_count}</dd></div>` : ''}</dl>${item.tags.length ? `<p class="media-library-tags">${item.tags.map(tag => `<span>${escape(tag)}</span>`).join('')}</p>` : ''}<a class="media-library-download" href="${escape(item.download_url)}">Herunterladen</a>`;
    };
    const render = payload => {
      lastPayload = payload;
      current = payload.data;
      if (selected) selected = current.find(item => item.id === selected.id) || null;
      list.innerHTML = current.length ? current.map(item => `<li><button type="button" class="media-library-item ${selected?.id === item.id ? 'is-selected' : ''}" data-media-id="${escape(item.id)}"><span class="media-library-file-icon" aria-hidden="true">${icon(item.kind)}</span><span class="media-library-item-main"><strong>${escape(item.title)}</strong><small>${escape(labels[item.source] || item.source)} · ${escape(item.formatted_size)} · ${date(item.created_at)}</small></span><span class="media-library-status status-${escape(item.status)}">${escape(labels[item.status] || item.status)}</span></button></li>`).join('') : '<li class="media-library-empty">Keine Medien für diese Auswahl.</li>';
      summary.textContent = `${payload.meta.total} Medien im Archiv`;
      pagination.innerHTML = payload.meta.last_page > 1 ? Array.from({ length: payload.meta.last_page }, (_, index) => `<button type="button" data-page="${index + 1}" ${payload.meta.current_page === index + 1 ? 'aria-current="page"' : ''}>${index + 1}</button>`).join('') : '';
      renderDetails(selected);
    };
    const load = async page => {
      summary.textContent = 'Archiv wird geladen …';
      try {
        const response = await fetch(query(page), { headers:{ Accept:'application/json' }, credentials:'same-origin' });
        if (!response.ok) throw new Error('library_load_failed');
        render(await response.json());
      } catch (_) { list.innerHTML = '<li class="media-library-empty">Das Archiv konnte nicht geladen werden.</li>'; summary.textContent = 'Fehler beim Laden'; }
    };
    form.addEventListener('input', () => load(1));
    form.addEventListener('change', () => load(1));
    list.addEventListener('click', event => { const id = event.target.closest('[data-media-id]')?.dataset.mediaId; if (!id) return; selected = current.find(item => item.id === id) || null; if (lastPayload) render(lastPayload); });
    pagination.addEventListener('click', event => { const page = Number(event.target.closest('[data-page]')?.dataset.page); if (Number.isSafeInteger(page)) load(page); });
    load(1);
  };
})();
