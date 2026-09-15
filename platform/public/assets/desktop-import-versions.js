(() => {
  const escape = value => String(value ?? '').replace(/[&<>'"]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch]));
  document.addEventListener('content-selected', event => {
    const item = event.detail, details = event.target, t = window.desktopImportLabels;
    if (!item.import_versions?.length) return;
    const panel = document.createElement('details'); panel.className='media-inspector';
    const versionDate = value => {
      const date = new Date(value);
      return Number.isNaN(date.getTime()) ? '' : date.toLocaleString(document.documentElement.lang || 'de');
    };
    panel.innerHTML=`<summary>${escape(t.import_versions)}</summary><p>${escape(t.import_versions_hint)}</p>${item.import_enriched ? `<p>${escape(t.import_enriched)}</p>` : ''}<div class="content-import-version-list">${item.import_versions.map(version => `<div class="content-import-version-row"><button type="button" class="desktop-button" data-import-version="${escape(version.url)}">${escape(version.title)}</button><small>${escape(versionDate(version.created_at))}</small>${version.delete_url ? `<button type="button" class="content-import-version-delete" data-import-version-delete="${escape(version.delete_url)}" aria-label="${escape(t.import_version_delete)}" title="${escape(t.import_version_delete)}">×</button>` : ''}</div>`).join('')}</div><p data-import-version-status role="status" aria-live="polite"></p><div data-import-version-body></div>`;
    details.append(panel);
    let generation=0;
    panel.addEventListener('click', async event => {
      const remove=event.target.closest('[data-import-version-delete]');
      if (remove) {
        const status=panel.querySelector('[data-import-version-status]');
        if (details.dataset.dirty === 'true') {status.textContent=t.import_version_save_first;return;}
        if (!window.confirm(t.import_version_delete_confirm)) return;
        remove.disabled=true;status.textContent='';
        try {
          const response=await fetch(remove.dataset.importVersionDelete,{method:'DELETE',credentials:'same-origin',
            headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
            body:JSON.stringify({confirmation:'DELETE'})});
          const result=await response.json().catch(()=>({}));
          if (!response.ok) throw new Error(result.message||t.load_error);
          generation++;
          remove.closest('.content-import-version-row').remove();
          panel.querySelector('[data-import-version-body]').replaceChildren();
          status.textContent=t.import_version_deleted;
        } catch(error) {status.textContent=error.message;remove.disabled=false;}
        return;
      }
      const button=event.target.closest('[data-import-version]'); if (!button) return;
      const current=++generation, body=panel.querySelector('[data-import-version-body]');
      try {
        const response=await fetch(button.dataset.importVersion,{credentials:'same-origin',headers:{Accept:'application/json'}});
        if (!response.ok) throw new Error(t.load_error);
        const version=await response.json(); if (current !== generation || !panel.isConnected) return;
        body.innerHTML=`<h4>${escape(version.title)}</h4><p class="content-original-text">${escape(version.body)}</p><details><summary>${escape(t.import_raw_data)}</summary><pre class="content-original-text">${escape(JSON.stringify(version.metadata,null,2))}</pre></details>`;
      } catch(error) {body.textContent=error.message;}
    });
  });
})();
