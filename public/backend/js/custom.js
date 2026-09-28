$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
});

$(document).on('change', '.role-permission-switch', function() {
    let checked = $(this).is(':checked');
    let roleId = $(this).data('role-id');
    let permId = $(this).data('id');

    $.ajax({
        url: ajax_url.rolePermission,
        method: 'POST',
        data: {
            checked: checked,
            role_id: roleId,
            perm_id: permId
        },
        success: function(response) {
            console.log('Permission updated successfully');
        },
        error: function(xhr, status, error) {
            console.error('Error updating permission:', error);
        }
    });
});

function checkResponse(res, onSuccess = null) {
    const type    = res.success ? 'success' : 'danger';
    const sound   = res.success ? 'success.mp3' : 'error.mp3';
    const message = res.message ?? (res.success ? 'Əməliyyat uğurlu oldu' : 'Xəta baş verdi');

    jQuery.notify(
        { title: 'Bildiriş!', message: message },
        { type: type, delay: 3000, allow_dismiss: false,z_index: 99999 }
    );

    new Audio('/backend/sound/' + sound).play();

    if (res.success && onSuccess) onSuccess(res);
}
function checkError(xhr) {
    const errors = xhr.responseJSON?.errors;
    if (errors) {
        Object.values(errors).forEach(function (msgs) {
            jQuery.notify(
                { title: 'Bildiriş!', message: msgs[0] },
                { type: 'danger', delay: 3000, allow_dismiss: false,z_index: 99999 }
            );
        });
    } else {
        jQuery.notify(
            { title: 'Bildiriş!', message: xhr.responseJSON?.message ?? 'Server xətası' },
            { type: 'danger', delay: 3000, allow_dismiss: false,z_index: 99999 }
        );
    }
    new Audio('/backend/sound/error.mp3').play();
}
function ajaxPost(url, data, onSuccess = null, reload = true) {
    $.post(url, { ...data })
        .done(function (res) {
            checkResponse(res, function () {
                if (onSuccess) onSuccess(res);
                if (reload) setTimeout(() => location.reload(), 1000);
            });
        })
        .fail(function (xhr) {
            checkError(xhr);
        });
}
$(document).ready(function () {
    $('#addSize').on('click', function () {
        let sizeRow = $('.size-row:first').clone();

        sizeRow.find('input').val('');
        sizeRow.find('#addSize')
            .removeClass('btn-primary')
            .addClass('btn-danger removeSize')
            .html('-');
        $('.size_area').append(sizeRow);
    });

    $(document).on('click', '.removeSize', function () {
        if ($('.size-row').length > 1) {
            $(this).closest('.size-row').remove();
        } else {
            alert('Ən az bir ölçü qalmalıdır.');
        }
    });

    $('#addImage').on('click', function () {
        let imageRow = $('#imagerow').clone();

        imageRow.find('input').val('');

        imageRow.find('#addImage')
            .removeClass('btn-primary')
            .addClass('btn-danger removeImage')
            .html('-');

        $('.image_area').append(imageRow);
    });

    $(document).on('click', '.removeImage', function () {
        $(this).closest('.row').remove();
    });
});

