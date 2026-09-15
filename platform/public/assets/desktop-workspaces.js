(() => {
  const modules = new Map(), controllers = new Map(), pending = new Map();
  const labels = () => window.desktopWorkspaceLabels || {};
  const t = key => labels()[key] || key?.split('.').reduce((value, part) => value?.[part], labels()) || labels().states?.[key] || labels().titles?.[key] || ({q:labels().search, file:labels().upload, count:labels().events, occurred_on:labels().start})[key] || key;
  const bookAiState = row => { const state = row.metadata?.book_pdf_ai?.status; return state ? `${t('book_ai_status')}: ${t('states.' + state) || state}` : ''; };
  const el = (tag, text, cls) => { const node = document.createElement(tag); if (text !== undefined) node.textContent = text; if (cls) node.className = cls; return node; };
  const request = async (url, data, method = 'GET') => {
    const opts = {method, credentials:'same-origin', headers:{Accept:'application/json', 'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content}};
    if (data instanceof FormData) opts.body = data; else if (data !== undefined) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(data); }
    const response = await fetch(url, opts); const result = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' · ') || result.message || t('error'));
    return result;
  };
  const clean = root => { if (root.querySelector('[data-workspace-editor] > form')?.dataset.dirty === 'true') throw new Error(t('save_before_action')); };
  const button = (key, action) => { const b = el('button', t(key), 'desktop-button'); b.type = 'button'; b.addEventListener('click', event => { if (b.closest('[data-workspace-editor]')) { try { clean(b.closest('[data-workspace]')); } catch (error) { feedback(b.closest('[data-workspace]'), error.message, true); return; } } action(event); }); return b; };
  const feedback = (root, text, error = false) => { const out = root.querySelector('[data-workspace-feedback]'); out.textContent = text; out.classList.toggle('is-error', error); };
  const run = async (root, operation) => { try { await operation(); } catch (e) { if (root.isConnected) feedback(root, e.message, true); } };
  const field = (name, type = 'text', value = '', options = []) => {
    const label = el('label', t(name)); let input;
    if (type === 'select') { input = el('select'); for (const v of options) { const pair = Array.isArray(v) ? v : [v, t(v)]; input.add(new Option(pair[1], pair[0])); } }
    else if (type === 'textarea') { input = el('textarea'); input.rows = 5; input.maxLength = 100000; }
    else { input = el('input'); input.type = type; }
    input.name = name; if (type === 'checkbox') input.checked = !!value; else input.value = type === 'date' && value ? String(value).slice(0, 10) : Array.isArray(value) ? value.join(', ') : value ?? '';
    if (type === 'datetime-local' && value) { const date = new Date(value); if (!Number.isNaN(date.getTime())) input.value = new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16); }
    label.append(input); return label;
  };
  const lookup = async (label, kind, current = null, multiple = false) => {
    const select = label.querySelector('select'); select.disabled = true; select.multiple = multiple; if (multiple) select.size = 5;
    const chosen = new Set((Array.isArray(current) ? current : current ? [current] : []).map(String)); const retained = new Map([...chosen].map(id => [id, id]));
    const search = el('input'); search.type = 'search'; search.className = 'workspace-lookup-search'; search.placeholder = t('search'); search.setAttribute('aria-label', t('search')); label.insertBefore(search, select);
    let sequence = 0, timer;
    const load = async () => { const seq = ++sequence; const data = await request('/desktop/lookups?' + new URLSearchParams({kind, q:search.value})); if (seq !== sequence) return;
      for (const row of data.data || data) retained.set(String(row.id), row.title || row.name);
      const selected = new Set([...chosen, ...[...select.selectedOptions].map(o => o.value)].filter(Boolean)); const rows = data.data || data; select.replaceChildren(); if (!multiple) select.add(new Option('—', ''));
      const listed = new Set(); for (const row of rows) { const id = String(row.id); listed.add(id); const option = new Option(row.title || row.name, id); option.selected = selected.has(id); select.add(option); }
      for (const id of selected) if (!listed.has(id)) { const option = new Option(retained.get(id) || id, id); option.selected = true; select.add(option); }
    };
    select.addEventListener('change', () => { chosen.clear(); for (const option of select.selectedOptions) if (option.value) chosen.add(option.value); });
    search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => load().catch(() => {}), 250); }); await load(); select.disabled = false; return select;
  };
  const formData = form => { const data = {}; for (const input of form.elements) { if (!input.name || input.disabled) continue; data[input.name] = input.type === 'checkbox' ? input.checked : input.multiple ? [...input.selectedOptions].map(o => o.value) : input.type === 'datetime-local' && input.value ? new Date(input.value).toISOString() : input.value || null; } return data; };
  const open = (app, row) => { if (row) pending.set(app, row); document.querySelector(`.os-start-menu [data-open-app="${CSS.escape(app)}"]`)?.click(); const api = controllers.get(app); if (row && api?.root.isConnected) { pending.delete(app); run(api.root, () => api.edit(row)); } };
  const openSeparate = (app, row = {}) => { pending.set(app, row); const win = window.openDesktopProgram?.(app, {forceNew:true}); if (!win) document.querySelector(`.os-start-menu [data-open-app="${CSS.escape(app)}"]`)?.click(); return win; };
  const pager = (root, page, load) => { const nav = root.querySelector('[data-workspace-pager]'); nav.replaceChildren(); const current = page.current_page || page.meta?.current_page || 1, last = page.last_page || page.meta?.last_page || 1; const prev = button('previous', () => load(current - 1)), next = button('next', () => load(current + 1)); prev.disabled = current <= 1; next.disabled = current >= last; nav.append(prev, el('span', `${current} / ${last} · ${page.total ?? page.meta?.total ?? ''}`), next); };
  const listRows = (root, items, select, remove) => {
    const list = root.querySelector('[data-workspace-list]'); list.replaceChildren();
    if (!items.length) list.append(el('p', t('empty'), 'workspace-empty'));
    for (const row of items) {
      const b = el('div', undefined, 'workspace-row'); b.tabIndex = 0; b.setAttribute('role', 'button');
      const detail = [t(row.status || row.kind || ''), row.refresh_status ? t(row.refresh_status) : '',
        [row.due_date?.slice(0,10), row.due_time].filter(Boolean).join(' '), row.project?.title,
        row.owner?.name, row.assignee?.name, row.source, bookAiState(row)].filter(Boolean).join(' · ');
      b.append(el('strong', row.title || row.name || row.subject || row.question || row.email || ('#' + row.id)), el('small', detail));
      if (remove) { const action = button('delete', () => remove(row)); action.className = 'desktop-button is-danger workspace-row-remove'; action.addEventListener('click', event => { event.preventDefault(); event.stopPropagation(); }, {capture:true}); b.append(action); }
      b.addEventListener('click', event => { if (event.target.closest('.workspace-row-remove')) return; select(row); });
      b.addEventListener('keydown', event => { if (event.target === b && ['Enter', ' '].includes(event.key)) { event.preventDefault(); select(row); } }); list.append(b);
    }
  };
  const cards = (root, items, select, remove) => {
    const list = root.querySelector('[data-workspace-list]'); list.replaceChildren();
    if (!items.length) { list.append(el('p', t('empty'), 'workspace-empty')); return; }
    const vertical = root.dataset.workspace === 'books-pdf'; const grid = el('div', undefined, 'workspace-card-grid');
    for (const row of items) {
      const card = el('article', undefined, 'workspace-card'); card.tabIndex = 0; card.setAttribute('role', 'button');
      const title = el('strong', row.title || row.name || row.subject || row.question || ('#' + row.id));
      const visual = el('span', undefined, 'workspace-card-visual');
      if (row.cover_url) { const image = el('img'); image.src = row.cover_url; image.alt = ''; visual.append(image); }
      else visual.append(el('span', row.kind === 'category' ? '◆' : row.kind === 'topic' ? '✦' : row.pdf_ready ? 'PDF' : '◈', 'workspace-card-placeholder'));
      const body = el('span', undefined, 'workspace-card-body');
      const meta = [row.author, row.owner?.name, row.project?.title, row.kind ? t(row.kind) : '', row.status ? t(row.status) : '', row.pdf_ready === false ? t('book_pdf_missing') : row.pdf_ready ? t('book_pdf_ready') : '', bookAiState(row)].filter(Boolean);
      body.append(el('small', meta.join(' · '))); if (row.description) body.append(el('span', row.description, 'workspace-card-description'));
      if (vertical) card.append(title, visual, body); else { body.prepend(title); card.append(visual, body); }
      if (remove) { const action = button('delete', () => remove(row)); action.className = 'desktop-button is-danger workspace-card-remove'; action.addEventListener('click', event => { event.preventDefault(); event.stopPropagation(); }, {capture:true}); card.append(action); }
      card.addEventListener('click', event => { if (event.target.closest('.workspace-card-remove')) return; select(row); });
      card.addEventListener('keydown', event => { if (event.target === card && ['Enter', ' '].includes(event.key)) { event.preventDefault(); select(row); } }); grid.append(card);
    }
    list.append(grid);
  };
  const table = (root, columns, items) => { const table = el('table', undefined, 'workspace-table'), head = el('tr'); for (const key of columns) head.append(el('th', t(key))); table.append(head); for (const row of items) { const tr = el('tr'); for (const key of columns) tr.append(el('td', typeof row[key] === 'object' ? JSON.stringify(row[key]) : row[key] ?? '—')); table.append(tr); } root.append(table); };
  const crud = (root, config) => {
    const filters = root.querySelector('[data-workspace-filters]'), actions = root.querySelector('[data-workspace-actions]'), editorMode = root.dataset.workspaceMode === 'editor'; let page = 1, items = [], editGeneration = 0;
    root.classList.toggle('workspace-editor-mode', editorMode);
    root.classList.toggle('workspace-catalog-mode', !editorMode);
    const viewKey = `atapin.workspace.view.${root.dataset.workspace}`; let view = 'cards'; try { view = localStorage.getItem(viewKey) || 'cards'; } catch (_) {}
    const select = row => editorMode ? edit(row) : openSeparate(root.dataset.workspace, row);
    const canDelete = config.delete || ['projects', 'topics', 'books-pdf'].includes(root.dataset.workspace);
    const deleteItem = row => run(root, async () => {
      if (!window.confirm(t('delete_confirm'))) return;
      await request(config.url + '/' + row.id, {confirmation:'DELETE'}, config.deleteMethod || 'DELETE');
      feedback(root, t('deleted'));
      window.desktopNotify?.(t('deleted'), row.title || row.name || row.subject || '', 'success');
      document.dispatchEvent(new Event('desktop-media-changed'));
      await load(1);
    });
    const renderItems = () => view === 'list' ? listRows(root, items, select, canDelete ? deleteItem : null) : cards(root, items, select, canDelete ? deleteItem : null);
    const edit = async (row = {}) => {
      const generation = ++editGeneration; if (config.detail && row.id) row = await config.detail(row); if (generation !== editGeneration || !root.isConnected) return;
      const editor = root.querySelector('[data-workspace-editor]'); if (editor.querySelector('form')?.dataset.dirty === 'true' && !window.confirm(window.desktopImportLabels?.discard_edits || t('save_before_action'))) return;
      editor.replaceChildren(el('h2', row.title || row.name || row.subject || t('new')));
      if (config.intake && !row.id) { const handled = await config.intake(editor, row, root, load, edit); if (handled) return; }
      const form = el('form'); editor.append(form); form.addEventListener('input', () => form.dataset.dirty = 'true'); form.addEventListener('change', () => form.dataset.dirty = 'true');
      for (const spec of config.fields || []) { const [name, type = 'text', options = [], defaultValue] = spec; const value = row[name] ?? row.metadata?.[name] ?? defaultValue ?? ''; const f = field(name, type, value, options); form.append(f); const input = f.querySelector('input,textarea,select'); if (['title','name','subject','question'].includes(name)) input.required = true; if (type === 'number') { input.min = 0; input.step = name === 'price' ? '0.01' : '1'; input.inputMode = 'decimal'; } if (config.lookups?.[name]) try { await lookup(f, config.lookups[name], value, !!config.multiple?.includes(name)); } catch (error) { f.append(el('small', error.message)); } }
      if (config.readonly?.(row)) for (const input of form.elements) input.disabled = true;
      const save = el('button', t(config.submitLabel || 'save'), 'desktop-button is-primary'); save.type = 'submit'; form.append(save); if (config.readonly?.(row)) save.hidden = true;
      const aiBusy = root.dataset.workspace === 'books-pdf' && row.id && ['queued','processing'].includes(row.metadata?.book_pdf_ai?.status);
      if (aiBusy) {
        const notice = el('div', t('book_ai_wait'), 'workspace-ai-busy');
        notice.setAttribute('role', 'status');
        editor.insertBefore(notice, form);
        for (const input of form.elements) input.disabled = true;
        save.hidden = true;
        form.classList.add('is-ai-busy');
      }
      if (canDelete && row.id && !config.readonly?.(row)) {
        const remove = el('button', t('delete'), 'desktop-button is-danger'); remove.type = 'button';
        remove.addEventListener('click', () => run(root, async () => {
          if (!window.confirm(t('delete_confirm'))) return;
          remove.disabled = true;
          try {
            await request(config.url + '/' + row.id, {confirmation:'DELETE'}, config.deleteMethod || 'DELETE');
            delete form.dataset.dirty;
            feedback(root, t('deleted'));
            document.dispatchEvent(new Event('desktop-media-changed'));
            if (editorMode) root.closest('.os-window')?.querySelector('[data-window-action="close"]')?.click(); else await load(1);
          } finally { remove.disabled = false; }
        }));
        form.append(remove);
      }
      form.addEventListener('submit', event => { event.preventDefault(); if (form.classList.contains('is-ai-busy')) return; run(root, async () => { save.disabled = true; try { let data = formData(form); if (config.transform) data = config.transform(data); const result = await request(config.url + (row.id ? '/' + row.id : ''), data, row.id ? (config.updateMethod || 'PATCH') : 'POST'); delete form.dataset.dirty; feedback(root, t('saved')); document.dispatchEvent(new Event('desktop-media-changed')); if (!editorMode) await load(page); const id = result.id || result.project_id || result.task_id; if (editorMode && id) await edit({id}); } finally { save.disabled = false; } }); });
      if (generation !== editGeneration || !root.isConnected) return;
      root._workspaceEdit = edit;
      await config.extra?.(editor, row, async () => { delete form.dataset.dirty; if (!editorMode) await load(page); const updated = items.find(item => item.id === row.id); if (updated) await edit(updated); }, root);
    };
    const load = async (number = page) => run(root, async () => { page = number; feedback(root, t('loading')); const data = formData(filters); delete data.q; if (filters.elements.q?.value) data.q = filters.elements.q.value; const query = new URLSearchParams(Object.entries(data).filter(([,v]) => v !== null).map(([key,value]) => [key, typeof value === 'boolean' ? Number(value) : value])); query.set('page', page); const result = await request(config.url + '?' + query); const resultPage = config.page ? config.page(result) : result; items = resultPage.data || []; renderItems(); pager(root, resultPage, load); feedback(root, config.notice?.(result) || ''); config.afterLoad?.(items, select, root); });
    if (editorMode) { filters.hidden = true; actions.hidden = true; root.querySelector('[data-workspace-list]').hidden = true; root.querySelector('[data-workspace-pager]').hidden = true; const row = pending.get(root.dataset.workspace) || {}; pending.delete(root.dataset.workspace); run(root, () => edit(row)); }
    else { filters.append(field('q','search')); if (config.statuses) filters.append(field('status','select','',[['',t('all')], ...config.statuses])); for (const f of config.filters || []) { const label = field(...f); filters.append(label); if (config.filterLookups?.[f[0]]) lookup(label, config.filterLookups[f[0]]).catch(error => feedback(root, error.message, true)); } filters.append(button('refresh', () => load(1))); const cardsButton = button('book_view_cards', () => { view = 'cards'; try { localStorage.setItem(viewKey, view); } catch (_) {} renderItems(); cardsButton.setAttribute('aria-pressed','true'); listButton.setAttribute('aria-pressed','false'); }); const listButton = button('book_view_list', () => { view = 'list'; try { localStorage.setItem(viewKey, view); } catch (_) {} renderItems(); cardsButton.setAttribute('aria-pressed','false'); listButton.setAttribute('aria-pressed','true'); }); cardsButton.setAttribute('aria-pressed', String(view === 'cards')); listButton.setAttribute('aria-pressed', String(view === 'list')); actions.append(button(config.newLabel || 'new', () => openSeparate(root.dataset.workspace)), cardsButton, listButton, button('refresh', () => load())); root.querySelector('[data-workspace-editor]').replaceChildren(el('p', t('choose'))); document.addEventListener('desktop-media-changed', () => { if (root.isConnected) load(1); }); load(); }
    const api = {root, load, edit:select, get items() { return items; }}; controllers.set(root.dataset.workspace, api); return api;
  };
  const mount = async (content, app, options = {}) => { content.replaceChildren(el('p', t('loading'), 'workspace-empty')); try { const response = await fetch('/desktop/workspaces/' + encodeURIComponent(app), {credentials:'same-origin', headers:{Accept:'text/html'}}); if (!response.ok) throw new Error(t('error') + ` (${response.status})`); const html = await response.text(); if (!content.isConnected) return; content.innerHTML = html; const root = content.querySelector('[data-workspace]'); root.dataset.workspaceMode = options.mode || 'catalog'; const initialize = modules.get(app); if (!initialize) throw new Error(t('error')); await initialize(root); } catch (e) { content.replaceChildren(el('p', e.message, 'workspace-empty'), button('refresh', () => mount(content, app, options))); } };
  window.DesktopWorkspaces = {register:(name,module) => modules.set(name,module), t, el, request, button, feedback, run, field, lookup, formData, open, openSeparate, pager, rows:listRows, cards, table, crud, mount, clean};
  window.initializeDesktopWorkspace = mount;
})();
