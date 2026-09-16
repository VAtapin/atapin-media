import {createInset,mountInsetEditor} from '/assets/desktop-browser-pip.js?v=1';
import {mountBrowserEvents} from '/assets/desktop-browser-events.js?v=1';
const W=window.DesktopWorkspaces;
const endpoint='/desktop/live-studio';
const studios=new WeakMap();
const css=document.createElement('link');css.rel='stylesheet';css.href='/assets/desktop-browser-studio.css?v=8';document.head.append(css);
const request=async(url,options={})=>{
  const response=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},...options});
  const data=response.status===204?{}:await response.json().catch(()=>({}));
  if(!response.ok){const error=new Error(data.message||'Live studio request failed');error.status=response.status;error.retryAfter=Number(response.headers.get('Retry-After'))||null;throw error;}return data;
};
export const busy=root=>Boolean(studios.get(root)?.busy());
export async function initialize(root,event,configured=false){
  const old=studios.get(root);if(old?.busy())return;old?.dispose();
  const obsEvent=event;
  event={}; // Browser input is chosen here, independently of the editor's initially opened OBS event.
  const panel=W.el('section',undefined,'desktop-browser-studio desktop-publishing-card');panel.dataset.browserStudio='';
  const tabs=W.el('nav',undefined,'desktop-browser-tabs'),grid=root.querySelector('.desktop-live-studio-grid');grid.before(tabs,panel);
  let disposed=false,labels={},pc=null,session=null,context=null,mic=null,camera=null,screen=null,canvasStream=null,audioDestination=null;
  let micGain=null,screenGain=null,recorder=null,recording=false,pcm=[],pcmBytes=0,wav=null,drawTimer=null,beatTimer=null,preparing=false,uploadedMediaId=null,statsTimer=null,leaseUntil=0;
  let scene='camera',pip=false,imageIndex=0,images=[],imageUrls=[],sources=[],observer=null,operation=0,creating=false;
  let previewWindow=null,previewWindowStream=null,previewPanel=null,previewLayout=null,previewButton=null,previewHead=null,previewHeading=null,eventPicker=null;
  const inset=createInset();let inlineInsetEditor=null,popupInsetEditor=null,popupInsetHint=null;
  const syncInsetEditors=()=>{inlineInsetEditor?.update();popupInsetEditor?.update();if(popupInsetHint)popupInsetHint.hidden=!pip||scene==='camera';};
  const t=key=>labels[key]||key;
  const detachPreview=detached=>{eventPicker?.place(detached);if(previewPanel)previewPanel.hidden=detached;previewLayout?.classList.toggle('is-detached',detached);if(previewButton){previewButton.textContent=t(detached?'show_preview':'open_preview');(detached?previewHeading:previewHead)?.append(previewButton);}};
  const closePreviewWindow=()=>{previewWindowStream?.getTracks().forEach(track=>track.stop());previewWindowStream=null;if(previewWindow&&!previewWindow.closed)previewWindow.close();previewWindow=null;popupInsetEditor=null;popupInsetHint=null;detachPreview(false);};
  const status=W.el('p');status.setAttribute('role','status');
  const video=()=>{const node=document.createElement('video');node.muted=true;node.playsInline=true;node.autoplay=true;return node;};
  const cameraVideo=video(),screenVideo=video();
  const canvas=document.createElement('canvas');canvas.width=1280;canvas.height=720;canvas.dataset.studioCanvas='';
  const ctx=canvas.getContext('2d');
  const controls=W.el('div',undefined,'desktop-browser-controls'),leftControls=W.el('div',undefined,'desktop-browser-control-column'),rightControls=W.el('div',undefined,'desktop-browser-control-column');controls.append(leftControls,rightControls);
  let fieldGroup=controls;
  const group=(key,name,column)=>{const section=W.el('fieldset',undefined,'desktop-browser-group '+name);section.append(W.el('legend',t(key)));column.append(section);return section;};
  const actionRow=(section,...buttons)=>{const row=W.el('div',undefined,'desktop-browser-actions');row.append(...buttons);section.append(row);return row;};
  const action=(key,fn)=>{const b=W.el('button',t(key),'desktop-button');b.type='button';b.dataset.studioAction=key;
    b.onclick=async()=>{b.disabled=true;try{await fn();}catch(error){status.textContent=error.message||t('error');}finally{if(!disposed)b.disabled=false;}};return b;};
  const input=(key,type='text')=>{const label=W.el('label',t(key)),field=document.createElement('input');field.type=type;field.dataset.studioField=key;label.append(field);fieldGroup.append(label);return field;};
  const select=key=>{const label=W.el('label',t(key)),field=document.createElement('select');field.dataset.studioField=key;label.append(field);fieldGroup.append(label);return field;};
  const state={busy:()=>Boolean(pc||session||recording||preparing||creating),dispose:()=>{
    if(disposed)return;disposed=true;clearInterval(drawTimer);clearInterval(beatTimer);clearInterval(statsTimer);observer?.disconnect();window.removeEventListener('beforeunload',unload);
    if(session)fetch(endpoint+'/sessions/'+session,{method:'DELETE',credentials:'same-origin',keepalive:true,headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content}}).catch(()=>{});
    pc?.close();canvasStream?.getTracks().forEach(track=>track.stop());for(const stream of [mic,camera,screen])stream?.getTracks().forEach(track=>track.stop());
    recorder?.disconnect();sources.forEach(source=>source.disconnect());context?.close();imageUrls.forEach(url=>URL.revokeObjectURL(url));eventPicker?.dispose();closePreviewWindow();document.removeEventListener('click',guardClose,true);panel.remove();tabs.remove();grid.hidden=false;delete root.dataset.studioCapturing;
  }};studios.set(root,state);
  const unload=e=>{if(state.busy()){e.preventDefault();e.returnValue='';}};
  window.addEventListener('beforeunload',unload);
  const guardClose=e=>{if(state.busy()&&(e.target.closest('[data-close-all]')||e.target.closest('[data-window-action=close]')?.closest('.os-window')===root.closest('.os-window'))){e.preventDefault();e.stopImmediatePropagation();status.textContent=t('close_warning');}};
  document.addEventListener('click',guardClose,true);
  observer=new MutationObserver(()=>{if(!root.isConnected)state.dispose();});observer.observe(document.body,{childList:true,subtree:true});
  try{
    const server=await request(endpoint+'/server');if(disposed)return;labels=server.labels;
    const mode=value=>{root.dataset.studioMode=value;panel.hidden=value!=='browser';grid.hidden=value==='browser';if(value==='browser')for(const frame of root.querySelectorAll('[data-live-console] iframe')){frame.removeAttribute('src');frame.hidden=true;}else closePreviewWindow();for(const button of tabs.children)button.setAttribute('aria-pressed',String(button.dataset.mode===value));};
    for(const [value,label]of [['obs',t('obs_mode')],['browser',t('browser_mode')]]){const button=W.el('button',label,'desktop-button');button.type='button';button.dataset.mode=value;button.onclick=()=>mode(value);tabs.append(button);}mode(root.dataset.studioMode||'obs');
    const telemetry=W.el('p');telemetry.dataset.studioTelemetry='';
    const layout=W.el('div',undefined,'desktop-browser-layout'),preview=W.el('aside',undefined,'desktop-browser-preview');previewHead=W.el('div',undefined,'desktop-browser-preview-head');previewPanel=preview;previewLayout=layout;
    const openPreview=action('open_preview',()=>{
      if(previewWindow&&!previewWindow.closed){previewWindow.focus();return;}
      const popup=window.open('','_blank','popup,width=720,height=470,resizable=yes');
      if(!popup)throw new Error(t('popup_blocked'));
      previewWindow=popup;
      try{
        popup.document.title=t('preview')+' · '+(event.title||t('quick_new'));popup.document.documentElement.lang=document.documentElement.lang||'de';
        const viewport=popup.document.createElement('meta');viewport.name='viewport';viewport.content='width=device-width,initial-scale=1';
        const style=popup.document.createElement('style');style.textContent='*{box-sizing:border-box}body{margin:0;padding:16px;background:#102238;color:#fff;font:14px Inter,Arial,sans-serif}header{margin-bottom:12px;overflow-wrap:anywhere}header small{display:block;margin-top:4px;color:#cbd9e6}header small[hidden]{display:none}video{display:block;width:100%;aspect-ratio:16/9;object-fit:contain;background:#071526;border-radius:10px}.desktop-browser-pip-surface{position:relative;width:100%}.desktop-browser-pip-handle{position:absolute;z-index:2;border:2px solid #fff;box-shadow:0 0 0 1px #102238,0 0 8px #102238;cursor:move;touch-action:none}.desktop-browser-pip-handle[hidden]{display:none}.desktop-browser-pip-handle [data-pip-resize]{position:absolute;right:-2px;bottom:-2px;width:20px;height:20px;border:2px solid #102238;background:#fff;cursor:nwse-resize}.desktop-browser-pip-handle:focus-visible{outline:3px solid #ffce65;outline-offset:3px}';
        popup.document.head.append(viewport,style);
        const heading=popup.document.createElement('header'),video=popup.document.createElement('video');heading.textContent=event.title||t('quick_new');video.muted=true;video.playsInline=true;video.autoplay=true;
        popupInsetHint=popup.document.createElement('small');popupInsetHint.textContent=t('pip_hint');heading.append(popupInsetHint);
        const surface=popup.document.createElement('div');surface.className='desktop-browser-pip-surface';surface.append(video);
        popupInsetEditor=mountInsetEditor(surface,inset,{label:t('pip_adjust'),visible:()=>pip&&scene!=='camera',onChange:syncInsetEditors});
        popup.document.body.replaceChildren(heading,surface);syncInsetEditors();
        previewWindowStream=canvas.captureStream(30);video.srcObject=previewWindowStream;video.play().catch(()=>{});
        popup.addEventListener('beforeunload',()=>{previewWindowStream?.getTracks().forEach(track=>track.stop());previewWindowStream=null;previewWindow=null;popupInsetEditor=null;popupInsetHint=null;detachPreview(false);},{once:true});
        detachPreview(true);
      }catch(error){closePreviewWindow();throw error;}
    });
    previewButton=openPreview;previewHead.append(W.el('strong',t('preview')),openPreview);
    const inlineSurface=W.el('div',undefined,'desktop-browser-pip-surface');inlineSurface.append(canvas);
    inlineInsetEditor=mountInsetEditor(inlineSurface,inset,{label:t('pip_adjust'),visible:()=>pip&&scene!=='camera',onChange:syncInsetEditors});
    preview.append(previewHead,inlineSurface);layout.append(preview,controls);
    const heading=W.el('div',undefined,'desktop-browser-heading');previewHeading=heading;heading.append(W.el('h3',t('title')));
    const selectedLabel=W.el('p',t('selected_event')+': '+t('choose_placeholder'),'desktop-browser-selected-event');
    panel.append(heading,selectedLabel,W.el('p',t('intro')),layout);
    eventPicker=mountBrowserEvents({root,panel,t,request,onSelect:data=>{
      event=data||{};selectedLabel.textContent=t('selected_event')+': '+(event.title||(eventPicker?.mode()==='new'?t('quick_new'):t('choose_placeholder')));
      if(previewWindow&&!previewWindow.closed){previewWindow.document.title=t('preview')+' · '+(event.title||t('quick_new'));previewWindow.document.querySelector('header').firstChild.textContent=event.title||t('quick_new');}
    }});
    await eventPicker.refresh(server.session?.source_record_id||null);if(disposed)return;
    const devices=group('devices','is-devices',leftControls);fieldGroup=devices;
    const cameraChoice=select('camera'),micChoice=select('microphone');cameraChoice.append(new Option(t('none'),'none'));micChoice.append(new Option(t('microphone'),''));
    const micVolume=input('mic_volume','range');micVolume.min=0;micVolume.max=1;micVolume.step=.01;micVolume.value=.8;micVolume.oninput=()=>{if(micGain)micGain.gain.value=Number(micVolume.value);};
    const meter=document.createElement('meter');meter.min=0;meter.max=1;meter.setAttribute('aria-label',t('microphone'));const meterLabel=W.el('label',t('audio_level'));meterLabel.append(meter);devices.append(meterLabel);
    const visuals=group('visuals','is-visuals',rightControls);fieldGroup=visuals;
    const scenes=select('scene');for(const key of ['camera','screen','image'])scenes.append(new Option(t(key+'_scene'),key));scenes.onchange=()=>{scene=scenes.value;syncInsetEditors();};
    const pictureInPicture=input('pip','checkbox');pictureInPicture.parentElement.append(W.el('small',t('pip_hint')));pictureInPicture.onchange=()=>{pip=pictureInPicture.checked;syncInsetEditors();};
    const overlay=input('overlay');overlay.maxLength=100;
    const sharedVolume=input('screen_volume','range');sharedVolume.min=0;sharedVolume.max=1;sharedVolume.step=.01;sharedVolume.value=.5;sharedVolume.oninput=()=>{if(screenGain)screenGain.gain.value=Number(sharedVolume.value);};
    const imageInput=input('image','file');imageInput.accept='image/png,image/jpeg,image/webp';imageInput.multiple=true;
    const imageChoice=select('active_image');imageChoice.onchange=()=>{imageIndex=Number(imageChoice.value);scene='image';scenes.value=scene;};
    const audio=group('audio_group','is-audio',leftControls),broadcast=group('broadcast','is-broadcast',controls);
    broadcast.append(W.el('p',t('event_start_hint'),'desktop-browser-event-start-hint'));
    imageInput.onchange=async()=>{
      try{
        const files=[...imageInput.files];if(files.length>20||files.some(f=>f.size>20*1024*1024||!['image/png','image/jpeg','image/webp'].includes(f.type)))throw new Error(t('error'));
        const loaded=[];for(const file of files){const url=URL.createObjectURL(file);imageUrls.push(url);const image=new Image();image.src=url;await image.decode();loaded.push({image,name:file.name});}
        if(disposed)return;images=loaded;const currentUrls=loaded.map(item=>item.image.src);imageUrls.filter(url=>!currentUrls.includes(url)).forEach(url=>URL.revokeObjectURL(url));imageUrls=currentUrls;imageChoice.replaceChildren();images.forEach((item,index)=>imageChoice.append(new Option(item.name,String(index))));imageIndex=0;scene='image';scenes.value=scene;
      }catch(error){status.textContent=error.message;}
    };
    const fit=(source,x=0,y=0,width=1280,height=720)=>{
      const sw=source.videoWidth||source.naturalWidth,sh=source.videoHeight||source.naturalHeight;if(!sw||!sh)return;
      const ratio=Math.min(width/sw,height/sh);ctx.drawImage(source,x+(width-sw*ratio)/2,y+(height-sh*ratio)/2,sw*ratio,sh*ratio);
    };
    drawTimer=setInterval(()=>{
      if(disposed)return;ctx.fillStyle='#102238';ctx.fillRect(0,0,1280,720);
      if(previewWindow?.closed)closePreviewWindow();
      syncInsetEditors();
      if(scene==='screen')fit(screenVideo);else if(scene==='image'&&images[imageIndex])fit(images[imageIndex].image);else if(scene==='camera')fit(cameraVideo);
      if(pip&&scene!=='camera'&&cameraVideo.videoWidth){ctx.fillStyle='#102238';ctx.fillRect(inset.x,inset.y,inset.w,inset.h);fit(cameraVideo,inset.x+8,inset.y+8,inset.w-16,inset.h-16);}
      if(overlay.value){ctx.fillStyle='rgba(16,34,56,.8)';ctx.fillRect(0,650,1280,70);ctx.font='32px sans-serif';ctx.fillStyle='#fff';ctx.fillText(overlay.value,24,697,1232);}
      if(analyser){const values=new Float32Array(analyser.fftSize);analyser.getFloatTimeDomainData(values);meter.value=Math.sqrt(values.reduce((sum,n)=>sum+n*n,0)/values.length);}
    },1000/30);
    let analyser=null;
    const attachAudio=(stream,gain)=>{if(!stream.getAudioTracks().length)return;const source=context.createMediaStreamSource(stream);source.connect(gain);sources.push(source);gain.connect(audioDestination);gain.connect(analyser);};
    const prepare=async()=>{
      if(pc||recording)throw new Error(t('close_warning'));preparing=true;
      try{
        if(!isSecureContext||!navigator.mediaDevices?.getUserMedia||!canvas.captureStream||!window.AudioWorkletNode)throw new Error(t('unsupported'));
        if(!context){context=new AudioContext({sampleRate:48000});audioDestination=context.createMediaStreamDestination();analyser=context.createAnalyser();analyser.fftSize=512;micGain=context.createGain();micGain.gain.value=Number(micVolume.value);screenGain=context.createGain();screenGain.gain.value=Number(sharedVolume.value);
          await context.audioWorklet.addModule('/assets/desktop-podcast-recorder.js?v=1');recorder=new AudioWorkletNode(context,'podcast-recorder');analyser.connect(recorder);const silence=context.createGain();silence.gain.value=0;recorder.connect(silence);silence.connect(context.destination);
          recorder.port.onmessage=e=>{if(!recording)return;if(pcmBytes+e.data.byteLength>256*1024*1024){finishRecording();status.textContent=t('record_limit');return;}pcm.push(e.data);pcmBytes+=e.data.byteLength;};
        }
        await context.resume();root.dataset.studioCapturing='true';mic?.getTracks().forEach(track=>track.stop());camera?.getTracks().forEach(track=>track.stop());
        camera=null;cameraVideo.srcObject=null;
        mic=await navigator.mediaDevices.getUserMedia({audio:micChoice.value?{deviceId:{exact:micChoice.value},echoCancellation:true}:true,video:false});attachAudio(mic,micGain);
        if(cameraChoice.value!=='none'){camera=await navigator.mediaDevices.getUserMedia({audio:false,video:cameraChoice.value?{deviceId:{exact:cameraChoice.value},width:{ideal:1280},height:{ideal:720}}:true});cameraVideo.srcObject=camera;await cameraVideo.play();}
        const devices=await navigator.mediaDevices.enumerateDevices();const selectedCamera=cameraChoice.value,selectedMic=micChoice.value;
        cameraChoice.replaceChildren(new Option(t('camera'),''),new Option(t('none'),'none'));micChoice.replaceChildren(new Option(t('microphone'),''));for(const device of devices){if(device.kind==='videoinput')cameraChoice.append(new Option(device.label||t('camera'),device.deviceId));if(device.kind==='audioinput')micChoice.append(new Option(device.label||t('microphone'),device.deviceId));}cameraChoice.value=selectedCamera;micChoice.value=selectedMic;
        status.textContent=t('ready');
      }finally{preparing=false;if(disposed)for(const stream of [mic,camera,screen])stream?.getTracks().forEach(track=>track.stop());}
    };
    // Default camera is requested on first preparation; "none" remains an explicit audio-only option.
    cameraChoice.prepend(new Option(t('camera'),''));cameraChoice.value='';
    actionRow(devices,action('prepare',prepare),action('mute',()=>{micVolume.value=Number(micVolume.value)>0?'0':'.8';micVolume.oninput();if(!pc)status.textContent=t(Number(micVolume.value)>0?'ready':'muted');}));
    actionRow(visuals,action('screen',async()=>{
      if(!context)throw new Error(t('choose_source'));
      const captured=await navigator.mediaDevices.getDisplayMedia({video:true,audio:true});screen?.getTracks().forEach(track=>track.stop());screen=captured;screenVideo.srcObject=screen;await screenVideo.play();attachAudio(screen,screenGain);scene='screen';scenes.value=scene;
      screen.getVideoTracks()[0].onended=()=>{if(scene==='screen'){scene=images.length?'image':'camera';scenes.value=scene;}};
    }),action('screen_stop',()=>{screen?.getTracks().forEach(track=>track.stop());screen=null;screenVideo.srcObject=null;if(scene==='screen'){scene=images.length?'image':'camera';scenes.value=scene;}}));
    const finishRecording=()=>{
      if(!recording)return;recording=false;recorder.port.postMessage(false);
      const header=new ArrayBuffer(44),view=new DataView(header);const text=(offset,value)=>{for(let i=0;i<value.length;i++)view.setUint8(offset+i,value.charCodeAt(i));};
      text(0,'RIFF');view.setUint32(4,36+pcmBytes,true);text(8,'WAVE');text(12,'fmt ');view.setUint32(16,16,true);view.setUint16(20,1,true);view.setUint16(22,1,true);view.setUint32(24,context.sampleRate,true);view.setUint32(28,context.sampleRate*2,true);view.setUint16(32,2,true);view.setUint16(34,16,true);text(36,'data');view.setUint32(40,pcmBytes,true);
      wav=new Blob([header,...pcm],{type:'audio/wav'});pcm=[];pcmBytes=0;status.textContent=t('ready');
    };
    const audioActions=actionRow(audio,action('record',async()=>{if(!mic||!recorder||recording)throw new Error(t('choose_source'));if(wav&&!confirm(t('download')+'?'))return;wav=null;uploadedMediaId=null;pcm=[];pcmBytes=0;await context.resume();recording=true;recorder.port.postMessage(true);status.textContent=t('recording');}),action('record_stop',finishRecording),action('download',()=>{
      if(!wav)throw new Error(t('choose_source'));const url=URL.createObjectURL(wav),a=document.createElement('a');a.href=url;a.download='podcast-'+(event.id||'browser')+'.wav';a.click();setTimeout(()=>URL.revokeObjectURL(url),10000);
    }));
    if(typeof window.uploadDesktopMedia==='function')audioActions.append(action('upload',async()=>{
      if(!wav||recording)throw new Error(t('choose_source'));uploadedMediaId=await window.uploadDesktopMedia(new File([wav],'podcast-'+(event.id||'browser')+'.wav',{type:'audio/wav'}),root.dataset.userId,()=>{},null);status.textContent=t('uploaded');
    }));
    audioActions.append(action('podcast_draft',async()=>{if(!uploadedMediaId)throw new Error(t('audio_unavailable'));if(!event.id)throw new Error(t('choose_required'));await request(endpoint+'/events/'+event.id+'/podcast',{method:'POST',body:JSON.stringify({confirm:true,media_id:uploadedMediaId})});status.textContent=t('podcast_created');}));
    const stop=async()=>{operation++;const id=session;clearInterval(beatTimer);clearInterval(statsTimer);pc?.close();pc=null;canvasStream?.getTracks().forEach(track=>track.stop());canvasStream=null;if(id)await request(endpoint+'/sessions/'+id,{method:'DELETE'});session=null;eventPicker.lock(false);status.textContent=t('ended');};
    actionRow(broadcast,action('start',async()=>{
      if(session||pc||!mic||(scene==='camera'&&!camera)||(scene==='screen'&&!screen)||(scene==='image'&&!images.length))throw new Error(t('choose_source'));
      if(!server.available||!server.browser_enabled)throw new Error(t('browser_disabled'));eventPicker.validate();if(!confirm(t('confirm_start')))return;
      creating=true;try{event=await eventPicker.ensureEvent();}finally{creating=false;}
      status.textContent=t('connecting');pc=new RTCPeerConnection();
      const generation=++operation;
      try{
        canvasStream=canvas.captureStream(30);for(const track of [...canvasStream.getVideoTracks(),...audioDestination.stream.getAudioTracks()]){
          const transceiver=pc.addTransceiver(track,{direction:'sendonly'});if(track.kind==='video'&&transceiver.setCodecPreferences){const codecs=RTCRtpSender.getCapabilities('video').codecs.filter(codec=>codec.mimeType.toLowerCase()==='video/h264');if(!codecs.length)throw new Error(t('unsupported'));transceiver.setCodecPreferences(codecs);}
        }
        pc.onconnectionstatechange=()=>{if(pc?.connectionState==='failed'){const message=t('browser_connection_failed');stop().catch(()=>{}).finally(()=>{status.textContent=message;});}};
        await pc.setLocalDescription(await pc.createOffer());
        await new Promise((resolve,reject)=>{if(pc.iceGatheringState==='complete'){resolve();return;}const timeout=setTimeout(()=>reject(new Error(t('browser_connection_failed'))),12000);pc.onicegatheringstatechange=()=>{if(pc?.iceGatheringState==='complete'){clearTimeout(timeout);resolve();}};});
        const startRequest=()=>request(endpoint+'/events/'+event.id+'/browser',{method:'POST',body:JSON.stringify({confirm:true,sdp:pc.localDescription.sdp})});
        let data;try{data=await startRequest();}catch(error){
          if(error.status!==429||!error.retryAfter||error.retryAfter>10)throw error;
          status.textContent=t('start_wait').replace(':seconds',String(error.retryAfter));
          await new Promise(resolve=>setTimeout(resolve,(error.retryAfter+1)*1000));
          if(disposed||operation!==generation)throw new Error(t('ended'));
          data=await startRequest();
        }
        if(disposed||operation!==generation){await request(endpoint+'/sessions/'+data.id,{method:'DELETE'});throw new Error(t('ended'));}session=data.id;
        event.published=true;event.browser_enabled=true;event.starts_at=data.starts_at||event.starts_at;event.status=data.status||'starting';eventPicker.complete(event);eventPicker.lock(true);
        root.dispatchEvent(new CustomEvent('desktop-live-event-started',{detail:{...event}}));
        await pc.setRemoteDescription({type:'answer',sdp:data.sdp});
        const beat=async()=>{try{const data=await request(endpoint+'/sessions/'+session+'/heartbeat',{method:'POST'});leaseUntil=Date.parse(data.expires_at)||leaseUntil;status.textContent=t(data.status==='live'?'live':'connecting');}catch(error){
          const rejected=[401,403,404,409].includes(error.status),leaseExpired=leaseUntil&&Date.now()>=leaseUntil;
          if(rejected||leaseExpired){await stop().catch(()=>{});status.textContent=error.message;return;}
          status.textContent=t('heartbeat_warning');
        }};
        beatTimer=setInterval(beat,10000);await beat();
        let previous=null;statsTimer=setInterval(async()=>{const current=pc;if(!current)return;try{const reports=await current.getStats();if(pc!==current)return;for(const report of reports.values())if(report.type==='outbound-rtp'&&report.kind==='video'&&!report.isRemote){let rate='—';if(previous&&report.timestamp>previous.timestamp)rate=Math.round((report.bytesSent-previous.bytesSent)*8/(report.timestamp-previous.timestamp))+' kbit/s';telemetry.textContent=t('telemetry')+' · '+t('bitrate')+': '+rate+' · '+t('frames')+': '+(report.framesEncoded??'—');previous=report;}}catch{/* Statistics are optional, never made-up. */}},1000);
      }catch(error){await stop().catch(()=>{});eventPicker.lock(false);if(error.status===429)throw new Error(t('start_cooldown').replace(':seconds',String(error.retryAfter||60)));throw error;}
    }),action('stop',stop));broadcast.append(status,telemetry);
    const settings=W.el('details');settings.append(W.el('summary',t('server')),W.el('p',t(server.available?'available':'unavailable')));if(!server.available)settings.append(W.el('p',t('unavailable_hint')));if(!server.browser_enabled)settings.append(W.el('p',t('enable_hint')));settings.append(W.el('p',t('setup_hint')),W.el('p',t('network_hint')));panel.append(settings);
    if(configured){const notice=W.el('p',t(server.available?'config_saved':'config_saved_api_unavailable'),'desktop-browser-config-notice');notice.dataset.serverConfigResult='';notice.dataset.state=server.available?'success':'warning';notice.setAttribute('role','status');settings.append(notice);settings.open=true;}
    if(server.can_configure){
      const form=W.el('form'),host=document.createElement('input'),enable=document.createElement('input');host.value=server.host||location.hostname;host.required=true;host.name='live_browser_host';enable.type='checkbox';enable.checked=server.browser_enabled;enable.name='live_browser_enabled';
      const h=W.el('label',t('host')),e=W.el('label',t('enable')),hint=W.el('small',t('host_hint'));h.append(host,hint);e.append(enable);
      const apply=W.el('button',t('apply'),'desktop-button'),feedback=W.el('p',undefined,'desktop-browser-config-notice');apply.type='submit';feedback.dataset.serverConfigFeedback='';feedback.setAttribute('aria-live','polite');form.append(h,e,apply,feedback);
      form.onsubmit=async ev=>{
        ev.preventDefault();if(!confirm(t('confirm_config')))return;
        feedback.removeAttribute('role');feedback.dataset.state='pending';feedback.textContent=t('config_applying');apply.disabled=true;apply.textContent=t('config_applying');
        try{await request(endpoint+'/server',{method:'POST',body:JSON.stringify({confirm:true,live_browser_host:host.value,live_browser_enabled:enable.checked})});await initialize(root,obsEvent,true);}
        catch(error){feedback.dataset.state='error';feedback.setAttribute('role','alert');feedback.textContent=t('config_not_saved')+' '+(error.message||t('error'));feedback.scrollIntoView({block:'nearest'});}
        finally{if(!disposed){apply.disabled=false;apply.textContent=t('apply');}}
      };
      settings.append(form);
    }
    if(configured)settings.querySelector('[data-server-config-result]').scrollIntoView({block:'nearest'});
    if(server.can_disconnect&&obsEvent?.id)settings.append(action('disconnect',async()=>{if(confirm(t('confirm_disconnect')))await request(endpoint+'/events/'+obsEvent.id+'/disconnect',{method:'POST',body:JSON.stringify({confirm:true})});}));
    if(server.session){session=server.session.id;eventPicker.lock(true);status.textContent=t('restore_session');}
  }catch(error){if(!disposed){panel.append(status);status.textContent=error.message;}}
}
