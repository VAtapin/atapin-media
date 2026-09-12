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
  const retry = async operation => {
    for (let attempt = 0; ; attempt++) {
      try { return await operation(); }
      catch (error) {
        if (attempt >= 4 || error.retry === false) throw error;
        await new Promise(resolve => setTimeout(resolve, Math.min(1000 * 2 ** attempt, 8000)));
      }
    }
  };
  window.uploadDesktopMedia = async (file, user, progress = () => {}) => {
    if (!file.size || !crypto.subtle || !crypto.randomUUID) throw new Error('Upload nicht verfügbar.');
    const storageKey = `media-upload:${user}:${file.name}:${file.size}:${file.lastModified}`;
    let key;
    try { key = localStorage.getItem(storageKey); } catch (_) { /* Private mode. */ }
    key ||= crypto.randomUUID();
    try { localStorage.setItem(storageKey, key); } catch (_) { /* Upload still works. */ }
    const session = await retry(() => request('/desktop/media/uploads', { method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ request_key: key, name: file.name, size: file.size }),
    }));
    let offset = Number(session.offset);
    const chunkSize = Number(session.chunk_size);
    if (!Number.isSafeInteger(offset) || offset < 0 || offset > file.size || !Number.isSafeInteger(chunkSize) || chunkSize <= 0) throw new Error('Ungültige Upload-Sitzung.');
    progress(offset, file.size);
    while (offset < file.size) {
      const chunk = file.slice(offset, Math.min(offset + chunkSize, file.size));
      const digest = await crypto.subtle.digest('SHA-256', await chunk.arrayBuffer());
      const hash = [...new Uint8Array(digest)].map(value => value.toString(16).padStart(2, '0')).join('');
      const uploaded = await retry(() => request(`/desktop/media/uploads/${encodeURIComponent(session.id)}/chunk`, {
        method: 'POST', headers: { 'Content-Type': 'application/octet-stream', 'X-Upload-Offset': String(offset), 'X-Chunk-SHA256': hash }, body: chunk,
      }));
      const next = Number(uploaded.offset);
      if (!Number.isSafeInteger(next) || next <= offset || next > file.size) throw new Error('Upload-Fortschritt konnte nicht bestätigt werden.');
      offset = next; progress(offset, file.size);
    }
    const result = await retry(() => request(`/desktop/media/uploads/${encodeURIComponent(session.id)}/finish`, { method: 'POST' }));
    try { localStorage.removeItem(storageKey); } catch (_) { /* Private mode. */ }
    document.dispatchEvent(new Event('desktop-media-changed'));
    return result.media_id;
  };
})();
