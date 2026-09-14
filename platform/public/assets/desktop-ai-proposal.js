(() => {
  const W=window.DesktopWorkspaces;
  window.appendAiProposal=(editor,row,load,root)=>{
    if(!row.proposal)return;
    const keys=['answer','title','short_description','seo_title','seo_description','social_text'];
    const ownerForm=editor.querySelector('form');
    const form=W.el('form');form.dataset.aiProposal='';
    form.append(W.el('h3',W.t('edit_proposal')));
    for(const key of keys){const label=W.field(key,'textarea',row.proposal[key]||'');const input=label.querySelector('textarea');input.maxLength=({answer:20000,title:255,short_description:300,seo_title:255,seo_description:500,social_text:10000})[key];input.disabled=row.status!=='completed';if(key==='answer')input.required=true;form.append(label);}
    const save=W.el('button',W.t('save'),'desktop-button');save.type='submit';save.hidden=row.status!=='completed';form.append(save);editor.append(form);
    const copy=W.el('button',W.t('copy'),'desktop-button');copy.type='button';copy.onclick=()=>W.run(root,async()=>{const data=row.status==='completed'?Object.fromEntries(new FormData(form)):row.proposal;const text=keys.filter(k=>data[k]).map(k=>`${W.t(k)}: ${data[k]}`).join('\n\n');await navigator.clipboard.writeText(text);W.feedback(root,W.t('copied'));});form.append(copy);
    let version=row.proposal_version;
    form.addEventListener('input',()=>{ownerForm.dataset.dirty='true';});
    form.addEventListener('submit',event=>{event.preventDefault();W.run(root,async()=>{save.disabled=true;try{const data=await W.request('/desktop/assistant/'+row.id,{proposal_version:version,proposal:Object.fromEntries(new FormData(form))},'PATCH');version=data.proposal_version;delete ownerForm.dataset.dirty;W.feedback(root,W.t('saved'));await load();}finally{save.disabled=false;}});});
    if(row.status==='completed'&&(row.source_record_id||row.product_id)&&(['title','summary','seo'].includes(row.purpose)||(['social','youtube_description'].includes(row.purpose)&&row.context?.provider))){
      editor.append(W.button('apply',()=>W.run(root,async()=>{await W.request('/desktop/assistant/'+row.id+'/apply',{proposal_version:version},'POST');W.feedback(root,W.t('saved'));await load();})));
    }
  };
})();
