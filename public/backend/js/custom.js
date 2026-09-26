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

