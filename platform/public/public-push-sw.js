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
