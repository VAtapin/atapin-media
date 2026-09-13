for(const button of document.querySelectorAll('[data-push-url]')){
  const capable=isSecureContext&&'serviceWorker' in navigator&&'PushManager' in window&&'Notification' in window;
  const render=enabled=>{button.textContent=window.publicLabels[enabled?'push_cancel':'push_enable'];button.classList.toggle('current',enabled);};
  const post=async(subscription,mode)=>{
    const response=await fetch(button.dataset.pushUrl,{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},body:JSON.stringify({...subscription.toJSON(),mode})});
    if(!response.ok)throw new Error(window.publicLabels.push_unavailable);
    const data=await response.json();render(data.enabled);return data;
  };
  if(!capable){button.disabled=true;button.title=window.publicLabels.push_unsupported;continue;}
  navigator.serviceWorker.getRegistration('/').then(async registration=>{const subscription=await registration?.pushManager.getSubscription();if(subscription)await post(subscription,'status');}).catch(()=>{});
  button.addEventListener('click',async()=>{
    button.disabled=true;
    try{
      if(!button.dataset.pushKey)throw new Error(window.publicLabels.push_unavailable);
      if(await Notification.requestPermission()!=='granted')throw new Error(window.publicLabels.push_denied);
      await navigator.serviceWorker.register('/public-push-sw.js');const registration=await navigator.serviceWorker.ready;
      let subscription=await registration.pushManager.getSubscription();
      if(!subscription){
        const key=button.dataset.pushKey,base64=(key+'='.repeat((4-key.length%4)%4)).replace(/-/g,'+').replace(/_/g,'/');
        subscription=await registration.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:Uint8Array.from(atob(base64),char=>char.charCodeAt(0))});
      }
      const data=await post(subscription,'toggle');const feedback=document.querySelector('[data-public-feedback]');if(feedback){feedback.hidden=false;feedback.textContent=window.publicLabels[data.enabled?'push_enabled':'push_cancelled'];}
    }catch(error){const feedback=document.querySelector('[data-public-feedback]');if(feedback){feedback.hidden=false;feedback.textContent=error.message;}}
    finally{button.disabled=false;}
  });
}
