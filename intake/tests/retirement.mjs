import assert from 'node:assert/strict';
import {spawn} from 'node:child_process';
import {createServer} from 'node:net';
import {resolve} from 'node:path';
const socket=createServer();await new Promise(r=>socket.listen(0,'127.0.0.1',r));
const port=socket.address().port;await new Promise(r=>socket.close(r));
const server=spawn(process.env.PHP_BINARY||'php',['-S',`127.0.0.1:${port}`,'-t',resolve('intake/public')],{stdio:'ignore'});
try {
 const origin=`http://127.0.0.1:${port}`;let response;
 for(let n=0;n<50;n++){try{response=await fetch(origin,{redirect:'manual'});break;}catch{await new Promise(r=>setTimeout(r,100));}}
 assert(response,'server started');assert.equal(response.status,303);assert.equal(response.headers.get('location'),'/desktop');
 for(const action of ['overview','start','chunk','finish']) {
  const reply=await fetch(`${origin}/api.php?action=${action}`,{method:'POST',body:'{}',headers:{'Content-Type':'application/json'}});
  assert.equal(reply.status,410);assert.equal((await reply.json()).error,'media_library_required');
 }
 console.log('Retirement HTTP checks passed.');
}finally{server.kill();}
