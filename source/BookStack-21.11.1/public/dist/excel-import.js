(() => {
    'use strict';
    const input = document.getElementById('excel-document');
    if (!input) return;
    const validate = () => {
        const file = input.files[0];
        const maximum = Number(input.dataset.maxBytes);
        input.setCustomValidity(file && file.size > maximum
            ? `File Excel vượt quá ${maximum / 1024} KB. Hãy tách sheet/vùng dữ liệu thành file nhỏ hơn.` : '');
    };
    input.addEventListener('change', () => { validate(); input.reportValidity(); });
    input.form.addEventListener('submit', event => {
        validate();
        if (!input.checkValidity()) { event.preventDefault(); input.reportValidity(); }
    });
})();
