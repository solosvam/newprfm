$(function () {
    'use strict';

    const form = $('#creditProfileForm');
    if (!form.length) return;

    const changeLabel = form.data('change-label');
    const genericError = form.data('error-message');

    form.find('.credit-change').on('click', function () {
        $(this).closest('.credit-photo').find('input[type="file"]').trigger('click');
    });

    form.find('.credit-file').on('change', function () {
        const file = this.files && this.files[0];
        if (!file) return;

        const card = $(this).closest('.credit-photo');
        const preview = card.find('.credit-preview');
        const previous = preview.data('objectUrl');

        if (previous) URL.revokeObjectURL(previous);

        const objectUrl = URL.createObjectURL(file);
        preview.attr('src', objectUrl).data('objectUrl', objectUrl).prop('hidden', false);
        card.find('.credit-empty').prop('hidden', true);
        card.find('.credit-change').text(changeLabel);
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
                    card.find('.credit-file').val('').prop('required', false).removeClass('is-visible');
                });

                window.parfumshopNotify(response.message, 'success');
            },
            error: function (xhr) {
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    const errors = xhr.responseJSON.errors;

                    $.each(errors, function (field, messages) {
                        form.find('[name="' + field + '"]').addClass('is-invalid');
                        form.find('[data-error="' + field + '"]').text(messages[0]);
                    });

                    form.find('.is-invalid').first().trigger('focus');
                    window.parfumshopNotify(Object.values(errors)[0][0], 'error');
                    return;
                }

                window.parfumshopNotify(genericError, 'error');
            },
            complete: function () {
                button.prop('disabled', false);
            }
        });
    });
});
