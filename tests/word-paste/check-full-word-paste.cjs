const fs=require('fs'),path=require('path'),http=require('http'),assert=require('node:assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base='http://localhost:8080';
const png=fs.readFileSync(path.resolve(__dirname,'../../source/BookStack-21.11.1/public/user_avatar.png')).toString('base64');
let sources=Array.from({length:15},(_,i)=>`file:///C:/Users/Vissoft_PC/AppData/Local/Temp/msohtmlclip1/01/clip_image${String((i+1)*2).padStart(3,'0')}.jpg`);
const fragment=src=>'<html><head><style>p.MsoNormal{font-family:Times New Roman;font-size:14pt}</style></head><body>'+src.map((s,i)=>`<p class="MsoNormal">WORD_TEXT_${i}<img src="${s}" width="60" height="60"></p>`).join('')+'<p>WORD_END</p></body></html>';
async function run(){
 let resolveRequest;const bridgeRequested=new Promise(r=>resolveRequest=r);let release;const hold=new Promise(r=>release=r);
 const calls=[];let child=null,childResult=null,manifest=null,expectedHashes=null;
 const native=process.env.TEST_NATIVE_BRIDGE_FIXTURE==='1';
 const server=native?null:http.createServer(async(req,res)=>{
  const cors={'Access-Control-Allow-Origin':base,'Access-Control-Allow-Methods':'POST, OPTIONS','Access-Control-Allow-Headers':'Content-Type','Access-Control-Allow-Private-Network':'true','Content-Type':'application/json'};
  if(req.headers.origin!==base){res.writeHead(403);res.end();return}
  if(req.method==='OPTIONS'){res.writeHead(204,cors);res.end();return}
  let body='';for await(const part of req)body+=part;
  const payload=JSON.parse(body);calls.push(payload);resolveRequest();await hold;
  res.writeHead(200,cors);res.end(JSON.stringify({imagesBySource:sources.map(source=>({source,data:`data:image/png;base64,${png}`})),images:15,imageBytes:Buffer.from(png,'base64').length*15}));
 });
 if(native){
  manifest=path.resolve(__dirname,'../../../outputs/word-paste/native-raster/full-word-native-manifest.json');
  if(fs.existsSync(manifest+'.release'))fs.unlinkSync(manifest+'.release');
  const {spawn}=require('node:child_process');
  child=spawn('C:/Users/Vissoft_PC/.cache/codex-runtimes/codex-primary-runtime/dependencies/native/powershell/pwsh.exe',['-NoProfile','-STA','-File',path.join(__dirname,'serve-native-bridge-fixture.ps1'),'-Manifest',manifest],{windowsHide:true});
  childResult=new Promise((resolve,reject)=>{let output='';child.stdout.on('data',data=>{output+=data;process.stdout.write(data)});child.stderr.on('data',data=>process.stderr.write(data));child.once('error',reject);child.once('exit',code=>code===0?resolve(output):reject(Error('Native bridge fixture exited '+code)))});
  await new Promise((resolve,reject)=>{child.stdout.on('data',data=>{if(String(data).includes('READY'))resolve()});child.once('error',reject);child.once('exit',code=>{if(code!==0)reject(Error('Native bridge failed to start'))})});
  const nativeManifest=JSON.parse(fs.readFileSync(manifest,'utf8'));sources=nativeManifest.sources;expectedHashes=nativeManifest.hashes;
 }else await new Promise((resolve,reject)=>{server.once('error',reject);server.listen(32145,'127.0.0.1',resolve)});
 const browser=await chromium.launch({channel:'chrome',headless:true});const context=await browser.newContext();const page=await context.newPage();
 try{
  let observedRequest=null;
  page.on('request',request=>{if(request.url()==='http://127.0.0.1:32145/prepare'&&request.method()==='POST'){observedRequest=request.postDataJSON();resolveRequest()}});
  await page.goto(base+'/login');await context.request.post(base+'/login',{form:{_token:await page.locator('[name=_token]').first().inputValue(),email:'admin@admin.com',password:'password'}});
  await page.goto(base+'/create-book');const book=await context.request.post(base+'/books',{form:{_token:await page.locator('[name=_token]').first().inputValue(),name:'Ctrl A Word 15 ảnh '+Date.now()}});
  await page.goto(book.url()+'/create-page');await page.waitForFunction(()=>window.editor?.initialized);
  await page.locator('[name=name]').fill('Ctrl+A Word — 15 ảnh tại con trỏ');
  const bin=await page.evaluate(html=>{
   const e=window.editor;e.setContent('<p>BEFORE</p><p>LEFT|RIGHT</p><p>AFTER</p>');e.focus();
   const text=e.getBody().querySelectorAll('p')[1].firstChild;const r=e.getDoc().createRange();r.setStart(text,5);r.collapse(true);e.selection.setRng(r);
   e.getBody().dispatchEvent(new KeyboardEvent('keydown',{key:'v',code:'KeyV',keyCode:86,which:86,ctrlKey:true,bubbles:true,cancelable:true}));
   const bin=!!e.getDoc().getElementById('mcepastebin');
   const data=new DataTransfer();data.setData('text/html',html);data.setData('text/plain','Word text and images');data.setData('text/rtf','');
   e.getBody().dispatchEvent(new ClipboardEvent('paste',{clipboardData:data,bubbles:true,cancelable:true}));
   e.getBody().dispatchEvent(new KeyboardEvent('keyup',{key:'v',code:'KeyV',keyCode:86,which:86,ctrlKey:true,bubbles:true,cancelable:true}));
   return bin;
  },fragment(sources));
  await Promise.race([bridgeRequested,new Promise((_,reject)=>setTimeout(()=>reject(Error('Bridge was not called')),10000))]);
  assert.equal(bin,false,'Ctrl+V created TinyMCE temporary pastebin');assert.deepEqual(observedRequest.sources,sources);
  await page.evaluate(()=>{window.editor.selection.select(window.editor.getBody(),true);window.editor.selection.collapse(false)});
  if(native)fs.writeFileSync(manifest+'.release','release');release();
  await page.waitForFunction(()=>window.wordPasteDiagnostics?.phase==='complete'&&window.editor.getBody().textContent.includes('WORD_END'));
  const result=await page.evaluate(()=>({text:window.editor.getBody().textContent,images:Array.from(window.editor.getBody().querySelectorAll('img')).map(img=>({src:img.src,loaded:img.complete&&img.naturalWidth>0})),diagnostics:window.wordPasteDiagnostics}));
  assert.equal(result.images.length,15);assert.ok(result.images.every(img=>img.loaded&&img.src.includes('/uploads/images/gallery/')));
  assert.ok(!result.text.includes('Ảnh chưa có dữ liệu'));
  const text=result.text.replace(/\s+/g,'');assert.ok(text.indexOf('LEFT|')<text.indexOf('WORD_TEXT_0'));assert.ok(text.indexOf('WORD_END')<text.indexOf('RIGHT'));
  for(let i=0;i<14;i++)assert.ok(text.indexOf('WORD_TEXT_'+i)<text.indexOf('WORD_TEXT_'+(i+1)));
  assert.equal(result.diagnostics.nativeBridge.used,true);
  if(expectedHashes){
   const crypto=require('node:crypto');
   for(let i=0;i<result.images.length;i++){
    const image=await context.request.get(result.images[i].src);
    assert.equal(crypto.createHash('sha256').update(await image.body()).digest('hex'),expectedHashes[i],'Image bytes/order changed at index '+i);
   }
  }
  await Promise.all([page.waitForURL(/\/page\//),page.evaluate(()=>document.querySelector('form[data-page-id]').submit())]);await page.reload();
  assert.equal(await page.locator('.page-content img').count(),15);
  const out=path.resolve(__dirname,'../../../outputs/word-paste/full-word-paste-result.json');fs.writeFileSync(out,JSON.stringify({passed:true,images:15,rtfBytes:0,clipboardFiles:0,caretPreserved:true,textOrderPreserved:true,imageBytesVerified:!!expectedHashes,savedUrl:page.url(),test:native?'TinyMCE Ctrl+V sequence; real native PowerShell bridge with injected Word-style data object and fifteen temporary JPEG files; real gallery/save/reload':'TinyMCE Ctrl+V sequence, mock native bridge transport, real gallery/save/reload'},null,2));
  console.log('PASS: Ctrl+V with HTML + 15 file references, no RTF/files -> bridge -> all images and text at caret -> save/reload');
  console.log('DEMO:',page.url());
 }finally{
  release();if(native)fs.writeFileSync(manifest+'.release','release');await browser.close();
  if(server){server.closeAllConnections();await new Promise(r=>server.close(r))}
  if(childResult)await childResult;
 }
}
run().catch(e=>{console.error(e);process.exitCode=1});
