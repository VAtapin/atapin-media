(() => {
  const W=window.DesktopWorkspaces;
  window.initializeMigrationWizard=root=>{
    const wizard=root.querySelector('[data-migration-wizard]');if(!wizard)return;
    const key=document.querySelector('[data-desktop]').dataset.storageKey+'.migration-review';let reviewed={};
    try{const saved=JSON.parse(localStorage.getItem(key));if(saved&&typeof saved==='object'&&!Array.isArray(saved))reviewed=saved;}catch{}
    let cursor=0,steps=[];
    const panel=wizard.querySelector('[data-migration-steps]'),message=wizard.querySelector('[data-migration-message]');
    const render=()=>{panel.replaceChildren();if(!steps.length){message.textContent=W.t('empty');return;}
      const step=steps[cursor];panel.append(W.el('h3',`${cursor+1} / ${steps.length} · ${W.t('migration_'+step.id)}`),W.el('p',W.t('migration_'+step.id+'_hint')));
      if(step.count!==undefined)panel.append(W.el('p',W.t('existing_records')+': '+step.count));
      if(step.configured!==undefined)panel.append(W.el('p',W.t(step.configured?'payment_ready':'payment_not_ready')));
      panel.append(W.button('open',()=>W.open(step.app)));
      const label=W.field('reviewed_by_me','checkbox',!!reviewed[step.id]);label.querySelector('input').onchange=event=>{reviewed[step.id]=event.target.checked;try{localStorage.setItem(key,JSON.stringify(reviewed));}catch{message.textContent=W.t('preferences_not_saved');}};panel.append(label);
      const prev=W.button('previous',()=>{cursor--;render();}),next=W.button('next',()=>{cursor++;render();});prev.disabled=cursor===0;next.disabled=cursor===steps.length-1;panel.append(prev,next);
      if(step.id==='review'){
        const list=W.el('section');panel.append(list);
        const page=async number=>{try{const data=await W.request('/desktop/content?review=1&page='+number);if(!list.isConnected)return;list.replaceChildren();for(const row of data.data){const b=W.button('open',()=>{const app=row.kind==='post'?'posts':'videos';W.open(app);document.querySelector(`[data-content-library][data-section="${app}"]`)?.dispatchEvent(new CustomEvent('local-content-open',{detail:{url:row.detail_url}}));});b.textContent=row.title;list.append(b);if(document.querySelector('.os-start-menu [data-open-app=ai-assistant]'))list.append(W.button('generate_ai',()=>W.open('ai-assistant',{source_record_id:row.id,purpose:'seo',question:W.t('seo')})));}const previous=W.button('previous',()=>page(number-1)),next=W.button('next',()=>page(number+1));previous.disabled=number<=1;next.disabled=number>=data.meta.last_page;list.append(previous,W.el('span',`${number} / ${data.meta.last_page}`),next);}catch(error){message.textContent=error.message;}};page(1);
      }
    };
    const load=async()=>{try{const data=await W.request('/desktop/migration');if(!wizard.isConnected)return;steps=data.steps;cursor=Math.min(cursor,Math.max(0,steps.length-1));message.textContent='';render();}catch(error){message.textContent=error.message;}};
    wizard.addEventListener('toggle',()=>{if(wizard.open)load();});
  };
})();
