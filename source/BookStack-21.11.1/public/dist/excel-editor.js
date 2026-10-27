(() => {
    'use strict';
    const contentCss = document.currentScript.dataset.contentCss;
    // Use BookStack's existing public hooks before TinyMCE initializes. Styling
    // only the parent page cannot override the iframe's 840px content limit.
    document.addEventListener('editor-tinymce::pre-init', event => {
        const config = event.detail.config;
        config.content_css = [].concat(config.content_css || [], contentCss);
        config.body_class = `${config.body_class || ''} excel-editor-content`;
    });
    document.addEventListener('editor-tinymce::setup', event => {
        const editor = event.detail.editor;
        editor.on('init', () => enableDrag(editor));
    });

    function enableDrag(editor) {
        const doc = editor.getDoc();
        const win = editor.getWin();
        const body = editor.getBody();
        const scroller = doc.scrollingElement || doc.documentElement;
        doc.documentElement.classList.add('excel-editor-root');
        body.setAttribute('aria-label', 'Nội dung Excel. Giữ chuột trái kéo bảng để cuộn ngang; giữ Shift để chọn chữ.');
        let pointer = null;
        let suppressClick = false;
        const stop = event => {
            if (!pointer || (event && event.pointerId !== pointer.id)) return;
            const id = pointer.id;
            suppressClick = pointer.dragging;
            pointer = null;
            body.classList.remove('excel-dragging');
            if (body.hasPointerCapture(id)) body.releasePointerCapture(id);
        };
        const down = event => {
            if (event.pointerType !== 'mouse' || event.button !== 0 || event.shiftKey) return;
            if (!event.target.closest('table') || event.target.closest('a, button, input, textarea, select, [contenteditable="false"], .mce-resizehandle')) return;
            if (scroller.scrollWidth <= scroller.clientWidth) return;
            suppressClick = false;
            pointer = {id:event.pointerId, x:event.clientX, y:event.clientY, scroll:scroller.scrollLeft, dragging:false};
        };
        const move = event => {
            if (!pointer || event.pointerId !== pointer.id) return;
            const dx = event.clientX - pointer.x;
            if (!pointer.dragging) {
                if (Math.abs(dx) < 5 || Math.abs(dx) < Math.abs(event.clientY - pointer.y)) return;
                pointer.dragging = true;
                body.setPointerCapture(pointer.id);
                body.classList.add('excel-dragging');
                const selection = win.getSelection();
                if (selection) selection.removeAllRanges();
            }
            event.preventDefault();
            event.stopImmediatePropagation();
            scroller.scrollLeft = pointer.scroll - dx;
        };
        const preventSelection = event => {
            if (pointer && pointer.dragging) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        };
        const click = event => {
            if (suppressClick) {
                event.preventDefault(); event.stopImmediatePropagation(); suppressClick = false;
            }
        };
        const blur = () => stop();
        doc.addEventListener('pointerdown', down, true);
        doc.addEventListener('pointermove', move, {capture:true, passive:false});
        doc.addEventListener('mousemove', preventSelection, true);
        doc.addEventListener('dragstart', preventSelection, true);
        doc.addEventListener('click', click, true);
        doc.addEventListener('pointerup', stop, true);
        doc.addEventListener('pointercancel', stop, true);
        body.addEventListener('lostpointercapture', blur);
        win.addEventListener('blur', blur);
        editor.on('remove', () => {
            stop();
            doc.removeEventListener('pointerdown', down, true);
            doc.removeEventListener('pointermove', move, true);
            doc.removeEventListener('mousemove', preventSelection, true);
            doc.removeEventListener('dragstart', preventSelection, true);
            doc.removeEventListener('click', click, true);
            doc.removeEventListener('pointerup', stop, true);
            doc.removeEventListener('pointercancel', stop, true);
            body.removeEventListener('lostpointercapture', blur);
            win.removeEventListener('blur', blur);
        });
    }
})();
