(() => {
  const t=()=>window.desktopImportLabels||{};
  const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const api=async(url,options={})=>{
    const reply=await fetch(url,{credentials:'same-origin',...options,headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||'',...options.headers}});
    const data=await reply.json();if(!reply.ok)throw new Error(Object.values(data.errors||{}).flat().join(' ')||data.message||t().load_error);return data;
  };
  const post=(url,data={})=>api(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
  const dialog=title=>{
    const box=document.createElement('dialog');box.className='import-report-dialog os-window-content';
    box.innerHTML=`<div class="media-library-toolbar-row"><strong>${esc(title)}</strong><button type="button" class="desktop-button" data-close>${esc(t().report_close)}</button></div><div data-body></div>`;
    document.body.append(box);box.querySelector('[data-close]').onclick=()=>box.close();box.addEventListener('close',()=>box.remove());box.showModal();return box;
  };
  window.loadTakeoutInventory=async root=>{
    const select=root.querySelector('[data-takeout-batch]'),message=root.querySelector('[data-takeout-message]');
    try {
      const data=await api(root.dataset.takeoutUrl);const selected=select.value;select.replaceChildren(new Option(t().takeout_select,''));
      data.batches.forEach(batch=>select.append(new Option(batch.layout==='folder'?`${t().takeout_folder}: ${batch.name}`:`${batch.id} · ${batch.parts.length} ${t().takeout_parts_short} · ${(batch.parts.reduce((sum,p)=>sum+p.bytes,0)/1024**3).toFixed(2)} GB${batch.report?' · '+t().takeout_manifest_preferred:''}`,batch.id)));
      select.value=selected||data.batches[0]?.id||'';
      const update=()=>{
        const batch=data.batches.find(b=>b.id===select.value),parts=root.querySelector('[data-takeout-parts]');
        parts.disabled=batch?.layout==='folder';parts.closest('label').hidden=parts.disabled;
        if(batch?.parts.length)parts.value=batch.parts.length;
        message.textContent=!data.available?t().takeout_unavailable:!data.batches.length?t().takeout_empty:batch?.report?`${t().takeout_manifest_preferred}: ${batch.report}`:t().takeout_manifest_optional;
      };
      select.onchange=update;update();
    }catch(e){message.textContent=e.message;}
  };
  // Confirm decoding, seeking, HTTP range support and actual playback in THIS browser, not merely file existence.
  const playCheck=(url,signal,host)=>new Promise((resolve,reject)=>{
    const video=document.createElement('video');video.muted=true;video.controls=true;video.preload='auto';host.replaceChildren(video);
    let started=false,seeked=false,done=false,timer;
    const finish=error=>{if(done)return;done=true;clearTimeout(timer);signal.removeEventListener('abort',abort);video.pause();video.removeAttribute('src');video.load();error?reject(error):resolve();};
    const abort=()=>finish(new Error(t().audit_stopped));signal.addEventListener('abort',abort,{once:true});
    video.onerror=()=>finish(new Error(t().audit_decode_failed));
    video.onloadeddata=()=>{
      if(!video.videoWidth||!video.videoHeight)return finish(new Error(t().audit_decode_failed));
      if(video.duration<=0)return finish(new Error(t().audit_decode_failed));
      if(seeked)return;seeked=true;video.currentTime=Number.isFinite(video.duration)?Math.min(5,video.duration/3):0.05;
    };
    video.onseeked=()=>{if(!started){started=true;const before=video.currentTime;video.ontimeupdate=()=>{if(video.currentTime>before+0.1)finish();};video.play().catch(finish);}};
    timer=setTimeout(()=>finish(new Error(t().audit_timeout)),20000);
    (async()=>{
      try {
        if(new URL(url,location.href).origin!==location.origin)throw new Error(t().audit_local_only);
        const reply=await fetch(url,{credentials:'same-origin',headers:{Range:'bytes=0-1'},signal});
        if(reply.status!==206||!/^bytes 0-1\/\d+$/.test(reply.headers.get('Content-Range')||'')){await reply.body?.cancel();throw new Error(t().audit_range_failed);}
        await reply.body?.cancel();if(!done)video.src=url;
      }catch(e){finish(e);}
    })();
  });
  window.showImportReport=async(url,audit=false)=>{
    const box=dialog(audit?t().audit_title:t().report_title),body=box.querySelector('[data-body]');let page=1,data,controller;
    body.innerHTML=`<p>${esc(audit?t().audit_hint:t().report_hint)}</p><div class="media-library-toolbar-row"><select data-outcome aria-label="${esc(t().report_outcome)}"><option value="">${esc(t().report_all)}</option>${['added','merged','duplicate','linked','ambiguous','unmatched','unsupported','failed','available','missing'].map(k=>`<option value="${k}">${esc(t()['outcome_'+k]||k)}</option>`).join('')}</select><button type="button" class="desktop-button" data-refresh>${esc(t().refresh)}</button>${audit?`<button type="button" class="desktop-button is-primary" data-play-all disabled>${esc(t().audit_browser_all)}</button><button type="button" class="desktop-button" data-stop disabled>${esc(t().audit_stop)}</button>`:''}</div><p data-summary role="status"></p><div data-player></div><ol data-items></ol><div class="media-library-pagination" data-pages></div>`;
    const message=body.querySelector('[data-summary]'),items=body.querySelector('[data-items]'),player=body.querySelector('[data-player]');
    const load=async()=>{
      data=await api(url+'?'+new URLSearchParams({page,outcome:body.querySelector('[data-outcome]').value}));if(!box.isConnected)return;
      message.textContent=(t()['run_'+data.status]||data.status)+' · '+Object.entries(data.summary).map(([key,count])=>`${t()['outcome_'+key]||key}: ${count}`).join(' · ');
      items.innerHTML=data.data.map(item=>`<li><strong>${esc(item.label)}</strong><p>${esc(t()['outcome_'+item.outcome]||item.outcome)}${item.metadata?.browser_status?' · '+esc(t()['browser_'+item.metadata.browser_status]||item.metadata.browser_status):''}</p>${item.metadata?.reason||item.metadata?.error?`<p>${esc(item.metadata.reason||item.metadata.error)}</p>`:''}${item.metadata?.browser_reason?`<p>${esc(item.metadata.browser_reason)}</p>`:''}${item.metadata?.candidate_ids?.length?`<p>${esc(t().report_candidates)}: ${esc(item.metadata.candidate_ids.join(', '))}</p>`:''}${item.metadata?.technical?`<small>${esc(JSON.stringify(item.metadata.technical))}</small>`:''}${item.metadata?.preview_url?`<button type="button" class="desktop-button" data-play="${item.id}">${esc(t().audit_preview)}</button>`:''}${item.browser_url?`<button type="button" class="desktop-button" data-check="${item.id}">${esc(t().audit_browser_one)}</button>`:''}${item.open_url?`<button type="button" class="desktop-button" data-inspect="${item.id}">${esc(t().open_content)}</button>`:''}${item.outcome==='failed'?`<button type="button" class="desktop-button" data-retry="${item.id}">${esc(t().retry)}</button>`:''}</li>`).join('');
      items.querySelectorAll('li').forEach((row,index)=>{
        const m=data.data[index].metadata;if(m?.manifest_available===undefined)return;
        const info=document.createElement('p');info.textContent=`${t().takeout_expected_files}: ${m.manifest_available?m.expected_files:'—'} · ${t().takeout_available_files}: ${m.available_files}${m.manifest_available?` · ${t().takeout_missing_files}: ${m.missing_files} · ${t().takeout_extra_files}: ${m.extra_files}`:''}`;row.append(info);
      });
      body.querySelector('[data-pages]').innerHTML=`<button type="button" class="desktop-button" data-page="${page-1}" ${page<=1?'disabled':''}>‹</button><span>${page}/${data.meta.last_page}</span><button type="button" class="desktop-button" data-page="${page+1}" ${page>=data.meta.last_page?'disabled':''}>›</button>`;
      if(audit && !controller)body.querySelector('[data-play-all]').disabled=['queued','running','stop_requested'].includes(data.status);
    };
    const check=async item=>{
      let status='playable',reason='';try{await playCheck(item.metadata.preview_url,controller.signal,player);}catch(e){if(controller.signal.aborted)throw e;status='failed';reason=e.message;}
      await post(item.browser_url,{status,reason});return status;
    };
    body.addEventListener('click',async event=>{
      const button=event.target.closest('button');if(!button)return;
      try {
        if(button.hasAttribute('data-refresh'))return await load();
        if(button.dataset.page){page=Number(button.dataset.page);return await load();}
        if(button.hasAttribute('data-stop'))return controller?.abort();
        const item=data.data.find(item=>String(item.id)===(button.dataset.play||button.dataset.check||button.dataset.inspect||button.dataset.retry));
        if(button.dataset.play){player.innerHTML=`<video class="media-library-preview video" controls preload="metadata" src="${esc(item.metadata.preview_url)}"></video>`;return;}
        if(button.dataset.inspect){const detail=await api(item.open_url);player.innerHTML=`<h3>${esc(detail.title||item.label)}</h3><p class="content-original-text">${esc(detail.body||detail.summary||'')}</p>${(detail.assets||[]).map(asset=>asset.preview_url?asset.kind==='image'?`<img class="media-library-preview image" src="${esc(asset.preview_url)}" alt="">`:asset.kind==='video'?`<video controls class="media-library-preview video" src="${esc(asset.preview_url)}"></video>`:'':'').join('')}`;return;}
        if(button.dataset.retry){await post(url.replace(/\/report$/,'')+'/items/'+item.id+'/retry');return await load();}
        if(button.hasAttribute('data-play-all')||button.dataset.check) {
          if(controller)return;controller=new AbortController();body.querySelector('[data-stop]').disabled=false;body.querySelector('[data-play-all]').disabled=true;
          try {
            let all=[];
            if(item)all=[item];else {let n=1;do{const batch=await api(url+'?'+new URLSearchParams({page:n,type:'video-check'}));all.push(...batch.data.filter(x=>x.metadata?.preview_url && x.outcome!=='missing'));if(n>=batch.meta.last_page)break;n++;}while(!controller.signal.aborted);}
            let passed=0;for(let n=0;n<all.length;n++){if(controller.signal.aborted)break;message.textContent=`${n+1}/${all.length}: ${all[n].label}`;if(await check(all[n])==='playable')passed++;}
            await load();message.textContent+=` · ${t().audit_browser_result}: ${passed}/${all.length}`;
          }finally{controller=null;if(box.isConnected){body.querySelector('[data-stop]').disabled=true;body.querySelector('[data-play-all]').disabled=false;}}
        }
      }catch(e){message.textContent=e.message;}
    });
    body.querySelector('[data-outcome]').onchange=()=>{page=1;load().catch(e=>message.textContent=e.message);};
    const timer=setInterval(()=>{if(!box.isConnected){clearInterval(timer);return;}if(!controller && ['queued','running','stop_requested'].includes(data?.status))load().catch(e=>message.textContent=e.message);},3000);
    box.addEventListener('close',()=>{controller?.abort();clearInterval(timer);});
    try{await load();}catch(e){message.textContent=e.message;}
  };
  document.addEventListener('click',async event=>{
    const button=event.target.closest('[data-local-video-check],[data-import-report],[data-takeout-refresh],[data-catalog-reset]');if(!button)return;
    try {
      if(button.hasAttribute('data-takeout-refresh'))return await window.loadTakeoutInventory(button.closest('[data-import-center]'));
      if(button.dataset.importReport)return await window.showImportReport(button.dataset.importReport,button.dataset.audit==='true');
      if(button.hasAttribute('data-local-video-check')){button.disabled=true;const run=await post('/desktop/imports/video-check');await window.showImportReport(run.report_url,true);button.disabled=false;return;}
      const preview=await api('/desktop/imports/catalog-reset'),box=dialog(t().reset_start),body=box.querySelector('[data-body]');
      body.innerHTML=`<p>${esc(t().reset_hint)}</p><p>${preview.records} ${esc(t().records)} · ${preview.playlists} ${esc(t().kind_playlist)}</p><button type="button" class="desktop-button" data-confirm-reset>${esc(t().reset_confirm)}</button><p role="status"></p>`;
      const addRestore=id=>{
        const undo=document.createElement('button');undo.type='button';undo.className='desktop-button';undo.textContent=t().reset_restore;body.append(undo);
        undo.onclick=async()=>{undo.disabled=true;try{await post('/desktop/imports/catalog-restore',{reset_id:id});box.close();document.dispatchEvent(new Event('desktop-media-changed'));}catch(e){body.querySelector('[role="status"]').textContent=e.message;undo.disabled=false;}};
      };
      (preview.resets||[]).forEach(reset=>addRestore(reset.id));
      body.querySelector('[data-confirm-reset]').onclick=async e=>{
        if(!confirm(t().reset_confirm_question))return;e.target.disabled=true;
        try{const result=await post('/desktop/imports/catalog-reset',{confirmation:'RESET'});body.querySelector('[role="status"]').textContent=t().reset_done;document.dispatchEvent(new Event('desktop-media-changed'));
          addRestore(result.reset_id);
        }catch(error){body.querySelector('[role="status"]').textContent=error.message;e.target.disabled=false;}
      };
    }catch(e){button.disabled=false;const box=dialog(t().load_error);box.querySelector('[data-body]').textContent=e.message;}
  });
  // Add report buttons after Import Center refreshes its existing history, without duplicating its loader.
  document.addEventListener('import-runs-loaded',event=>event.detail.forEach(run=>{
    const root=event.target,article=root.querySelector(`[data-import-run="${run.id}"]`);if(!article)return;
    const button=document.createElement('button');button.type='button';button.className='desktop-button';button.dataset.importReport=run.report_url;button.dataset.audit=String(run.source==='local-video-check');button.textContent=t().report_title;article.querySelector('.import-run-actions').append(button);
  }));
})();
