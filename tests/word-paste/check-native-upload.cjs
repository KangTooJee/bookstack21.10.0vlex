const fs = require('fs');
const path = require('path');
const assert = require('node:assert/strict');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname,'../../../outputs/word-paste/native-raster');
const html = fs.readFileSync(path.join(root,'native-clipboard.html'),'utf8');
const expected = Buffer.from(/src="data:image\/png;base64,([^"]+)"/.exec(html)[1],'base64');
const base = 'http://localhost:8080';
async function run() {
    const browser=await chromium.launch({channel:'chrome',headless:true});
    const context=await browser.newContext({viewport:{width:1440,height:1000}});
    const page=await context.newPage();
    try {
        await page.goto(base+'/login');
        const token=await page.locator('[name=_token]').first().inputValue();
        await context.request.post(base+'/login',{form:{_token:token,email:'admin@admin.com',password:'password'}});
        await page.goto(base+'/create-book');
        const book=await context.request.post(base+'/books',{form:{_token:await page.locator('[name=_token]').first().inputValue(),name:'Ảnh raster từ clipboard Windows '+Date.now(),description:'Actual Windows PNG bytes, prepared HTML then gallery upload'}});
        await page.goto(book.url()+'/create-page');
        await page.waitForFunction(()=>window.editor?.initialized);
        await page.locator('[name=name]').fill('Ảnh PNG thật — lấy từ clipboard Windows');
        await page.evaluate(html=>{
            window.editor.focus();
            const data=new DataTransfer(); data.setData('text/html',html);
            window.editor.getBody().dispatchEvent(new ClipboardEvent('paste',{clipboardData:data,bubbles:true,cancelable:true}));
        },html);
        await page.waitForFunction(()=>{
            const img=window.editor.getBody().querySelector('img');
            return img&&img.src.includes('/uploads/images/gallery/')&&img.complete&&img.naturalWidth>0&&window.editor.getBody().contentEditable==='true';
        });
        const before=await page.evaluate(()=>{
            const img=window.editor.getBody().querySelector('img');return {url:img.src,width:img.naturalWidth,height:img.naturalHeight};
        });
        assert.deepEqual(await (await context.request.get(before.url)).body(),expected);
        await Promise.all([page.waitForURL(/\/page\//),page.evaluate(()=>document.querySelector('form[data-page-id]').submit())]);
        await page.reload();
        const savedUrl=page.url();
        const img=page.locator('.page-content img');
        assert.equal(await img.count(),1);
        assert.deepEqual(await (await context.request.get(await img.getAttribute('src'))).body(),expected);
        await page.screenshot({path:path.join(root,'native-upload-saved.png'),fullPage:true});
        fs.writeFileSync(path.join(root,'native-upload-result.json'),JSON.stringify({savedUrl,imageBytes:expected.length,width:before.width,height:before.height,bytesIdentical:true,
            input:'Real Windows clipboard PNG captured by native helper without changing clipboard',pasteTest:'Prepared HTML inserted with synthetic ClipboardEvent',persisted:'Real HTTP gallery upload, save and reload'},null,2));
        console.log('PASS: actual Windows PNG -> prepared HTML -> gallery -> saved page; bytes identical');
        console.log('DEMO:',savedUrl);
    } finally {await browser.close();}
}
run().catch(error=>{console.error(error);process.exitCode=1;});
