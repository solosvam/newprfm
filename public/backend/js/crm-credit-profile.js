(function () {
    'use strict';

    window.initCrmCreditProfile = function () {
        const form = $('#crmCreditProfileForm');
        if (!form.length || form.data('initialized')) return;
        form.data('initialized', true);
        const series = form.find('[name="id_card_series"]');
        const doubleSide = form.data('double-side-series') || [];
        const genericError = form.data('error-message');
        let request = null;
        let readVersion = 0;
        let busy = false;

        function setBusy(value) {
            busy = value;
            form.find('[type="submit"], [type="file"]').prop('disabled', value);
        }

        function toggleBackSide() {
            const back = form.find('[data-back-side]');
            const needed = doubleSide.includes(series.val());
            back.prop('hidden', !needed);
            // icazəsiz admində şəkil gizlidir, amma yüklənib (.crm-idcard-locked) — yenidən tələb olunmur
            back.find('[type="file"]').prop('required', needed && back.find('.crm-credit-preview').prop('hidden') && !back.find('.crm-idcard-locked').length);
            if (!needed) {
                back.find('.is-invalid').removeClass('is-invalid');
                back.find('[data-error]').text('');
            }
        }
        series.on('change', toggleBackSide);
        toggleBackSide();

        form.find('.crm-credit-preview').on('error', function () {
            $(this).prop('hidden', true);
            const input = $(this).siblings('[type="file"]');
            input.prop('required', input.attr('name') === 'id_card_front' || doubleSide.includes(series.val()));
        });

        form.find('[type="file"]').on('change', async function () {
            const input = this;
            let file = input.files && input.files[0];
            if (!file) return;
            setBusy(true);
            try {
                const blob = await window.IdCardCropper.open(file);
                if (!blob || !form[0].isConnected) {
                    input.value = '';
                    return;
                }
                file = new File([blob], input.name + '.jpg', { type: 'image/jpeg' });
                const transfer = new DataTransfer();
                transfer.items.add(file);
                input.files = transfer.files;
                const preview = $(input).siblings('.crm-credit-preview');
                const previous = preview.data('objectUrl');
                if (previous) URL.revokeObjectURL(previous);
                const url = URL.createObjectURL(file);
                preview.attr('src', url).data('objectUrl', url).prop('hidden', false);
                $(input).siblings('.crm-idcard-locked').prop('hidden', true);
                $(input).removeClass('is-invalid');
                form.find('[data-error="' + input.name + '"]').text('');
                toggleBackSide();
            } catch (error) {
                input.value = '';
                ocrMessage('bad', [genericError]);
                return;
            } finally {
                setBusy(false);
            }
            if (input.name === 'id_card_front') readIdCard(file);
        });

        const ocrStatus = form.find('[data-ocr-status]');

        function ocrMessage(kind, lines) {
            ocrStatus.prop('hidden', false).removeClass('alert-info alert-success alert-warning').addClass({ loading: 'alert-info', ok: 'alert-success', bad: 'alert-warning' }[kind]).empty();
            lines.forEach(function (line) { ocrStatus.append($('<div>').text(line)); });
        }

        function fieldLabel(el) {
            return el.attr('aria-label') || el.closest('[class*="col-md-"]').find('label').first().text().replace('*', '').trim();
        }

        function readIdCard(file) {
            ocrMessage('loading', [form.data('ocr-reading')]);
            const data = new FormData();
            data.append('image', file);
            data.append('_token', form.find('input[name="_token"]').val());

            if (request) request.abort();
            const version = ++readVersion;
            setBusy(true);
            request = $.ajax({ url: form.data('ocr-url'), type: 'POST', data: data, processData: false, contentType: false })
                .done(function (response) {
                    if (version !== readVersion || !form[0].isConnected) return;
                    const filled = [];
                    $.each(response.fields || {}, function (name, value) {
                        const el = form.find('[name="' + name + '"]');
                        if (!el.length || !value) return;
                        // müştərinin özü yazdığını dəyişmirik — yalnız boş və ya əvvəl OCR-ın doldurduğu xana
                        if (el.val() && !el.hasClass('is-autofilled') && el.val() !== value) return;
                        if (el.is('select')) {
                            if (!el.find('option').filter(function () { return this.value === value; }).length) return;
                            el.val(value).trigger('change');
                        } else {
                            el.val(value);
                        }
                        el.addClass('is-autofilled').removeClass('is-invalid');
                        form.find('[data-error="' + name + '"]').text('');
                        filled.push(fieldLabel(el));
                    });

                    const lines = [];
                    let kind = 'ok';
                    if (!response.is_id_card && !filled.length) {
                        lines.push(form.data('ocr-not-card'));
                        kind = 'bad';
                    } else if (filled.length) {
                        lines.push(String(form.data('ocr-done')).replace(':fields', filled.join(', ')));
                    } else {
                        lines.push(form.data('ocr-nothing'));
                        kind = 'bad';
                    }
                    if (response.name_match === false && response.card_name) {
                        lines.push(String(form.data('ocr-name-mismatch')).replace(':name', response.card_name));
                        kind = 'bad';
                    }
                    ocrMessage(kind, lines);
                })
                .fail(function (xhr) {
                    if (xhr.statusText === 'abort' || version !== readVersion || !form[0].isConnected) return;
                    const json = xhr.responseJSON || {};
                    ocrMessage('bad', [json.message || genericError].concat(json.debug ? [json.debug] : [])); // debug yalnız APP_DEBUG=true
                }).always(function () {
                    if (version === readVersion) setBusy(false);
                });
        }

        // müştəri OCR-ın doldurduğu xananı düzəldəndə işarə götürülür
        form.on('input change', '.is-autofilled', function (event) {
            if (event.isTrigger) return;
            $(this).removeClass('is-autofilled');
        });

        form.on('submit', function (event) {
            if (busy) event.preventDefault();
        });
    };
})();
