(() => {
  const W = window.DesktopWorkspaces;
  if (!W) return;
  const {t, el, button, request, run, field, lookup, formData, crud} = W;
  const bookJobsKey = 'atapin.desktop.book-ai-jobs.v1';
  const readBookJobs = () => { try { return JSON.parse(localStorage.getItem(bookJobsKey) || '{}') || {}; } catch (_) { return {}; } };
  const writeBookJobs = jobs => { try { localStorage.setItem(bookJobsKey, JSON.stringify(jobs)); } catch (_) {} };
  let bookMonitorTimer;
  const startBookMonitor = () => {
    clearTimeout(bookMonitorTimer);
    const jobs = readBookJobs(); const ids = Object.keys(jobs);
    if (!ids.length) return;
    bookMonitorTimer = setTimeout(async () => {
      for (const id of ids) {
        try {
          const latest = (await request('/desktop/books/' + id)).product;
          const state = latest.metadata?.book_pdf_ai?.status;
          if (['queued','processing'].includes(state)) continue;
          const title = latest.title || jobs[id].title || '';
          delete jobs[id]; writeBookJobs(jobs);
          const failed = state === 'failed'; const partial = state === 'partial';
          const key = failed ? 'book_ai_failed_message' : partial ? 'book_ai_partial_message' : 'book_ai_ready_message';
          window.desktopNotify?.(t(failed ? 'book_ai_failed_title' : partial ? 'book_ai_partial_title' : 'book_ai_ready_title'), `${title ? title + ' · ' : ''}${t(key)}`, failed ? 'error' : partial ? 'warning' : 'success', () => W.open('books-pdf', {id:Number(id)}));
          document.dispatchEvent(new Event('desktop-media-changed'));
        } catch (_) { /* Keep the job for the next browser tick. */ }
      }
      startBookMonitor();
    }, 5000);
  };
  const trackBook = (id, title = '') => { const jobs = readBookJobs(); jobs[id] = {title}; writeBookJobs(jobs); startBookMonitor(); };
  startBookMonitor();
  const tags = data => ({...data, tags:(data.tags || '').split(',').map(value => value.trim()).filter(Boolean)});
  const bookData = data => { const result = tags(data); const amount = Number(String(result.price ?? '').replace(',', '.')); result.price_cents = Number.isFinite(amount) ? Math.max(0, Math.round(amount * 100)) : 0; delete result.price; return result; };
  const linked = (editor, title, items, app) => {
    const section = el('section', undefined, 'workspace-asset-panel');
    section.append(el('h3', title));
    for (const item of items || []) {
      const link = el('button', item.title || item.name || ('#' + item.id), 'desktop-button');
      link.type = 'button'; link.addEventListener('click', () => W.open(app, item)); section.append(link);
    }
    editor.append(section);
  };
  const imageUpload = (editor, row, endpoint, load) => {
    if (!row.id) return;
    const section = el('section', undefined, 'workspace-asset-panel');
    section.append(el('h3', t('image')),
      el('p', row.cover_url ? t('image_replace_hint') : t('image_upload_hint'), 'workspace-muted'));
    const form = el('form', undefined, 'workspace-asset-form');
    const file = field('file', 'file'); file.querySelector('input').accept = 'image/jpeg,image/png,image/webp,image/gif';
    const submit = el('button', t('upload_image'), 'desktop-button'); submit.type = 'submit'; form.append(file, submit);
    form.addEventListener('submit', event => { event.preventDefault(); run(editor.closest('[data-workspace]'), async () => { submit.disabled = true; try { await request(endpoint, new FormData(form), 'POST'); const mainForm = editor.querySelector('form'); if (mainForm) delete mainForm.dataset.dirty; const root = editor.closest('[data-workspace]'); if (root._workspaceEdit) await root._workspaceEdit({id:row.id}); else await load(); } finally { submit.disabled = false; } }); });
    section.append(form); editor.append(section);
  };
  const projectExtra = (editor, row, load) => {
    if (row.id) {
      const stats = el('div', undefined, 'workspace-editor-callout');
      stats.append(el('strong', `${t('open_tasks_count')}: ${row.open_tasks_count || 0}`), el('span', `${t('records_count')}: ${row.records_count || 0} · ${t('products_count')}: ${row.products_count || 0}`));
      editor.append(stats);
      editor.append(button('new_task', () => W.openQuick('tasks', {project_id:row.id})));
      linked(editor, t('tasks_count'), row.tasks?.data, 'tasks');
      linked(editor, t('records_count'), row.records?.data, 'videos');
      linked(editor, t('products_count'), row.products?.data, 'books-pdf');
      window.appendProjectTimeline?.(editor, row.id);
    }
    imageUpload(editor, row, row.id ? `/desktop/projects/${row.id}/cover` : '', load);
  };
  const topicExtra = (editor, row, load) => imageUpload(editor, row, row.id ? `/desktop/taxonomy/${row.id}/cover` : '', load);
  const bookExtra = async (editor, row, load, host) => {
    const state = row.metadata?.book_pdf_ai?.status;
    if (row.id && ['queued','processing'].includes(state)) {
      trackBook(row.id, row.title || '');
      const poll = async () => {
        if (!editor.isConnected) return;
        try {
          const latest = (await request('/desktop/books/' + row.id)).product;
          const next = latest.metadata?.book_pdf_ai?.status;
          if (next && next !== state) await host._workspaceEdit({id:row.id});
          else setTimeout(poll, 3000);
        } catch (_) { setTimeout(poll, 5000); }
      };
      setTimeout(poll, 2500);
    }
    const panel = el('section', undefined, 'workspace-asset-panel');
    panel.append(el('h3', t('book_files')));
    if (state) {
      panel.append(el('p', `${t('book_ai_status')}: ${t('states.' + state) || state}`, 'workspace-muted'));
      const hintKey = {queued:'book_ai_queued', processing:'book_ai_processing', completed:'book_ai_completed', partial:'book_ai_partial', failed:'book_ai_failed'}[state] || 'book_ai_status_hint';
      panel.append(el('p', t(hintKey), 'workspace-muted'));
      if (row.metadata?.book_pdf_ai?.source_text_chars) panel.append(el('p', `${t('book_ai_source_chars')}: ${row.metadata.book_pdf_ai.source_text_chars}`, 'workspace-muted'));
      if (['failed','partial'].includes(state) && row.id) {
        const retry = button('book_analyze_retry', () => run(host, async () => {
          retry.disabled = true;
          try { await request(`/desktop/books/${row.id}/analyze`, undefined, 'POST'); if (host._workspaceEdit) await host._workspaceEdit({id:row.id}); else await load(); }
          finally { retry.disabled = false; }
        }));
        panel.append(retry);
      }
    }
    if (row.metadata?.book_pdf_ai?.error) panel.append(el('p', `${t('book_ai_error')}: ${row.metadata.book_pdf_ai.error}`, 'workspace-feedback is-error'));
    editor.prepend(panel);
    if (!row.id) return;
    const assets = row.assets || [];
    for (const asset of assets) {
      const line = el('p', undefined, 'workspace-file-line');
      line.append(el('strong', asset.roles?.includes('public_download') || asset.roles?.includes('paid_download') ? t('full') : asset.roles?.join(', ') || t('file')),
        el('span', ` ${asset.title} · ${asset.mime}`));
      if (asset.preview_url) { const link = el('a', t('open'), 'workspace-link'); link.href = asset.preview_url; link.target = '_blank'; link.rel = 'noreferrer'; line.append(link); }
      panel.append(line);
    }
    const form = el('form', undefined, 'workspace-asset-form');
    const slot = field('slot', 'select', 'full', [['full', t('full')], ['cover', t('cover')], ['sample', t('sample')]]);
    const file = field('file', 'file'); file.querySelector('input').accept = '.pdf,image/jpeg,image/png,image/webp,image/gif';
    const submit = el('button', t('upload'), 'desktop-button'); submit.type = 'submit'; form.append(slot, file, submit); panel.append(form);
    form.addEventListener('submit', event => { event.preventDefault(); run(host, async () => { submit.disabled = true; try { const data = new FormData(form); if (!file.querySelector('input').files.length) throw new Error(t('book_pdf_required')); await request(`/desktop/books/${row.id}/assets`, data, 'POST'); if (host._workspaceEdit) await host._workspaceEdit({id:row.id}); else await load(); } finally { submit.disabled = false; } }); });
  };
  const projectConfig = {
    url:'/desktop/projects', newLabel:'new_project', inlineEdit:true, quickCreate:{fields:[['title'],['type','select',[['','—'],'mixed','video','post','book','podcast','live']]]}, detail:async row => { const data = await request('/desktop/projects/' + row.id); return {...data.project, tasks:data.tasks, records:data.records, products:data.products}; }, updateMethod:'PUT', statuses:['idea','script','production','review','published'],
    filters:[['type','select','',[['',t('all')],'mixed','video','post','book','podcast','live']],['user_id','select'],['due_before','date']], filterLookups:{user_id:'users'},
    fields:[['title'],['description','textarea'],['type','select',['mixed','video','post','book','podcast','live'],'mixed'],['status','select',['idea','script','production','review','published'],'idea'],['user_id','select'],['team_ids','select'],['start_date','date'],['due_date','date'],['next_action'],['tags']],
    lookups:{user_id:'users',team_ids:'users'}, multiple:['team_ids'], transform:tags, extra:projectExtra,
  };
  const topicConfig = {
    url:'/desktop/taxonomy', filters:[['kind','select','',[['',t('all')],'topic','category']]], fields:[['name'],['kind','select',['topic','category'],'topic'],['description','textarea'],['slug'],['parent_id','select'],['active','checkbox',[],true]],
    lookups:{parent_id:'terms'}, extra:topicExtra,
  };
  const bookConfig = {
    url:'/desktop/books', newLabel:'new_book', statuses:['draft','active','archived'],
    fields:[['title'],['subtitle'],['description','textarea'],['author'],['contents','textarea'],['edition_text','textarea'],['isbn'],['language','select',['de','en'],'de'],['page_count','number'],['publication_date','date'],['tags'],['seo_title'],['seo_description','textarea'],['price','number',[],0],['currency','select',['EUR','USD','CHF','GBP'],'EUR'],['status','select',['draft','active','archived'],'draft'],['project_id','select'],['taxonomy_term_ids','select'],['external_shop_url','url']],
    lookups:{project_id:'projects',taxonomy_term_ids:'terms'}, multiple:['taxonomy_term_ids'], transform:bookData,
    detail:async row => { const data = await request('/desktop/books/' + row.id); return {...data.product, price:data.product.price_cents === null || data.product.price_cents === undefined ? '' : (Number(data.product.price_cents) / 100).toFixed(2), assets:data.assets}; }, extra:bookExtra,
    intake:async (editor, row, root, load, edit) => {
      const section = el('section', undefined, 'workspace-pdf-intake'); section.append(el('h2', t('new_book')),
        el('p', t('book_upload_ai_hint'), 'workspace-muted'));
      const form = el('form', undefined, 'workspace-pdf-drop'); const file = field('file', 'file'); file.querySelector('input').accept = 'application/pdf'; file.querySelector('input').required = true;
      const submit = el('button', t('book_analyze'), 'desktop-button is-primary'); submit.type = 'submit'; form.append(file, submit); section.append(form); editor.append(section);
      form.addEventListener('submit', event => { event.preventDefault(); run(root, async () => { submit.disabled = true; try { const data = await request('/desktop/books/intake', new FormData(form), 'POST'); trackBook(data.id, file.querySelector('input').files[0]?.name || ''); window.desktopNotify?.(t('book_ai_background_title'), t('book_ai_background_message'), 'info'); document.dispatchEvent(new Event('desktop-media-changed')); editor.closest('.os-window')?.querySelector('[data-window-action="close"]')?.click(); } finally { submit.disabled = false; } }); });
      return true;
    },
  };
  W.register('projects', root => crud(root, projectConfig));
  W.register('topics', root => crud(root, topicConfig));
  W.register('books-pdf', root => crud(root, bookConfig));
})();
