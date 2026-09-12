(() => {
  const request = async (url, options = {}) => {
    const response = await fetch(url, { credentials: 'same-origin', ...options, headers: {
      Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', ...options.headers,
    } });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      const error = new Error(data.message || 'Upload fehlgeschlagen.');
      error.retry = response.status >= 500 || response.status === 409 || response.status === 429;
      throw error;
    }
    return data;
  };
  window.createDesktopUploadControl = () => {
    let paused = false, cancelled = false;
    const waiters = new Set();
    const wake = () => {for (const resolve of waiters) resolve(); waiters.clear();};
    const abort = new AbortController();
    return {signal:abort.signal,
      pause:() => {paused = true;}, resume:() => {paused = false; wake();},
      stop:() => {cancelled = true; paused = false; abort.abort(); wake();},
      checkpoint:async () => {
        while (paused && !cancelled) await new Promise(resolve => {waiters.add(resolve);});
        if (cancelled) {const error = new Error(window.desktopImportLabels.upload_stopped); error.name = 'UploadStopped'; throw error;}
      },
    };
  };
  const retry = async (operation, control) => {
    for (let attempt = 0; ; attempt++) {
      await control?.checkpoint();
      try { return await operation(); }
      catch (error) {
        if (control?.signal.aborted || error.name === 'AbortError' || error.name === 'UploadStopped') throw error;
        if (attempt >= 4 || error.retry === false) throw error;
        await new Promise(resolve => setTimeout(resolve, Math.min(1000 * 2 ** attempt, 8000)));
      }
    }
  };
  window.uploadDesktopMedia = async (file, user, progress = () => {}, control = null) => {
    if (!file.size || !crypto.subtle || !crypto.randomUUID) throw new Error('Upload nicht verfügbar.');
    const storageKey = `media-upload:${user}:${file.webkitRelativePath || file.name}:${file.size}:${file.lastModified}`;
    let key;
    try { key = localStorage.getItem(storageKey); } catch (_) { /* Private mode. */ }
    key ||= crypto.randomUUID();
    try { localStorage.setItem(storageKey, key); } catch (_) { /* Upload still works. */ }
    const session = await retry(() => request('/desktop/media/uploads', { method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ request_key: key, name: file.name, size: file.size }), signal:control?.signal,
    }), control);
    let offset = Number(session.offset);
    const chunkSize = Number(session.chunk_size);
    if (!Number.isSafeInteger(offset) || offset < 0 || offset > file.size || !Number.isSafeInteger(chunkSize) || chunkSize <= 0) throw new Error('Ungültige Upload-Sitzung.');
    progress(offset, file.size);
    while (offset < file.size) {
      await control?.checkpoint();
      const chunk = file.slice(offset, Math.min(offset + chunkSize, file.size));
      const digest = await crypto.subtle.digest('SHA-256', await chunk.arrayBuffer());
      const hash = [...new Uint8Array(digest)].map(value => value.toString(16).padStart(2, '0')).join('');
      const uploaded = await retry(() => request(`/desktop/media/uploads/${encodeURIComponent(session.id)}/chunk`, {
        method: 'POST', headers: { 'Content-Type': 'application/octet-stream', 'X-Upload-Offset': String(offset), 'X-Chunk-SHA256': hash }, body: chunk, signal:control?.signal,
      }), control);
      const next = Number(uploaded.offset);
      if (!Number.isSafeInteger(next) || next <= offset || next > file.size) throw new Error('Upload-Fortschritt konnte nicht bestätigt werden.');
      offset = next; progress(offset, file.size);
    }
    const result = await retry(() => request(`/desktop/media/uploads/${encodeURIComponent(session.id)}/finish`, { method: 'POST', signal:control?.signal,
      headers:{'Content-Type':'application/json'}, body:JSON.stringify({client_relative_path:file.webkitRelativePath || null}),
    }), control);
    try { localStorage.removeItem(storageKey); } catch (_) { /* Private mode. */ }
    document.dispatchEvent(new Event('desktop-media-changed'));
    return result.media_id;
  };
})();
