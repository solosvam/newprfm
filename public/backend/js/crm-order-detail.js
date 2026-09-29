$(document).ready(function () {
    // Ödəniş linki: kopyala
    $(document).on('click', '[data-pay-link-copy]', function () {
        const btn = this;
        const input = btn.closest('.input-group').querySelector('[data-pay-link-url]');
        const done = () => {
            btn.textContent = 'Kopyalandı ✓';
            setTimeout(() => { btn.textContent = 'Kopyala'; }, 1500);
        };
        if (navigator.clipboard?.writeText) {
            navigator.clipboard.writeText(input.value).then(done);
        } else {
            input.select();
            document.execCommand('copy');
            done();
        }
    });

    // Ödəniş linki: SMS ilə göndər (CrmController::sendPayLink)
    $(document).on('click', '[data-pay-link-sms]', function () {
        const btn = $(this);
        const label = btn.text();
        btn.prop('disabled', true).text('Göndərilir…');
        $.post(btn.data('url'))
            .done(res => window.checkResponse(res))
            .fail(xhr => window.checkError(xhr))
            .always(() => btn.prop('disabled', false).text(label));
    });

});
