(() => {
    'use strict';
    const region = document.querySelector('.excel-page .page-content');
    if (!region) return;
    let pointer = null;
    let suppressClick = false;
    const stop = event => {
        if (!pointer || event.pointerId !== pointer.id) return;
        suppressClick = pointer.dragging;
        if (region.hasPointerCapture(pointer.id)) region.releasePointerCapture(pointer.id);
        region.classList.remove('excel-dragging');
        pointer = null;
    };
    region.addEventListener('pointerdown', event => {
        if (event.pointerType !== 'mouse' || event.button !== 0 || event.shiftKey) return;
        if (!event.target.closest('table') || event.target.closest('a, button, input, textarea, select, [contenteditable="true"]')) return;
        if (region.scrollWidth <= region.clientWidth) return;
        suppressClick = false;
        pointer = {id: event.pointerId, x: event.clientX, y: event.clientY, scroll: region.scrollLeft, dragging: false};
    });
    window.addEventListener('pointermove', event => {
        if (!pointer || event.pointerId !== pointer.id) return;
        const dx = event.clientX - pointer.x;
        if (!pointer.dragging) {
            if (Math.abs(dx) < 5 || Math.abs(dx) < Math.abs(event.clientY - pointer.y)) return;
            pointer.dragging = true;
            region.setPointerCapture(pointer.id);
            region.classList.add('excel-dragging');
            const selection = window.getSelection();
            if (selection) selection.removeAllRanges();
        }
        event.preventDefault();
        region.scrollLeft = pointer.scroll - dx;
    }, {passive: false});
    window.addEventListener('pointerup', stop);
    window.addEventListener('pointercancel', stop);
    window.addEventListener('blur', () => { if (pointer) stop({pointerId: pointer.id}); });
    region.addEventListener('lostpointercapture', () => {
        region.classList.remove('excel-dragging');
        pointer = null;
    });
    region.addEventListener('click', event => {
        if (suppressClick) {
            event.preventDefault();
            event.stopPropagation();
            suppressClick = false;
        }
    }, true);
    region.addEventListener('dragstart', event => {
        if (event.target.closest('table')) event.preventDefault();
    });
})();
