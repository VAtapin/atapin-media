(() => {
  const labels = {
    intake: 'Dateien des Eigentümers',
    youtube: 'YouTube-Archiv',
    upload: 'Media-Library-Upload',
    unsorted: 'Unsortiert',
    processing: 'In Verarbeitung',
    ready: 'Bereit',
    needs_attention: 'Benötigt Aufmerksamkeit',
    failed: 'Fehlgeschlagen',
    video: 'Video',
    audio: 'Audio',
    image: 'Bild',
    document: 'Dokument',
    pdf: 'PDF',
    other: 'Datei',
  };

  const escape = value => String(value ?? '').replace(/[&<>'"]/g, char => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    "'": '&#39;',
    '"': '&quot;',
  }[char]));

  const prettyBytes = value => {
    const amount = Number(value ?? 0);
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let index = 0;
    let size = amount;
    while (size >= 1024 && index < units.length - 1) {
      size /= 1024;
      index++;
    }
    return `${new Intl.NumberFormat('de-DE', { maximumFractionDigits: index === 0 ? 0 : 1 }).format(size)} ${units[index]}`;
  };

  const prettyDate = value => value ? new Intl.DateTimeFormat('de-DE', {
    dateStyle: 'medium',
  }).format(new Date(value)) : '–';

  const icon = kind => ({ video: '▶', audio: '♫', image: '▧', document: '▤', pdf: '▤', other: '…' }[kind] || '…');

  const preview = item => {
    if (!item.preview_url) return '';
    const url = escape(item.preview_url);
    if (item.kind === 'image') return `<img class="media-library-preview image" src="${url}" alt="">`;
    if (item.kind === 'audio') return `<audio class="media-library-preview audio" controls preload="metadata" src="${url}"></audio>`;
    if (item.kind === 'video') return `<video class="media-library-preview video" controls preload="metadata" src="${url}"></video>`;
    if (item.mime === 'application/pdf') return `<iframe class="media-library-preview pdf" src="${url}" title="PDF-Vorschau" sandbox></iframe>`;
    return '';
  };

  const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

  const extractError = payload => {
    if (!payload || typeof payload !== 'object') return null;
    if (typeof payload.message === 'string' && payload.message.length > 0) return payload.message;
    if (typeof payload.error === 'string' && payload.error.length > 0) return payload.error;
    if (typeof payload.errors === 'object') {
      const flattened = Object.values(payload.errors)
        .flatMap(group => Array.isArray(group) ? group : []);
      const combined = flattened.filter(entry => typeof entry === 'string');
      if (combined.length > 0) return combined.join(' ');
    }
    return null;
  };

  const requestJson = async (url, options = {}) => {
    const headers = {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...(options.headers || {}),
    };
    const token = csrfToken();
    if (token) headers['X-CSRF-TOKEN'] = token;
    const response = await fetch(url, {
      credentials: 'same-origin',
      ...options,
      headers,
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
      const message = extractError(payload) || 'Upload fehlgeschlagen.';
      throw new Error(message);
    }
    return payload;
  };

  const sha256 = async blob => {
    if (!crypto?.subtle) throw new Error('Browser-Unterstützung für Upload fehlt.');
    const buffer = await blob.arrayBuffer();
    const hash = await crypto.subtle.digest('SHA-256', buffer);
    return [...new Uint8Array(hash)].map(item => item.toString(16).padStart(2, '0')).join('');
  };

  const randomRequestKey = () => crypto.randomUUID?.() || `${Date.now()}-${Math.random().toString(16).slice(2)}`;

  window.initializeMediaLibrary = root => {
    if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true';

    const form = root.querySelector('[data-library-filter]');
    const list = root.querySelector('[data-library-list]');
    const summary = root.querySelector('[data-library-summary]');
    const details = root.querySelector('[data-library-details]');
    const pagination = root.querySelector('[data-library-pagination]');
    const uploadButton = root.querySelector('[data-media-upload]');
    const uploadInput = root.querySelector('[data-media-upload-input]');
    const uploadMessage = root.querySelector('[data-media-upload-message]');

    let selected = null;
    let current = [];
    let lastPayload = null;
    let uploading = false;

    const setUploadMessage = (text, isError = false) => {
      if (!uploadMessage) return;
      uploadMessage.textContent = text || '';
      uploadMessage.hidden = !text;
      uploadMessage.classList.toggle('is-error', Boolean(isError));
    };

    const setUploading = state => {
      uploading = state;
      if (uploadButton) uploadButton.disabled = state;
      if (uploadInput) uploadInput.disabled = state;
    };

    const uploadFile = file => window.uploadDesktopMedia(file, root.dataset.userId, (offset, total) => {
      setUploadMessage(`${file.name}: ${prettyBytes(offset)} / ${prettyBytes(total)}`);
    });

    const uploadSelectedFiles = async files => {
      if (!files?.length) return;
      setUploading(true);
      setUploadMessage('Lade Medien hoch …');
      try {
        for (const file of files) {
          await uploadFile(file);
        }
        await load(1);
        setUploadMessage('');
      } catch (error) {
        setUploadMessage(error.message || 'Upload fehlgeschlagen.', true);
      } finally {
        if (uploadInput) uploadInput.value = '';
        setUploading(false);
      }
    };

    const query = page => {
      const params = new URLSearchParams(new FormData(form));
      if (page > 1) params.set('page', page);
      [...params.entries()].filter(([, value]) => !value).forEach(([key]) => params.delete(key));
      return `${root.dataset.libraryUrl}?${params}`;
    };

    const renderDetails = item => {
      const tags = Array.isArray(item?.tags) ? item.tags : [];
      if (!item) {
        details.innerHTML = '<p>Wähle ein Medium, um Details zu sehen.</p>';
        return;
      }
      details.innerHTML = `${preview(item)}
      <span class="media-library-detail-icon" aria-hidden="true">${icon(item.kind)}</span>
      <h3>${escape(item.title)}</h3>
      <p>${escape(item.original_name)}</p>
      <dl>
        <div><dt>Typ</dt><dd>${escape(labels[item.kind] || item.kind)}</dd></div>
        <div><dt>Quelle</dt><dd>${escape(labels[item.source] || item.source)}</dd></div>
        <div><dt>Status</dt><dd>${escape(labels[item.status] || item.status)}</dd></div>
        <div><dt>Größe</dt><dd>${escape(prettyBytes(item.bytes))}</dd></div>
        <div><dt>Hinzugefügt</dt><dd>${prettyDate(item.created_at)}</dd></div>
        ${item.asset_count ? `<div><dt>Verbundene Assets</dt><dd>${item.asset_count}</dd></div>` : ''}
      </dl>
      ${tags.length ? `<p class="media-library-tags">${tags.map(tag => `<span>${escape(tag)}</span>`).join('')}</p>` : ''}
      <a class="media-library-download" href="${escape(item.download_url)}">Herunterladen</a>`;
    };

    const render = payload => {
      lastPayload = payload;
      current = payload.data;
      if (selected) selected = current.find(item => item.id === selected.id) || null;
      list.innerHTML = current.length
        ? current
          .map(item => `<li><button type="button" class="media-library-item ${selected?.id === item.id ? 'is-selected' : ''}" data-media-id="${escape(item.id)}"><span class="media-library-file-icon" aria-hidden="true">${icon(item.kind)}</span><span class="media-library-item-main"><strong>${escape(item.title)}</strong><small>${escape(labels[item.source] || item.source)} · ${escape(prettyBytes(item.bytes))} · ${prettyDate(item.created_at)}</small></span><span class="media-library-status status-${escape(item.status)}">${escape(labels[item.status] || item.status)}</span></button></li>`)
          .join('')
        : '<li class="media-library-empty">Keine Medien für diese Auswahl.</li>';
      summary.textContent = `${payload.meta.total} Medien im Archiv`;
      pagination.innerHTML = payload.meta.last_page > 1
        ? Array.from({ length: payload.meta.last_page }, (_, index) => `<button type="button" data-page="${index + 1}" ${payload.meta.current_page === index + 1 ? 'aria-current="page"' : ''}>${index + 1}</button>`).join('')
        : '';
      renderDetails(selected);
    };

    const load = async page => {
      summary.textContent = 'Archiv wird geladen …';
      try {
        const payload = await requestJson(query(page), {
          method: 'GET',
          headers: { Accept: 'application/json' },
        });
        render(payload);
      } catch (_) {
        list.innerHTML = '<li class="media-library-empty">Das Archiv konnte nicht geladen werden.</li>';
        summary.textContent = 'Fehler beim Laden';
      }
    };

    form.addEventListener('input', () => load(1));
    form.addEventListener('change', () => load(1));
    list.addEventListener('click', event => {
      const id = event.target.closest('[data-media-id]')?.dataset.mediaId;
      if (!id) return;
      selected = current.find(item => item.id === id) || null;
      if (lastPayload) render(lastPayload);
    });
    pagination.addEventListener('click', event => {
      const page = Number(event.target.closest('[data-page]')?.dataset.page);
      if (Number.isSafeInteger(page)) load(page);
    });

    if (uploadButton && uploadInput) {
      uploadButton.addEventListener('click', () => {
        if (uploading) return;
        uploadInput.click();
      });
      uploadInput.addEventListener('change', () => {
        if (uploading) return;
        uploadSelectedFiles(Array.from(uploadInput.files || []));
      });
    }

    load(1);
  };
})();
