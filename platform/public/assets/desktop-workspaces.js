(() => {
  const modules = new Map(),controllers=new Map(),pending=new Map();
  const labels = () => window.desktopWorkspaceLabels || {};
  const t = key => key?.split('.').reduce((value,part)=>value?.[part],labels()) || labels().states?.[key] || labels().titles?.[key] || ({q:labels().search,file:labels().upload,count:labels().events,occurred_on:labels().start})[key] || key;
  const el = (tag, text, cls) => {const node=document.createElement(tag);if(text!==undefined)node.textContent=text;if(cls)node.className=cls;return node;};
  const request = async (url,data,method='GET') => {
    const opts={method,credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content}};
    if(data instanceof FormData)opts.body=data;else if(data!==undefined){opts.headers['Content-Type']='application/json';opts.body=JSON.stringify(data);}
    const response=await fetch(url,opts);const result=await response.json().catch(()=>({}));
    if(!response.ok)throw new Error(Object.values(result.errors||{}).flat().join(' · ')||result.message||t('error'));
    return result;
  };
  const clean=root=>{if(root.querySelector('[data-workspace-editor] > form')?.dataset.dirty==='true')throw new Error(t('save_before_action'));};
  const button=(key,action)=>{const b=el('button',t(key),'desktop-button');b.type='button';b.addEventListener('click',event=>{if(b.closest('[data-workspace-editor]')){try{clean(b.closest('[data-workspace]'));}catch(error){feedback(b.closest('[data-workspace]'),error.message,true);return;}}action(event);});return b;};
  const feedback=(root,text,error=false)=>{const out=root.querySelector('[data-workspace-feedback]');out.textContent=text;out.classList.toggle('is-error',error);};
  const run=async(root,operation)=>{try{await operation();}catch(e){if(root.isConnected)feedback(root,e.message,true);}};
  const field=(name,type='text',value='',options=[])=>{
    const label=el('label',t(name));let input;
    if(type==='select'){input=el('select');for(const v of options){const pair=Array.isArray(v)?v:[v,t(v)];input.add(new Option(pair[1],pair[0]));}}
    else if(type==='textarea'){input=el('textarea');input.rows=5;input.maxLength=100000;}
    else {input=el('input');input.type=type;}
    input.name=name;if(type==='checkbox')input.checked=!!value;else input.value=type==='date'&&value?String(value).slice(0,10):value??'';
    label.append(input);return label;
  };
  const lookup=async(label,kind,current=null,multiple=false)=>{
    const select=label.querySelector('select');select.disabled=true;select.multiple=multiple;if(multiple)select.size=5;
    const chosen=new Set((Array.isArray(current)?current:current?[current]:[]).map(String));
    const retained=new Map([...chosen].map(id=>[id,id]));
    const search=el('input');search.type='search';search.className='workspace-lookup-search';search.placeholder=t('search');search.setAttribute('aria-label',t('search'));label.insertBefore(search,select);
    let sequence=0,timer;
    const load=async()=>{const seq=++sequence;const data=await request('/desktop/lookups?'+new URLSearchParams({kind,q:search.value}));if(seq!==sequence)return;
      for(const row of data.data||data)retained.set(String(row.id),row.title||row.name);
      const selected=new Set([...chosen,...[...select.selectedOptions].map(o=>o.value)].filter(Boolean));
      const rows=data.data||data;select.replaceChildren();if(!multiple)select.add(new Option('—',''));
      const listed=new Set();for(const row of rows){const id=String(row.id);listed.add(id);const option=new Option(row.title||row.name,id);option.selected=selected.has(id);select.add(option);}
      for(const id of selected)if(!listed.has(id)){const option=new Option(retained.get(id)||id,id);option.selected=true;select.add(option);}
    };
    select.addEventListener('change',()=>{chosen.clear();for(const option of select.selectedOptions)if(option.value)chosen.add(option.value);});
    search.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(()=>load().catch(()=>{}),250);});await load();select.disabled=false;return select;
  };
  const formData=form=>{const data={};for(const input of form.elements){if(!input.name||input.disabled)continue;data[input.name]=input.type==='checkbox'?input.checked:input.multiple?[...input.selectedOptions].map(o=>o.value):input.value||null;}return data;};
  const open=(app,row)=>{if(row)pending.set(app,row);document.querySelector(`.os-start-menu [data-open-app="${CSS.escape(app)}"]`)?.click();const api=controllers.get(app);if(row&&api?.root.isConnected){pending.delete(app);run(api.root,()=>api.edit(row));}};
  const pager=(root,page,load)=>{const nav=root.querySelector('[data-workspace-pager]');nav.replaceChildren();const current=page.current_page||page.meta?.current_page||1,last=page.last_page||page.meta?.last_page||1;const prev=button('previous',()=>load(current-1)),next=button('next',()=>load(current+1));prev.disabled=current<=1;next.disabled=current>=last;nav.append(prev,el('span',`${current} / ${last} · ${page.total??page.meta?.total??''}`),next);};
  const rows=(root,items,select)=>{const list=root.querySelector('[data-workspace-list]');list.replaceChildren();if(!items.length)list.append(el('p',t('empty'),'workspace-empty'));for(const row of items){const b=el('button',undefined,'workspace-row');b.type='button';b.append(el('strong',row.title||row.name||row.subject||row.question||row.email||`#${row.id}`),el('small',[t(row.status||row.kind||''),row.refresh_status?t(row.refresh_status):'',row.due_date?.slice(0,10),row.project?.title,row.assignee?.name,row.source].filter(Boolean).join(' · ')));b.addEventListener('click',()=>run(root,()=>select(row)));list.append(b);}};
  const table=(root,columns,items)=>{const table=el('table',undefined,'workspace-table'),head=el('tr');for(const key of columns)head.append(el('th',t(key)));table.append(head);for(const row of items){const tr=el('tr');for(const key of columns)tr.append(el('td',typeof row[key]==='object'?JSON.stringify(row[key]):row[key]??'—'));table.append(tr);}root.append(table);};
  const crud=(root,config)=>{
    const filters=root.querySelector('[data-workspace-filters]'),actions=root.querySelector('[data-workspace-actions]');let page=1,items=[],editGeneration=0;
    filters.append(field('q','search'));if(config.statuses)filters.append(field('status','select','',[['',t('all')],...config.statuses]));
    for(const f of config.filters||[])filters.append(field(...f));filters.append(button('refresh',()=>load(1)));
    const edit=async(row={})=>{
      const generation=++editGeneration;if(config.detail&&row.id)row=await config.detail(row);if(generation!==editGeneration||!root.isConnected)return;
      const editor=root.querySelector('[data-workspace-editor]');if(editor.querySelector('form')?.dataset.dirty==='true'&&!window.confirm(window.desktopImportLabels.discard_edits))return;editor.replaceChildren(el('h2',row.title||row.name||row.subject||t('new')));const form=el('form');editor.append(form);form.addEventListener('input',()=>form.dataset.dirty='true');form.addEventListener('change',()=>form.dataset.dirty='true');
      for(const spec of config.fields){const [name,type='text',options=[],defaultValue]=spec;const value=row[name]??row.metadata?.[name]??defaultValue??'';const f=field(name,type,value,options);form.append(f);const input=f.querySelector('input,textarea,select');if(['title','name','subject','question'].includes(name))input.required=true;if(type==='number'){input.min=0;input.step=1;}if(config.lookups?.[name])try{await lookup(f,config.lookups[name],value,!!config.multiple?.includes(name));}catch(error){f.append(el('small',error.message));}}
      if(config.readonly?.(row))for(const input of form.elements)input.disabled=true;
      const save=el('button',t(config.submitLabel||'save'),'desktop-button is-primary');save.type='submit';form.append(save);
      if(config.readonly?.(row))save.hidden=true;
      form.addEventListener('submit',event=>{event.preventDefault();run(root,async()=>{save.disabled=true;try{let data=formData(form);if(config.transform)data=config.transform(data);const result=await request(config.url+(row.id?'/'+row.id:''),data,row.id?(config.updateMethod||'PATCH'):'POST');delete form.dataset.dirty;feedback(root,t('saved'));document.dispatchEvent(new Event('desktop-media-changed'));await load(page);const id=result.id||result.project_id||result.task_id;const updated=items.find(r=>r.id===id||r.id===row.id);if(updated)await edit(updated);}finally{save.disabled=false;}});});
      if(generation!==editGeneration||!root.isConnected)return;
      await config.extra?.(editor,row,async()=>{delete form.dataset.dirty;await load(page);const updated=items.find(item=>item.id===row.id);if(updated)await edit(updated);},root);
    };
    const load=async(number=page)=>run(root,async()=>{page=number;feedback(root,t('loading'));const data=formData(filters);delete data.q;if(filters.elements.q?.value)data.q=filters.elements.q.value;const query=new URLSearchParams(Object.entries(data).filter(([,v])=>v!==null).map(([key,value])=>[key,typeof value==='boolean'?Number(value):value]));query.set('page',page);const result=await request(config.url+'?'+query);const resultPage=config.page?config.page(result):result;items=resultPage.data||[];rows(root,items,edit);pager(root,resultPage,load);feedback(root,config.notice?.(result)||'');config.afterLoad?.(items,edit,root);});
    filters.addEventListener('submit',e=>{e.preventDefault();load(1);});actions.append(button('new',()=>run(root,()=>edit())),button('refresh',()=>load()));root.querySelector('[data-workspace-editor]').append(el('p',t('choose')));const api={root,load,edit,get items(){return items;}};controllers.set(root.dataset.workspace,api);if(pending.has(root.dataset.workspace)){const row=pending.get(root.dataset.workspace);pending.delete(root.dataset.workspace);run(root,()=>edit(row));}load();return api;
  };
  const mount=async(content,app)=>{
    content.replaceChildren(el('p',t('loading'),'workspace-empty'));
    try{const response=await fetch('/desktop/workspaces/'+encodeURIComponent(app),{credentials:'same-origin',headers:{Accept:'text/html'}});if(!response.ok)throw new Error(t('error')+` (${response.status})`);const html=await response.text();if(!content.isConnected)return;content.innerHTML=html;const root=content.querySelector('[data-workspace]');const initialize=modules.get(app);if(!initialize)throw new Error(t('error'));await initialize(root);}catch(e){content.replaceChildren(el('p',e.message,'workspace-empty'),button('refresh',()=>mount(content,app)));}
  };
  window.DesktopWorkspaces={register:(name,module)=>modules.set(name,module),t,el,request,button,feedback,run,field,lookup,formData,open,pager,rows,table,crud,mount,clean};
  window.initializeDesktopWorkspace=mount;
})();
