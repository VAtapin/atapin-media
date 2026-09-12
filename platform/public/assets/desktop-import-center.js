(() => {
  const escape = value => String(value ?? '').replace(/[&<>'"]/g, char => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    "'": '&#39;',
    '"': '&quot;',
  }[char]));

  const statusLabel = {
    queued: 'Warten',
    running: 'Läuft',
    complete: 'Erledigt',
    partial: 'Teilweise',
    failed: 'Fehlgeschlagen',
  };

  const requestJson = async (url, options = {}) => {
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const headers = {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...(options.headers || {}),
    };
    if (token) {
      headers['X-CSRF-TOKEN'] = token;
    }

    const response = await fetch(url, {
      credentials: 'same-origin',
      ...options,
      headers,
    });

    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
      const message = typeof payload?.message === 'string'
        ? payload.message
        : 'Import konnte nicht gestartet werden.';
      throw new Error(message);
    }
    return payload;
  };

  const normalizeTarget = value => value && String(value).trim()
    ? String(value).trim()
    : 'media_library';

  const buildPayload = (formData) => {
    const source = String(formData.source || '').trim();
    const value = String(formData.source_value || '').trim();
    const target = normalizeTarget(formData.target_profile);
    const payload = {
      source,
      target_profile: target,
      only_unsorted: Boolean(formData.only_unsorted),
      notes: String(formData.notes || '').trim() || undefined,
    };

    if (source === 'local-folder' || source === 'local-archive') {
      payload.path = value;
    } else {
      payload.source_ref = value;
    }

    if (formData.channel_id) {
      payload.channel_id = formData.channel_id;
    }
    if (formData.playlist_id) {
      payload.playlist_id = formData.playlist_id;
    }
    if (!payload.source_ref && payload.path) {
      payload.source_ref = payload.path;
    }
    return payload;
  };

  const setFormMessage = (element, text, isError = false) => {
    if (!element) return;
    element.textContent = text || '';
    element.hidden = !text;
    element.classList.toggle('is-error', Boolean(isError));
  };

  const normalizeSourceValueHint = selectedSource => {
    const known = {
      'intake': 'Pfadeigabe nicht nötig — Intake-Archiv wird direkt eingelesen.',
      'youtube': 'Pfad oder Quelle nicht nötig, wenn Channel-/Playlist-ID gesetzt ist.',
      'youtube-service': 'Lege hier einen YouTube-Link, Video- oder Channel-URL ein.',
      'local-folder': 'Pfad relativ zu private/import-inbox (z. B. ordner/unterordner).',
      'local-archive': 'Pfad relativ zu private/import-inbox (z. B. upload.zip).',
      'tiktok': 'Link oder öffentliche Profil-/Channel-URL.',
      'instagram': 'Link oder öffentliche Profil-/Post-URL.',
      'facebook-video': 'Link oder öffentliche Video-/Gruppe-URL.',
    };
    return known[selectedSource] || 'Gib den Pfad oder die URL der Quelle ein.';
  };

  const renderRun = run => `
    <article class="import-center-run">
      <b>${escape(run.source)} · ${escape(statusLabel[run.status] || run.status || 'Unbekannt')}</b>
      <dl>
        <dt>Quelle</dt><dd>${escape(run.source_ref || '—')}</dd>
        <dt>Zielbereich</dt><dd>${escape(run.target_profile || '—')}</dd>
        <dt>Gefunden / Importiert / Übersprungen</dt><dd>${escape(run.discovered || 0)} / ${escape(run.imported || 0)} / ${escape(run.skipped || 0)}</dd>
        <dt>Notiz</dt><dd>${escape(Array.isArray(run.notes) && run.notes.length ? run.notes.join(' / ') : run.error || '—')}</dd>
        <dt>Aktualisiert</dt><dd>${escape(run.updated_at || '—')}</dd>
      </dl>
    </article>`;

  const renderStatus = (runList) => {
    const running = runList.filter((run) => run.status === 'running' || run.status === 'queued').length;
    return `${runList.length} Jobs, ${running} in Bearbeitung`;
  };

  const applyRunHints = (select, value) => {
    const inputHint = select.closest('form')?.querySelector('[data-import-source-value]')?.closest('label');
    const note = inputHint?.querySelector('small');
    if (note) note.textContent = normalizeSourceValueHint(value);
  };

  window.initializeImportCenter = root => {
    if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true';

    const form = root.querySelector('[data-import-form]');
    const sourceSelect = root.querySelector('[data-import-source]');
    const targetSelect = root.querySelector('[data-import-target]');
    const message = root.querySelector('[data-import-message]');
    const list = root.querySelector('[data-import-run-list]');
    const status = root.querySelector('[data-import-status]');
    const refreshButton = root.querySelector('[data-import-refresh]');
    const optionsUrl = root.dataset.importsOptionsUrl;
    const listUrl = root.dataset.importsUrl;
    const startUrl = root.dataset.importsUrl;

    if (!form || !sourceSelect || !targetSelect || !list || !status || !optionsUrl || !listUrl) {
      return;
    }

    const hydrateSourceAndTargets = async () => {
      const payload = await requestJson(optionsUrl);
      payload.sources?.forEach((source) => {
        const option = document.createElement('option');
        option.value = source.id;
        option.textContent = source.label;
        sourceSelect.append(option);
      });

      payload.targets?.forEach((target) => {
        const option = document.createElement('option');
        option.value = target.id;
        option.textContent = target.label;
        targetSelect.append(option);
      });
    };

    const loadRuns = async () => {
      const payload = await requestJson(`${listUrl}?per_page=12`, { method: 'GET' });
      const runs = Array.isArray(payload.data) ? payload.data : [];
    list.innerHTML = runs.length
        ? runs.map(renderRun).join('')
        : '<div class="import-center-empty">Noch keine Import-Vorgänge gestartet.</div>';
      status.textContent = renderStatus(runs);
    };

    sourceSelect.addEventListener('change', () => applyRunHints(sourceSelect, sourceSelect.value));
    form.addEventListener('submit', async event => {
      event.preventDefault();
      const formData = Object.fromEntries(new FormData(form).entries());
      const payload = buildPayload(formData);
      setFormMessage(message, 'Import wird gestartet ...');
      try {
        if (!payload.source) {
          throw new Error('Bitte zuerst eine Quelle auswählen.');
        }
        if (!payload.path && !payload.source_ref && ['local-folder', 'local-archive', 'youtube-service', 'tiktok', 'instagram', 'facebook-video'].includes(payload.source)) {
          throw new Error('Bitte Pfad oder URL angeben.');
        }
        if (!payload.path && !payload.source_ref && payload.source === 'youtube') {
          if (!payload.channel_id && !payload.playlist_id) {
            throw new Error('Für YouTube-Archiv bitte Channel-ID oder Playlist-ID angeben.');
          }
        }
        const response = await requestJson(startUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });
        setFormMessage(message, `Import gestartet: ${response.import_id}`);
        form.reset();
        if (sourceSelect.options.length && sourceSelect.selectedIndex >= 0) {
          applyRunHints(sourceSelect, sourceSelect.value);
        }
        await loadRuns();
      } catch (error) {
        setFormMessage(message, error.message || 'Import konnte nicht gestartet werden.', true);
      }
    });

    hydrateSourceAndTargets().then(() => {
      if (sourceSelect.options.length > 1) sourceSelect.selectedIndex = 1;
      applyRunHints(sourceSelect, sourceSelect.value);
      loadRuns();
      setInterval(loadRuns, 5000);
    }).catch(() => {
      setFormMessage(message, 'Optionen konnten nicht geladen werden.', true);
    });
    refreshButton?.addEventListener('click', () => loadRuns());
  };
})();
