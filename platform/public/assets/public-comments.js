for(const root of document.querySelectorAll('[data-comments-url]')) {
  const list=root.querySelector('[data-comment-list]');
  let active=true,running=false,queued=false,page=new URL(location.href).searchParams.get('page')||'1',timer,controller;
  const pulse=async()=>{
    clearTimeout(timer);if(!active)return;if(running){queued=true;return;}if(document.hidden)return;
    running=true;controller=new AbortController();const timeout=setTimeout(()=>controller.abort(),8000);
    try{
      const response=await fetch(root.dataset.commentsUrl+'?page='+encodeURIComponent(page),{headers:{Accept:'application/json'},cache:'no-store',signal:controller.signal});
      if(response.ok){const data=await response.json();if(active){list.innerHTML=data.html;root.querySelector('[data-comment-count]').textContent=data.total;}}
    }catch{}finally{clearTimeout(timeout);running=false;}
    if(queued){queued=false;return pulse();}if(active)timer=setTimeout(pulse,15000);
  };
  list.addEventListener('click',event=>{const link=event.target.closest('.public-pagination a');if(!link)return;event.preventDefault();page=new URL(link.href).searchParams.get('page')||'1';pulse();});
  document.addEventListener('public-live-refresh',()=>{page='1';pulse();});
  document.addEventListener('visibilitychange',()=>{if(!document.hidden)pulse();});
  window.addEventListener('pagehide',()=>{active=false;clearTimeout(timer);controller?.abort();});
  window.addEventListener('pageshow',event=>{if(event.persisted){active=true;pulse();}});pulse();
}
for(const details of document.querySelectorAll('.public-full-description'))details.addEventListener('toggle',()=>{details.querySelector('summary').textContent=window.publicLabels[details.open?'show_less':'show_more'];});
