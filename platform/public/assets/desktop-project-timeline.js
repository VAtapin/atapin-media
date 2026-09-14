(() => {
  const W=window.DesktopWorkspaces;
  window.appendProjectTimeline=(editor,id)=>{
    const panel=W.el('section');editor.append(panel);
    const load=async page=>{try{const data=(await W.request('/desktop/projects/'+id+'?timeline_page='+page)).timeline;if(!panel.isConnected)return;panel.replaceChildren(W.el('h3',W.t('timeline')));for(const event of data.data){const change=event.status&&event.status!==event.previous_status?' · '+(event.previous_status?W.t(event.previous_status)+' → ':'')+W.t(event.status):'';panel.append(W.el('p',W.t(event.action==='project.saved'?'project_updated':'task_updated')+change+' · '+new Date(event.created_at).toLocaleString()));}if(!data.data.length)panel.append(W.el('p',W.t('empty')));const prev=W.button('previous',()=>load(page-1)),next=W.button('next',()=>load(page+1));prev.disabled=page<=1;next.disabled=page>=data.last_page;panel.append(prev,W.el('span',`${page} / ${data.last_page}`),next);}catch(error){if(panel.isConnected)panel.replaceChildren(W.el('p',error.message));}};load(1);
  };
})();
