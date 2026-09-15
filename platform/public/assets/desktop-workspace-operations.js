(() => {
  const W=window.DesktopWorkspaces,{t,el,button,field,lookup,formData,request,run}=W;
  const dateString=d=>`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
  W.register('calendar',async root=>{
    root.classList.add('workspace-calendar-root');
    const layout=root.querySelector('.workspace-layout'),list=root.querySelector('[data-workspace-list]'),editor=root.querySelector('[data-workspace-editor]'),filters=root.querySelector('[data-workspace-filters]'),actions=root.querySelector('[data-workspace-actions]');
    layout.classList.add('workspace-calendar-layout');editor.hidden=true;
    let cursor=new Date(),mode='month';cursor.setHours(12,0,0,0);
    const clone=date=>new Date(date.getTime()),addDays=(date,days)=>{const next=clone(date);next.setDate(next.getDate()+days);next.setHours(12,0,0,0);return next;};
    const monday=date=>addDays(date,-((date.getDay()+6)%7));
    const dateField=field('calendar_date','date',dateString(cursor));dateField.querySelector('input').name='date';
    const projectField=field('project_id','select');
    const typeField=field('calendar_type','select','',[['',t('all')],'project','task','publication','live']);typeField.querySelector('select').name='type';
    const providerField=field('provider','select','',[['',t('all')],'website','youtube','facebook','instagram','telegram','x']);
    filters.append(dateField,projectField,typeField,providerField,button('refresh',()=>load()));
    lookup(projectField,'projects').catch(error=>W.feedback(root,error.message,true));
    const rangeLabel=el('strong','', 'workspace-calendar-range');
    const modeButtons=new Map();
    const range=()=>{
      if(mode==='month'){
        const monthStart=new Date(cursor.getFullYear(),cursor.getMonth(),1,12),monthEnd=new Date(cursor.getFullYear(),cursor.getMonth()+1,0,12);
        return {start:monday(monthStart),end:addDays(monday(monthEnd),6),monthStart,monthEnd};
      }
      if(mode==='week'){const start=monday(cursor);return {start,end:addDays(start,6)};}
      return {start:clone(cursor),end:addDays(cursor,30)};
    };
    const formatRange=period=>{
      const locale=document.documentElement.lang||'de';
      if(mode==='month')return cursor.toLocaleDateString(locale,{month:'long',year:'numeric'});
      return `${period.start.toLocaleDateString(locale,{day:'numeric',month:'short'})} – ${period.end.toLocaleDateString(locale,{day:'numeric',month:'short',year:'numeric'})}`;
    };
    const hideEditor=()=>{editor.hidden=true;editor.replaceChildren();};
    const select=event=>{
      editor.hidden=false;editor.className='workspace-editor workspace-calendar-detail';
      const meta=el('p',undefined,'workspace-calendar-detail-meta');meta.append(el('span',t(event.type),`workspace-calendar-type is-${event.type}`),document.createTextNode(` ${event.date}${event.time?' · '+event.time:''} · ${t(event.status)}`));
      const controls=el('div',undefined,'workspace-actions'),open=button('open',()=>W.open(event.app,['projects','tasks'].includes(event.app)?{id:event.subject_id}:undefined));controls.append(open,button('close',hideEditor));
      editor.replaceChildren(el('h2',event.title),meta,controls);
      if(event.providers?.length)editor.append(el('p',`${t('providers')}: ${event.providers.map(t).join(', ')}`));
      if(event.status==='failed')editor.append(el('p',t('schedule_failed'),'workspace-feedback is-error'));
      if(event.schedule_id&&['scheduled','queued'].includes(event.status))editor.querySelector('.workspace-actions').append(button('cancel',()=>run(root,async()=>{await request('/desktop/planning/'+event.schedule_id,undefined,'DELETE');hideEditor();load();})));
      editor.scrollIntoView({block:'nearest',behavior:'smooth'});
    };
    const eventButton=event=>{
      const item=el('button',undefined,`workspace-calendar-event is-${event.type}`);item.type='button';item.title=[event.time,event.title,t(event.type),t(event.status)].filter(Boolean).join(' · ');
      if(event.time)item.append(el('time',event.time));item.append(el('span',event.title));item.addEventListener('click',()=>select(event));return item;
    };
    const renderMonth=(events,period)=>{
      const surface=el('div',undefined,'workspace-calendar-surface is-month');surface.dataset.calendarSurface='month';
      const weekdays=el('div',undefined,'workspace-calendar-weekdays');for(let day=0;day<7;day++)weekdays.append(el('span',addDays(monday(cursor),day).toLocaleDateString(document.documentElement.lang||'de',{weekday:'short'})));
      const grid=el('div',undefined,'workspace-calendar-grid');
      for(let day=clone(period.start);day<=period.end;day=addDays(day,1)){
        const key=dateString(day),cell=el('section',undefined,'workspace-calendar-day');cell.dataset.calendarDay=key;
        if(day.getMonth()!==cursor.getMonth())cell.classList.add('is-outside');if(key===dateString(new Date()))cell.classList.add('is-today');
        const heading=el('div',undefined,'workspace-calendar-day-heading');heading.append(el('span',day.toLocaleDateString(document.documentElement.lang||'de',{day:'numeric'})),el('small',day.toLocaleDateString(document.documentElement.lang||'de',{weekday:'short'})));cell.append(heading);
        const dayEvents=events.get(key)||[];for(const event of dayEvents)cell.append(eventButton(event));if(!dayEvents.length)cell.append(el('span','', 'workspace-calendar-day-empty'));grid.append(cell);
      }
      surface.append(weekdays,grid);list.append(surface);
    };
    const renderWeek=(events,period)=>{
      const surface=el('div',undefined,'workspace-calendar-surface is-week');surface.dataset.calendarSurface='week';const grid=el('div',undefined,'workspace-calendar-week');
      for(let day=clone(period.start);day<=period.end;day=addDays(day,1)){
        const key=dateString(day),cell=el('section',undefined,'workspace-calendar-week-day');cell.dataset.calendarDay=key;if(key===dateString(new Date()))cell.classList.add('is-today');
        const head=el('header');head.append(el('span',day.toLocaleDateString(document.documentElement.lang||'de',{weekday:'short'})),el('strong',day.toLocaleDateString(document.documentElement.lang||'de',{day:'numeric',month:'short'})));cell.append(head);
        const dayEvents=events.get(key)||[];if(dayEvents.length)for(const event of dayEvents)cell.append(eventButton(event));else cell.append(el('p',t('calendar_no_events'),'workspace-calendar-empty'));grid.append(cell);
      }
      surface.append(grid);list.append(surface);
    };
    const renderList=(events,period)=>{
      const surface=el('div',undefined,'workspace-calendar-list');surface.dataset.calendarSurface='list';let count=0;
      for(let day=clone(period.start);day<=period.end;day=addDays(day,1)){
        const rows=events.get(dateString(day))||[];if(!rows.length)continue;count+=rows.length;const group=el('section',undefined,'workspace-calendar-list-day');group.append(el('h3',day.toLocaleDateString(document.documentElement.lang||'de',{weekday:'long',day:'numeric',month:'long'})));
        for(const event of rows){const item=el('button',undefined,`workspace-calendar-list-event is-${event.type}`);item.type='button';item.append(el('time',event.time||t('calendar_all_day')),el('span',event.title),el('small',`${t(event.type)} · ${t(event.status)}`));item.addEventListener('click',()=>select(event));group.append(item);}surface.append(group);
      }
      if(!count)surface.append(el('p',t('calendar_no_events'),'workspace-empty'));list.append(surface);
    };
    const load=()=>run(root,async()=>{
      const chosen=new Date((filters.elements.date.value||dateString(cursor))+'T12:00:00');if(!Number.isNaN(chosen.getTime()))cursor=chosen;
      const period=range(),query=new URLSearchParams({start:dateString(period.start),end:dateString(period.end),project_id:filters.elements.project_id.value,type:filters.elements.type.value,provider:filters.elements.provider.value});
      const data=await request('/desktop/planning?'+query);list.replaceChildren();hideEditor();rangeLabel.textContent=formatRange(period);for(const [key,item] of modeButtons)item.classList.toggle('is-primary',key===mode),item.setAttribute('aria-pressed',String(key===mode));
      W.feedback(root,`${data.timezone}${data.limited?' · '+t('limited'):''}`);const events=new Map();for(const event of data.data){if(!events.has(event.date))events.set(event.date,[]);events.get(event.date).push(event);}
      if(mode==='month')renderMonth(events,period);else if(mode==='week')renderWeek(events,period);else renderList(events,period);
    });
    const shift=direction=>{if(mode==='month')cursor=new Date(cursor.getFullYear(),cursor.getMonth()+direction,1,12);else cursor=addDays(cursor,direction*(mode==='week'?7:31));filters.elements.date.value=dateString(cursor);load();};
    actions.append(button('previous',()=>shift(-1)),button('today',()=>{cursor=new Date();cursor.setHours(12,0,0,0);filters.elements.date.value=dateString(cursor);load();}),button('next',()=>shift(1)),rangeLabel);
    for(const view of ['month','week','list']){const control=button(view,()=>{mode=view;load();});control.classList.add('workspace-calendar-mode');modeButtons.set(view,control);actions.append(control);}
    filters.addEventListener('submit',event=>{event.preventDefault();load();});
    filters.addEventListener('change',event=>{if(event.target.matches('select'))load();});
    if(root.dataset.canPublish==='true')actions.append(button('schedule',()=>run(root,async()=>{
      editor.hidden=false;editor.className='workspace-editor workspace-calendar-detail';editor.replaceChildren(el('h2',t('schedule')));const f=el('form'),material=field('record_id','select');f.append(material);await lookup(material,'records');const time=field('publish_at','datetime-local');time.querySelector('input').required=true;f.append(time);
      const destinations=el('fieldset',undefined,'workspace-calendar-destinations');destinations.append(el('legend',t('providers')));for(const provider of ['website','youtube','facebook','instagram','telegram','x']){const p=field(provider,'checkbox',provider==='website');p.querySelector('input').dataset.provider=provider;destinations.append(p);}f.append(destinations);
      const controls=el('div',undefined,'workspace-actions'),save=el('button',t('schedule'),'desktop-button is-primary');save.type='submit';controls.append(save,button('close',hideEditor));f.append(controls);
      f.addEventListener('submit',event=>{event.preventDefault();run(root,async()=>{save.disabled=true;try{await request('/desktop/planning',{record_id:f.elements.record_id.value,publish_at:f.elements.publish_at.value,providers:[...f.querySelectorAll('[data-provider]:checked')].map(node=>node.dataset.provider)},'POST');W.feedback(root,t('saved'));hideEditor();load();}finally{save.disabled=false;}});});editor.append(f);editor.scrollIntoView({block:'nearest',behavior:'smooth'});
    })));
    load();
  });
  W.register('analytics',root=>{
    root.querySelector('.workspace-layout').classList.add('is-wide');const filters=root.querySelector('[data-workspace-filters]'),end=new Date(),start=new Date();start.setDate(start.getDate()-29);filters.append(field('start','date',dateString(start)),field('end','date',dateString(end)));
    const load=()=>run(root,async()=>{const query=new URLSearchParams(formData(filters)),d=await request('/desktop/analytics/data?'+query),list=root.querySelector('[data-workspace-list]');list.replaceChildren();const metrics=el('div',undefined,'workspace-metrics');for(const key of ['playbacks','confirmed_subscribers','registrations']){const card=el('article',t(key),'workspace-metric');card.append(el('strong',String(d.summary[key])));metrics.append(card);}list.append(metrics,el('p',t('external_unavailable')));for(const [key,columns] of [['events',['event','count']],['daily',['occurred_on','count']],['pages',['subject','count']],['content',['title','count']]]){list.append(el('h2',t(key)));W.table(list,columns,d[key]);}list.append(el('h2',t('sales')));W.table(list,['currency','cents','count'],d.summary.sales);const a=el('a',t('export'),'desktop-button');a.href='/desktop/analytics/export?'+query;root.querySelector('[data-workspace-actions]').replaceChildren(a);});filters.append(button('refresh',load));filters.addEventListener('submit',e=>{e.preventDefault();load();});load();
  });
  W.register('shop',root=>{
    const filters=root.querySelector('[data-workspace-filters]');filters.append(field('status','select','',[['',t('all')],'pending','paid','refunded','failed']));const load=async(page=1)=>run(root,async()=>{const query=new URLSearchParams({status:filters.elements.status.value,page});const d=await request('/desktop/sales?'+query);W.feedback(root,t(d.payment_ready?'payment_ready':'payment_not_ready'));W.rows(root,d.sales.data,async sale=>{const editor=root.querySelector('[data-workspace-editor]');editor.replaceChildren(el('h2',sale.product_title||sale.product?.title||`#${sale.id}`));W.table(editor,['status','amount_cents','currency','paid_at','provider_reference'],[sale]);editor.append(button('books-pdf',()=>W.open('books-pdf')));});W.pager(root,d.sales,load);});root.querySelector('[data-workspace-actions]').append(button('settings',()=>W.open('settings')),button('books-pdf',()=>W.open('books-pdf')));filters.append(button('refresh',()=>load()));filters.addEventListener('submit',e=>{e.preventDefault();load();});load();
  });
  W.register('integrations',root=>{
    const load=()=>run(root,async()=>{const d=await request('/desktop/integrations/data'),list=root.querySelector('[data-workspace-list]');list.replaceChildren();for(const row of [...d.connections,...Object.entries(d.services).map(([provider,connected])=>({provider,connected}))]){const card=el('article',undefined,'workspace-row');card.append(el('strong',t(row.provider)),el('p',t(row.connected?'connected':'not_connected')));if(row.public_url){const a=el('a',t('open'));a.href=row.public_url;a.target='_blank';a.rel='noopener';card.append(a);}if(['youtube','x'].includes(row.provider)&&!row.connected){const a=el('a',t('connected'),'desktop-button');a.href='/desktop/publishing/'+row.provider+'/connect';card.append(a);}if(['youtube','x'].includes(row.provider)&&row.connected)card.append(button('cancel',()=>run(root,async()=>{await request('/desktop/publishing/'+row.provider+'/disconnect',{},'POST');load();})));list.append(card);}for(const provider of d.profile_only)list.append(el('p',provider+': '+t('profile_only')));});root.querySelector('[data-workspace-actions]').append(button('settings',()=>W.open('settings')),button('refresh',load));load();
  });
  W.register('community',root=>{
    window.initializeCommunitySync?.(root);
    const filters=root.querySelector('[data-workspace-filters]');filters.append(field('q','search'),field('source','select','',[['',t('all')],'website','youtube','youtube-takeout']),field('state','select','',[['',t('all')],'unread','read']));
    const load=async(page=1)=>run(root,async()=>{const query=new URLSearchParams(Object.entries(formData(filters)).filter(([,v])=>v!==null));query.set('page',page);const d=await request('/desktop/community/inbox?'+query);W.rows(root,d.data,async row=>{const editor=root.querySelector('[data-workspace-editor]');editor.replaceChildren(el('h2',row.title),el('p',row.body),el('p',row.source+' · '+t(row.metadata?.moderation?.state||row.status)));if(row.metadata?.inbox_reply)editor.append(el('p',t('reply')+' · '+t(row.metadata.inbox_reply.status)));editor.append(button('read',()=>run(root,async()=>{await request('/desktop/community/inbox/'+row.id+'/read',{},'PATCH');load();})));if(row.source==='website'&&(row.metadata?.website_comment||row.metadata?.website_community))for(const decision of ['publish','reject'])editor.append(button(decision,()=>run(root,async()=>{await request('/desktop/community/moderation/'+row.id,{decision},'PATCH');load();})));editor.append(el('p',[typeof row.metadata?.author==='string'?row.metadata.author:'',(row.metadata?.posted_at||row.created_at)?new Date(row.metadata?.posted_at||row.created_at).toLocaleString():'',row.related_title].filter(Boolean).join(' · ')));if(row.external_url){const a=el('a',t('open'),'desktop-button');a.href=row.external_url;a.target='_blank';a.rel='noopener noreferrer';editor.append(a);}if(document.querySelector('.os-start-menu [data-open-app=ai-assistant]'))editor.append(button('generate_ai',()=>W.open('ai-assistant',{source_record_id:row.id,purpose:'reply',question:t('reply')})));if(row.reply_targets?.length){const f=el('form');f.append(field('body','textarea'),field('target','select',row.reply_targets.includes('youtube')?'youtube':'website',row.reply_targets));const submit=el('button',t('reply'),'desktop-button');f.append(submit);f.addEventListener('submit',e=>{e.preventDefault();run(root,async()=>{submit.disabled=true;try{await request('/desktop/community/inbox/'+row.id+'/reply',formData(f),'POST');load();}finally{submit.disabled=false;}});});editor.append(f);}else editor.append(el('p',t('reply_unavailable')));});W.pager(root,d,load);});filters.append(button('refresh',()=>load()));filters.addEventListener('submit',e=>{e.preventDefault();load();});load();
  });
})();
