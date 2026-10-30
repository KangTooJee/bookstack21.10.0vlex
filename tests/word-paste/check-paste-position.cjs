const fs=require('fs'),path=require('path'),assert=require('node:assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base='http://localhost:8080';
const png=fs.readFileSync(path.resolve(__dirname,'../word-import/fixture.png')).toString('base64');
async function run(){
 const browser=await chromium.launch({channel:'chrome',headless:true});
 const context=await browser.newContext();const page=await context.newPage();
 try{
  await page.goto(base+'/login');
  await context.request.post(base+'/login',{form:{_token:await page.locator('[name=_token]').first().inputValue(),email:'admin@admin.com',password:'password'}});
  await page.goto(base+'/create-book');
  const book=await context.request.post(base+'/books',{form:{_token:await page.locator('[name=_token]').first().inputValue(),name:'Vị trí dán Word '+Date.now()}});
  await page.goto(book.url()+'/create-page');await page.waitForFunction(()=>window.editor?.initialized);
  const results=[];
  for(const mode of ['caret','selection','table']){
   await page.evaluate(mode=>{
    const editor=window.editor;
    editor.setContent(mode==='table'?'<p>BEFORE</p><table><tbody><tr><td>LEFT|REPLACE|RIGHT</td><td>OTHER_CELL</td></tr></tbody></table><p>AFTER</p>':'<p>BEFORE</p><p>LEFT|REPLACE|RIGHT</p><p>AFTER</p>');
    editor.focus();
    const node=mode==='table'?editor.getBody().querySelector('td').firstChild:editor.getBody().querySelectorAll('p')[1].firstChild;
    const range=editor.getDoc().createRange();range.setStart(node,5);range.setEnd(node,mode==='caret'?5:12);editor.selection.setRng(range);
   },mode);
   let release;const hold=new Promise(resolve=>release=resolve);
   await page.route('**/images/gallery',async route=>{await hold;await route.continue()});
   const pasteBin=await page.evaluate(png=>{
    const target=window.editor.getBody();
    target.dispatchEvent(new KeyboardEvent('keydown',{key:'v',code:'KeyV',keyCode:86,which:86,ctrlKey:true,bubbles:true,cancelable:true}));
    const bin=!!window.editor.getDoc().getElementById('mcepastebin');
    const data=new DataTransfer();data.setData('text/html','<p>PASTE_START</p><p><img src="data:image/png;base64,'+png+'"></p><p>PASTE_END</p>');
    window.editor.getBody().dispatchEvent(new ClipboardEvent('paste',{clipboardData:data,bubbles:true,cancelable:true}));
    return bin;
   },png);
   await page.waitForFunction(()=>window.editor.getBody().contentEditable==='false');
   // A focus change/toolbar interaction resets TinyMCE's current selection.
   await page.evaluate(()=>{
    window.editor.getBody().dispatchEvent(new KeyboardEvent('keyup',{key:'v',code:'KeyV',keyCode:86,which:86,ctrlKey:true,bubbles:true,cancelable:true}));
    window.editor.selection.select(window.editor.getBody(),true);window.editor.selection.collapse(false);
   });
   release();await page.waitForFunction(()=>window.wordPasteDiagnostics?.phase==='complete'&&window.editor.getBody().textContent.includes('PASTE_END'));
   await page.unroute('**/images/gallery');
   const content=await page.evaluate(()=>window.editor.getBody().textContent);
   const normalized=content.replace(/\s+/g,'');
   assert.ok(normalized.indexOf('BEFORE')<normalized.indexOf('LEFT|'));
   assert.ok(normalized.indexOf('LEFT|')<normalized.indexOf('PASTE_START'));
   assert.ok(normalized.indexOf('PASTE_END')<normalized.indexOf('RIGHT'));
   assert.ok(normalized.indexOf('RIGHT')<normalized.indexOf('AFTER'));
   assert.equal(normalized.includes('REPLACE'),mode==='caret');
   if(mode==='table')assert.ok(normalized.indexOf('PASTE_END')<normalized.indexOf('OTHER_CELL'));
   results.push({mode,pasteBin,passed:true});console.log('PASS: Ctrl+V keydown/paste/keyup at '+mode+' despite selection reset during upload');
  }
  await page.evaluate(()=>{
   const e=window.editor;e.setContent('<p>LEFT|RIGHT</p>');e.focus();const text=e.getBody().querySelector('p').firstChild;
   const range=e.getDoc().createRange();range.setStart(text,5);range.collapse(true);e.selection.setRng(range);
   e.getBody().dispatchEvent(new KeyboardEvent('keydown',{key:'V',code:'KeyV',keyCode:86,which:86,ctrlKey:true,shiftKey:true,bubbles:true,cancelable:true}));
   const data=new DataTransfer();data.setData('text/plain','PLAIN_ONLY');data.setData('text/html','<p><strong>PLAIN_ONLY</strong></p>');
   e.getBody().dispatchEvent(new ClipboardEvent('paste',{clipboardData:data,bubbles:true,cancelable:true}));
   e.getBody().dispatchEvent(new KeyboardEvent('keyup',{key:'V',code:'KeyV',keyCode:86,which:86,ctrlKey:true,shiftKey:true,bubbles:true,cancelable:true}));
  });
  await page.waitForFunction(()=>window.editor.getBody().textContent.includes('PLAIN_ONLY'));
  const plain=await page.evaluate(()=>({text:window.editor.getBody().textContent.replace(/\s+/g,''),bold:!!window.editor.getBody().querySelector('strong,b'),bin:!!window.editor.getDoc().getElementById('mcepastebin')}));
  assert.equal(plain.text,'LEFT|PLAIN_ONLYRIGHT');assert.equal(plain.bold,false);assert.equal(plain.bin,false);
  results.push({mode:'plain-text-shortcut',passed:true});console.log('PASS: Ctrl+Shift+V still pastes plain text at caret and removes the temporary pastebin');
  const out=path.resolve(__dirname,'../../../outputs/word-paste/paste-position-result.json');fs.writeFileSync(out,JSON.stringify(results,null,2));
 }finally{await browser.close()}
}
run().catch(e=>{console.error(e);process.exitCode=1});
