const publicMenu = document.querySelector('.public-menu-button');
publicMenu?.addEventListener('click', () => {
  const opened = publicMenu.getAttribute('aria-expanded') !== 'true';
  publicMenu.setAttribute('aria-expanded', String(opened));
  document.querySelector('.public-navigation')?.classList.toggle('opened', opened);
});
const publicImageLightbox=document.querySelector('[data-image-lightbox-dialog]');
if(publicImageLightbox){
  const image=document.querySelector('[data-image-lightbox-image]'),caption=document.querySelector('[data-image-lightbox-caption]'),close=document.querySelector('[data-image-lightbox-close]'),previous=document.querySelector('[data-image-lightbox-prev]'),next=document.querySelector('[data-image-lightbox-next]');
  let triggers=[],index=0,returnFocus=null;
  const render=()=>{
    const trigger=triggers[index];if(!trigger||!image)return;
    image.src=trigger.dataset.imageLightboxSrc;image.alt=trigger.dataset.imageLightboxAlt||'';
    if(caption){caption.textContent=trigger.dataset.imageLightboxAlt||'';caption.hidden=!caption.textContent;}
    if(previous)previous.disabled=triggers.length<2;
    if(next)next.disabled=triggers.length<2;
  };
  const closeLightbox=()=>{if(publicImageLightbox.open)publicImageLightbox.close();returnFocus?.focus();};
  const openLightbox=trigger=>{triggers=[...document.querySelectorAll('[data-image-lightbox]')].filter(node=>node.dataset.imageLightboxGroup===trigger.dataset.imageLightboxGroup);index=Math.max(0,triggers.indexOf(trigger));returnFocus=document.activeElement;render();publicImageLightbox.showModal();close?.focus();};
  document.querySelectorAll('[data-image-lightbox]').forEach(trigger=>trigger.addEventListener('click',()=>openLightbox(trigger)));
  close?.addEventListener('click',closeLightbox);
  previous?.addEventListener('click',()=>{if(triggers.length>1){index=(index+triggers.length-1)%triggers.length;render();}});
  next?.addEventListener('click',()=>{if(triggers.length>1){index=(index+1)%triggers.length;render();}});
  publicImageLightbox.addEventListener('click',event=>{if(event.target===publicImageLightbox)closeLightbox();});
  publicImageLightbox.addEventListener('cancel',event=>{event.preventDefault();closeLightbox();});
  publicImageLightbox.addEventListener('close',()=>{if(image)image.removeAttribute('src');});
  document.addEventListener('keydown',event=>{if(!publicImageLightbox.open)return;if(event.key==='ArrowLeft'&&triggers.length>1){event.preventDefault();previous?.click();}if(event.key==='ArrowRight'&&triggers.length>1){event.preventDefault();next?.click();}});
}
document.addEventListener('keydown',event=>{
  if(event.key==='Escape'){publicMenu?.setAttribute('aria-expanded','false');document.querySelector('.public-navigation')?.classList.remove('opened');}
});
let publicFeedbackTimer;
const hidePublicFeedback=node=>{if(node){node.hidden=true;node.setAttribute('aria-hidden','true');}};
const publicFeedback=message=>{
  const node=document.querySelector('[data-public-feedback]');if(node){node.hidden=false;node.removeAttribute('aria-hidden');node.textContent=message;clearTimeout(publicFeedbackTimer);publicFeedbackTimer=setTimeout(()=>hidePublicFeedback(node),2500);}
};
for(const node of document.querySelectorAll('[data-auto-dismiss]')){
  const timer=setTimeout(()=>hidePublicFeedback(node),2500);
  node.querySelector('[data-dismiss-feedback]')?.addEventListener('click',()=>{clearTimeout(timer);hidePublicFeedback(node);});
}
for(const set of document.querySelectorAll('[data-public-tabs]')){
  const tabs=[...set.querySelectorAll('[role=tab]')];
  const activate=tab=>{
    for(const button of tabs){const selected=button===tab;button.setAttribute('aria-selected',String(selected));button.tabIndex=selected?0:-1;document.getElementById(button.getAttribute('aria-controls')).hidden=!selected;}
  };
  for(const tab of tabs){
    tab.addEventListener('click',()=>activate(tab));
    tab.addEventListener('keydown',event=>{
      const index=tabs.indexOf(tab);let next;
      if(event.key==='ArrowRight')next=tabs[(index+1)%tabs.length];
      if(event.key==='ArrowLeft')next=tabs[(index+tabs.length-1)%tabs.length];
      if(event.key==='Home')next=tabs[0];if(event.key==='End')next=tabs.at(-1);
      if(next){event.preventDefault();activate(next);next.focus();}
    });
  }
  if(location.hash==='#comments'){const tab=tabs.find(tab=>tab.getAttribute('aria-controls')==='panel-comments');if(tab)activate(tab);}
}
document.querySelectorAll('[data-submit-select]').forEach(select=>select.addEventListener('change',()=>select.form.requestSubmit()));
document.querySelectorAll('[data-share]').forEach(button=>button.addEventListener('click',async()=>{
  try{await navigator.clipboard.writeText(location.href);publicFeedback(window.publicLabels.share_copied);}catch{publicFeedback(window.publicLabels.share_failed);}
}));
document.querySelectorAll('[data-read-aloud]').forEach(button=>button.addEventListener('click',()=>{
  if(!('speechSynthesis' in window)){publicFeedback(window.publicLabels.speech_unavailable);return;}
  if(speechSynthesis.speaking){speechSynthesis.cancel();return;}
  const speech=new SpeechSynthesisUtterance(document.querySelector('[data-read-text]')?.textContent||'');speech.lang=document.documentElement.lang;speechSynthesis.speak(speech);
}));
for(const player of document.querySelectorAll('[data-view-url]')){
  let sent=false,inFlight=false;
  player.addEventListener('play',async()=>{
    if(sent||inFlight)return;inFlight=true;
    try{const response=await fetch(player.dataset.viewUrl,{method:'POST',keepalive:true,headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content}});if(response.ok)sent=true;}catch{}finally{inFlight=false;}
  });
}
document.querySelectorAll('[data-seek]').forEach(button=>button.addEventListener('click',()=>{
  const player=document.querySelector('.public-main-player');if(player&&player.readyState>0)player.currentTime=Math.max(0,Math.min(Number(button.dataset.seek),player.duration));
  else publicFeedback(window.publicLabels.no_local_playback);
}));
const publicCsrf=()=>document.querySelector('meta[name=csrf-token]')?.content||'';
const publicFormMessage=(form,message)=>{
  const node=form.querySelector('[data-public-form-message]');
  if(!node)return;
  node.textContent=message;node.hidden=false;
};
const publicFormError=(result,response)=>{
  if(result?.message)return result.message;
  if(result?.errors)return Object.values(result.errors).flat()[0];
  return `Request failed (${response.status})`;
};
const publicResetKinds=new Set(['newsletter','community','contact','review']);
for(const form of document.querySelectorAll('[data-public-form],[data-public-ajax]'))form.addEventListener('submit',async event=>{
  event.preventDefault();
  const button=form.querySelector('button[type="submit"],button:not([type])');
  if(button?.disabled)return;
  if(button)button.disabled=true;
  try{
    const response=await fetch(form.getAttribute('action'),{method:(form.getAttribute('method')||'POST').toUpperCase(),credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':publicCsrf()},body:new FormData(form)});
    if(response.status===401){location.href='/login';return;}
    let result={};try{result=await response.json();}catch{}
    if(!response.ok)throw new Error(publicFormError(result,response));
    if(form.dataset.publicAjax==='message'){
      form.reset();document.dispatchEvent(new Event('public-live-refresh'));publicFormMessage(form,result.message||window.publicLabels.message_sent);
    }else if(form.dataset.publicForm!==undefined&&result.kind==='state'){
      let status=form.querySelector('[data-public-form-message]');if(!status){status=document.createElement('small');status.dataset.publicFormMessage='';status.setAttribute('role','status');form.append(status);}publicFormMessage(form,result.message||window.publicLabels.saved);
      if(result.action==='vote'){
        form.classList.add('is-submitted');for(const input of form.querySelectorAll('input[type=radio],input[type=checkbox]'))input.disabled=true;
        if(button){button.textContent=result.message||window.publicLabels.saved;button.disabled=true;}
        window.setTimeout(()=>window.location.reload(),500);
      }else if(typeof result.enabled==='boolean'){
        button?.classList.toggle('current',result.enabled);
        button?.setAttribute('aria-pressed',String(result.enabled));
        const input=form.querySelector('[name=enabled]');if(input)input.value=result.enabled?'0':'1';
      }
    }
    if(form.dataset.publicAjax==='account-remove')form.closest('.public-account-item')?.remove();
    if(publicResetKinds.has(form.dataset.publicAjax))form.reset();
    if(form.dataset.publicAjax!=='message'&&form.dataset.publicForm===undefined)publicFeedback(result.message||window.publicLabels.saved);
  }catch(error){
    if(form.dataset.publicAjax==='message'||form.dataset.publicForm!==undefined){let status=form.querySelector('[data-public-form-message]');if(!status){status=document.createElement('small');status.dataset.publicFormMessage='';status.setAttribute('role','status');form.append(status);}publicFormMessage(form,error.message);}else publicFeedback(error.message);
  }finally{if(button&&!form.classList.contains('is-submitted'))button.disabled=false;}
});
for(const player of document.querySelectorAll('[data-progress-url]')){
  player.addEventListener('loadedmetadata',()=>{const position=Number(player.dataset.resume);if(position>0&&position<player.duration)player.currentTime=position;},{once:true});
  let last=0;
  const save=async()=>{
    if(Date.now()-last<30000||!Number.isFinite(player.currentTime))return;last=Date.now();
    try{await fetch(player.dataset.progressUrl,{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},body:JSON.stringify({action:'progress',position:Math.floor(player.currentTime)})});}catch{}
  };
  player.addEventListener('pause',save);player.addEventListener('timeupdate',()=>{if(player.currentTime>0)save();});
}
for(const badge of document.querySelectorAll('[data-current-live]')){
  let active=true,timer,controller;
  const check=async()=>{
    if(!active)return;
    if(!document.hidden){
      controller=new AbortController();const timeout=setTimeout(()=>controller.abort(),5000);
      try{const response=await fetch(badge.dataset.currentLive,{cache:'no-store',signal:controller.signal,headers:{Accept:'application/json'}});
        if(response.ok){const data=await response.json();if(typeof data.live==='boolean')badge.hidden=!data.live;}
      }catch{}finally{clearTimeout(timeout);}
    }
    if(active)timer=setTimeout(check,30000);
  };
  window.addEventListener('pagehide',()=>{active=false;clearTimeout(timer);controller?.abort();});
  window.addEventListener('pageshow',event=>{if(event.persisted){active=true;check();}});
  check();
}
for(const shell of document.querySelectorAll('[data-live-player]')){
  const frame=shell.querySelector('iframe'),fallback=shell.querySelector('[data-live-player-fallback]'),url=shell.dataset.hlsUrl,frameSrc=frame?.dataset.src||frame?.getAttribute('src');
  if(!frame||!fallback||!url)continue;
  let active=true,timer,failures=0;
  const showFallback=()=>{
    frame.hidden=true;
    if(frame.getAttribute('src')){frame.removeAttribute('src');}
    fallback.hidden=false;
  };
  const stop=()=>{active=false;clearTimeout(timer);showFallback();};
  const check=async()=>{
    if(!active)return;
    try{
      const response=await fetch(url,{cache:'no-store',credentials:'same-origin'}),text=await response.text();
      if(!active)return;
      const ready=response.ok&&text.includes('#EXTM3U')&&(text.includes('#EXTINF')||text.includes('#EXT-X-STREAM-INF'));
      failures=ready?0:failures+1;
      if(ready){
        if(frameSrc&&!frame.getAttribute('src'))frame.src=frameSrc;
        frame.hidden=false;fallback.hidden=true;
      }else if(response.status===401)stop();
      else if(failures>=2)showFallback();
    }catch{failures++;if(failures>=2)showFallback();}
    if(active)timer=setTimeout(check,5000);
  };
  document.addEventListener('public-live-status',event=>{if(event.detail?.status==='ended')stop();});
  check();
  window.addEventListener('pagehide',()=>{active=false;clearTimeout(timer);});
}
window.addEventListener('pagehide',()=>{if('speechSynthesis' in window)speechSynthesis.cancel();});
for(const button of document.querySelectorAll('[data-public-help]')){
  const dialog=document.getElementById(button.dataset.publicHelp);
  button.addEventListener('click',()=>{if(dialog&&!dialog.open)dialog.showModal();});
}
for(const root of document.querySelectorAll('[data-live-heartbeat]')){
  let timer,controller,active=true,signature='',running=false,queued=false,lastStatus=null;
  const pulse=async()=>{
    clearTimeout(timer);if(!active)return;if(running){queued=true;return;}
    if(!document.hidden){
      running=true;controller=new AbortController();const current=controller,timeout=setTimeout(()=>current.abort(),5000);
      try{
        const response=await fetch(root.dataset.liveHeartbeat,{method:'POST',signal:controller.signal,headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content}});
        if(response.status===404){active=false;publicFeedback(window.publicLabels.live_unavailable);return;}
        if(!response.ok)throw new Error(String(response.status));
        const data=await response.json();const online=root.querySelector('[data-live-online]');if(online)online.textContent=String(data.online);
        if(data.status&&data.status!==lastStatus){
          lastStatus=data.status;
          const label=window.publicLabels[`live_${data.status}`];
          for(const statusLine of document.querySelectorAll('[data-live-status-line]')){
            const statusLabel=statusLine.querySelector('[data-live-status-label]'),statusDot=statusLine.querySelector('[data-live-status-dot]');
            if(statusLabel&&label)statusLabel.textContent=label;
            statusLine.dataset.status=data.status;
            if(statusDot)statusDot.className=`public-live-status-dot status-${data.status}`;
          }
          document.dispatchEvent(new CustomEvent('public-live-status',{detail:{status:data.status}}));
        }
        if(data.status==='ended'){active=false;clearTimeout(timer);}
        const next=JSON.stringify(data.chat);
        const messages=root.querySelector('.public-chat-messages');
        if(next!==signature&&messages){
          signature=next;const bottom=messages.scrollHeight-messages.scrollTop-messages.clientHeight<50;
          if(data.chat.length){messages.replaceChildren(...data.chat.map(message=>{
            const article=document.createElement('article'),avatar=document.createElement('span'),body=document.createElement('div'),author=document.createElement('strong'),time=document.createElement('small'),text=document.createElement('p');
            article.className=message.blocked?'public-chat-message is-blocked':message.pending?'public-chat-message is-pending':'public-chat-message';
            avatar.className='public-avatar';avatar.textContent=message.author.slice(0,1)||'◇';author.textContent=message.author;time.textContent=message.time;text.textContent=message.body;body.append(author,time,text);if(message.blocked||message.pending){const status=document.createElement('span');status.className=message.blocked?'public-chat-status public-chat-status-blocked':'public-chat-status';status.textContent=message.blocked?window.publicLabels.chat_blocked:window.publicLabels.chat_moderation_pending;body.append(status);}article.append(avatar,body);return article;
          }));if(bottom)messages.scrollTop=messages.scrollHeight;}
          else{const empty=document.createElement('p');empty.className='public-empty';empty.textContent='◇ '+window.publicLabels.no_data;messages.replaceChildren(empty);}
        }
      }catch{const online=root.querySelector('[data-live-online]');if(online)online.textContent=window.publicLabels.live_connection_pending;}
      finally{clearTimeout(timeout);running=false;if(queued){queued=false;return pulse();}}
    }
    if(active)timer=setTimeout(pulse,15000);
  };
  document.addEventListener('visibilitychange',()=>{if(!document.hidden)pulse();});
  document.addEventListener('public-live-refresh',pulse);
  window.addEventListener('pagehide',()=>{active=false;clearTimeout(timer);controller?.abort();});
  window.addEventListener('pageshow',event=>{if(event.persisted){active=true;pulse();}});
  pulse();
}
