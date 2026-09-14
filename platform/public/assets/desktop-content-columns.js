(() => {
  const W=window.DesktopWorkspaces;
  const columns=['title','kind','source','status','workflow_stage','project','published_at'];
  document.addEventListener('content-list-loaded',event=>{
    const root=event.target.closest('[data-content-library]');if(!root)return;
    const form=root.querySelector('[data-content-filter]');
    if(!form.dataset.columnsReady){
      form.dataset.columnsReady='true';
      form.append(W.field('sort','select','created',['created','updated','title','status','published']),W.field('direction','select','desc',['desc','asc']),W.field('workflow_stage','select','',[['',W.t('all')],'idea','script','production','review','approved']),W.field('review','checkbox'));
      form.elements.review.value='1';
      for(const [name,kind,app] of [['project_id','projects','projects'],['taxonomy_term_id','terms','topics']]){if(!document.querySelector(`.os-start-menu [data-open-app="${app}"]`))continue;const label=W.field(name,'select');form.append(label);W.lookup(label,kind).catch(error=>{root.querySelector('[data-content-summary]').textContent=error.message;});}
      const choice=W.field('table_view','checkbox');form.append(choice);choice.querySelector('input').removeAttribute('name');
      root._contentTableChoice=choice;
      const key=document.querySelector('[data-desktop]').dataset.storageKey+'.content-columns';
      let chosen=columns;try{const saved=JSON.parse(localStorage.getItem(key));if(Array.isArray(saved)){const valid=saved.filter(value=>columns.includes(value));if(valid.length)chosen=valid;}}catch{}
      root._contentColumns=new Set(chosen);
      const panel=W.el('details');panel.append(W.el('summary',W.t('columns')));for(const column of columns){const label=W.field(column,'checkbox',root._contentColumns.has(column));const input=label.querySelector('input');input.removeAttribute('name');input.onchange=()=>{if(input.checked)root._contentColumns.add(column);else root._contentColumns.delete(column);try{localStorage.setItem(key,JSON.stringify([...root._contentColumns]));}catch{};};panel.append(label);}form.append(panel);
    }
    if(event.detail.playlist||!root._contentTableChoice.querySelector('input').checked)return;
    const table=W.el('table',undefined,'workspace-table'),head=W.el('tr');
    const selected=columns.filter(key=>root._contentColumns.has(key));for(const column of selected)head.append(W.el('th',W.t(column)));table.append(head);
    for(const item of event.detail.items){const row=W.el('tr');for(const column of selected){const cell=W.el('td');if(column==='title'){const b=W.el('button',item.title,'desktop-button');b.type='button';b.dataset.contentDetail=item.detail_url;cell.append(b);}else cell.textContent=['status','kind','workflow_stage'].includes(column)?W.t(item[column]||''):item[column]||'—';row.append(cell);}if(!selected.includes('title')){const cell=W.el('td'),b=W.el('button',W.t('open'),'desktop-button');b.type='button';b.dataset.contentDetail=item.detail_url;cell.append(b);row.append(cell);}table.append(row);}
    const li=W.el('li');li.style.overflowX='auto';li.append(table);root.querySelector('[data-content-list]').replaceChildren(li);
  });
})();
