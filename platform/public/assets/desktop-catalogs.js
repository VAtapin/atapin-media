(() => {
  const W = window.DesktopWorkspaces;
  if (!W) return;
  const {t, el, button, request, run, field, lookup, formData, crud} = W;
  const bookJobsKey = 'atapin.desktop.book-ai-jobs.v1';
  let editorFormSequence = 0;
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
      link.type = 'button'; link.addEventListener('click', () => {
        if (app === 'content') {
          const target = item.public_section === 'podcast' ? 'podcast' : item.kind === 'post' ? 'posts' : 'videos';
          const id = item.record_id || item.id;
          W.open(target);
          const openRecord = () => document.querySelector(`[data-content-library][data-section="${CSS.escape(target)}"]`)?.dispatchEvent(new CustomEvent('local-content-open',{detail:{url:'/desktop/content/'+id}}));
          if (!openRecord()) requestAnimationFrame(openRecord);
        } else W.open(app, item);
      }); section.append(link);
    }
    editor.append(section);
  };
  const imageUpload = (editor, row) => {
    if (!row.id) return;
    const section = el('section', undefined, 'workspace-asset-panel');
    section.append(el('h3', t('image')),
      el('p', row.cover_url ? t('image_replace_hint') : t('image_upload_hint'), 'workspace-muted'));
    const upload = el('div', undefined, 'workspace-asset-form');
    const file = field('file', 'file'),input=file.querySelector('input'); input.accept = 'image/jpeg,image/png,image/webp,image/gif'; upload.append(file);
    const mainForm = editor.querySelector(':scope > form') || editor.querySelector('form');
    const save = mainForm?.querySelector('button[type="submit"]');
    if (mainForm && save) { mainForm.id ||= `workspace-editor-form-${++editorFormSequence}`; save.setAttribute('form', mainForm.id); }
    window.enhanceDesktopFileInput(input,{profile:'cover',submitters:save?[save]:[],onUploaded:id=>{
      if (!mainForm) return;
      let mediaId = mainForm.querySelector('input[name="cover_media_id"]');
      if (!mediaId) { mediaId = el('input'); mediaId.type = 'hidden'; mediaId.name = 'cover_media_id'; mainForm.append(mediaId); }
      mediaId.value = id; mainForm.dataset.dirty = 'true'; mediaId.dispatchEvent(new Event('change', {bubbles:true}));
    }});
    section.append(upload); if (save) section.append(save); editor.append(section);
  };
  const projectExtra = (editor, row, load) => {
    if (row.id) {
      const stats = el('div', undefined, 'workspace-editor-callout');
      stats.append(el('strong', `${t('open_tasks_count')}: ${row.open_tasks_count || 0}`), el('span', `${t('records_count')}: ${row.records_count || 0} · ${t('products_count')}: ${row.products_count || 0}`));
      editor.append(stats);
      editor.append(button('new_task', () => W.openQuick('tasks', {project_id:row.id})));
      linked(editor, t('tasks_count'), row.tasks?.data, 'tasks');
      linked(editor, t('records_count'), row.records?.data, 'content');
      linked(editor, t('products_count'), row.products?.data, 'books-pdf');
      linked(editor, t('publication'), row.publications?.data, 'content');
      window.appendProjectTimeline?.(editor, row.id);
    }
    imageUpload(editor, row);
  };
  const taxonomyAssignments = (editor, row, host) => {
    if (!row.id) return;
    const panel = el('section', undefined, 'workspace-taxonomy-assignments');
    const head = el('header');
    head.append(el('div', undefined, 'workspace-taxonomy-heading'));
    head.firstChild.append(el('h3', t('taxonomy_assignment_title')), el('p', t('taxonomy_assignment_hint'), 'workspace-muted'));
    const counts = el('p', '', 'workspace-taxonomy-counts'); head.append(counts); panel.append(head);
    const scopes = ['posts','videos'];
    if (document.querySelector('.os-start-menu [data-open-app="books-pdf"]')) scopes.push('books');
    const tabs = el('nav', undefined, 'workspace-taxonomy-tabs'); tabs.setAttribute('aria-label', t('taxonomy_assignment_title'));
    const filters = el('form', undefined, 'workspace-taxonomy-filters');
    const search = field('q','search'), assigned = field('taxonomy_assignment_filter','select','all',[
      ['all',t('all')],['1',t('taxonomy_assigned_only')],['0',t('taxonomy_unassigned_only')],
    ]);
    filters.append(search, assigned);
    const list = el('div', undefined, 'workspace-taxonomy-list');
    const actions = el('div', undefined, 'workspace-taxonomy-actions'), pager = el('nav', undefined, 'workspace-taxonomy-pager'), message = el('p');
    message.className = 'workspace-feedback'; message.setAttribute('role','status'); message.setAttribute('aria-live','polite');
    panel.append(tabs, filters, list, actions, pager, message); editor.append(panel);
    let scope = 'posts', page = 1, current = null, debounce, loadGeneration = 0;
    const selectedIds = () => [...list.querySelectorAll('input[type="checkbox"]:checked')].map(input => Number(input.value));
    const control = (key, handler, cls = 'desktop-button') => { const node = el('button', t(key), cls); node.type = 'button'; node.addEventListener('click', handler); return node; };
    const updateCounts = data => { counts.textContent = scopes.map(name => `${t('taxonomy_scope_'+name)}: ${data.counts?.[name] || 0}`).join(' · '); };
    const load = async (nextPage = page) => run(host, async () => {
      const generation = ++loadGeneration; page = nextPage; message.textContent = t('loading');
      const query = new URLSearchParams({scope,page});
      if (search.querySelector('input').value.trim()) query.set('q', search.querySelector('input').value.trim());
      if (assigned.querySelector('select').value !== 'all') query.set('assigned', assigned.querySelector('select').value);
      const result = await request(`/desktop/taxonomy/${row.id}/assignments?${query}`); if (generation !== loadGeneration || !panel.isConnected) return; current = result; updateCounts(current); list.replaceChildren();
      if (!current.data.length) list.append(el('p', t('empty'), 'workspace-empty'));
      for (const item of current.data) {
        const line = el('label', undefined, 'workspace-taxonomy-item'), checkbox = el('input'); checkbox.type = 'checkbox'; checkbox.value = item.id;
        const content = el('span'); content.append(el('strong', item.title), el('small', [item.status ? t(item.status) : '', item.assigned ? t('taxonomy_assigned') : t('taxonomy_not_assigned')].filter(Boolean).join(' · ')));
        const open = control('open', event => { event.preventDefault(); if (scope === 'books') W.open('books-pdf',{id:item.id}); else window.openContentEditor?.(scope === 'posts' ? 'posts' : 'videos', `/desktop/content/${item.id}`); });
        line.append(checkbox, content, open); list.append(line);
      }
      pager.replaceChildren();
      const previous = control('previous', () => load(page - 1)), next = control('next', () => load(page + 1)); previous.disabled = page <= 1; next.disabled = page >= current.last_page;
      pager.append(previous, el('span', `${page} / ${current.last_page} · ${current.total}`), next); message.textContent = '';
    });
    const change = operation => run(host, async () => {
      const ids = selectedIds(); if (!ids.length) { message.textContent = t('taxonomy_assignment_selection_required'); return; }
      for (const node of actions.querySelectorAll('button')) node.disabled = true;
      try {
        const subjectType = scope === 'books' ? 'product' : 'record';
        const result = await request(`/desktop/taxonomy/${row.id}/assignments`, {scope,subject_type:subjectType,operation,subject_ids:ids}, 'PATCH');
        updateCounts(result); await load(page); message.textContent = t('saved');
      } finally { for (const node of actions.querySelectorAll('button')) node.disabled = false; }
    });
    actions.append(control('taxonomy_select_page', () => { for (const input of list.querySelectorAll('input[type="checkbox"]')) input.checked = true; }),
      control('taxonomy_add_selected', () => change('add'), 'desktop-button is-primary'), control('taxonomy_remove_selected', () => change('remove'), 'desktop-button is-danger'));
    for (const name of scopes) {
      const tab = control('taxonomy_scope_'+name, () => { scope = name; page = 1; for (const button of tabs.children) button.setAttribute('aria-pressed', String(button === tab)); load(1); });
      tab.setAttribute('aria-pressed', String(name === scope)); tabs.append(tab);
    }
    filters.addEventListener('submit', event => { event.preventDefault(); load(1); });
    search.querySelector('input').addEventListener('input', () => { clearTimeout(debounce); debounce = setTimeout(() => load(1), 250); });
    assigned.querySelector('select').addEventListener('change', () => load(1));
    load();
  };
  const topicExtra = (editor, row, load, host) => {
    imageUpload(editor, row);
    taxonomyAssignments(editor, row, host);
  };
  const organizeBookForm = editor => {
    const form = editor.querySelector(':scope > form');
    if (!form || form.querySelector('.workspace-more-settings')) return;
    form.classList.add('workspace-book-form');
    const taxonomy = form.elements.namedItem('taxonomy_term_ids')?.closest('label');
    if (taxonomy) {
      taxonomy.classList.add('workspace-taxonomy-field');
      const description = form.elements.namedItem('description')?.closest('label');
      if (description) description.after(taxonomy);
    }
    const more = el('details', undefined, 'workspace-more-settings'); more.dataset.bookMoreSettings = 'true';
    more.append(el('summary', t('further_settings')));
    for (const name of ['subtitle','edition_text','publication_date','tags','seo_title','seo_description','project_id','external_shop_url']) {
      const control = form.elements.namedItem(name);
      const label = control?.closest('label');
      if (label) more.append(label);
    }
    const save = form.querySelector('button[type="submit"]');
    form.insertBefore(more, save);
  };
  const reviewText = (key, values = {}) => {
    let value = window.desktopBookReviewLabels?.[key] || key;
    for (const [name, replacement] of Object.entries(values)) value = value.replace(`:${name}`, replacement);
    return value;
  };
  const bookReviews = root => {
    if (root.dataset.canModerateReviews !== 'true') return;
    const open = () => {
      root.querySelector('[data-book-review-dialog]')?.remove();
      const dialog = el('dialog', undefined, 'workspace-review-dialog'); dialog.dataset.bookReviewDialog = 'true';
      const header = el('header', undefined, 'workspace-review-head');
      const heading = el('h2', reviewText('title')); heading.id = `book-review-heading-${Date.now()}`;
      const close = el('button', reviewText('close'), 'desktop-button'); close.type = 'button'; close.addEventListener('click', () => dialog.close());
      header.append(heading, close); dialog.setAttribute('aria-labelledby', heading.id);
      const intro = el('p', reviewText('intro'), 'workspace-muted');
      const filters = el('form', undefined, 'workspace-review-filters');
      const searchLabel = el('label', reviewText('search')); const search = el('input'); search.type = 'search'; search.name = 'q'; search.maxLength = 120; searchLabel.append(search);
      const statusLabel = el('label', t('status')); const status = el('select'); status.name = 'status';
      for (const value of ['', 'pending', 'published', 'rejected']) status.add(new Option(reviewText(value || 'all'), value));
      statusLabel.append(status); const submit = el('button', t('refresh'), 'desktop-button'); submit.type = 'submit'; filters.append(searchLabel, statusLabel, submit);
      const feedback = el('p', '', 'workspace-review-feedback'); feedback.setAttribute('role', 'status'); feedback.setAttribute('aria-live', 'polite');
      const list = el('section', undefined, 'workspace-review-list');
      const pager = el('nav', undefined, 'workspace-review-pager'); pager.setAttribute('aria-label', t('pages'));
      dialog.append(header, intro, filters, feedback, list, pager);
      const load = async (page = 1, notice = '') => {
        feedback.textContent = t('loading'); feedback.classList.remove('is-error');
        try {
          const query = new URLSearchParams({page}); if (search.value.trim()) query.set('q', search.value.trim()); if (status.value) query.set('status', status.value);
          const result = await request('/desktop/book-reviews?' + query);
          list.replaceChildren();
          if (!result.data.length) list.append(el('p', reviewText('empty'), 'workspace-empty'));
          for (const review of result.data) {
            const card = el('article', undefined, 'workspace-review-card');
            const cardHead = el('header');
            const title = el('h3', review.product?.title || `#${review.product_id}`);
            const badge = el('span', reviewText(review.status), `workspace-review-status is-${review.status}`); cardHead.append(title, badge);
            const meta = el('p', `${review.user?.name || reviewText('anonymous')} · ${reviewText('rating', {rating:review.rating})} · ${new Date(review.created_at).toLocaleDateString()}`, 'workspace-muted');
            const body = el('p', review.body, 'workspace-review-body');
            const actions = el('div', undefined, 'workspace-review-actions');
            for (const [next, key] of [['published', 'approve'], ['rejected', 'reject']]) {
              const action = el('button', reviewText(key), `desktop-button${next === 'rejected' ? ' is-danger' : ' is-primary'}`); action.type = 'button'; action.disabled = review.status === next;
              action.addEventListener('click', async () => {
                action.disabled = true;
                try { await request('/desktop/book-reviews/' + review.id, {status:next}, 'PATCH'); await load(result.current_page, reviewText('saved')); }
                catch (error) { feedback.textContent = error.message; feedback.classList.add('is-error'); action.disabled = false; }
              });
              actions.append(action);
            }
            card.append(cardHead, meta, body, actions); list.append(card);
          }
          pager.replaceChildren();
          const previous = el('button', reviewText('previous'), 'desktop-button'); previous.type = 'button'; previous.disabled = result.current_page <= 1; previous.addEventListener('click', () => load(result.current_page - 1));
          const pageInfo = el('span', `${result.current_page} / ${result.last_page} · ${result.total}`);
          const next = el('button', reviewText('next'), 'desktop-button'); next.type = 'button'; next.disabled = result.current_page >= result.last_page; next.addEventListener('click', () => load(result.current_page + 1));
          pager.append(previous, pageInfo, next); feedback.textContent = notice;
        } catch (error) { feedback.textContent = error.message; feedback.classList.add('is-error'); }
      };
      filters.addEventListener('submit', event => { event.preventDefault(); load(1); });
      dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
      dialog.addEventListener('close', () => dialog.remove(), {once:true}); root.append(dialog); dialog.showModal(); load();
    };
    const trigger = el('button', reviewText('title'), 'desktop-button'); trigger.type = 'button'; trigger.dataset.bookReviews = 'true'; trigger.addEventListener('click', open);
    root.querySelector('[data-workspace-actions]').prepend(trigger);
  };
  const bookExtra = async (editor, row, load, host) => {
    organizeBookForm(editor);
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
    editor.querySelector(':scope > form')?.after(panel);
    if (!row.id) return;
    const assets = row.assets || [];
    for (const asset of assets) {
      const line = el('p', undefined, 'workspace-file-line');
      line.append(el('strong', asset.roles?.includes('public_download') || asset.roles?.includes('paid_download') ? t('full') : asset.roles?.join(', ') || t('file')),
        el('span', ` ${asset.title} · ${asset.mime}`));
      if (asset.preview_url) { const link = el('a', t('open'), 'workspace-link'); link.href = asset.preview_url; link.target = '_blank'; link.rel = 'noreferrer'; line.append(link); }
      panel.append(line);
    }
    const form = el('div', undefined, 'workspace-asset-form');
    const slot = field('slot', 'select', 'full', [['full', t('full')], ['cover', t('cover')], ['sample', t('sample')]]);
    const file = field('file', 'file'),input=file.querySelector('input'); input.accept = '.pdf,image/jpeg,image/png,image/webp,image/gif';
    const attach = el('button', t('save'), 'desktop-button'); attach.type = 'button'; attach.disabled = true; let uploadedMediaId = null;
    form.append(slot,file,attach);panel.append(form);
    window.enhanceDesktopFileInput(input,{profile:'attachment',onUploaded:id=>{uploadedMediaId=id;attach.disabled=false;}});
    attach.addEventListener('click',()=>run(host,async()=>{if(!uploadedMediaId)return;attach.disabled=true;try{await request(`/desktop/books/${row.id}/assets`,{slot:slot.querySelector('select').value,media_id:uploadedMediaId},'POST');if(host._workspaceEdit)await host._workspaceEdit({id:row.id});else await load();}finally{attach.disabled=false;}}));
  };
  const projectConfig = {
    url:'/desktop/projects', newLabel:'new_project', inlineEdit:true, quickCreate:{fields:[['title'],['type','select',[['','—'],'mixed','video','post','book','podcast','live']]]}, detail:async row => { const data = await request('/desktop/projects/' + row.id); return {...data.project, tasks:data.tasks, records:data.records, products:data.products, publications:data.publications}; }, updateMethod:'PUT', statuses:['idea','script','production','review','published'],
    filters:[['type','select','',[['',t('all')],'mixed','video','post','book','podcast','live']],['user_id','select'],['due_before','date']], filterLookups:{user_id:'users'},
    fields:[['title'],['description','textarea'],['type','select',['mixed','video','post','book','podcast','live'],'mixed'],['status','select',['idea','script','production','review','published'],'idea'],['user_id','select'],['team_ids','select'],['start_date','date'],['due_date','date'],['next_action'],['tags']],
    lookups:{user_id:'users',team_ids:'users'}, multiple:['team_ids'], transform:tags, extra:projectExtra,
  };
  const topicConfig = {
    url:'/desktop/taxonomy', filters:[['kind','select','',[['',t('all')],'topic','category']]], fields:[['name'],['kind','select',['topic','category'],'topic'],['description','textarea'],['slug'],['parent_id','select'],['active','checkbox',[],true]],
    lookups:{parent_id:'categories'}, extra:topicExtra,
  };
  const bookConfig = {
    url:'/desktop/books', newLabel:'new_book', inlineEdit:true, statuses:['draft','active','archived'],
    quickCreate:{fields:[['title']],transform:data=>bookData({...data,currency:'EUR',status:'draft'})},
    fields:[['title'],['subtitle'],['description','richtext'],['author'],['contents','richtext'],['edition_text','richtext'],['isbn'],['language','select',['de','en'],'de'],['page_count','number'],['publication_date','date'],['tags'],['seo_title'],['seo_description','textarea'],['price','number',[],0],['currency','select',['EUR','USD','CHF','GBP'],'EUR'],['status','select',['draft','active','archived'],'draft'],['project_id','select'],['taxonomy_term_ids','select'],['external_shop_url','url']],
    lookups:{project_id:'projects',taxonomy_term_ids:'terms'}, multiple:['taxonomy_term_ids'], transform:bookData,
    detail:async row => { const data = await request('/desktop/books/' + row.id); return {...data.product, price:data.product.price_cents === null || data.product.price_cents === undefined ? '' : (Number(data.product.price_cents) / 100).toFixed(2), assets:data.assets}; }, extra:bookExtra,
    intake:async (editor, row, root, load, edit) => {
      const section = el('section', undefined, 'workspace-pdf-intake'); section.append(el('h2', t('new_book')),
        el('p', t('book_upload_ai_hint'), 'workspace-muted'));
      const form = el('form', undefined, 'workspace-pdf-drop'); const file = field('file', 'file'),input=file.querySelector('input'); input.accept = 'application/pdf'; input.required = true;let mediaId=null,originalName='';
      const submit = el('button', t('book_analyze'), 'desktop-button is-primary'); submit.type = 'submit'; form.append(file, submit); section.append(form); editor.append(section);
      window.enhanceDesktopFileInput(input,{profile:'attachment',onUploaded:(id,selected)=>{mediaId=id;originalName=selected.name;}});
      form.addEventListener('submit', event => { event.preventDefault(); run(root, async () => { submit.disabled = true; try { if(!mediaId)throw new Error(t('book_pdf_required'));const data = await request('/desktop/books/intake',{media_id:mediaId,original_name:originalName},'POST'); trackBook(data.id, originalName); window.desktopNotify?.(t('book_ai_background_title'), t('book_ai_background_message'), 'info'); document.dispatchEvent(new Event('desktop-media-changed')); await edit({id:data.id}); } finally { submit.disabled = false; } }); });
      return true;
    },
  };
  W.register('projects', root => crud(root, projectConfig));
  W.register('topics', root => crud(root, topicConfig));
  W.register('books-pdf', root => { const workspace = crud(root, bookConfig); const intake = button('upload_pdf', () => workspace.edit({})); intake.dataset.bookPdfIntake = 'true'; root.querySelector('[data-workspace-actions]').prepend(intake); bookReviews(root); return workspace; });
})();
