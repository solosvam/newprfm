// crm.js

window.initTabContent = function () {
    new AcornIcons().replace();

    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    $('.select2').select2();
};

window.loadTab = function (url, params = {}) {
    $('#tabContent').html('<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>');

    $.get(url, params, function (html) {
        $('#tabContent').html(html);
        window.initTabContent();
    });
};
$(document).ready(function () {
    window.customerId = $('#customerTabs').data('customer-id');

    // Axtarış
    let searchTimer;
    $('#crm-search').on('input', function () {
        clearTimeout(searchTimer);
        const q = $(this).val().trim();

        if (!q) {
            $('#search-results').hide();
            return;
        }

        if (q.length < 5) {
            $('#search-results').hide();
            return;
        }

        searchTimer = setTimeout(function () {
            $.get(ajax_url.search.customer.crm, { q }, function (data) {
                if (!data.length) {
                    $('#search-results').hide();
                    return;
                }

                if (data.length === 1) {
                    window.location.href = 'crm/customer' + '/' + data[0].id;
                    return;
                }

                let html = '';
                data.forEach(function (item) {
                    const badge = item.type === 'int'
                        ? '<span class="badge bg-primary ms-1">Xaricdən daşınma</span>'
                        : `<span class="badge bg-warning text-dark ms-1">Ölkədaxili daşınma</span>`;

                    html += `<div class="p-3 border-bottom search-item" style="cursor:pointer;"
                              data-id="${item.id}" data-type="${item.type}">
                            ${item.fullname} ${badge}
                         </div>`;
                });

                $('#search-results').html(html).show();
            });
        }, 300);
    });

// Nəticəyə klik
    $(document).on('click', '.search-item', function () {

        window.location.href = 'crm/customer' + '/' + $(this).data('id');
    });

// Kənarı klikləyəndə bağla
    $(document).on('click', function (e) {
        if (!$(e.target).closest('#cashier-search, #search-results').length) {
            $('#search-results').hide();
        }
    });

    $(document).on('click', '#customerTabs .nav-link', function (e) {
        e.preventDefault();

        $('#customerTabs .nav-link').removeClass('active');
        $(this).addClass('active');

        const tab = $(this).data('tab');

        const url = ajax_url.customerTab;

        loadTab(url.replace(':id', customerId)
            .replace(':tab', tab)
        );
    });

    $('#customerTabs .nav-link.active').trigger('click');


    $(document).on('click', '#tabContent .pagination a', function (e) {
        e.preventDefault();
        loadTab($(this).attr('href'));
    });


    $('#smsModal').on('show.bs.modal', function (e) {

        $('#smsModalBody').html('<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>');

        $.get(ajax_url.crm.sms.replace(':id', customerId), function (html) {
            $('#smsModalBody').html(html);
        });
    });

    $('#resetPasswordBtn').on('click', function () {
        if (!confirm('Müştərinin şifrəsi yeniləniləcək və SMS göndəriləcək. Əminsiniz?')) return;

        const btn = $(this);

        btn.prop('disabled', true);

        $.post(ajax_url.crm.resetPassword.replace(':id', customerId))
            .done(function () {
                btn.prop('disabled', false);
                alert('Şifrə yeniləndi və SMS göndərildi');
            })
            .fail(function () {
                btn.prop('disabled', false);
                alert('Xəta baş verdi');
            });
    });

    $(document).on('click', '#refundSubmit', function () {
        const paymentId  = $('#refund_payment_id').val();
        const amount     = $('#refund_amount').val();
        const refundable = parseFloat($('#refund_refundable_amount').val());

        if (!amount) {
            alert('Məbləğ daxil edin');
            return;
        }

        if (parseFloat(amount) > refundable) {
            alert('Məbləğ qaytarıla bilən məbləğdən çox ola bilməz');
            return;
        }

        ajaxPost(ajax_url.crm.refund, {
            payment_id: paymentId,
            amount:     amount,
        }, function () {
            bootstrap.Modal.getInstance(document.getElementById('refundModal')).hide();
            setTimeout(() => location.reload(), 500);
        }, false);
    });

    $(document).on('click', '.btn-view-refunds', function () {
        const id = $(this).data('id');

        $('#refundViewBody').html('<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>');

        const modal = new bootstrap.Modal(document.getElementById('refundViewModal'));
        modal.show();

        $.get(ajax_url.crm.refund_payment.replace(':id', id), function (html) {
            $('#refundViewBody').html(html);
        });
    });


    $(document).on('click', '.btn-refund', function () {
        $('#refund_payment_id').val($(this).data('id'));
        $('#refund_payment_amount').val($(this).data('amount') + ' ' + $(this).data('currency'));
        $('#refund_refundable_amount').val($(this).data('refundable') + ' ' + $(this).data('currency'));
        $('#refund_amount').val('').attr('max', $(this).data('refundable'));

        bootstrap.Modal.getOrCreateInstance(document.getElementById('refundModal')).show();
    });

    $(document).on('click', '#refundSubmit', function () {
        const btn = $(this);
        const paymentId = $('#refund_payment_id').val();
        const amount = $('#refund_amount').val();

        if (!amount || parseFloat(amount) <= 0) {
            alert('Qaytarılacaq məbləği daxil edin.');
            return;
        }

        if (!confirm(amount + ' ₼ geri qaytarılsın?')) return;

        btn.prop('disabled', true);

        $.post('/admin/refund', {
            payment_id: paymentId,
            amount: amount
        }).done(function (response) {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('refundModal')).hide();
            alert(response.message);
            $('#customerTabs .nav-link[data-tab="payments"]').trigger('click');
        }).fail(function (xhr) {
            alert(xhr.responseJSON?.message || 'Geri ödəmə zamanı xəta baş verdi.');
        }).always(function () {
            btn.prop('disabled', false);
        });
    });

    $(document).on('click', '.btn-view-refunds', function () {
        const paymentId = $(this).data('id');
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('refundViewModal'));

        $('#refundViewBody').html('<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>');
        modal.show();

        $.get('/admin/refund/payment/' + paymentId, function (html) {
            $('#refundViewBody').html(html);
        }).fail(function () {
            $('#refundViewBody').html('<div class="alert alert-danger mb-0">Geri ödəmələr yüklənmədi.</div>');
        });
    });

});
