(() => {
  const escape = value => String(value ?? '').replace(/[&<>'"]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch]));
  window.appendMediaCover = (details, item) => {
    const root = details.closest('[data-media-library]');
    if (!root || root.dataset.canUndo !== 'true' || item.kind !== 'image') return;
    const t = window.desktopImportLabels;
    const panel = document.createElement('details'); panel.className = 'media-inspector';
    panel.innerHTML = `<summary>${escape(t.assign_cover)}</summary><p>${escape(t.cover_hint)}</p><form class="content-assignment"><label>${escape(t.cover_search)}<input name="q" maxlength="120"></label><button type="submit" class="desktop-button">${escape(t.cover_find)}</button></form><div data-cover-results></div><p role="status" aria-live="polite"></p>`;
    details.append(panel);
    const results = panel.querySelector('[data-cover-results]'), message = panel.querySelector('[role=status]');
    let generation = 0;
    const search = async (page = 1) => {
      const current = ++generation;
      try {
        const params = new URLSearchParams({section:'videos',q:panel.querySelector('[name=q]').value,page});
        const response = await fetch('/desktop/content?' + params, {credentials:'same-origin',headers:{Accept:'application/json'}});
        if (!response.ok) throw new Error(t.load_error);
        const data = await response.json();
        if (current !== generation || !panel.isConnected) return;
        results.innerHTML = data.data.map(record => `<p><button type="button" class="desktop-button" data-cover-record="${escape(record.id)}">${escape(record.title)} · ${escape(t['source_'+record.source] || record.source)}</button></p>`).join('') + `<div class="media-library-toolbar-row"><button type="button" class="desktop-button" data-cover-page="${page-1}" ${page<=1?'disabled':''}>‹</button><span>${page} / ${data.meta.last_page}</span><button type="button" class="desktop-button" data-cover-page="${page+1}" ${page>=data.meta.last_page?'disabled':''}>›</button></div>`;
        message.textContent = data.data.length ? '' : t.empty;
      } catch (error) {message.textContent = error.message;}
    };
    panel.querySelector('form').addEventListener('submit', event => {event.preventDefault(); search();});
    panel.addEventListener('toggle', () => {if (panel.open && !panel.dataset.loaded) {panel.dataset.loaded='true'; search();}});
    results.addEventListener('click', async event => {
      const page = event.target.closest('[data-cover-page]'); if (page) {search(Number(page.dataset.coverPage)); return;}
      const button = event.target.closest('[data-cover-record]'); if (!button) return;
      if (details.dataset.dirty === 'true') {message.textContent = t.save_before_ai; return;}
      if (!window.confirm(t.cover_confirm + '\n' + button.textContent)) return;
      button.disabled = true;
      try {
        const response = await fetch('/desktop/media/'+item.id+'/cover', {method:'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({record_id:Number(button.dataset.coverRecord)})});
        const result = await response.json(); if (!response.ok) throw new Error(result.message || t.load_error);
        message.textContent = t.saved; document.dispatchEvent(new Event('desktop-media-changed'));
      } catch (error) {message.textContent = error.message; button.disabled = false;}
    });
  };
})();
