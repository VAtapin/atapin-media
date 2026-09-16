for(const form of document.querySelectorAll('[data-ai-form]')){
  let controller;
  form.addEventListener('submit',async event=>{
    event.preventDefault();const button=form.querySelector('button'),answer=form.querySelector('[data-ai-answer]');button.disabled=true;controller=new AbortController();answer.textContent=window.publicLabels.ai_chat_wait;
    try{
      const response=await fetch(form.action,{method:'POST',headers:{Accept:'application/json'},body:new FormData(form),signal:controller.signal});
      const data=await response.json();if(!response.ok)throw new Error(data.message||window.publicLabels.ai_chat_error);
      answer.textContent=window.publicLabels.ai_chat_label+': '+data.answer;
    }catch(error){if(error.name!=='AbortError')answer.textContent=error.message;}
    finally{button.disabled=false;}
  });
  window.addEventListener('pagehide',()=>controller?.abort());
}
