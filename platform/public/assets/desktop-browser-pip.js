const FRAME_WIDTH=1280,FRAME_HEIGHT=720,ASPECT=294/178;
const clamp=(value,min,max)=>Math.min(max,Math.max(min,value));

export const createInset=()=>({x:966,y:522,w:294,h:178});

export function mountInsetEditor(surface,box,{label,visible,onChange}){
  const doc=surface.ownerDocument;
  const handle=doc.createElement('div');handle.className='desktop-browser-pip-handle';handle.dataset.pipHandle='';
  handle.tabIndex=0;handle.setAttribute('aria-label',label);
  const resize=doc.createElement('span');resize.dataset.pipResize='';resize.setAttribute('aria-hidden','true');handle.append(resize);surface.append(handle);
  const maxWidth=()=>Math.min(640,FRAME_WIDTH-box.x,(FRAME_HEIGHT-box.y)*ASPECT);
  const setSize=width=>{box.w=Math.round(clamp(width,160,maxWidth()));box.h=Math.round(box.w/ASPECT);};
  const update=()=>{
    handle.hidden=!visible();
    handle.style.left=`${box.x/FRAME_WIDTH*100}%`;handle.style.top=`${box.y/FRAME_HEIGHT*100}%`;
    handle.style.width=`${box.w/FRAME_WIDTH*100}%`;handle.style.height=`${box.h/FRAME_HEIGHT*100}%`;
  };
  let gesture=null;
  handle.addEventListener('pointerdown',event=>{
    if(!visible())return;
    const rect=surface.getBoundingClientRect();
    gesture={mode:event.target===resize?'resize':'move',pointerId:event.pointerId,x:event.clientX,y:event.clientY,box:{...box},width:rect.width,height:rect.height};
    handle.setPointerCapture(event.pointerId);event.preventDefault();
  });
  handle.addEventListener('pointermove',event=>{
    if(!gesture||event.pointerId!==gesture.pointerId)return;
    const dx=(event.clientX-gesture.x)*FRAME_WIDTH/gesture.width,dy=(event.clientY-gesture.y)*FRAME_HEIGHT/gesture.height;
    if(gesture.mode==='move'){
      box.x=Math.round(clamp(gesture.box.x+dx,0,FRAME_WIDTH-box.w));
      box.y=Math.round(clamp(gesture.box.y+dy,0,FRAME_HEIGHT-box.h));
    }else{
      const delta=Math.abs(dx)>=Math.abs(dy*ASPECT)?dx:dy*ASPECT;
      setSize(gesture.box.w+delta);
    }
    onChange();
  });
  const end=event=>{if(gesture?.pointerId===event.pointerId)gesture=null;};
  handle.addEventListener('pointerup',end);handle.addEventListener('pointercancel',end);
  handle.addEventListener('keydown',event=>{
    if(!['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(event.key))return;
    if(event.shiftKey)setSize(box.w+(event.key==='ArrowRight'||event.key==='ArrowUp'?16:-16));
    else{
      const step=event.ctrlKey?32:8;
      if(event.key==='ArrowLeft')box.x=clamp(box.x-step,0,FRAME_WIDTH-box.w);
      if(event.key==='ArrowRight')box.x=clamp(box.x+step,0,FRAME_WIDTH-box.w);
      if(event.key==='ArrowUp')box.y=clamp(box.y-step,0,FRAME_HEIGHT-box.h);
      if(event.key==='ArrowDown')box.y=clamp(box.y+step,0,FRAME_HEIGHT-box.h);
    }
    event.preventDefault();onChange();
  });
  update();return {node:handle,update};
}
