const publicMenu = document.querySelector('.public-menu-button');
publicMenu?.addEventListener('click', () => {
  const opened = publicMenu.getAttribute('aria-expanded') !== 'true';
  publicMenu.setAttribute('aria-expanded', String(opened));
  document.querySelector('.public-navigation')?.classList.toggle('opened', opened);
});
document.addEventListener('keydown',event=>{
  if(event.key==='Escape'){publicMenu?.setAttribute('aria-expanded','false');document.querySelector('.public-navigation')?.classList.remove('opened');}
});
const publicFeedback=message=>{
  const node=document.querySelector('[data-public-feedback]');if(node){node.hidden=false;node.textContent=message;}
};
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
for(const form of document.querySelectorAll('[data-public-form]'))form.addEventListener('submit',async event=>{
  event.preventDefault();const button=form.querySelector('button');button.disabled=true;
  const data=Object.fromEntries(new FormData(form));if('enabled' in data)data.enabled=data.enabled==='1';
  try{
    const response=await fetch(form.action,{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},body:JSON.stringify(data)});
    if(response.status===401){location.href='/login';return;}
    const result=await response.json();if(!response.ok)throw new Error(result.message);location.reload();
  }catch(error){publicFeedback(error.message);}finally{button.disabled=false;}
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
for(const shell of document.querySelectorAll('[data-live-player]')){
  const frame=shell.querySelector('iframe'),fallback=shell.querySelector('[data-live-player-fallback]'),url=shell.dataset.hlsUrl;
  if(!frame||!fallback||!url)continue;
  let failures=0;
  const check=async()=>{
    try{
      const response=await fetch(url,{cache:'no-store',credentials:'same-origin'}),text=await response.text();
      const ready=response.ok&&text.includes('#EXTM3U')&&(text.includes('#EXTINF')||text.includes('#EXT-X-STREAM-INF'));
      failures=ready?0:failures+1;
      if(failures>=2){frame.hidden=true;fallback.hidden=false;}
      else if(ready){frame.hidden=false;fallback.hidden=true;}
    }catch{failures++;if(failures>=2){frame.hidden=true;fallback.hidden=false;}}
  };
  check();setInterval(check,10000);
}
window.addEventListener('pagehide',()=>{if('speechSynthesis' in window)speechSynthesis.cancel();});
for(const button of document.querySelectorAll('[data-public-help]')){
  const dialog=document.getElementById(button.dataset.publicHelp);
  button.addEventListener('click',()=>{if(dialog&&!dialog.open)dialog.showModal();});
}
for(const root of document.querySelectorAll('[data-live-heartbeat]')){
  let timer,controller,active=true,signature='',running=false;
  const pulse=async()=>{
    clearTimeout(timer);if(!active||running)return;
    if(!document.hidden){
      running=true;controller=new AbortController();const current=controller,timeout=setTimeout(()=>current.abort(),5000);
      try{
        const response=await fetch(root.dataset.liveHeartbeat,{method:'POST',signal:controller.signal,headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content}});
        if(response.status===404){active=false;publicFeedback(window.publicLabels.live_unavailable);return;}
        if(!response.ok)throw new Error(String(response.status));
        const data=await response.json();root.querySelector('[data-live-online]').textContent=String(data.online);
        const next=JSON.stringify(data.chat);
        if(next!==signature){
          signature=next;const messages=root.querySelector('.public-chat-messages'),bottom=messages.scrollHeight-messages.scrollTop-messages.clientHeight<50;
          if(data.chat.length){messages.replaceChildren(...data.chat.map(message=>{
            const article=document.createElement('article'),avatar=document.createElement('span'),body=document.createElement('div'),author=document.createElement('strong'),time=document.createElement('small'),text=document.createElement('p');
            avatar.className='public-avatar';avatar.textContent=message.author.slice(0,1)||'◇';author.textContent=message.author;time.textContent=message.time;text.textContent=message.body;body.append(author,time,text);article.append(avatar,body);return article;
          }));if(bottom)messages.scrollTop=messages.scrollHeight;}
          else{const empty=document.createElement('p');empty.className='public-empty';empty.textContent='◇ '+window.publicLabels.no_data;messages.replaceChildren(empty);}
        }
      }catch{root.querySelector('[data-live-online]').textContent=window.publicLabels.live_connection_pending;}
      finally{clearTimeout(timeout);running=false;}
    }
    if(active)timer=setTimeout(pulse,15000);
  };
  document.addEventListener('visibilitychange',()=>{if(!document.hidden)pulse();});
  window.addEventListener('pagehide',()=>{active=false;clearTimeout(timer);controller?.abort();});
  window.addEventListener('pageshow',event=>{if(event.persisted){active=true;pulse();}});
  pulse();
}
