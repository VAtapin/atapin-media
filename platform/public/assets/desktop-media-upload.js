(() => {
  const imageProfiles = {
    media_library: { maxWidth: 2560, maxHeight: 1600, maxBytes: 3 * 1024 * 1024 },
    poster: { maxWidth: 1920, maxHeight: 1080, maxBytes: 2 * 1024 * 1024 },
    cover: { maxWidth: 1920, maxHeight: 1080, maxBytes: 2 * 1024 * 1024 },
    avatar: { maxWidth: 800, maxHeight: 800, maxBytes: 512 * 1024 },
    wallpaper: { maxWidth: 2560, maxHeight: 1440, maxBytes: 3 * 1024 * 1024 },
  };
  const imageMimes = new Set(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
  const labels = () => window.desktopImportLabels || {};
  const imageError = key => labels()[key] || 'Bild konnte nicht geprüft werden.';
  const readImage = file => new Promise((resolve, reject) => {
    if (window.createImageBitmap) {
      window.createImageBitmap(file).then(resolve).catch(reject);
      return;
    }
    const image = new Image();
    const url = URL.createObjectURL(file);
    image.onload = () => { URL.revokeObjectURL(url); resolve(image); };
    image.onerror = () => { URL.revokeObjectURL(url); reject(new Error(imageError('upload_image_invalid'))); };
    image.src = url;
  });
  const canvasBlob = (canvas, type, quality) => new Promise(resolve => canvas.toBlob(resolve, type, quality));
  const optimizedImage = async (file, profileName) => {
    const profile = imageProfiles[profileName];
    if (!profile) return file;
    if (!imageMimes.has(file.type)) throw new Error(imageError('upload_image_type'));
    if (file.size > 50 * 1024 * 1024) throw new Error(imageError('upload_image_source_size'));
    const image = await readImage(file);
    let width = image.width || image.naturalWidth;
    let height = image.height || image.naturalHeight;
    if (!width || !height) throw new Error(imageError('upload_image_invalid'));
    const initialScale = Math.min(1, profile.maxWidth / width, profile.maxHeight / height);
    width = Math.max(1, Math.round(width * initialScale));
    height = Math.max(1, Math.round(height * initialScale));
    let blob = null;
    let outputType = 'image/webp';
    for (let attempt = 0; attempt < 10; attempt++) {
      const canvas = document.createElement('canvas');
      canvas.width = width; canvas.height = height;
      const context = canvas.getContext('2d', { alpha: true });
      if (!context) throw new Error(imageError('upload_image_invalid'));
      context.drawImage(image, 0, 0, width, height);
      blob = await canvasBlob(canvas, outputType, Math.max(.5, .86 - attempt * .05));
      if (!blob) throw new Error(imageError('upload_image_invalid'));
      if (blob.type !== outputType) {
        outputType = 'image/jpeg';
        blob = await canvasBlob(canvas, outputType, Math.max(.5, .86 - attempt * .05));
      }
      if (blob && blob.size <= profile.maxBytes) break;
      width = Math.max(320, Math.round(width * .85));
      height = Math.max(180, Math.round(height * .85));
    }
    if (!blob || blob.size > profile.maxBytes) throw new Error(imageError('upload_image_too_large'));
    if (image.close) image.close();
    const extension = outputType === 'image/webp' ? 'webp' : 'jpg';
    const baseName = file.name.replace(/\.[^.]+$/, '') || 'bild';
    const prepared = new File([blob], `${baseName}.${extension}`, { type: outputType, lastModified: file.lastModified });
    if (file.webkitRelativePath) {
      Object.defineProperty(prepared, 'webkitRelativePath', { value: file.webkitRelativePath });
    }
    return prepared;
  };
  const prepareFile = async (file, options = {}) => {
    const profile = options.profile || 'media_library';
    if (!file.type.startsWith('image/') || !imageProfiles[profile]) return file;
    return optimizedImage(file, profile);
  };
  const setInputFile = (input, file) => {
    const transfer = new DataTransfer();
    transfer.items.add(file);
    input.files = transfer.files;
  };

  window.prepareDesktopMediaFile = prepareFile;
  window.initializeDesktopImageInputs = () => {
    document.querySelectorAll('[data-image-upload-profile]').forEach(input => {
      if (input.hasAttribute('data-auto-media-upload')) return;
      if (input.dataset.imageUploadInitialized === 'true') return;
      input.dataset.imageUploadInitialized = 'true';
      input.addEventListener('change', async () => {
        const file = input.files?.[0];
        if (!file) return;
        const status = input.closest('label')?.querySelector('[data-image-upload-status]');
        input.disabled = true;
        if (status) status.textContent = imageError('upload_image_preparing');
        try {
          const optimized = await prepareFile(file, { profile: input.dataset.imageUploadProfile });
          setInputFile(input, optimized);
          if (status) status.textContent = `${imageError('upload_image_optimized')} ${Math.round(optimized.size / 1024)} KB.`;
        } catch (error) {
          input.value = '';
          if (status) status.textContent = error.message;
        } finally { input.disabled = false; }
      });
    });
  };

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
  window.uploadDesktopMedia = async (sourceFile, user, progress = () => {}, control = null, options = {}) => {
    if (!sourceFile.size || !crypto.subtle || !crypto.randomUUID) throw new Error('Upload nicht verfügbar.');
    const file = await prepareFile(sourceFile, options);
    const storageKey = `media-upload:${user}:${file.webkitRelativePath || file.name}:${file.size}:${file.lastModified}`;
    let key;
    try { key = localStorage.getItem(storageKey); } catch (_) { /* Private mode. */ }
    key ||= crypto.randomUUID();
    try { localStorage.setItem(storageKey, key); } catch (_) { /* Upload still works. */ }
    const session = await retry(() => request('/desktop/media/uploads', { method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ request_key: key, name: file.name, size: file.size, profile: options.profile || 'media_library' }), signal:control?.signal,
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
  const uploadLabel = (key, fallback) => labels()[key] || fallback;
  const userIdFor = input => input.closest('[data-user-id]')?.dataset.userId
    || document.querySelector('[data-user-id]')?.dataset.userId || 'current';
  window.enhanceDesktopFileInput = (input, options = {}) => {
    if (!input || input.dataset.unifiedUploadInitialized === 'true') return input?._desktopUploader || null;
    input.dataset.unifiedUploadInitialized = 'true';
    const multiple = options.multiple ?? input.multiple;
    const zone = document.createElement('div');
    zone.className = 'desktop-file-drop'; zone.tabIndex = 0; zone.setAttribute('role', 'button');
    zone.innerHTML = `<span class="desktop-file-drop-icon" aria-hidden="true">⇧</span><span><strong>${uploadLabel('uploader_drop', 'Datei hierher ziehen')}</strong><small>${uploadLabel('uploader_choose', 'oder Datei auswählen')}</small></span><span class="desktop-file-drop-state" role="status"></span><span class="desktop-file-drop-list"></span>`;
    input.classList.add('desktop-file-native'); input.hidden = true;
    input.insertAdjacentElement('afterend', zone);
    const state = zone.querySelector('.desktop-file-drop-state');
    const list = zone.querySelector('.desktop-file-drop-list');
    let generation = 0;
    const api = { element:zone, input, mediaIds:[], files:[], uploading:false, clear:() => {generation++;api.uploading=false;api.mediaIds=[];api.files=[];input.value='';list.replaceChildren();state.textContent='';zone.classList.remove('is-complete','is-error','is-uploading');} };
    input._desktopUploader = api;
    const render = (file, text) => {
      const row=document.createElement('span');row.className='desktop-file-drop-item';
      const name=document.createElement('span');name.textContent=file.name;
      const status=document.createElement('small');status.textContent=text;
      row.append(name,status);list.append(row);return status;
    };
    const select = async rawFiles => {
      const files = [...rawFiles].filter(Boolean).slice(0, multiple ? undefined : 1);
      if (!files.length) return;
      const own=++generation;api.uploading=true;api.mediaIds=[];api.files=files;list.replaceChildren();zone.classList.remove('is-complete','is-error');zone.classList.add('is-uploading');
      const submitters=[...(input.form?.querySelectorAll('button[type="submit"],input[type="submit"]')||[])];const prior=submitters.map(button=>button.disabled);submitters.forEach(button=>{button.disabled=true;});
      state.textContent=uploadLabel('uploader_uploading','Wird hochgeladen …');
      try {
        for (const file of files) {
          const row=render(file,uploadLabel('upload_waiting','Warteschlange'));
          const id=await window.uploadDesktopMedia(file,options.userId||userIdFor(input),(done,total)=>{row.textContent=`${total?Math.floor(done/total*100):0} %`;},null,{profile:options.profile||input.dataset.imageUploadProfile||'media_library'});
          if(own!==generation)return;api.mediaIds.push(id);row.textContent=uploadLabel('uploader_uploaded','Hochgeladen');
          await options.onUploaded?.(id,file,api);
        }
        if(own!==generation)return;input.value='';zone.classList.remove('is-uploading');zone.classList.add('is-complete');
        state.textContent=files.length>1?`${files.length} ${uploadLabel('uploader_files_uploaded','Dateien hochgeladen')}`:uploadLabel('uploader_ready','Bereit zum Speichern');
        zone.dispatchEvent(new CustomEvent('desktop-upload-complete',{bubbles:true,detail:{mediaIds:[...api.mediaIds],files}}));
      } catch(error) {
        if(own!==generation)return;zone.classList.remove('is-uploading');zone.classList.add('is-error');state.textContent=error.message||uploadLabel('uploader_failed','Upload fehlgeschlagen.');
        options.onError?.(error,api);
      } finally {if(own===generation){api.uploading=false;submitters.forEach((button,index)=>{button.disabled=prior[index];});}}
    };
    api.select=select;
    zone.addEventListener('click',event=>{if(!event.target.closest('a,button')){event.preventDefault();event.stopPropagation();input.click();}});
    zone.addEventListener('keydown',event=>{if(event.key==='Enter'||event.key===' '){event.preventDefault();input.click();}});
    for(const name of ['dragenter','dragover'])zone.addEventListener(name,event=>{event.preventDefault();zone.classList.add('is-dragging');});
    for(const name of ['dragleave','drop'])zone.addEventListener(name,event=>{event.preventDefault();zone.classList.remove('is-dragging');});
    zone.addEventListener('drop',event=>select(event.dataTransfer?.files||[]));
    input.addEventListener('change',()=>select(input.files||[]));
    return api;
  };
  window.initializeDesktopFileInputs = root => {
    const scope=root||document,nodes=[...(scope.matches?.('input[type="file"][data-auto-media-upload]')?[scope]:[]),...scope.querySelectorAll('input[type="file"][data-auto-media-upload]')];nodes.forEach(input => {
      const target=input.dataset.uploadTarget;
      window.enhanceDesktopFileInput(input,{profile:input.dataset.uploadProfile||input.dataset.imageUploadProfile,multiple:input.multiple,onUploaded:id=>{
        if(!target)return;let hidden=input.form?.querySelector(`input[name="${CSS.escape(target)}"]`);
        if(!hidden){hidden=document.createElement('input');hidden.type='hidden';hidden.name=target;input.form?.append(hidden);}hidden.value=id;
      }});
    });
  };
  window.initializeDesktopImageInputs();
  window.initializeDesktopFileInputs();
  document.addEventListener?.('desktop-file-inputs-added',event=>window.initializeDesktopFileInputs(event.detail?.root));
  if(window.MutationObserver&&document.documentElement)new MutationObserver(changes=>{for(const change of changes)for(const node of change.addedNodes)if(node.nodeType===1)window.initializeDesktopFileInputs(node);}).observe(document.documentElement,{childList:true,subtree:true});
})();
