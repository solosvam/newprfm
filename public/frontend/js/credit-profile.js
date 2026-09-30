$(function () {
    'use strict';

    const form = $('#creditProfileForm');
    if (!form.length) return;

    // Vəsiqə seriyası — select2, axtarışda tam uyğunluq birinci ("AZ" → AZ, sonra AZE, AZN…)
    const series = form.find('.credit-series-select');
    if (series.length && $.fn.select2) {
        let term = '';
        series.select2({
            placeholder: series.data('placeholder'),
            width: '100%',
            dropdownCssClass: 'credit-series-dropdown',
            matcher(params, data) {
                term = (params.term || '').trim().toLocaleUpperCase('az');
                if (!term) return data;
                return data.text.toLocaleUpperCase('az').includes(term) ? data : null;
            },
            sorter(results) {
                if (!term) return results;
                const rank = (text) => {
                    const t = text.toLocaleUpperCase('az');
                    return t === term ? 0 : t.startsWith(term) ? 1 : 2;
                };
                return results.slice().sort((a, b) => rank(a.text) - rank(b.text));
            },
        }).on('change', function () {
            $(this).removeClass('is-invalid');
            form.find('[data-error="id_card_series"]').text('');
            toggleBackSide();
        });
    }

    // Arxa üz yalnız köhnə vəsiqə (AZE) üçün: seriyaya görə göstər/gizlət
    const photosRow = form.find('.credit-photos-row');
    const doubleSide = photosRow.data('double-side-series') || [];
    function toggleBackSide() {
        const back = form.find('[data-back-side]');
        const needed = doubleSide.includes(series.val());
        const input = back.find('.credit-file');
        const hasImage = !back.find('.credit-preview').prop('hidden');
        back.prop('hidden', !needed);
        input.prop('required', needed && !hasImage);
        if (!needed) {
            input.removeClass('is-invalid');
            back.find('.invalid-feedback').text('');
        }
    }

    const changeLabel = form.data('change-label');
    const genericError = form.data('error-message');

    // Şəkil faylı açıla bilmirsə, boş preview göstər.
    form.find('.credit-preview').on('error', function () {
        const selectLabel = form.data('select-label');

        form.find('.credit-preview').on('error', function () {
            const card = $(this).closest('.credit-photo');
            $(this).prop('hidden', true);
            card.find('.credit-empty').prop('hidden', false);
            card.find('.credit-change').text(selectLabel);
            card.find('.credit-file').prop('required', true);   // fayl serverdə yoxdursa, yenidən yükləmək məcburidir
        }).on('load', function () {
            const card = $(this).closest('.credit-photo');
            $(this).prop('hidden', false);
            card.find('.credit-empty').prop('hidden', true);
        });
    }).on('load', function () {
        const card = $(this).closest('.credit-photo');
        $(this).prop('hidden', false);
        card.find('.credit-empty').prop('hidden', true);
    });

    form.find('.credit-change').on('click', function () {
        $(this).closest('.credit-photo').find('input[type="file"]').trigger('click');
    });

    // Fayl seçilən kimi kəsmə modalı (id-card-cropper.js); kəsilmiş JPEG inputa qoyulur.
    // input.files proqramla dəyişəndə "change" təkrar gəlmir.
    form.find('.credit-file').on('change', async function () {
        const input = this;
        let file = input.files && input.files[0];
        if (!file) return;

        if (window.IdCardCropper) {
            const blob = await window.IdCardCropper.open(file);
            if (!blob) {                     // ləğv edildi — seçim götürülür, köhnə şəkil qalır
                input.value = '';
                return;
            }
            file = new File([blob], input.name + '.jpg', { type: 'image/jpeg' });
            const transfer = new DataTransfer();
            transfer.items.add(file);
            input.files = transfer.files;
        }

        $(input).removeClass('is-invalid');
        form.find('[data-error="' + input.name + '"]').text('');

        const card = $(input).closest('.credit-photo');
        const preview = card.find('.credit-preview');
        const previous = preview.data('objectUrl');

        if (previous) URL.revokeObjectURL(previous);

        const objectUrl = URL.createObjectURL(file);
        preview.attr('src', objectUrl).data('objectUrl', objectUrl).prop('hidden', false);
        card.find('.credit-empty').prop('hidden', true);
        card.find('.credit-change').text(changeLabel);

        if (input.name === 'id_card_front' && form.data('ocr-url')) readIdCard(file);
    });

    /* ---------- OCR: ön üzdən ata adı, FİN, seriya, nömrə ---------- */
    const ocrStatus = form.find('[data-ocr-status]');

    function ocrMessage(kind, lines) {
        ocrStatus.prop('hidden', false).removeClass('is-loading is-ok is-bad').addClass('is-' + kind).empty();
        lines.forEach(function (line) { ocrStatus.append($('<div>').text(line)); });
    }

    function fieldLabel(el) {
        const id = el.attr('id');
        return el.attr('aria-label') || form.find('label[for="' + id + '"]').first().text().replace('*', '').trim();
    }

    function readIdCard(file) {
        ocrMessage('loading', [form.data('ocr-reading')]);
        const data = new FormData();
        data.append('image', file);
        data.append('_token', form.find('input[name="_token"]').val());

        $.ajax({ url: form.data('ocr-url'), type: 'POST', data: data, processData: false, contentType: false })
            .done(function (response) {
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
                const json = xhr.responseJSON || {};
                ocrMessage('bad', [json.message || genericError].concat(json.debug ? [json.debug] : [])); // debug yalnız APP_DEBUG=true
            });
    }

    // müştəri OCR-ın doldurduğu xananı düzəldəndə işarə götürülür
    form.on('input change', '.is-autofilled', function (event) {
        if (event.isTrigger) return;
        $(this).removeClass('is-autofilled');
    });

    form.on('submit', function (event) {
        event.preventDefault();

        form.find('.is-invalid').removeClass('is-invalid');
        form.find('.invalid-feedback').text('');

        const button = $('#creditSubmit').prop('disabled', true);

        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: new FormData(this),
            processData: false,
            contentType: false,
            success: function (response) {
                $.each(response.images || {}, function (field, url) {
                    if (!url) return;

                    const card = form.find('[data-photo="' + field + '"]');
                    const preview = card.find('.credit-preview');
                    const previous = preview.data('objectUrl');

                    if (previous) {
                        URL.revokeObjectURL(previous);
                        preview.removeData('objectUrl');
                    }

                    preview.attr('src', url + '?v=' + Date.now()).prop('hidden', false);
                    card.find('.credit-empty').prop('hidden', true);
                    card.find('.credit-change').text(changeLabel);
                });

                $.notify(response.message, 'success');
                if (form.data('return-url')) window.location.assign(form.data('return-url'));
            },
            error: function (xhr) {
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    const errors = xhr.responseJSON.errors;

                    $.each(errors, function (field, messages) {
                        form.find('[name="' + field + '"]').addClass('is-invalid');
                        form.find('[data-error="' + field + '"]').text(messages[0]);
                    });

                    // select2-də əsl <select> gizlidir — fokus onun görünən sahəsinə
                    const firstInvalid = form.find('.is-invalid').first();
                    const select2Box = firstInvalid.next('.select2-container').find('.select2-selection');
                    (select2Box.length ? select2Box : firstInvalid).trigger('focus');
                    $.notify(Object.values(errors)[0][0], 'error');
                    return;
                }

                $.notify(genericError, 'error');
            },
            complete: function () {
                button.prop('disabled', false);
            }
        });
    });
});
