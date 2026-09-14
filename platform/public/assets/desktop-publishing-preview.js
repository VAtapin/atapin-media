(() => {
  const W=window.DesktopWorkspaces;
  window.initializePublishingPreview=root=>{
    const select=root.querySelector('[data-publishing-record]'),destinations=root.querySelector('[data-publishing-destinations]'),panel=W.el('section',undefined,'desktop-publishing-card');panel.dataset.publishingPreview='';destinations.after(panel);let generation=0;
    const load=async()=>{const sequence=++generation;if(!select.value){panel.replaceChildren();return;}try{const data=await W.request('/desktop/publishing/preview?record_id='+encodeURIComponent(select.value));if(sequence!==generation||!root.isConnected)return;panel.replaceChildren(W.el('h3',W.t('destination_preview')));for(const input of destinations.querySelectorAll('input:checked:not(:disabled)')){const provider=input.value,preview=data.previews[provider];if(!preview)continue;const section=W.el('section');section.append(W.el('h4',W.t(provider)),W.el('p',W.t('visibility')+': '+W.t(preview.visibility)),W.el('pre',preview.caption));section.querySelector('pre').style.cssText='white-space:pre-wrap;overflow-wrap:anywhere';if(preview.tags?.length)section.append(W.el('p',preview.tags.join(', ')));renderMedia(section,preview);if(document.querySelector('.os-start-menu [data-open-app=ai-assistant]')&&provider!=='website')section.append(W.button('generate_ai',()=>W.open('ai-assistant',{source_record_id:data.id,provider,purpose:'social',question:W.t('social')})));panel.append(section);}}catch(error){if(sequence===generation)panel.replaceChildren(W.el('p',error.message));}};
    root.addEventListener('change',event=>{if(event.target===select||destinations.contains(event.target))load();});root.addEventListener('publishing-loaded',load);load();
    function renderMedia(section,preview){
      for(const media of [preview.media,preview.cover?.id!==preview.media?.id?preview.cover:null]){
        if(!media)continue;const a=W.el('a',W.t('media_preview')+' · '+media.mime+' · '+media.bytes);a.href=media.preview_url;a.target='_blank';a.rel='noopener';section.append(a);
        if(media.kind==='image'){const img=W.el('img');img.src=media.preview_url;img.alt=W.t('platform_cover');img.loading='lazy';img.style.cssText='display:block;max-width:100%;max-height:180px;object-fit:contain';section.append(img);}
      }
      for(const warning of ['requires_render','requires_website'])if(preview[warning])section.append(W.el('p',W.t(warning)));
      for(const row of preview.reply_markup?.inline_keyboard||[])for(const target of row){const a=W.el('a',target.text);a.href=target.url;a.target='_blank';a.rel='noopener';section.append(a);}
      for(const schedule of preview.schedules||[])section.append(W.el('p',W.t('scheduled')+': '+new Date(schedule.publish_at).toLocaleString(document.documentElement.lang)));
    }
  };
})();
