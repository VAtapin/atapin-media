(() => {
  const W=window.DesktopWorkspaces;
  window.appendDocumentImport=(details,item)=>{
    if(!['text/plain','text/html','application/vnd.openxmlformats-officedocument.wordprocessingml.document'].includes(item.mime)||details.closest('[data-media-library]')?.dataset.canContent!=='true')return;
    const button=document.createElement('button');button.className='desktop-button';button.type='button';button.textContent=W.t('import_article');const status=document.createElement('p');status.setAttribute('role','status');details.append(button,status);
    const check=async()=>{if(!details.isConnected||details.dataset.currentId!==item.id)return;try{const data=await W.request('/desktop/media/'+item.id+'/details');const state=data.document_import||{};status.textContent=W.t(state.status||'');if(state.record_id){button.disabled=false;button.textContent=W.t('open');button.onclick=()=>{W.open('posts');document.querySelector('[data-content-library][data-section=posts]')?.dispatchEvent(new CustomEvent('local-content-open',{detail:{url:'/desktop/content/'+state.record_id}}));};}else if(state.status==='queued'){button.disabled=true;setTimeout(check,2000);}else button.disabled=false;}catch(error){status.textContent=error.message;button.disabled=false;}};
    button.onclick=async()=>{if(details.dataset.dirty==='true'){status.textContent=W.t('save_before_action');return;}button.disabled=true;try{await W.request('/desktop/media/'+item.id+'/article',{},'POST');status.textContent=W.t('queued');check();}catch(error){status.textContent=error.message;button.disabled=false;}};
    check();
  };
})();
