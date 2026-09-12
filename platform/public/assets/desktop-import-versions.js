(() => {
  const escape = value => String(value ?? '').replace(/[&<>'"]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch]));
  document.addEventListener('content-selected', event => {
    const item = event.detail, details = event.target, t = window.desktopImportLabels;
    if (!item.import_versions?.length) return;
    const panel = document.createElement('details'); panel.className='media-inspector';
    panel.innerHTML=`<summary>${escape(t.import_versions)}</summary><p>${escape(t.import_versions_hint)}</p>${item.import_enriched ? `<p>${escape(t.import_enriched)}</p>` : ''}${item.import_versions.map(version => `<p><button type="button" class="desktop-button" data-import-version="${escape(version.url)}">${escape(version.title)}</button></p>`).join('')}<div data-import-version-body></div>`;
    details.append(panel);
    let generation=0;
    panel.addEventListener('click', async event => {
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
