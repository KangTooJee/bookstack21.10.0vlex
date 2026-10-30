const fs = require('fs');
const path = require('path');
const assert = require('node:assert/strict');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const output = path.resolve(__dirname, '../../../outputs/word-paste/customer-fidelity');
const sample = process.env.CUSTOMER_SAMPLE || path.resolve(__dirname, '../../../outputs/word-paste/customer-sample.docx');
fs.mkdirSync(output, {recursive:true});
const base = process.env.TEST_BASE_URL || 'http://localhost:8080';
async function run() {
    const browser = await chromium.launch({channel:'chrome',headless:true});
    const context = await browser.newContext({viewport:{width:1440,height:1050}});
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const checks = [];
    const pass = text => { checks.push(text); console.log('PASS:',text); };
    const token = async () => page.locator('[name=_token]').first().inputValue();
    const editorReady = async () => page.waitForFunction(() => window.editor?.initialized);
    try {
        await page.goto(`${base}/login`);
        await context.request.post(`${base}/login`, {form:{_token:await token(),email:'admin@admin.com',password:'password'}});
        await page.goto(`${base}/create-book`);
        const created = await context.request.post(`${base}/books`, {form:{_token:await token(),name:`Đối chiếu mẫu Quỹ Công Đoàn ${Date.now()}`,description:'Kiểm tra file mẫu khách hàng, font, viền, ảnh và xuất Word'}});
        const bookUrl = created.url();
        await page.goto(`${bookUrl}/import-word`);
        const imported = await context.request.post(`${bookUrl}/import-word`, {multipart:{_token:await token(),name:'Quy chế Quỹ Công Đoàn — mẫu đối chiếu',document:{name:'customer.docx',mimeType:'application/vnd.openxmlformats-officedocument.wordprocessingml.document',buffer:fs.readFileSync(sample)}}});
        assert.match(imported.url(), /\/draft\/\d+/);
        await page.goto(imported.url());
        await editorReady();
        const inspect = async () => page.evaluate(() => {
            const body = window.editor ? window.editor.getBody() : document.querySelector('.page-content');
            const doc = body.querySelector('[data-office-document]');
            const win = body.ownerDocument.defaultView;
            const heading = Array.from(body.querySelectorAll('p')).find(p => p.textContent.trim() === 'QUY ĐỊNH');
            const cells = Array.from(body.querySelectorAll('td')).map(cell => ({border:win.getComputedStyle(cell).borderTopStyle,text:cell.textContent.trim().slice(0,60)}));
            return {wrapper:!!doc,font:win.getComputedStyle(doc).fontFamily,size:win.getComputedStyle(doc).fontSize,page:doc.getAttribute('data-office-page'),headingAlign:heading && win.getComputedStyle(heading).textAlign,
                images:Array.from(body.querySelectorAll('img')).map(img => ({src:img.src,width:img.naturalWidth,height:img.naturalHeight,display:win.getComputedStyle(img).display})),cells,html:body.innerHTML,text:body.textContent};
        });
        let state = await inspect();
        assert.ok(state.font.includes('Times New Roman')); assert.equal(state.size, '18.6667px'); assert.equal(state.headingAlign, 'center');
        assert.equal(state.page, '11907,16840,1134,1134,1134,1701');
        assert.ok(state.cells.every(cell => cell.border === 'none'));
        assert.equal(state.images.length, 2, 'Letterhead rules should survive import');
        assert.ok(state.images.every(img => img.width > 1 && img.height > 0));
        assert.equal(await page.evaluate(() => {
            const el=Array.from(window.editor.getBody().querySelectorAll('span')).find(el=>el.childElementCount===0 && el.textContent.includes('CÔNG ĐOÀN CÔNG TY'));
            return window.editor.getWin().getComputedStyle(el).fontSize;
        }), '17.3333px');
        fs.writeFileSync(path.join(output,'imported-editor.html'), state.html);
        pass('File mẫu: Times New Roman 14pt, căn giữa tiêu đề, hai bảng không viền, hai nét kẻ, lề A4 gốc');
        await page.screenshot({path:path.join(output,'imported-editor.png'),fullPage:true});
        await Promise.all([page.waitForURL(/\/page\//),page.evaluate(() => document.querySelector('form[data-page-id]').submit())]);
        const importedUrl = page.url();
        await page.reload();
        state = await inspect();
        assert.equal(state.size, '18.6667px'); assert.ok(state.cells.every(cell => cell.border === 'none'));
        await page.locator('[data-office-document]').screenshot({path:path.join(output,'imported-content.png')});
        pass('Lưu và mở trang xem: giữ font, căn lề, bảng không tự thêm viền');
        const download = await context.request.get(`${importedUrl}/export/word`);
        assert.equal(download.status(), 200);
        fs.writeFileSync(path.join(output,'customer-roundtrip.docx'), await download.body());
        pass('Xuất lại DOCX từ nội dung đã lưu');

        // Regression cases use actual editor events and real gallery uploads.
        await page.goto(`${bookUrl}/create-page`); await editorReady();
        await page.locator('[name=name]').fill('Dán Google Docs, bảng viền và ảnh — kiểm tra hồi quy');
        await page.evaluate(() => {
            window.pasteNotices = [];
            window.$events.listen('warning', text => window.pasteNotices.push(text));
            window.$events.listen('error', text => window.pasteNotices.push(text));
        });
        const paste = (html,rtf='') => page.evaluate(({html,rtf}) => {
            window.editor.focus();
            window.editor.selection.select(window.editor.getBody(),true); window.editor.selection.collapse(false);
            const clipboard = new DataTransfer(); clipboard.setData('text/html',html); if(rtf) clipboard.setData('text/rtf',rtf);
            window.editor.getBody().dispatchEvent(new ClipboardEvent('paste',{clipboardData:clipboard,bubbles:true,cancelable:true}));
        },{html,rtf});
        await paste('<b id="docs-internal-guid-123" style="font-weight:normal"><p style="font-family:Times New Roman;font-size:14pt;text-align:justify;text-indent:28pt;line-height:150%">Văn bản Google Docs chỉ có chữ.</p><img src="https://example.invalid/spacer.gif" width="1" height="1"></b>');
        await page.waitForFunction(() => window.editor.getBody().textContent.includes('Văn bản Google Docs'));
        assert.deepEqual(await page.evaluate(() => window.pasteNotices), []);
        pass('Google Docs chỉ chữ + spacer 1×1: không cảnh báo thiếu ảnh');
        await paste('<html><head><style>p.MsoNormal{font-family:"Times New Roman";font-size:14pt;text-align:justify;text-indent:28pt;line-height:150%} td.source{border-top:2pt double #123456;border-right:1pt solid #aabbcc;border-bottom:3pt dashed #445566;border-left:none;padding:3pt}</style></head><body><p class="MsoNormal">Đoạn từ Word có font, thụt lề và giãn dòng.</p><table style="width:1800px"><tr><td class="source">Viền gốc từng cạnh</td><td style="border:none">Ô không viền</td></tr></table></body></html>');
        await page.waitForFunction(() => window.editor.getBody().textContent.includes('Viền gốc từng cạnh'));
        let formatting = await page.evaluate(() => {
            const body=window.editor.getBody(),win=window.editor.getWin();
            const p=Array.from(body.querySelectorAll('p')).find(p=>p.textContent.includes('Đoạn từ Word'));
            const cell=body.querySelector('td');
            const ps=win.getComputedStyle(p),cs=win.getComputedStyle(cell);
            return {font:ps.fontFamily,size:ps.fontSize,indent:ps.textIndent,lineHeight:ps.lineHeight,align:ps.textAlign,border:cs.borderTopStyle,borderWidth:cs.borderTopWidth,right:cs.borderRightColor,bottom:cs.borderBottomStyle,left:cs.borderLeftStyle};
        });
        assert.ok(formatting.font.includes('Times New Roman')); assert.equal(formatting.size,'18.6667px'); assert.equal(formatting.indent,'37.3333px'); assert.equal(formatting.align,'justify');
        assert.equal(formatting.lineHeight,'28px'); assert.equal(formatting.border,'double'); assert.equal(formatting.bottom,'dashed'); assert.equal(formatting.left,'none');
        pass('Dán Word: inline CSS từ class, font 14pt, justify, indent 28pt, line-height 150%, viền khác nhau từng cạnh');
        const png=fs.readFileSync(path.resolve(__dirname,'../word-import/fixture.png'));
        const rt=`{\\rtf1{\\pict\\pngblip ${png.toString('hex')}}{\\pict\\pngblip ${png.toString('hex')}}}`;
        await paste('<p>Chữ và hai ảnh cùng lúc<img src="file:///one.png" width="0" height="0"><img src="file:///two.png"></p>',rt);
        await page.waitForFunction(() => window.editor.getBody().querySelectorAll('img[src*="/uploads/images/"]').length===2);
        await page.waitForFunction(() => window.editor.getBody().contentEditable==='true');
        assert.ok(await page.evaluate(()=>Array.from(window.editor.getBody().querySelectorAll('img')).every(img=>img.complete&&img.naturalWidth>0&&img.getBoundingClientRect().width>0&&img.getBoundingClientRect().height>0)));
        pass('Dán chữ + hai ảnh: lưu gallery trước, ảnh decode được và hiện ngay trong edit, gỡ kích thước 0');
        await Promise.all([page.waitForURL(/\/page\//),page.evaluate(()=>document.querySelector('form[data-page-id]').submit())]);
        const pasteUrl=page.url();
        await page.reload();
        const cell=page.locator('.page-content td').first();
        assert.equal(await cell.evaluate(el=>getComputedStyle(el).borderTopStyle),'double');
        assert.equal(await cell.evaluate(el=>getComputedStyle(el).borderBottomStyle),'dashed');
        assert.ok(await page.locator('.page-content').evaluate(el=>el.scrollWidth>el.clientWidth));
        assert.ok(await page.locator('.page-content').evaluate(el=>getComputedStyle(el).overflowX==='auto'));
        pass('Trang xem: giữ viền bảng từng cạnh; bảng 1800px cuộn trong nội dung, không đè sidebar');
        await page.screenshot({path:path.join(output,'paste-viewer.png'),fullPage:true});
        assert.deepEqual(errors,[]);
        fs.writeFileSync(path.join(output,'result.json'),JSON.stringify({importedUrl,pasteUrl,checks,formatting,errors,clipboard:'Synthetic clipboard events; real customer DOCX import, gallery upload, save, reopen and DOCX export'},null,2));
        console.log('DEMO:',importedUrl);
    } catch(error) {
        await page.screenshot({path:path.join(output,'failure.png'),fullPage:true});
        console.error('URL',page.url(),await page.locator('body').innerText());
        throw error;
    } finally {await browser.close();}
}
run().catch(error=>{console.error(error);process.exitCode=1;});
