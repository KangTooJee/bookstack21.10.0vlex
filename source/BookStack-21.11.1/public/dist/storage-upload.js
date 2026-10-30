(() => {
    'use strict';
    const form = document.getElementById('storage-upload');
    if (!form) return;
    const input = document.getElementById('storage-files');
    const results = document.getElementById('storage-results');
    const refresh = document.getElementById('storage-refresh');
    function render(row, name, state, errors = [], pendingLabel = 'Chờ upload') {
        const extension = name.includes('.') ? name.split('.').pop().toUpperCase() : '';
        const type = /^(DOC|DOCX)$/.test(extension) ? 'Word' : /^(XLS|XLSX)$/.test(extension) ? 'Excel' : extension === 'PDF' ? 'PDF' : 'File';
        row.className = 'vlex-upload-result vlex-upload-result--' + state;
        row.setAttribute('role', 'listitem');
        const icon = document.createElement('span');
        icon.className = 'vlex-upload-result__icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = state === 'success' ? '✓' : state === 'error' ? '×' : '…';
        const content = document.createElement('div');
        content.className = 'vlex-upload-result__content';
        const filename = document.createElement('strong');
        filename.className = 'vlex-upload-result__name';
        filename.textContent = name;
        const kind = document.createElement('span');
        kind.className = 'vlex-upload-result__type';
        kind.textContent = type + (extension && type.toUpperCase() !== extension ? ' · ' + extension : '');
        content.append(filename, kind);
        const status = document.createElement('span');
        status.className = 'vlex-upload-result__status';
        status.textContent = state === 'success' ? 'Đã lưu' : state === 'error' ? 'Thất bại' : pendingLabel;
        if (state === 'error') {
            const reasons = document.createElement('ul');
            reasons.className = 'vlex-upload-result__errors';
            errors.forEach(message => {
                const reason = document.createElement('li');
                reason.textContent = message;
                reasons.appendChild(reason);
            });
            content.appendChild(reasons);
        }
        row.replaceChildren(icon, content, status);
    }
    function preflight(file) {
        const errors = [];
        if (!/\.(docx?|xlsx?|pdf)$/i.test(file.name)) errors.push('Định dạng không được phép.');
        if (file.size > Number(input.dataset.maxBytes)) errors.push('Vượt giới hạn dung lượng/file.');
        if (!file.size) errors.push('File rỗng.');
        return errors;
    }
    function show(file) {
        const row = document.createElement('div');
        const errors = preflight(file);
        render(row, file.name, errors.length ? 'error' : 'pending', errors);
        results.appendChild(row);
        return row;
    }
    input.addEventListener('change', () => {
        results.replaceChildren();
        Array.from(input.files).forEach(show);
    });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const files = Array.from(input.files);
        if (!files.length) return;
        const button = form.querySelector('button');
        button.disabled = true;
        input.disabled = true;
        results.replaceChildren();
        const rows = files.map(show);
        // One file per request preserves individual errors and avoids aggregate PHP POST limits.
        for (const [index, file] of files.entries()) {
            const row = rows[index];
            if (preflight(file).length) continue;
            render(row, file.name, 'pending', [], 'Đang kiểm tra…');
            const body = new FormData(form);
            body.delete('files[]');
            body.append('files[]', file);
            try {
                const response = await fetch(form.action, {method: 'POST', body, headers: {'Accept': 'application/json'}});
                if (!response.ok) throw new Error(response.status === 413 ? 'File vượt giới hạn máy chủ.' : 'Yêu cầu thất bại (HTTP ' + response.status + ').');
                const data = await response.json();
                const result = data.results[0];
                render(row, file.name, result.status === 'stored' ? 'success' : 'error', result.errors);
                if (result.status === 'stored') refresh.hidden = false;
            } catch (error) {
                render(row, file.name, 'error', ['Chưa xác nhận được kết quả upload. ' + error.message + ' Kiểm tra danh sách file trước khi thử lại.']);
                refresh.hidden = false;
            }
        }
        button.disabled = false;
        input.disabled = false;
    });
})();
