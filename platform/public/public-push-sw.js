const CACHE='manna-static-v1';
const STATIC_URLS=['/','/favicon.png','/manifest.webmanifest','/assets/brand/owner/app-icon.png','/assets/fonts/fonts.css','/assets/brand/ui-kit.css','/assets/public.css','/assets/public-pages.css'];

self.addEventListener('install',event=>event.waitUntil(caches.open(CACHE).then(cache=>cache.addAll(STATIC_URLS)).then(()=>self.skipWaiting())));
self.addEventListener('activate',event=>event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(key=>key!==CACHE).map(key=>caches.delete(key)))).then(()=>self.clients.claim())));
self.addEventListener('fetch',event=>{
  const request=event.request;
  if(request.method!=='GET'||new URL(request.url).origin!==self.location.origin)return;
  const path=new URL(request.url).pathname;
  if(!STATIC_URLS.includes(path)&&!path.startsWith('/assets/'))return;
  event.respondWith(fetch(request).then(response=>{if(response.ok)caches.open(CACHE).then(cache=>cache.put(request,response.clone()));return response;}).catch(()=>caches.match(request)));
});
self.addEventListener('push',event=>{
  event.waitUntil((async()=>{
    let data;try{data=event.data.json();}catch{return;}
    if(typeof data.url!=='string'||!data.url.startsWith('/live?'))return;
    await self.registration.showNotification(data.title,{body:data.body,tag:data.tag,data:{url:data.url}});
  })());
});
self.addEventListener('notificationclick',event=>{
  event.notification.close();
  event.waitUntil((async()=>{
    const url=new URL(event.notification.data.url,self.location.origin);
    if(url.origin!==self.location.origin)return;
    const clients=await self.clients.matchAll({type:'window'});
    for(const client of clients)if(client.url===url.href){await client.focus();return;}
    await self.clients.openWindow(url.href);
  })());
});
