(() => {
  const escape = value => String(value ?? '').replace(/[&<>'"]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch]));
  const request = async (url, data = null, method = 'GET') => {
    const response = await fetch(url, {method, credentials:'same-origin', headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content || ''}, ...(data ? {body:JSON.stringify(data)} : {})});
    const result = await response.json();
    if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message);
    return result;
  };
  window.initializeMediaOrganization = (root, files) => {
    const t = window.desktopImportLabels;
    const list = root.querySelector('[data-library-list]');
    const bulk = root.querySelector('[data-media-bulk]');
    const manager = root.querySelector('[data-media-collections]');
    const selected = new Set();
    let selecting = false, collectionPage = 1, memberPage = 1, activeCollection = null;
    const message = (value, error = false) => {const node = root.querySelector('[data-media-upload-message]'); node.textContent = value; node.hidden = !value; node.classList.toggle('is-error', error);};
    const refreshSelection = () => {
      if (!bulk) return;
      bulk.hidden = !selecting;
      bulk.querySelector('[data-media-selected-count]').textContent = selected.size + ' ' + t.selected_files;
      list.querySelectorAll('[data-media-check]').forEach(input => {input.checked = selected.has(input.dataset.mediaCheck);});
    };
    const decorate = () => {
      list.classList.toggle('is-selecting', selecting);
      list.querySelectorAll('[data-media-check]').forEach(input => input.remove());
      if (selecting) for (const row of list.children) {
        const button = row.querySelector('[data-media-id]'); if (!button) continue;
        const input = document.createElement('input'); input.type = 'checkbox'; input.dataset.mediaCheck = button.dataset.mediaId;
        input.setAttribute('aria-label', button.querySelector('strong').textContent); row.prepend(input);
      }
      refreshSelection();
    };
    root.querySelector('[data-library-select]')?.addEventListener('click', event => {selecting = !selecting; if (!selecting) selected.clear(); event.currentTarget.setAttribute('aria-pressed', String(selecting)); decorate();});
    list.addEventListener('change', event => {
      const input = event.target.closest('[data-media-check]'); if (!input) return;
      if (input.checked && selected.size >= 100) {input.checked = false; message(t.selection_limit, true); return;}
      if (input.checked) selected.add(input.dataset.mediaCheck); else selected.delete(input.dataset.mediaCheck);
      refreshSelection();
    });
    bulk?.querySelector('[data-media-clear-selection]').addEventListener('click', () => {selected.clear(); refreshSelection();});
    bulk?.querySelector('[data-media-select-page]').addEventListener('click', () => {for (const item of files()) {if (selected.size >= 100) break; selected.add(item.id);} refreshSelection();});
    bulk?.addEventListener('submit', async event => {
      event.preventDefault();
      const button = bulk.querySelector('[type="submit"]'); if (button.disabled) return;
      const values = Object.fromEntries(new FormData(bulk));
      const payload = {ids:[...selected]};
      for (const key of ['status','target_profile','collection_id']) if (values[key]) payload[key] = values[key];
      const tags = [...new Set(values.add_tags.split(',').map(tag => tag.trim()).filter(Boolean))];
      if (tags.length) payload.add_tags = tags;
      if (values.archive_action) payload.archived = values.archive_action === 'archive';
      try {
        if (!selected.size) throw new Error(t.no_selection);
        if (Object.keys(payload).length === 1) throw new Error(t.no_changes);
        button.disabled = true;
        const result = await request('/desktop/media/organize', payload, 'PATCH');
        selected.clear(); bulk.reset(); refreshSelection(); message(t.saved + ': ' + result.count);
        document.dispatchEvent(new Event('desktop-media-changed'));
      } catch (error) {message(error.message, true);}
      finally {button.disabled = false;}
    });
    const pages = (node, page, last, attr) => {node.innerHTML = '<button type="button" ' + attr + '="' + (page - 1) + '" ' + (page <= 1 ? 'disabled' : '') + '>‹</button><span>' + page + ' / ' + last + '</span><button type="button" ' + attr + '="' + (page + 1) + '" ' + (page >= last ? 'disabled' : '') + '>›</button>';};
    const loadCollections = async (page = 1) => {
      const q = manager.querySelector('[data-collection-search] [name=q]').value;
      const result = await request('/desktop/media/collections?' + new URLSearchParams({page,q}));
      if (!root.isConnected) return;
      collectionPage = result.meta.current_page;
      manager.querySelector('[data-collection-list]').innerHTML = result.data.length ? result.data.map(item => '<li><button type="button" class="desktop-button" data-collection-id="' + item.id + '"><span>' + escape(item.title) + '</span><small>' + item.media_count + '</small></button></li>').join('') : '<li>' + escape(t.no_collections) + '</li>';
      pages(manager.querySelector('[data-collection-pages]'), collectionPage, result.meta.last_page, 'data-collection-page');
      for (const select of root.querySelectorAll('[data-media-collection-options]')) {
        const current = select.value, previous = select.selectedOptions[0]?.cloneNode(true), blank = select.options[0].cloneNode(true);
        select.replaceChildren(blank);
        for (const item of result.data) select.append(new Option(item.title, item.id));
        if (current && !result.data.some(item => String(item.id) === current) && previous) select.append(previous);
        select.value = current;
      }
    };
    const loadCollection = async (id, page = 1) => {
      const item = await request('/desktop/media/collections/' + id + '?page=' + page);
      activeCollection = item.id; memberPage = item.meta.current_page;
      const details = manager.querySelector('[data-collection-details]');
      details.innerHTML = (root.dataset.canEdit === 'true' ? '<form class="media-collection-form" data-collection-edit><label>' + escape(t.title) + '<input name="title" required maxlength="255" value="' + escape(item.title) + '"></label><label>' + escape(t.description) + '<textarea name="description" maxlength="10000" rows="2">' + escape(item.description) + '</textarea></label><button type="submit" class="desktop-button">' + escape(t.save) + '</button></form>' : '<h3>' + escape(item.title) + '</h3><p>' + escape(item.description) + '</p>') + '<ol class="media-collection-members">' + item.data.map(file => '<li value="' + file.position + '"><strong>' + escape(file.title) + '</strong>' + (file.archived ? '<small>' + escape(t.archived_files) + '</small>' : '') + '<a class="desktop-button" href="' + escape(file.download_url) + '">' + escape(t.download_original) + '</a>' + (root.dataset.canEdit === 'true' ? ['up','down','remove'].map(action => '<button type="button" class="desktop-button" data-collection-member="' + file.id + '" data-member-action="' + action + '">' + escape(t['member_' + action]) + '</button>').join('') : '') + '</li>').join('') + '</ol><nav class="media-library-pagination" data-collection-member-pages></nav>';
      pages(details.querySelector('[data-collection-member-pages]'), memberPage, item.meta.last_page, 'data-member-page');
      details.querySelector('[data-collection-edit]')?.addEventListener('submit', async event => {
        event.preventDefault(); const button = event.target.querySelector('[type=submit]'); button.disabled = true;
        try {await request('/desktop/media/collections/' + activeCollection, Object.fromEntries(new FormData(event.target)), 'PATCH'); await loadCollections(collectionPage); message(t.saved);}
        catch (error) {message(error.message, true);} finally {button.disabled = false;}
      });
    };
    manager.addEventListener('click', async event => {
      const collection = event.target.closest('[data-collection-id]'), page = event.target.closest('[data-collection-page]'), member = event.target.closest('[data-collection-member]'), membersPage = event.target.closest('[data-member-page]');
      try {
        if (collection) await loadCollection(collection.dataset.collectionId);
        if (page) await loadCollections(Number(page.dataset.collectionPage));
        if (membersPage) await loadCollection(activeCollection, Number(membersPage.dataset.memberPage));
        if (member) {member.disabled = true; await request('/desktop/media/collections/' + activeCollection + '/members/' + member.dataset.collectionMember, {action:member.dataset.memberAction}, 'PATCH'); await loadCollection(activeCollection, memberPage); await loadCollections(collectionPage); document.dispatchEvent(new Event('desktop-media-changed'));}
      } catch (error) {message(error.message, true); if (member) member.disabled = false;}
    });
    manager.querySelector('[data-collection-search]').addEventListener('submit', event => {event.preventDefault(); loadCollections().catch(error => message(error.message, true));});
    manager.querySelector('[data-collection-create]')?.addEventListener('submit', async event => {
      event.preventDefault(); const button = event.target.querySelector('[type=submit]'); if (button.disabled) return; button.disabled = true;
      try {const item = await request('/desktop/media/collections', Object.fromEntries(new FormData(event.target)), 'POST'); event.target.reset(); await loadCollections(); await loadCollection(item.id); message(t.saved);}
      catch (error) {message(error.message, true);} finally {button.disabled = false;}
    });
    root.querySelector('[data-library-collections]').addEventListener('click', event => {
      const show = manager.hidden; manager.hidden = !show; selecting = false; selected.clear(); decorate();
      root.querySelector('[data-library-content-container]').hidden = true;
      for (const selector of ['[data-library-filter]','[data-library-summary]','.media-library-layout','[data-library-pagination]','[data-library-content-toggle]','[data-library-grid]','[data-library-select]','[data-classify-batch]','[data-library-import-existing]']) for (const node of root.querySelectorAll(selector)) node.hidden = show;
      root.querySelector('[data-library-content-toggle]').textContent = t.content;
      event.currentTarget.setAttribute('aria-pressed', String(show));
      if (show) loadCollections().catch(error => message(error.message, true));
    });
    loadCollections().catch(error => message(error.message, true));
    const reset = () => {selecting = false; selected.clear(); manager.hidden = true; decorate(); root.querySelector('[data-library-select]')?.setAttribute('aria-pressed','false'); root.querySelector('[data-library-collections]').setAttribute('aria-pressed','false');};
    return {decorate, reset};
  };
  window.appendMediaInspector = (details, item) => {
    if (!item.detail_url) return;
    const t = window.desktopImportLabels;
    const inspector = document.createElement('details'); inspector.className = 'media-inspector'; inspector.innerHTML = '<summary>' + escape(t.more_details) + '</summary><div data-media-inspector></div>';
    details.append(inspector);
    inspector.addEventListener('click', async event => {
      const button = event.target.closest('[data-media-undo]'); if (!button) return;
      if (details.dataset.dirty === 'true') {inspector.querySelector('[data-media-inspector]').insertAdjacentHTML('beforeend', '<p>' + escape(t.save_before_ai) + '</p>'); return;}
      button.disabled = true;
      try {await request(button.dataset.mediaUndo, {}, 'POST'); document.dispatchEvent(new Event('desktop-media-changed'));}
      catch (error) {button.disabled = false; inspector.querySelector('[data-media-inspector]').insertAdjacentHTML('beforeend','<p>' + escape(error.message) + '</p>');}
    });
    inspector.addEventListener('toggle', async () => {
      if (!inspector.open || inspector.dataset.loaded) return;
      inspector.dataset.loaded = 'true';
      const node = inspector.querySelector('[data-media-inspector]');
      try {
        const data = await request(item.detail_url);
        node.innerHTML = (data.summary ? '<p class="content-original-text">' + escape(data.summary) + '</p>' : '') + '<h4>' + escape(t.storage_location) + '</h4><p>' + escape(data.storage.disk) + ' / ' + escape(data.storage.path) + '</p>' + (data.storage.sha256 ? '<small>SHA-256: ' + escape(data.storage.sha256) + '</small>' : '') + (data.external_url ? '<p><a class="desktop-button" href="' + escape(data.external_url) + '" target="_blank" rel="noopener noreferrer">' + escape(t.open_external) + ' ↗</a></p>' : '') + '<h4>' + escape(t.related_files) + '</h4>' + [...(data.parent ? [data.parent] : []), ...data.assets].map(asset => '<p><a class="desktop-button" href="' + escape(asset.download_url) + '">' + escape(asset.title) + '</a></p>').join('') + '<h4>' + escape(t.collections) + '</h4><p>' + escape(data.collections.map(collection => collection.title).join(', ') || '—') + '</p><h4>' + escape(t.used_in) + '</h4><p>' + escape(data.usages.map(usage => usage.title).join(', ') || '—') + '</p><h4>' + escape(t.ai_history) + '</h4>' + data.classifications.map(log => '<p>' + escape(log.provider) + ' · ' + escape(log.model) + ' · ' + escape(t['ai_log_' + log.status] || log.status) + (log.confidence !== null ? ' · ' + Math.round(log.confidence * 100) + ' %' : '') + '</p>' + (log.proposal ? '<p>' + escape(log.proposal.title) + '<br>' + escape(log.proposal.summary) + '<br>' + escape((log.proposal.tags || []).join(', ')) + '</p>' : '') + (details.closest('[data-media-library]')?.dataset.canUndo === 'true' && log.undo_url ? '<button type="button" class="desktop-button" data-media-undo="' + escape(log.undo_url) + '">' + escape(t.undo_ai) + '</button>' : '')).join('');
      } catch (error) {node.textContent = error.message; delete inspector.dataset.loaded;}
    });
  };
})();
