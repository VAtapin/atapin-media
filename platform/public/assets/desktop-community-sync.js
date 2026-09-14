(() => {
  const W=window.DesktopWorkspaces;
  window.initializeCommunitySync=root=>{
    const panel=W.el('details');panel.append(W.el('summary',W.t('sync_comments')));root.querySelector('[data-workspace-filters]').after(panel);
    const list=W.el('div');panel.append(W.button('sync_comments',async()=>{if(!confirm(W.t('confirm_comment_sync')))return;try{await W.request('/desktop/community/sync',{confirm:true},'POST');await load();}catch(error){W.feedback(root,error.message,true);}}),list);
    let sequence=0;const load=async()=>{const generation=++sequence;try{const data=await W.request('/desktop/community/sync');if(generation!==sequence||!root.isConnected)return;list.replaceChildren();for(const run of data.data){const row=W.el('section');row.append(W.el('p',W.t(run.status)+' · '+W.t('sync_pages')+': '+run.pages+' · '+W.t('sync_imported')+': '+run.imported));if(run.error)row.append(W.el('p',run.error));if(run.status==='failed')row.append(W.button('retry_sync',async()=>{try{await W.request('/desktop/community/sync/'+run.id+'/retry',{},'POST');load();}catch(error){W.feedback(root,error.message,true);}}));list.append(row);}}catch(error){W.feedback(root,error.message,true);}};
    panel.addEventListener('toggle',()=>{if(panel.open)load();});const timer=setInterval(()=>{if(!root.isConnected)clearInterval(timer);else if(panel.open)load();},5000);
  };
})();
