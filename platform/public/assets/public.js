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
window.addEventListener('pagehide',()=>{if('speechSynthesis' in window)speechSynthesis.cancel();});
