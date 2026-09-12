(() => {
  const escape = value => String(value ?? '').replace(/[&<>'"]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch]));
  const request = async (url, options = {}) => {
    const response = await fetch(url, {credentials:'same-origin', ...options, headers:{Accept:'application/json', 'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content || '', ...options.headers}});
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || window.desktopImportLabels.load_error);
    return data;
  };
  const sourceFromLink = value => {
    try {
      const url = new URL(value);
      if (url.protocol !== 'https:' || url.username || url.password || url.port) return null;
      const host = url.hostname.toLowerCase();
      if (['youtube.com','www.youtube.com','m.youtube.com','youtu.be'].includes(host)) return 'youtube-service';
      if (['tiktok.com','www.tiktok.com'].includes(host)) return 'tiktok';
      if (['instagram.com','www.instagram.com'].includes(host)) return 'instagram';
      if (['facebook.com','www.facebook.com','m.facebook.com'].includes(host)) return 'facebook-video';
    } catch (_) {}
    return null;
  };
  window.initializeImportCenter = root => {
    if (!root || root.dataset.initialized) return;
    root.dataset.initialized = 'true';
    const t = window.desktopImportLabels;
    const form = root.querySelector('[data-import-form]');
    const message = root.querySelector('[data-import-message]');
    const start = root.querySelector('[data-import-start]');
    const fileInput = root.querySelector('[data-import-file]');
    const link = root.querySelector('[data-import-link]');
    const list = root.querySelector('[data-import-run-list]');
    const history=root.querySelector('[data-import-history-dialog]');
    root.querySelector('[data-open-import-history]').addEventListener('click',()=>{history.showModal();loadRuns().catch(historyError);});
    root.querySelector('[data-close-import-history]').addEventListener('click',()=>history.close());
    const historyError=error=>{
      root.querySelector('[data-import-status]').textContent=t.history_error+': '+error.message;
      if(!list.children.length)list.textContent=t.history_error+': '+error.message;
    };
    const browserList = root.querySelector('[data-import-browser-list]');
    const browserMessage = root.querySelector('[data-import-browser-message]');
    const folderButton = root.querySelector('[data-import-use-folder]');
    const up = root.querySelector('[data-import-up]');
    let selection = null, folder = '', parent = null, runPage = 1, previousStatuses = new Map();
    let browsing = 0, serverNow = Date.now();
    let uploadControl = null, uploadPaused = false;
    const uploadControls = root.querySelector('[data-import-upload-controls]');
    const pause = root.querySelector('[data-import-upload-pause]');
    pause.addEventListener('click', () => {if (!uploadControl) return; uploadPaused = !uploadPaused; uploadControl[uploadPaused ? 'pause' : 'resume'](); pause.textContent = t[uploadPaused ? 'upload_resume' : 'upload_pause'];});
    root.querySelector('[data-import-upload-stop]').addEventListener('click', () => uploadControl?.stop());
    const method = () => form.querySelector('[name="method"]:checked')?.value;
    const showMessage = (value, error = false) => {message.textContent = value; message.hidden = !value; message.classList.toggle('is-error', error);};
    const selected = () => {root.querySelector('[data-import-selection]').textContent = selection ? t.selected + ': ' + (selection.path === '.' ? t.browser_root : selection.path) : t.select_folder_hint;};
    const loadFolder = async path => {
      const generation = ++browsing;
      selection = null; selected();
      browserMessage.textContent = t.browser_loading;
      folderButton.disabled = true; up.disabled = true; browserList.replaceChildren();
      try {
        const data = await request(root.dataset.importFilesUrl + '?' + new URLSearchParams({path}));
        if (generation !== browsing || !root.isConnected) return;
        folder = data.path; parent = data.parent; selection = null; selected();
        root.querySelector('[data-import-browser-path]').textContent = folder ? t.browser_root + ' / ' + folder : t.browser_root;
        folderButton.disabled = !data.available; up.disabled = parent === null;
        browserList.innerHTML = data.data.map(item => '<li>' + (item.type === 'file' ? '<span class="import-browser-file">▤ ' + escape(item.name) + ' <small>' + escape(t.file_in_folder) + '</small></span>' : '<button type="button" class="desktop-button" data-import-entry="' + escape(item.path) + '" data-entry-source="' + escape(item.source) + '"><span aria-hidden="true">' + (item.type === 'folder' ? '▱' : '▤') + '</span> ' + escape(item.name) + ' <small>' + escape(item.type === 'folder' ? t.open_folder : t.select_archive) + '</small></button>') + '</li>').join('');
        browserMessage.textContent = !data.available ? t.browser_unavailable : data.truncated ? t.browser_truncated : !data.data.length ? t.browser_empty : '';
      } catch (error) {browserList.replaceChildren(); browserMessage.textContent = error.message;}
    };
    const setMethod = value => {
      form.querySelector('[name="method"][value="' + value + '"]').checked = true;
      root.querySelectorAll('[data-import-panel]').forEach(panel => {panel.hidden = panel.dataset.importPanel !== value; panel.querySelectorAll('input, select').forEach(input => {input.disabled = panel.hidden;});});
      start.textContent = value === 'existing' ? t.register_existing : t.start;
      showMessage('');
      if (value === 'server') loadFolder(folder);
      if (value === 'takeout') window.loadTakeoutInventory?.(root);
    };
    up.addEventListener('click', () => {if (parent !== null) loadFolder(parent);});
    root.querySelector('[data-import-reload]').addEventListener('click', () => loadFolder(folder));
    browserList.addEventListener('click', event => {
      const button = event.target.closest('[data-import-entry]'); if (!button) return;
      if (button.dataset.entrySource === 'local-folder') loadFolder(button.dataset.importEntry);
      else {selection = {source:'local-archive', path:button.dataset.importEntry}; selected();}
    });
    folderButton.addEventListener('click', () => {selection = {source:'local-folder', path:folder || '.'}; selected();});
    form.querySelectorAll('[name="method"]').forEach(input => input.addEventListener('change', () => setMethod(input.value)));
    link.addEventListener('input', () => {const source = sourceFromLink(link.value.trim()); root.querySelector('[data-import-detected]').textContent = !link.value ? '' : source ? t.detected_service + ': ' + t['source_' + source] : t.supported_links;});
    fileInput?.addEventListener('change', () => setMethod('computer'));
    const zone = root.querySelector('[data-import-drop]');
    zone?.addEventListener('dragover', event => {event.preventDefault(); zone.classList.add('is-dragging');});
    zone?.addEventListener('dragleave', () => zone.classList.remove('is-dragging'));
    zone?.addEventListener('drop', event => {
      event.preventDefault(); zone.classList.remove('is-dragging');
      if (start.disabled) return;
      if (event.dataTransfer.files.length !== 1) {showMessage(t.one_archive, true); return;}
      fileInput.files = event.dataTransfer.files; setMethod('computer');
    });
    const date = value => value ? new Intl.DateTimeFormat(document.documentElement.lang, {dateStyle:'medium',timeStyle:'short'}).format(new Date(value)) : '—';
    const bytes = value => {
      const n = Math.max(0, Number(value) || 0), units = ['B','KB','MB','GB','TB'];
      const unit = n ? Math.min(4, Math.floor(Math.log(n) / Math.log(1024))) : 0;
      return new Intl.NumberFormat(document.documentElement.lang, {maximumFractionDigits:1}).format(n / 1024 ** unit) + ' ' + units[unit];
    };
    const progressHtml = run => {
      if (run.status === 'queued') return '<p class="import-live-status">' + escape(t.progress_queued) + '</p>';
      if (!['running','stop_requested'].includes(run.status)) return '';
      const p = run.progress || {}, seconds = Math.max(0, Math.floor((serverNow - Date.parse(p.activity_at || run.updated_at)) / 1000));
      const fresh = Number.isFinite(seconds) && seconds <= 30;
      const activity = Number.isFinite(seconds) ? (fresh ? t.progress_active : t.progress_last).replace(':seconds', seconds) : t.history_stale;
      let html = '<div class="import-live-status' + (fresh ? ' is-active' : '') + '"><strong>' + escape(t['stage_' + p.stage] || t.processing) + '</strong><p>' + escape(activity) + '</p>';
      if (seconds > 600) html += '<p class="import-progress-warning">' + escape(t.progress_stale) + '</p>';
      if (p.part && p.parts) html += '<p>' + escape(t.progress_part.replace(':part', p.part).replace(':parts', p.parts)) + (p.archive ? ' · ' + escape(p.archive) : '') + '</p>';
      if (p.file) html += '<p class="import-progress-file">' + escape(t.progress_file) + ': ' + escape(p.file) + '</p>';
      if (Number(p.file_total_bytes) > 0) {
        const done = Math.min(Number(p.file_total_bytes), Math.max(0, Number(p.file_bytes) || 0)), percent = Math.floor(done / Number(p.file_total_bytes) * 100);
        html += '<label class="import-file-progress">' + escape(t.progress_file_bytes) + ': ' + bytes(done) + ' / ' + bytes(p.file_total_bytes) + ' · ' + percent + ' %<progress max="' + Number(p.file_total_bytes) + '" value="' + done + '"></progress></label>';
      }
      if (p.stage === 'extract' && Number(p.extract_total_bytes) > 0) html += '<p>' + escape(t.progress_extracted) + ': ' + bytes(p.extracted_bytes) + ' / ' + bytes(p.extract_total_bytes) + '</p>';
      if (p.stage === 'extract' && p.entry && p.entries) html += '<small>' + escape(t.progress_entry.replace(':entry', p.entry).replace(':entries', p.entries)) + '</small>';
      return html + '</div>';
    };
    const workerText = worker => {
      if (!worker || worker.state === 'idle') return '';
      let value = t['worker_' + worker.state] || t.worker_unavailable;
      if (worker.state === 'working') value += ' · ' + t.worker_io.replace(':seconds', worker.interval_seconds) + ': ' + t.worker_read + ' ' + bytes(worker.read_bytes) + ', ' + t.worker_written + ' ' + bytes(worker.written_bytes);
      if (worker.observed_at) value += ' · ' + t.worker_measured + ': ' + date(worker.observed_at);
      return value + ' ' + t.worker_scope;
    };
    const runHtml = run => '<article class="import-center-run" data-import-run="' + escape(run.id) + '"><div class="import-run-heading"><strong>' + escape(t['source_' + run.source] || run.source) + '</strong><span class="media-library-status status-' + escape(run.status) + '">' + escape(t['run_' + run.status] || run.status) + '</span></div>' + progressHtml(run) + (run.status === 'stop_requested' ? '<small>' + escape(t.stop_pending_hint) + '</small>' : '') + (run.source_ref ? '<p>' + escape(run.source_ref) + '</p>' : '') + '<dl><dt>' + escape(t.result_added) + '</dt><dd>' + Number(run.imported || 0) + '</dd><dt>' + escape(t.result_found) + '</dt><dd>' + Number(run.discovered || 0) + '</dd><dt>' + escape(t.result_skipped) + '</dt><dd>' + Number(run.skipped || 0) + '</dd><dt>' + escape(t.updated) + '</dt><dd>' + escape(date(run.updated_at)) + '</dd></dl>' + (run.skipped ? '<small>' + escape(t.skipped_hint) + '</small>' : '') + (run.error || run.notes?.length ? '<p class="is-error">' + escape(run.error || run.notes.join(' / ')) + '</p>' : '') + '<div class="import-run-actions"><button type="button" class="desktop-button" data-open-app="media">' + escape(t.view_library) + '</button>' + (['failed','partial','cancelled'].includes(run.status) ? '<button type="button" class="desktop-button" data-import-retry="' + escape(run.id) + '">' + escape(t.retry) + '</button>' : '') + (['queued','running'].includes(run.status) ? '<button type="button" class="desktop-button" data-import-stop="' + escape(run.id) + '">' + escape(t.stop_import) + '</button>' : '') + '</div></article>';
    const loadRuns = async (number = runPage) => {
      const data = await request(root.dataset.importsUrl + '?page=' + number);
      if (!root.isConnected) return;
      serverNow = Date.parse(data.meta.server_time) || Date.now();
      runPage = data.meta.current_page;
      if (data.data.some(run => ['complete','partial','cancelled'].includes(run.status) && previousStatuses.get(run.id) !== run.status)) document.dispatchEvent(new Event('desktop-media-changed'));
      previousStatuses = new Map(data.data.map(run => [run.id,run.status]));
      list.innerHTML = data.data.length ? data.data.map(runHtml).join('') : '<p class="import-center-empty">' + escape(t.no_runs) + '</p>';
      root.dispatchEvent(new CustomEvent('import-runs-loaded',{bubbles:true,detail:data.data}));
      const active=data.meta.active??data.data.filter(run=>['queued','running','stop_requested'].includes(run.status)).length;
      const current=data.meta.active_run||data.data[0];
      root.querySelectorAll('[data-import-worker-status]').forEach(element => {element.textContent = workerText(data.meta.worker); element.hidden = !element.textContent;});
      root.querySelector('[data-import-status]').textContent=data.meta.total+' '+t.run_history+' · '+active+' '+t.history_active+(current?' · '+(t['source_'+current.source]||current.source)+': '+(t['run_'+current.status]||current.status)+(current.progress?.stage?' · '+(t['stage_'+current.progress.stage]||current.progress.stage):'')+' · '+t.history_updated+': '+date(current.updated_at)+(active&&Date.now()-Date.parse(current.updated_at)>600000?' · '+t.history_stale:''):'');
      root.querySelector('[data-import-pages]').innerHTML = '<button type="button" data-import-page="' + (runPage - 1) + '" ' + (runPage <= 1 ? 'disabled' : '') + '>‹</button><span>' + runPage + ' / ' + data.meta.last_page + '</span><button type="button" data-import-page="' + (runPage + 1) + '" ' + (runPage >= data.meta.last_page ? 'disabled' : '') + '>›</button>';
    };
    form.addEventListener('submit', async event => {
      event.preventDefault(); if (start.disabled) return;
      const payload = {target_profile:root.querySelector('[data-import-target]').value, only_unsorted:true, notes:root.querySelector('[data-import-notes]').value};
      const activeMethod = method();
      form.querySelectorAll('[name="method"]').forEach(input => {input.disabled = true;});
      try {
        if (activeMethod === 'computer') {
          const file = fileInput?.files[0];
          if (!file || !/\.(zip|tar|tar\.gz|tgz)$/i.test(file.name)) throw new Error(t.archive_required);
          start.disabled = true;
          uploadControl = window.createDesktopUploadControl(); uploadPaused = false; pause.textContent = t.upload_pause; uploadControls.hidden = false; fileInput.disabled = true;
          payload.source = 'local-archive';
          payload.media_id = await window.uploadDesktopMedia(file, root.dataset.userId, (bytes, total) => showMessage(file.name + ': ' + Math.floor(bytes / total * 100) + ' %'), uploadControl);
          await uploadControl.checkpoint(); uploadControls.hidden = true;
        } else if (activeMethod === 'link') {
          payload.source_ref = link.value.trim(); payload.source = sourceFromLink(payload.source_ref);
          if (!payload.source) throw new Error(t.supported_links);
        } else if (activeMethod === 'takeout') {payload.source='youtube-takeout'; payload.batch=root.querySelector('[data-takeout-batch]').value; payload.expected_parts=Number(root.querySelector('[data-takeout-parts]').value);}
        else if (activeMethod === 'existing') payload.source = root.querySelector('[data-import-existing]').value;
        else {if (!selection) throw new Error(t.select_folder_hint); Object.assign(payload, selection);}
        start.disabled = true; showMessage(t.starting);
        await request(root.dataset.importsUrl, {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
        showMessage(t.started); if (fileInput) fileInput.value = '';
        await loadRuns(1);
        if(root.isConnected && !history.open)history.showModal();
      } catch (error) {showMessage(uploadControl?.signal.aborted ? t.upload_stopped : error.message, true);}
      finally {start.disabled = false; uploadControls.hidden = true; uploadControl = null; if (fileInput) fileInput.disabled = method() !== 'computer'; form.querySelectorAll('[name="method"]').forEach(input => {input.disabled = false;});}
    });
    root.querySelector('[data-import-refresh]').addEventListener('click', () => loadRuns().catch(error => showMessage(error.message, true)));
    root.querySelector('[data-import-pages]').addEventListener('click', event => {const button = event.target.closest('[data-import-page]'); if (button) loadRuns(Number(button.dataset.importPage)).catch(error => showMessage(error.message, true));});
    list.addEventListener('click', async event => {
      const button = event.target.closest('[data-import-retry], [data-import-stop]'); if (!button) return;
      const stopping = Boolean(button.dataset.importStop);
      if (stopping && !window.confirm(t.stop_import_confirm)) return;
      button.disabled = true;
      try {await request(root.dataset.importsUrl + '/' + (button.dataset.importStop || button.dataset.importRetry) + (stopping ? '/stop' : '/retry'), {method:'POST'}); await loadRuns();}
      catch (error) {showMessage(error.message, true); button.disabled = false;}
    });
    request(root.dataset.importsOptionsUrl).then(data => {
      for (const target of data.targets) root.querySelector('[data-import-target]').append(new Option(t[target.id] || target.label, target.id));
    }).catch(error => showMessage(error.message, true));
    setMethod(method());
    loadRuns().catch(historyError);
    const timer = setInterval(() => {if (!root.isConnected) {clearInterval(timer); return;} loadRuns().catch(historyError);}, 5000);
  };
})();
