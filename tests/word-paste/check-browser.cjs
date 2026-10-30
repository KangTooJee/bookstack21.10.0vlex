const fs = require('fs');
const path = require('path');
const assert = require('node:assert/strict');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const base = process.env.TEST_BASE_URL || 'http://localhost:8080';
const output = path.resolve(__dirname, '../../../outputs/word-paste');
fs.mkdirSync(output, {recursive:true});
const png = fs.readFileSync(path.resolve(__dirname, '../word-import/fixture.png'));
const rtf = `{\\rtf1{\\*\\shppict{\\pict\\pngblip\\picw120\\pich80\n${png.toString('hex')}}}}`;
// Small vector icon in the standard WMF clipboard representation.
function wmfIcon() {
    const record = (fn, parameters) => {
        const buffer = Buffer.alloc(6 + parameters.length * 2);
        buffer.writeUInt32LE(buffer.length / 2); buffer.writeUInt16LE(fn, 4);
        parameters.forEach((value, i) => buffer.writeUInt16LE(value, 6 + i * 2));
        return buffer;
    };
    const records = [record(0x020b,[0,0]), record(0x020c,[100,100]), record(0x02fc,[0,0xc020,0x0070,0]), record(0x012d,[0]), record(0x041b,[90,90,10,10]), record(0,[])];
    const header = Buffer.alloc(18);
    header.writeUInt16LE(1); header.writeUInt16LE(9,2); header.writeUInt16LE(0x0300,4);
    header.writeUInt32LE(9 + records.reduce((sum, r) => sum + r.length / 2, 0),6);
    header.writeUInt16LE(1,10); header.writeUInt32LE(7,12);
    return Buffer.concat([header,...records]);
}
const vectorRtf = `{\\rtf1\\ansi{\\pict\\wmetafile8\\picw100\\pich100\\picwgoal1500\\pichgoal1500 ${wmfIcon().toString('hex')}}}`;
const html = `<html xmlns:w="urn:schemas-microsoft-com:office:word"><head><style>
@list l0:level1 {mso-level-number-format:roman-upper;}
@list l0:level2 {mso-level-number-format:roman-lower;}
</style></head><body><h2>Thử nghiệm dán Word vào New page</h2>
<p>Nội dung tiếng Việt có <b>chữ đậm</b> và <i>chữ nghiêng</i>.</p>
<p style="mso-list:l0 level1 lfo1"><!--[if !supportLists]><span style="mso-list:Ignore">IV.<span>&nbsp;</span></span><![endif]-->Mục La Mã thứ tư</p>
<p style="mso-list:l0 level2 lfo1"><span style="mso-list:Ignore">ii.&nbsp;</span>Mục con thứ hai</p>
<p style="mso-list:l0 level1 lfo1"><span style="mso-list:Ignore">V.&nbsp;</span>Mục La Mã thứ năm</p>
<table style="border-collapse:collapse;width:80%"><tr><td style="border:1px solid #333">Ô tiếng Việt</td><td>Ảnh nội bộ</td></tr></table>
<p><img src="file:///C:/Temp/clip_image001.png" alt="Ảnh dán từ Word" width="120" height="80"></p>
<p>Kết thúc nội dung thử nghiệm.</p></body></html>`;

