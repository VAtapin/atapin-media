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
    root.querySelector('.workspace-layout').classList.add('is-wide','analytics-layout');
    const filters=root.querySelector('[data-workspace-filters]'),end=new Date(),start=new Date();start.setDate(start.getDate()-29);
    const editor=root.querySelector('[data-workspace-editor]');
    filters.append(field('start','date',dateString(start)),field('end','date',dateString(end)));
    const number=value=>Number(value)||0;
    const formatNumber=value=>new Intl.NumberFormat(document.documentElement.lang||'de-DE').format(number(value));
    const formatCurrency=(cents,currency)=>new Intl.NumberFormat(document.documentElement.lang||'de-DE',{style:'currency',currency:currency||'EUR'}).format(number(cents)/100);
    const hideDetail=()=>{editor.replaceChildren();editor.className='workspace-editor analytics-detail';editor.classList.remove('is-open');};
    const showDetail=(title,description,columns,items)=>{
      editor.className='workspace-editor analytics-detail is-open';editor.replaceChildren(el('p',t('analytics_detail'),'workspace-eyebrow'),el('h2',title),el('p',description,'analytics-detail-copy'));
      if(items?.length)W.table(editor,columns,items);else editor.append(el('p',t('no_data'),'workspace-empty'));
      editor.append(button('close',hideDetail));editor.scrollIntoView({behavior:'smooth',block:'nearest'});
    };
    const metric=(label,value,detail,accent)=>{const card=el('article',undefined,`analytics-metric is-${accent||'blue'}`);card.append(el('span',label),el('strong',value),el('small',detail));return card;};
    const section=(title,subtitle,cls='analytics-card')=>{const card=el('section',undefined,cls);const head=el('header');head.append(el('div',undefined,'analytics-card-heading'));head.firstChild.append(el('h2',title),el('p',subtitle));card.append(head);return card;};
    const addBar=(card,row,max,type)=>{const label=type==='event'?row.event:(type==='page'?row.subject:row.title);const b=el('button',undefined,'analytics-bar-row');b.type='button';b.title=label;b.append(el('span',label),el('strong',formatNumber(row.count)));const track=el('span',undefined,'analytics-bar-track'),fill=el('span',undefined,'analytics-bar-fill');fill.style.width=`${Math.max(4,number(row.count)/max*100)}%`;track.append(fill);b.append(track);b.addEventListener('click',()=>showDetail(label,`${t('count')}: ${formatNumber(row.count)}`,type==='event'?['event','count']:type==='page'?['subject','count']:['title','count'],[row]));card.append(b);};
    const renderChart=(card,rows)=>{
      const chart=el('div',undefined,'analytics-chart');
      if(!rows.length){chart.append(el('p',t('no_data'),'workspace-empty'));card.append(chart);return;}
      const width=760,height=250,pad={top:22,right:18,bottom:38,left:42},max=Math.max(1,...rows.map(row=>number(row.count))),svg=document.createElementNS('http://www.w3.org/2000/svg','svg');
      svg.setAttribute('viewBox',`0 0 ${width} ${height}`);svg.setAttribute('role','img');svg.setAttribute('aria-label',t('daily'));
      const defs=document.createElementNS('http://www.w3.org/2000/svg','defs'),gradient=document.createElementNS('http://www.w3.org/2000/svg','linearGradient');gradient.id='analytics-area-gradient';gradient.setAttribute('x1','0');gradient.setAttribute('x2','0');gradient.setAttribute('y1','0');gradient.setAttribute('y2','1');
      const stopTop=document.createElementNS('http://www.w3.org/2000/svg','stop');stopTop.setAttribute('offset','0%');stopTop.setAttribute('stop-color','#b8842e');stopTop.setAttribute('stop-opacity','.30');const stopBottom=document.createElementNS('http://www.w3.org/2000/svg','stop');stopBottom.setAttribute('offset','100%');stopBottom.setAttribute('stop-color','#b8842e');stopBottom.setAttribute('stop-opacity','0');gradient.append(stopTop,stopBottom);defs.append(gradient);svg.append(defs);
      const innerWidth=width-pad.left-pad.right,innerHeight=height-pad.top-pad.bottom,point=(row,index)=>({x:pad.left+(rows.length===1?innerWidth/2:index/(rows.length-1)*innerWidth),y:pad.top+innerHeight-(number(row.count)/max*innerHeight)}),points=rows.map(point);
      for(let step=0;step<4;step++){const y=pad.top+innerHeight-step/3*innerHeight,line=document.createElementNS('http://www.w3.org/2000/svg','line');line.setAttribute('x1',pad.left);line.setAttribute('x2',width-pad.right);line.setAttribute('y1',y);line.setAttribute('y2',y);line.setAttribute('class','analytics-chart-grid');svg.append(line);}
      const linePath=points.map((p,index)=>`${index?'L':'M'} ${p.x} ${p.y}`).join(' '),areaPath=`${linePath} L ${points.at(-1).x} ${pad.top+innerHeight} L ${points[0].x} ${pad.top+innerHeight} Z`,area=document.createElementNS('http://www.w3.org/2000/svg','path');area.setAttribute('d',areaPath);area.setAttribute('class','analytics-chart-area');svg.append(area);
      const line=document.createElementNS('http://www.w3.org/2000/svg','path');line.setAttribute('d',linePath);line.setAttribute('class','analytics-chart-line');svg.append(line);
      rows.forEach((row,index)=>{const p=points[index],circle=document.createElementNS('http://www.w3.org/2000/svg','circle');circle.setAttribute('cx',p.x);circle.setAttribute('cy',p.y);circle.setAttribute('r',rows.length>18?'3.5':'5');circle.setAttribute('class','analytics-chart-point');circle.setAttribute('tabindex','0');circle.setAttribute('aria-label',`${row.occurred_on}: ${formatNumber(row.count)}`);circle.addEventListener('click',()=>showDetail(row.occurred_on,`${t('daily')}: ${row.occurred_on}`,['occurred_on','count'],[row]));circle.addEventListener('keydown',event=>{if(event.key==='Enter'||event.key===' '){event.preventDefault();circle.click();}});svg.append(circle);if(index===0||index===rows.length-1||index===Math.floor(rows.length/2)){const text=document.createElementNS('http://www.w3.org/2000/svg','text');text.setAttribute('x',p.x);text.setAttribute('y',height-12);text.setAttribute('text-anchor',index===0?'start':index===rows.length-1?'end':'middle');text.setAttribute('class','analytics-chart-label');text.textContent=String(row.occurred_on).slice(5);svg.append(text);}});
      const peak=document.createElementNS('http://www.w3.org/2000/svg','text');peak.setAttribute('x',pad.left);peak.setAttribute('y',14);peak.setAttribute('class','analytics-chart-value');peak.textContent=formatNumber(max);svg.append(peak);chart.append(svg);card.append(chart);
    };
    const renderDashboard=(d,query)=>{
      const list=root.querySelector('[data-workspace-list]');list.replaceChildren();hideDetail();
      const events=d.events||[],daily=d.daily||[],pages=d.pages||[],content=d.content||[],sales=d.summary.sales||[],eventTotal=events.reduce((total,row)=>total+number(row.count),0),saleTotal=sales.map(row=>formatCurrency(row.cents,row.currency)).join(' · ')||'—',saleCount=sales.reduce((total,row)=>total+number(row.count),0);
      const dashboard=el('div',undefined,'analytics-dashboard'),metrics=el('div',undefined,'analytics-metrics');metrics.append(metric(t('total_events'),formatNumber(eventTotal),t('analytics_period'),'blue'),metric(t('playbacks'),formatNumber(d.summary.playbacks),t('analytics_period'),'gold'),metric(t('registrations'),formatNumber(d.summary.registrations),t('analytics_period'),'green'),metric(t('sales'),saleTotal,`${formatNumber(saleCount)} ${t('count').toLowerCase()}`,'rose'));dashboard.append(metrics,el('p',t('external_unavailable'),'analytics-source-note'));
      const overview=el('div',undefined,'analytics-overview-grid'),activity=section(t('daily'),t('click_for_details'),'analytics-card analytics-card-chart');activity.querySelector('.analytics-card-heading').append(el('span',`${formatNumber(eventTotal)} ${t('events').toLowerCase()}`,'analytics-card-badge'));renderChart(activity,daily);overview.append(activity);
      const eventCard=section(t('top_events'),t('click_for_details'));if(events.length){const max=Math.max(1,...events.map(row=>number(row.count)));events.slice(0,6).forEach(row=>addBar(eventCard,row,max,'event'));}else eventCard.append(el('p',t('no_data'),'workspace-empty'));overview.append(eventCard);dashboard.append(overview);
      const rankings=el('div',undefined,'analytics-overview-grid');const pageCard=section(t('top_pages'),t('click_for_details'));if(pages.length){const max=Math.max(1,...pages.map(row=>number(row.count)));pages.slice(0,5).forEach(row=>addBar(pageCard,row,max,'page'));}else pageCard.append(el('p',t('no_data'),'workspace-empty'));const contentCard=section(t('top_content'),t('click_for_details'));if(content.length){const max=Math.max(1,...content.map(row=>number(row.count)));content.slice(0,5).forEach(row=>addBar(contentCard,row,max,'content'));}else contentCard.append(el('p',t('no_data'),'workspace-empty'));rankings.append(pageCard,contentCard);dashboard.append(rankings);
      const details=el('section',undefined,'analytics-data-details'),detailsHead=el('header');detailsHead.append(el('div',undefined,'analytics-card-heading'));detailsHead.firstChild.append(el('h2',t('data_details')),el('p',t('analytics_details_hint')));details.append(detailsHead);
      for(const [key,columns,rows] of [['events',['event','count'],events],['daily',['occurred_on','count'],daily],['pages',['subject','count'],pages],['content',['title','count'],content]]){const disclosure=document.createElement('details'),summary=document.createElement('summary');summary.append(el('span',t(key)),el('small',`${formatNumber(rows.length)} ${t('count').toLowerCase()}`));disclosure.append(summary);if(rows.length)W.table(disclosure,columns,rows);else disclosure.append(el('p',t('no_data'),'workspace-empty'));details.append(disclosure);}
      if(sales.length){const disclosure=document.createElement('details'),summary=document.createElement('summary');summary.append(el('span',t('sales')),el('small',`${formatNumber(saleCount)} ${t('count').toLowerCase()}`));disclosure.append(summary);W.table(disclosure,['currency','amount','count'],sales.map(row=>({...row,amount:formatCurrency(row.cents,row.currency)})));details.append(disclosure);}
      dashboard.append(details);list.append(dashboard);const a=el('a',t('export'),'desktop-button');a.href='/desktop/analytics/export?'+query;root.querySelector('[data-workspace-actions]').replaceChildren(a);
    };
    const load=()=>run(root,async()=>{const query=new URLSearchParams(formData(filters)),d=await request('/desktop/analytics/data?'+query);renderDashboard(d,query);});
    filters.append(button('refresh',load));filters.addEventListener('submit',e=>{e.preventDefault();load();});load();
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
