(() => {
    'use strict';
    const errorText = 'Clipboard chưa cung cấp đủ dữ liệu ảnh nên chưa dán đoạn này. Hãy dán phần chữ trước; sau đó copy từng ảnh trong Word, đặt con trỏ vào vị trí mong muốn rồi nhấn Ctrl+V để dán ảnh.';

    function romanValue(text) {
        const values = {I:1, V:5, X:10, L:50, C:100, D:500, M:1000};
        let value = 0;
        const upper = text.toUpperCase();
        for (let i = 0; i < upper.length; i++) {
            const current = values[upper[i]];
            value += current < (values[upper[i + 1]] || 0) ? -current : current;
        }
        return value;
    }

    // Word puts visible list markers in mso-list:Ignore spans rather than <li>.
    // Convert these before TinyMCE removes Office-specific styles and comments.
    function normalize(html) {
        html = html.replace(/<!--\[if !supportLists\]-->([\s\S]*?)<!--\[endif\]-->/gi, '$1');
        html = html.replace(/<!--\[if !supportLists\]>([\s\S]*?)<!\[endif\]-->/gi, '$1');
        const doc = new DOMParser().parseFromString(html, 'text/html');
        // Office and Google Docs put significant formatting in <head> rules.
        // Materialize it before the editor strips the head/classes.
        const cascade = new Map();
        for (const style of doc.querySelectorAll('style')) {
            try {
                const sheet = new CSSStyleSheet();
                sheet.replaceSync(style.textContent);
                for (const rule of sheet.cssRules) {
                    if (!rule.selectorText || !rule.style) continue;
                    for (const selector of rule.selectorText.split(',')) {
                        let elements;
                        try { elements = doc.querySelectorAll(selector); } catch (_) { continue; }
                        const specificity = (selector.match(/#/g) || []).length * 10000 + (selector.match(/[.\[:]/g) || []).length * 100 + (selector.match(/(?:^|\s|>)[a-z]/gi) || []).length;
                        for (const el of elements) {
                            const values = cascade.get(el) || new Map();
                            for (const property of rule.style) {
                                const weight = specificity + (rule.style.getPropertyPriority(property) ? 1000000 : 0);
                                if (!values.has(property) || values.get(property).weight <= weight) values.set(property, {weight, value:rule.style.getPropertyValue(property)});
                            }
                            cascade.set(el, values);
                        }
                    }
                }
            } catch (_) { /* Invalid Office CSS does not discard the fragment. */ }
        }
        for (const [el, values] of cascade) {
            const inline = el.getAttribute('style') || '';
            el.setAttribute('style', Array.from(values, ([property, rule]) => `${property}:${rule.value}`).join(';') + ';' + inline);
        }
        for (const table of doc.body.querySelectorAll('table')) {
            for (const cell of table.querySelectorAll('td,th')) {
                if (cell.closest('table') !== table) continue;
                const border = Number(table.getAttribute('border')) > 0 ? `${Number(table.getAttribute('border'))}px solid black` : '0';
                const padding = Number(table.getAttribute('cellpadding')) || 0;
                cell.setAttribute('style', `border:${border};padding:${padding}px;` + (cell.getAttribute('style') || ''));
            }
        }
        for (const el of doc.querySelectorAll('p[align],td[align],th[align],div[align]')) {
            if (/^(left|right|center|justify)$/.test(el.getAttribute('align'))) el.style.textAlign = el.getAttribute('align');
        }
        for (const font of Array.from(doc.querySelectorAll('font'))) {
            const span = doc.createElement('span');
            if (font.hasAttribute('face')) span.style.fontFamily = font.getAttribute('face');
            if (font.hasAttribute('color')) span.style.color = font.getAttribute('color');
            const size = [0,7.5,9.75,12,13.5,18,24,36][Number(font.getAttribute('size'))];
            if (size) span.style.fontSize = `${size}pt`;
            span.setAttribute('style', span.getAttribute('style') + ';' + (font.getAttribute('style') || ''));
            while (font.firstChild) span.appendChild(font.firstChild);
            font.replaceWith(span);
        }
        // Some Word icons have only a VML image inside an Office conditional
        // comment, without the usual HTML <img> fallback.
        if (!doc.body.querySelector('img')) {
            const walker = doc.createTreeWalker(doc.body, NodeFilter.SHOW_COMMENT);
            const comments = [];
            while (walker.nextNode()) comments.push(walker.currentNode);
            for (const comment of comments) {
                const image = /<v:imagedata\b[^>]*\bsrc=["']([^"']+)["']/i.exec(comment.data);
                if (!image) continue;
                const img = doc.createElement('img');
                img.setAttribute('src', image[1]);
                comment.replaceWith(img);
            }
        }
        const css = Array.from(doc.querySelectorAll('style')).map(el => el.textContent).join('\n');
        const definitions = new Map();
        css.replace(/@list\s+(l\d+):level(\d+)\s*\{([^}]+)\}/gi, (_, list, level, rules) => {
            const format = /mso-level-number-format\s*:\s*([\w-]+)/i.exec(rules);
            if (format) definitions.set(`${list}:${level}`, format[1].toLowerCase());
            return '';
        });
        const stack = [];
        for (const p of Array.from(doc.body.querySelectorAll('p'))) {
            const list = /mso-list\s*:\s*(l\d+)\s+level(\d+)\s+(lfo\d+)/i.exec(p.getAttribute('style') || '');
            if (!list) { stack.length = 0; continue; }
            const marker = Array.from(p.querySelectorAll('span')).find(el => /mso-list\s*:\s*ignore/i.test(el.getAttribute('style') || ''));
            if (!marker) { stack.length = 0; continue; }
            const label = marker.textContent.trim().replace(/[.()\s\u00a0]+/g, '');
            const rawFormat = definitions.get(`${list[1]}:${list[2]}`);
            const format = ({'roman-upper':'upper-roman', 'roman-lower':'lower-roman', 'alpha-upper':'upper-alpha', 'alpha-lower':'lower-alpha'})[rawFormat] || rawFormat;
            const roman = /^(upper|lower)-roman$/.test(format || '') || (!format && /^[ivxlcdm]+$/i.test(label));
            const bullet = format === 'bullet' || /^[\u2022\u00b7\uf0b7\u25cf\u25cb\u00a7]$/.test(label);
            const style = roman ? (label === label.toUpperCase() ? 'upper-roman' : 'lower-roman') :
                bullet ? 'disc' : /^[a-z]$/i.test(label) ? (label === label.toUpperCase() ? 'upper-alpha' : 'lower-alpha') : 'decimal';
            const value = roman ? romanValue(label) : /^\d+$/.test(label) ? Number(label) : /^[a-z]$/i.test(label) ? label.toLowerCase().charCodeAt(0) - 96 : 1;
            const level = Number(list[2]);
            const key = `${list[1]}:${list[3]}`;
            while (stack.length && (stack[stack.length - 1].level > level || stack[stack.length - 1].key !== key)) stack.pop();
            let entry = stack[stack.length - 1];
            if (!entry || entry.level !== level || entry.style !== style || entry.container !== p.parentNode) {
                const ol = doc.createElement(bullet ? 'ul' : 'ol');
                ol.style.listStyleType = style;
                if (!bullet && value !== 1) ol.setAttribute('start', value);
                if (entry && entry.level < level && entry.last) entry.last.appendChild(ol);
                else p.parentNode.insertBefore(ol, p);
                entry = {level, key, style, list:ol, last:null, container:p.parentNode};
                stack.push(entry);
            }
            marker.remove();
            const li = doc.createElement('li');
            li.setAttribute('style', p.getAttribute('style') || '');
            if (!bullet) li.setAttribute('value', value);
            while (p.firstChild) li.appendChild(p.firstChild);
            entry.list.appendChild(li);
            entry.last = li;
            p.remove();
        }

        doc.querySelectorAll('script,style,link,meta,iframe,object,embed,svg,form,input,button,video,audio,source').forEach(el => el.remove());
        const styles = /^(color|background-color|font-family|font-size|font-weight|font-style|font-variant|text-decoration(?:-\w+)?|text-align|text-indent|vertical-align|white-space|line-height|letter-spacing|word-spacing|width|height|max-width|max-height|border(?:-[a-z-]+)?|padding(?:-(?:top|right|bottom|left))?|margin(?:-(?:top|right|bottom|left))?|list-style-type|table-layout|page-break-before|page-break-after|break-before|break-after|display|float|clear)$/;
        for (const el of [doc.body, ...doc.body.querySelectorAll('*')]) {
            for (const attr of Array.from(el.attributes)) {
                if (/^on/i.test(attr.name) || /^(srcset|background|srcdoc)$/i.test(attr.name) || /^(?:javascript|vbscript|data):/i.test(attr.value.trim()) && attr.name !== 'src') el.removeAttribute(attr.name);
            }
            for (const property of Array.from(el.style)) {
                if (!styles.test(property) || /url\s*\(|expression\s*\(|\\/i.test(el.style.getPropertyValue(property))) el.style.removeProperty(property);
            }
            if (el.tagName === 'IMG') el.removeAttribute('srcset');
        }
        // Ignore invisible clipboard spacers/tracking pixels (including the
        // 1x1 image embedded in the supplied customer document).
        for (const img of doc.body.querySelectorAll('img')) {
            const width = parseFloat(img.getAttribute('width') || img.style.width);
            const height = parseFloat(img.getAttribute('height') || img.style.height);
            const remoteSpacer = /^https?:/i.test(img.getAttribute('src') || '') && ((width <= 1 && height <= 1) || img.style.display === 'none');
            if (!img.alt && ((width === 1 && height === 1) || remoteSpacer)) img.remove();
        }
        if (!doc.body.querySelector('[data-office-document]')) {
            const wrapper = doc.createElement('div');
            wrapper.className = 'office-document';
            wrapper.setAttribute('data-office-document', 'true');
            wrapper.setAttribute('style', doc.body.getAttribute('style') || '');
            while (doc.body.firstChild) wrapper.appendChild(doc.body.firstChild);
            doc.body.appendChild(wrapper);
        }
        return doc.body;
    }

    // RTF clipboard uses hexadecimal PNG/JPEG payloads in balanced pict groups.
    // Ignore shape metadata and alternative WMF/EMF representations.
    function rtfPictures(rtf) {
        const pictures = [];
        const pattern = /\{\\pict\b/g;
        let match;
        while ((match = pattern.exec(rtf))) {
            let depth = 1, end = pattern.lastIndex;
            for (; end < rtf.length && depth; end++) {
                if (rtf[end] === '\\') { end++; continue; }
                if (rtf[end] === '{') depth++;
                if (rtf[end] === '}') depth--;
            }
            const group = rtf.slice(pattern.lastIndex, end - 1);
            pattern.lastIndex = end;
            pictures.push(group);
        }
        return pictures;
    }

    function rtfImages(rtf) {
        const images = [];
        for (const group of rtfPictures(rtf)) {
            const type = /\\pngblip\b/.test(group) ? 'image/png' : /\\jpegblip\b/.test(group) ? 'image/jpeg' : null;
            if (!type) continue;
            const flat = group.replace(/\{[^{}]*\}/g, '').replace(/\\[a-z]+(?:-?\d+)?\s?/gi, '').replace(/\s/g, '');
            if (!flat || flat.length % 2 || /[^\da-f]/i.test(flat)) continue;
            const bytes = new Uint8Array(flat.length / 2);
            for (let i = 0; i < bytes.length; i++) bytes[i] = parseInt(flat.slice(i * 2, i * 2 + 2), 16);
            images.push(new Blob([bytes], {type}));
        }
        return images;
    }

    async function persistImages(body, clipboard, pageId) {
        const nodes = Array.from(body.querySelectorAll('img'));
        if (!nodes.length) return;
        const managed = src => {
            const url = new URL(src, window.location.href);
            return url.origin === window.location.origin && /\/uploads\/images\//.test(url.pathname);
        };
        const local = nodes.filter(img => {
            const src = img.getAttribute('src') || '';
            return !managed(src) && !/^data:image\/(png|jpeg|gif|webp);base64,/i.test(src);
        });
        let binaries = rtfImages(clipboard.getData('text/rtf') || '');
        async function withoutSpacers(blobs) {
            const result = [];
            for (const blob of blobs) {
                try {
                    const bitmap = await createImageBitmap(blob);
                    const spacer = bitmap.width === 1 && bitmap.height === 1;
                    bitmap.close();
                    if (!spacer) result.push(blob);
                } catch (_) { result.push(blob); }
            }
            return result;
        }
        binaries = await withoutSpacers(binaries);
        const sources = [...new Set(local.map(img => img.getAttribute('src')))];
        if (binaries.length !== sources.length) {
            const files = Array.from(clipboard.items || []).filter(item => item.kind === 'file').map(item => item.getAsFile()).filter(Boolean);
            const raster = (files.length ? files : Array.from(clipboard.files || [])).filter(file => /^image\/(png|jpeg|gif|webp)$/.test(file.type));
                const usable = await withoutSpacers(raster);
                if (usable.length === sources.length) binaries = usable;
        }
        // Browser clipboard often contains Word WMF/EMF icons but no PNG.
        // Convert only inline picture groups on the LAN server, then upload
        // through the normal permission-checked BookStack gallery endpoint.
        if (sources.length && binaries.length !== sources.length) {
            const groups = rtfPictures(clipboard.getData('text/rtf') || '');
            if (groups.length) {
                const data = new FormData();
                data.append('clipboard', new Blob([`{\\rtf1\\ansi ${groups.map(group => `{\\pict${group}}`).join('\\par ')}}`], {type:'application/rtf'}), 'clipboard.rtf');
                try {
                    const response = await window.$http.post(window.baseUrl('/clipboard/word-images'), data);
                    const converted = new DOMParser().parseFromString(response.data.html, 'text/html');
                    binaries = await withoutSpacers(await Promise.all(Array.from(converted.querySelectorAll('img')).map(async img => (await fetch(img.getAttribute('src'))).blob())));
                } catch (error) {
                    // Preserve text even if a vector format cannot be converted.
                    console.warn('Clipboard vector conversion failed', error);
                    binaries = [];
                }
            }
        }
        const bySource = new Map(binaries.length === sources.length ? sources.map((src, i) => [src, binaries[i]]) : []);
        const uploaded = new Map();
        for (const img of nodes) {
            const src = img.getAttribute('src') || '';
            const url = new URL(src, window.location.href);
            // Keep only already-managed gallery images without uploading again.
            if (url.origin === window.location.origin && /\/uploads\/images\//.test(url.pathname)) {
                img.setAttribute('src', url.href);
                continue;
            }
            if (!uploaded.has(src)) {
                let blob = bySource.get(src);
                if (!blob && /^data:image\/(png|jpeg|gif|webp);base64,/i.test(src)) blob = await (await fetch(src)).blob();
                // Web clipboard images can be accessible HTTP(S)/blob sources.
                // Read in the user's browser, then retain only the internal URL.
                if (!blob && /^(https?:|blob:)/i.test(src)) {
                    try {
                        const response = await fetch(src, {credentials:'omit', referrerPolicy:'no-referrer'});
                        if (response.ok && /^image\/(png|jpeg|gif|webp)/i.test(response.headers.get('Content-Type') || '')) blob = await response.blob();
                    } catch (_) { /* CORS or unavailable bytes: mark this image only. */ }
                }
                if (!blob) {
                    throw new Error(errorText);
                }
                const ext = blob.type === 'image/jpeg' ? 'jpg' : blob.type.split('/')[1];
                const data = new FormData();
                data.append('file', blob, `word-paste-${Date.now()}.${ext}`);
                data.append('uploaded_to', pageId);
                const response = await window.$http.post(window.baseUrl('/images/gallery'), data);
                uploaded.set(src, response.data.url);
            }
            img.setAttribute('src', uploaded.get(src));
            const loaded = new Image();
            loaded.src = uploaded.get(src);
            await loaded.decode();
            if (!loaded.naturalWidth || !loaded.naturalHeight) throw new Error('Stored image is unreadable');
            // Never retain Word's hidden/zero-size image box after upload.
            for (const dimension of ['width', 'height']) {
                if (parseFloat(img.getAttribute(dimension)) <= 0) img.removeAttribute(dimension);
                if (parseFloat(img.style[dimension]) <= 0) img.style.removeProperty(dimension);
            }
            img.style.maxWidth = '100%';
            if (img.style.display === 'none') img.style.removeProperty('display');
            // Word often wraps images in links to temporary files.
            const link = img.closest('a');
            if (link && /^(file:|cid:)/i.test(link.getAttribute('href') || '')) link.removeAttribute('href');
        }
    }

    document.addEventListener('editor-tinymce::pre-init', event => {
        const config = event.detail.config;
        const asset = new URL(document.querySelector('script[src*="/dist/word-paste.js"]').src);
        asset.pathname = asset.pathname.replace('word-paste.js', 'office-content.css');
        config.content_css = [].concat(config.content_css || [], asset.href);
        config.extended_valid_elements = (config.extended_valid_elements || '').replace(/,?div\[[^\]]*\]/g, '') + ',div[drawio-diagram|class|style|data-office-document|data-office-page]';
        config.valid_children = '+div[p|h1|h2|h3|h4|h5|h6|blockquote|pre|img|table|div|ol|ul]';
        config.paste_remove_styles = false;
    });

    document.addEventListener('editor-tinymce::setup', event => {
        const editor = event.detail.editor;
        const component = event.target;
        const pageId = component.getAttribute('option:wysiwyg-editor:page-id');
        let busy = false;
        editor.on('init', () => {
            const inputWindow = editor.getWin();
            const inputDocument = editor.getDoc();
            let lastUserRange = null;
            let shortcutRange = null;
            let plainShortcut = false;
            let shortcutAt = 0;
            const readUserRange = () => {
                const selection = inputWindow.getSelection();
                if (!selection || !selection.rangeCount) return null;
                const range = selection.getRangeAt(0);
                const parent = range.startContainer.nodeType === 1 ? range.startContainer : range.startContainer.parentElement;
                if (!editor.getBody().contains(range.commonAncestorContainer) || parent?.closest('#mcepastebin')) return null;
                return range.cloneRange();
            };
            const rememberRange = () => {
                if (busy) return;
                const range = readUserRange();
                if (range) lastUserRange = range;
            };
            const beforePasteShortcut = event => {
                const isV = event.code === 'KeyV' || event.key?.toLowerCase() === 'v' || event.keyCode === 86;
                const modifiedV = isV && (event.ctrlKey || event.metaKey);
                const shiftInsert = event.shiftKey && (event.key === 'Insert' || event.keyCode === 45);
                if (!modifiedV && !shiftInsert) return;
                rememberRange();
                shortcutRange = lastUserRange?.cloneRange() || null;
                shortcutAt = Date.now();
                plainShortcut = modifiedV && event.shiftKey;
                // TinyMCE 4's keydown handler creates #mcepastebin, moves the
                // selection there, then deletes it on keyup. A cloned DOM Range
                // in that temporary node collapses to the end when it is removed.
                // Keep the native browser paste default, but bypass that handler.
                if (!plainShortcut) event.stopImmediatePropagation();
            };
            inputWindow.addEventListener('keydown', beforePasteShortcut, true);
            inputDocument.addEventListener('selectionchange', rememberRange);
            const form = component.closest('form');
            const submit = form.submit;
            const waiting = () => window.$events.emit('warning', 'Đang lưu ảnh từ Word vào kho nội bộ. Vui lòng chờ rồi lưu trang.');
            const guard = event => { if (busy) { event.preventDefault(); event.stopImmediatePropagation(); waiting(); } };
            form.addEventListener('submit', guard, true);
            form.submit = function () { if (busy) waiting(); else submit.call(form); };
            const paste = async event => {
                const source = event.clipboardData;
                if (!source || event.shiftKey) return;
                const recentShortcut = Date.now() - shortcutAt < 3000;
                if (recentShortcut && plainShortcut) {
                    shortcutAt = 0;
                    return;
                }
                const insertionRange = (recentShortcut && shortcutRange ? shortcutRange : readUserRange() || lastUserRange)?.cloneRange();
                shortcutAt = 0;
                // Native DataTransfer becomes unreadable once the paste event
                // returns. Capture bytes and formats before any async work.
                const formats = new Map(['text/html','text/plain','text/rtf'].map(type => [type, source.getData(type)]));
                const captured = Array.from(source.items || []).filter(item => item.kind === 'file').map(item => item.getAsFile()).filter(Boolean);
                const files = captured.length ? captured : Array.from(source.files || []);
                const clipboard = {types:Array.from(source.types || []), getData:type => formats.get(type) || '', files,
                    items:files.map(file => ({kind:'file',type:file.type,getAsFile:() => file}))};
                let html = clipboard.getData('text/html');
                const imageItems = Array.from(clipboard.items || []).filter(item => item.kind === 'file' && /^image\//.test(item.type));
                const rtf = clipboard.getData('text/rtf');
                const pictures = rtfPictures(rtf);
                if (!html && (imageItems.length || pictures.length)) {
                    const text = document.createElement('p');
                    text.textContent = clipboard.getData('text/plain');
                    const count = imageItems.length || pictures.length;
                    html = (text.textContent ? text.outerHTML : '') + Array.from({length:count}, (_, i) => `<p><img src="cid:clipboard-${i}"></p>`).join('');
                }
                if (!html) return;
                event.preventDefault();
                event.stopImmediatePropagation();
                if (busy) { waiting(); return; }
                if (!insertionRange) {
                    window.$events.emit('warning', 'Hãy click vào vị trí cần dán trong nội dung rồi dán lại.');
                    return;
                }
                const insertionAnchor = insertionRange.startContainer;
                busy = true;
                const wasEditable = editor.getBody().getAttribute('contenteditable');
                editor.getBody().setAttribute('contenteditable', 'false');
                editor.setProgressState(true);
                try {
                    const body = normalize(html);
                    window.wordPasteDiagnostics = {types:Array.from(clipboard.types || []), images:body.querySelectorAll('img').length, files:imageItems.length, rtfPictures:pictures.length,phase:'uploading'};
                    await persistImages(body, clipboard, pageId);
                    // Restore editability and focus first. Restoring a bookmark
                    // in a non-editable body can silently insert at its start.
                    if (!editor.getBody().contains(insertionAnchor)) throw new Error('Vị trí dán đã thay đổi trong khi tải ảnh. Hãy đặt lại con trỏ rồi dán lại.');
                    editor.getBody().setAttribute('contenteditable', wasEditable || 'true');
                    const existingImages = new Set(editor.getBody().querySelectorAll('img'));
                    window.wordPasteDiagnostics.phase = 'inserting';
                    editor.undoManager.transact(() => {
                        editor.focus();
                        editor.selection.setRng(insertionRange);
                        editor.insertContent(body.innerHTML);
                    });
                    const insertedImages = Array.from(editor.getBody().querySelectorAll('img')).filter(img => !existingImages.has(img));
                    await Promise.all(insertedImages.map(img => img.decode()));
                    window.wordPasteDiagnostics.phase = 'complete';
                    editor.fire('change');
                } catch (error) {
                    if (window.wordPasteDiagnostics) window.wordPasteDiagnostics.phase = 'failed';
                    const message = error && typeof error.message === 'string' ? error.message : '';
                    if (message === errorText) {
                        window.$events.emit('warning', message);
                    } else {
                        window.$events.emit('error', message.startsWith('Vị trí dán đã thay đổi') ? message : 'Chưa dán nội dung. Tải ảnh lên kho nội bộ thất bại; kiểm tra quyền tạo ảnh và dung lượng rồi thử lại.');
                    }
                    console.error('Word paste failed', error);
                } finally {
                    editor.getBody().setAttribute('contenteditable', wasEditable || 'true');
                    editor.setProgressState(false);
                    busy = false;
                }
            };
            inputWindow.addEventListener('paste', paste, true);
            editor.on('remove', () => {
                inputWindow.removeEventListener('paste', paste, true);
                inputWindow.removeEventListener('keydown', beforePasteShortcut, true);
                inputDocument.removeEventListener('selectionchange', rememberRange);
                form.removeEventListener('submit', guard, true);
                form.submit = submit;
            });
        });
    });
})();