async function run() {
    const browser = await chromium.launch({headless:true, channel:'chrome'});
    const context = await browser.newContext({viewport:{width:1440,height:1000}});
    const page = await context.newPage();
    const errors = [], external = [], checks = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('request', req => { if (/^https?:/.test(req.url()) && new URL(req.url()).origin !== new URL(base).origin) external.push(req.url()); });
    const pass = name => { checks.push(name); console.log(`PASS: ${name}`); };
    try {
        await page.goto(`${base}/login`);
        const token = await page.locator('[name=_token]').first().inputValue();
        const login = await context.request.post(`${base}/login`, {form:{_token:token,email:'admin@admin.com',password:'password'}});
        assert.ok(!login.url().endsWith('/login'), 'Login failed');
        await page.goto(`${base}/create-book`);
        const book = await context.request.post(`${base}/books`, {form:{_token:await page.locator('[name=_token]').first().inputValue(),name:`Thử nghiệm dán Word ${Date.now()}`,description:'Trang mẫu kiểm tra dán Word'}});
        const bookUrl = book.url().replace(/\/$/, '');
        console.log('Created test book:', bookUrl);
        await page.goto(`${bookUrl}/create-page`);
        await page.waitForFunction(() => window.editor && window.editor.initialized);
        await page.locator('[name=name]').fill('Dán Word — La Mã và ảnh nội bộ');
        await page.evaluate(() => {
            window.pasteNotifications = [];
            window.$events.listen('error', message => window.pasteNotifications.push(message));
            window.$events.listen('warning', message => window.pasteNotifications.push(message));
        });
        const paste = async (content, rich = '', image = null) => {
            await page.waitForFunction(() => !window.wordPasteDiagnostics || ['complete','failed'].includes(window.wordPasteDiagnostics.phase));
            return page.evaluate(({content,rich,image}) => {
            window.editor.focus();
            const doc = window.editor.getDoc();
            const data = new DataTransfer();
            data.setData('text/html', content);
            if (rich) data.setData('text/rtf', rich);
            if (image) data.items.add(new File([Uint8Array.from(atob(image), c => c.charCodeAt(0))], 'clipboard.png', {type:'image/png'}));
            doc.body.dispatchEvent(new ClipboardEvent('paste', {clipboardData:data,bubbles:true,cancelable:true}));
            }, {content,rich,image});
        };
        // Slow the real gallery upload to exercise both native and shortcut saves.
        let release;
        const hold = new Promise(resolve => { release = resolve; });
        await page.route('**/images/gallery', async route => { await hold; await route.continue(); });
        await paste(html, rtf);
        await page.waitForFunction(() => window.editor.getBody().getAttribute('contenteditable') === 'false');
        const draftUrl = page.url();
        await page.evaluate(() => { const form = document.querySelector('form[data-page-id]'); form.requestSubmit(); form.submit(); });
        assert.equal(page.url(), draftUrl);
        assert.ok((await page.evaluate(() => window.pasteNotifications)).some(x => x.includes('Đang lưu ảnh')));
        release();
        await page.waitForFunction(() => window.editor.getContent().includes('/uploads/images/gallery/'));
        await page.unroute('**/images/gallery');
        pass('Chặn lưu trang trong lúc ảnh đang tải lên');
        const inspect = () => page.evaluate(() => {
            const body = window.editor ? window.editor.getBody() : document.querySelector('.page-content');
            const top = body.querySelector('ol');
            return {html:body.innerHTML, text:body.textContent, start:top?.getAttribute('start'), style:top?.style.listStyleType,
                lower:body.querySelector('ol ol')?.style.listStyleType,
                image:body.querySelector('img')?.getAttribute('src'), count:body.querySelectorAll('img').length};
        });
        let state = await inspect();
        assert.equal(state.start, '4'); assert.equal(state.style, 'upper-roman'); assert.equal(state.lower, 'lower-roman');
        assert.ok(state.text.includes('Mục La Mã thứ năm')); assert.ok(state.text.includes('Ô tiếng Việt'));
        assert.equal(state.count, 1); assert.ok(!state.html.includes('file:///'));
        pass('Clipboard HTML + RTF: tiếng Việt, định dạng, bảng, IV/V và danh sách ii lồng nhau');
        // Missing bytes must not insert a document with silently lost images.
        const before = await page.evaluate(() => window.editor.getContent());
        await paste('<p class="MsoNormal">Không được mất nội dung<img src="file:///missing.png"></p>');
        await page.waitForFunction(() => window.pasteNotifications.some(x => x.includes('Clipboard chưa cung cấp đủ dữ liệu ảnh')));
        assert.ok(await page.evaluate(() => window.pasteNotifications.some(x => x.includes('copy từng ảnh trong Word') && x.includes('đặt con trỏ vào vị trí mong muốn'))));
        assert.equal(await page.evaluate(() => window.editor.getContent()), before);
        pass('Clipboard thiếu bytes ảnh: toast hướng dẫn copy từng ảnh tại con trỏ, giữ nguyên nội dung');
        await page.screenshot({path:path.join(output, 'editor.png'),fullPage:true});
        await Promise.all([page.waitForURL(/\/page\//), page.evaluate(() => document.querySelector('form[data-page-id]').submit())]);
        const pageUrl = page.url();
        await page.reload();
        state = await inspect();
        assert.equal(state.start, '4'); assert.equal(state.style, 'upper-roman'); assert.equal(state.lower, 'lower-roman');
        assert.ok(state.text.includes('Kết thúc nội dung thử nghiệm.')); assert.equal(state.count, 1);
        const image = await context.request.get(state.image);
        assert.equal(image.status(), 200); assert.deepEqual(await image.body(), png);
        pass('Lưu → tải lại: danh sách La Mã và ảnh giữ nguyên; bytes ảnh trong kho trùng clipboard');
        await page.screenshot({path:path.join(output, 'saved-page.png'),fullPage:true});
        await page.goto(`${pageUrl}/edit`);
        await page.waitForFunction(() => window.editor && window.editor.initialized);
        state = await inspect();
        assert.equal(state.start, '4'); assert.equal(state.style, 'upper-roman'); assert.equal(state.lower, 'lower-roman'); assert.equal(state.count, 1);
        pass('Mở lại màn sửa: giữ La Mã, cấp con và đường dẫn ảnh nội bộ');
        await paste('', '', png.toString('base64'));
        await page.waitForFunction(() => window.editor.getBody().querySelectorAll('img[src*="/uploads/images/"]').length === 2);
        pass('Dán ảnh đơn từ clipboard file, không cần HTML hoặc RTF');
        await paste('<p class="MsoNormal">Icon từ HTML + clipboard bitmap<img src="file:///icon.png"></p>', '', png.toString('base64'));
        await page.waitForFunction(() => window.editor.getBody().querySelectorAll('img[src*="/uploads/images/"]').length === 3);
        pass('Icon Word: HTML file:// + clipboard image/png');
        await paste('<p class="MsoNormal">Icon vector WMF từ Word<img src="file:///icon.wmf" width="32" height="32"></p>', vectorRtf);
        await page.waitForFunction(() => window.editor.getBody().querySelectorAll('img[src*="/uploads/images/"]').length === 4);
        pass('Icon WMF trong RTF → LibreOffice nội bộ → PNG trong kho ảnh');
        const csrf = await page.locator('[name=_token]').first().inputValue();
        const invalid = await context.request.post(`${base}/clipboard/word-images`, {
            headers:{'X-CSRF-TOKEN':csrf,Accept:'application/json'},
            multipart:{clipboard:{name:'clipboard.rtf',mimeType:'application/rtf',buffer:Buffer.from('{\\rtf1{\\field{\\*\\fldinst INCLUDEPICTURE "http://example.invalid/image.png"}}}')}}
        });
        assert.equal(invalid.status(), 422);
        const anonymous = await browser.newContext();
        const denied = await anonymous.request.post(`${base}/clipboard/word-images`, {maxRedirects:0});
        assert.ok([302,401,419].includes(denied.status()));
        await anonymous.close();
        pass('Endpoint clipboard yêu cầu đăng nhập/CSRF và từ chối RTF có field liên kết ngoài');
        // Base64 image and upload rejection use the same real paste integration.
        await page.evaluate(() => {
            window.pasteNotifications = [];
            window.$events.listen('error', message => window.pasteNotifications.push(message));
        });
        const initial = await page.evaluate(() => window.editor.getContent());
        await page.route('**/images/gallery', route => route.fulfill({status:403,contentType:'application/json',body:'{"message":"Forbidden"}'}));
        await paste(`<p class="MsoNormal">Upload failure<img src="data:image/png;base64,${png.toString('base64')}"></p>`);
        await page.waitForFunction(() => window.pasteNotifications.some(x => x.includes('Tải ảnh lên kho nội bộ thất bại')));
        assert.equal(await page.evaluate(() => window.editor.getContent()), initial);
        await page.unroute('**/images/gallery');
        pass('Lỗi quyền upload 403: báo lỗi, không chèn ảnh tạm hoặc mất nội dung');
        await Promise.all([page.waitForURL(url => !url.pathname.endsWith('/edit')), page.evaluate(() => document.querySelector('form[data-page-id]').submit())]);
        await page.reload();
        state = await inspect();
        assert.equal(state.count, 4); assert.equal(state.style, 'upper-roman');
        for (const src of await page.locator('.page-content img').evaluateAll(images => images.map(img => img.src))) assert.equal((await context.request.get(src)).status(), 200);
        await page.screenshot({path:path.join(output, 'saved-page.png'),fullPage:true});
        pass('Mở lại trang có ảnh đơn, bitmap icon và WMF: tất cả ảnh nội bộ tải được');
        assert.deepEqual(errors, []); assert.deepEqual(external, []);
        pass('Không lỗi JavaScript, không tải Internet và không gọi ứng dụng ngoài');
        fs.writeFileSync(path.join(output, 'result.json'), JSON.stringify({pageUrl,bookUrl,checks,external,errors,clipboard:'Synthetic ClipboardEvent with Word HTML and RTF; real HTTP upload/save/reopen',testedAt:new Date().toISOString()}, null, 2));
        console.log(`DEMO: ${pageUrl}`);
    } catch (error) {
        await page.screenshot({path:path.join(output, 'failure.png'),fullPage:true});
        console.error('URL:', page.url(), 'Body:', (await page.locator('body').innerText()).slice(0, 1500));
        console.error('Editor:', await page.evaluate(() => window.editor?.getContent()));
        throw error;
    } finally { await browser.close(); }
}
run().catch(error => { console.error(error); process.exitCode = 1; });
