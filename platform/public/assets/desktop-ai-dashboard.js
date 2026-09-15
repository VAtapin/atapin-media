(() => {
  const W = window.DesktopWorkspaces;
  if (!W) return;
  const labels = () => window.desktopAiLabels || {};
  const t = key => labels()[key] || key;
  const purposeLabel = purpose => t('purpose_' + purpose) || purpose;
  const statusLabel = status => t('status_' + status) || status;
  const button = (text, action, primary = false) => { const b = document.createElement('button'); b.type = 'button'; b.className = 'desktop-button' + (primary ? ' is-primary' : ''); b.textContent = text; b.addEventListener('click', action); return b; };
  const formatNumber = value => new Intl.NumberFormat(document.documentElement.lang || 'de-DE').format(Number(value || 0));
  const formatDate = value => value ? new Date(value).toLocaleString(document.documentElement.lang || 'de-DE', {dateStyle:'short', timeStyle:'short'}) : '—';
  const formatCost = stats => stats.cost_known ? `${(Number(stats.cost_micros || 0) / 1000000).toFixed(4)} USD` : t('cost_unknown');
  const createMetric = (label, value, note, tone = '') => { const card = document.createElement('article'); card.className = 'ai-dashboard-metric ' + tone; const span = document.createElement('span'); span.textContent = label; const strong = document.createElement('strong'); strong.textContent = value; const small = document.createElement('small'); small.textContent = note || ''; card.append(span, strong, small); return card; };
  const openDialog = (root, title) => {
    let dialog = root.querySelector('[data-ai-dialog]');
    if (!dialog) {
      dialog = document.createElement('dialog'); dialog.className = 'ai-dashboard-dialog'; dialog.dataset.aiDialog = 'true';
      dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
      root.append(dialog);
    }
    dialog.replaceChildren();
    const header = document.createElement('header'); header.className = 'ai-dashboard-dialog-head';
    const heading = document.createElement('h2'); heading.textContent = title;
    const close = button(t('cancel'), () => dialog.close());
    header.append(heading, close);
    const body = document.createElement('div'); body.className = 'ai-dashboard-dialog-body';
    dialog.append(header, body);
    if (!dialog.open) dialog.showModal();
    return { dialog, body };
  };
  const openGlobalDialog = title => {
    let dialog = document.querySelector('[data-ai-global-dialog]');
    if (!dialog) {
      dialog = document.createElement('dialog'); dialog.className = 'ai-dashboard-dialog'; dialog.dataset.aiGlobalDialog = 'true';
      dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
      document.body.append(dialog);
    }
    dialog.replaceChildren();
    const header = document.createElement('header'); header.className = 'ai-dashboard-dialog-head';
    const heading = document.createElement('h2'); heading.textContent = title;
    const close = button(t('cancel'), () => dialog.close());
    header.append(heading, close);
    const body = document.createElement('div'); body.className = 'ai-dashboard-dialog-body';
    dialog.append(header, body);
    if (!dialog.open) dialog.showModal();
    return { dialog, body };
  };
  const renderDetail = (root, row) => {
    const {body} = openDialog(root, row.question || t('history')); const meta = document.createElement('p'); meta.className = 'ai-dashboard-detail-meta'; meta.textContent = [purposeLabel(row.purpose), statusLabel(row.status), formatDate(row.created_at)].join(' · '); body.append(meta);
    if (row.record?.title) { const material = document.createElement('p'); const materialLabel = document.createElement('strong'); materialLabel.textContent = t('material') + ': '; material.append(materialLabel, document.createTextNode(row.record.title)); body.append(material); }
    const answerTitle = document.createElement('h3'); answerTitle.textContent = t('answer'); const answer = document.createElement('div'); answer.className = 'ai-dashboard-answer'; answer.textContent = row.answer || t('no_answer'); body.append(answerTitle, answer);
    const facts = document.createElement('dl'); facts.className = 'ai-dashboard-facts'; [['model',row.model||'—'],['tokens',formatNumber(row.total_tokens)],['cost',row.estimated_cost_micros == null ? t('cost_unknown') : `${(Number(row.estimated_cost_micros)/1000000).toFixed(4)} USD`]].forEach(([key,value])=>{const dt=document.createElement('dt');dt.textContent=t(key);const dd=document.createElement('dd');dd.textContent=value;facts.append(dt,dd);}); body.append(facts); const note = document.createElement('p'); note.className='ai-dashboard-muted'; note.textContent=t('read_only'); body.append(note);
  };
  const setupGlobalChat = () => {
    const trigger = document.querySelector('[data-ai-chat]');
    if (!trigger || trigger.dataset.aiChatReady) return;
    trigger.dataset.aiChatReady = 'true';
    trigger.addEventListener('click', () => {
      const {dialog, body} = openGlobalDialog(t('help'));
      const hint = document.createElement('p'); hint.className = 'ai-dashboard-muted'; hint.textContent = t('request_hint');
      const form = document.createElement('form'); form.className = 'ai-dashboard-request-form';
      const label = document.createElement('label'); label.textContent = t('question');
      const textarea = document.createElement('textarea'); textarea.name = 'question'; textarea.rows = 8; textarea.required = true; textarea.maxLength = 4000; textarea.placeholder = t('question_help'); label.append(textarea);
      const actions = document.createElement('div'); actions.className = 'workspace-actions';
      const send = button(t('send'), async () => { if (!form.reportValidity()) return; send.disabled = true; body.querySelector('[data-ai-error]')?.remove(); try { await W.request('/desktop/assistant', {purpose:'admin_help', question:textarea.value}, 'POST'); const notice = document.createElement('p'); notice.className = 'ai-dashboard-muted'; notice.textContent = t('request_sent'); body.replaceChildren(notice); } catch (error) { const notice = document.createElement('p'); notice.dataset.aiError = 'true'; notice.className = 'workspace-feedback is-error'; notice.textContent = error.message; body.append(notice); } finally { send.disabled = false; } }, true);
      actions.append(send); form.append(label, actions); body.append(hint, form); textarea.focus();
    });
  };
  setupGlobalChat();
  let load;
  W.register('ai-assistant', root => {
    root.classList.add('ai-dashboard-root'); const filters = root.querySelector('[data-workspace-filters]'); const actions = root.querySelector('[data-workspace-actions]'); const list = root.querySelector('[data-workspace-list]'); const editor = root.querySelector('[data-workspace-editor]'); editor.hidden = true;
    const q = W.field('q','search'); const status = W.field('status','select','',[['',t('all_statuses')],['queued',t('status_queued')],['processing',t('status_processing')],['completed',t('status_completed')],['failed',t('status_failed')],['applied',t('status_applied')]]); const purpose = W.field('purpose','select','',[['',t('all_purposes')],['admin_help',t('purpose_admin_help')],['chat',t('purpose_chat')],['title',t('purpose_title')],['summary',t('purpose_summary')],['seo',t('purpose_seo')],['social',t('purpose_social')],['structure',t('purpose_structure')],['prioritize',t('purpose_prioritize')]]); filters.replaceChildren(q,status,purpose); actions.replaceChildren();
    const query = () => new URLSearchParams(Object.entries(W.formData(filters)).filter(([,value]) => value !== null));
    const render = data => { list.replaceChildren(); const dashboard = document.createElement('div'); dashboard.className='ai-dashboard'; const stats=data.stats||{}; const metrics=document.createElement('section'); metrics.className='ai-dashboard-metrics'; metrics.append(createMetric(t('total'),formatNumber(stats.total),t('history')),createMetric(t('today'),formatNumber(stats.today),t('active'), 'is-gold'),createMetric(t('completed'),formatNumber(stats.completed),t('failed'),'is-green'),createMetric(t('tokens'),formatNumber(stats.tokens),formatCost(stats),'is-rose')); dashboard.append(metrics); const info=document.createElement('p'); info.className='ai-dashboard-note'; info.textContent=`${t('model')}: ${data.model || '—'} · ${t('knowledge')}: ${formatNumber(data.knowledge?.entries)} ${t('entries')} · ${t('read_only')}`; dashboard.append(info); const heading=document.createElement('div'); heading.className='ai-dashboard-section-heading'; const title=document.createElement('h2'); title.textContent=t('history'); heading.append(title); dashboard.append(heading); const rows=document.createElement('div'); rows.className='ai-dashboard-request-list'; const items=data.requests?.data||[]; if(!items.length){const empty=document.createElement('p');empty.className='workspace-empty';empty.textContent=W.t('empty');rows.append(empty);} items.forEach(row=>{const item=document.createElement('button');item.type='button';item.className='ai-dashboard-request-row'; const main=document.createElement('span');main.className='ai-dashboard-request-main'; const question=document.createElement('strong');question.textContent=row.question||'—'; const sub=document.createElement('small');sub.textContent=[purposeLabel(row.purpose),row.record?.title||'',formatDate(row.created_at)].filter(Boolean).join(' · ');main.append(question,sub); const state=document.createElement('span');state.className='ai-dashboard-status is-'+row.status;state.textContent=statusLabel(row.status); item.append(main,state); item.addEventListener('click',()=>renderDetail(root,row)); rows.append(item);}); dashboard.append(rows); list.append(dashboard); W.pager(root,data.requests,load); };
    load = async page => { const params=query(); params.set('page', page || 1); W.run(root, async()=>{ const data=await W.request('/desktop/assistant?'+params.toString()); render(data); }); };
    filters.addEventListener('input',()=>{clearTimeout(root._aiFilterTimer);root._aiFilterTimer=setTimeout(()=>load(1),250);}); filters.addEventListener('change',()=>load(1)); load(1); const timer=setInterval(()=>{if(!root.isConnected){clearInterval(timer);return;}load(1);},10000); return {root,load,edit:row=>renderDetail(root,row)};
  });
})();
