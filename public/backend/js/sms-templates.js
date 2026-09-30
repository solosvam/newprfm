// SMS şablonları: simvol və SMS sayı (160 simvol = 1 SMS)
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.sms-template-text').forEach(function (textarea) {
        const form = textarea.closest('form');
        const charCount = form.querySelector('.sms-char-count');
        const partCount = form.querySelector('.sms-part-count');
        const warning = form.querySelector('.sms-limit-warning');

        function update() {
            const length = Array.from(textarea.value).length;
            const parts = Math.max(1, Math.ceil(length / 160));
            charCount.textContent = length;
            partCount.textContent = parts;
            warning.classList.toggle('d-none', length <= 160);
            warning.textContent = length > 160 ? '160 simvol keçildi — ' + parts + '-ci SMS-ə keçdi.' : '';
        }

        textarea.addEventListener('input', update);
        update();
    });
});
