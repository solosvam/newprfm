// crm.js

window.initTabContent = function () {
    new AcornIcons().replace();
    if (window.initCrmCreditProfile) window.initCrmCreditProfile();

    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    // select2 konteynerinin özü də "select2" class-ı alır — yalnız <select>-ləri götürürük
    $('select.select2').select2();
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
            $.get(ajax_url.search.customer.crm, { q }, function (response) {
                const data = response.results || [];
                if (!data.length) {
                    showNotFound(response);
                    return;
                }

                if (data.length === 1) {
                    window.location.href = ajax_url.crm.customer.replace(':id', data[0].id);
                    return;
                }

                let html = '';
                data.forEach(function (item) {
                    html += `<div class="p-3 border-bottom search-item" style="cursor:pointer;" data-id="${Number(item.id)}"></div>`;
                });

                $('#search-results').html(html).show();
                // Ad textContent ilə yazılır — HTML kimi yox (XSS olmasın)
                $('#search-results .search-item').each(function (i) { this.textContent = data[i].fullname; });
            });
        }, 300);
    });

    // Tapılmadı: ad/FİN — sadəcə mesaj; nömrə — "Yeni müştəri yarat" (modal nömrə ilə açılır)
    function showNotFound(response) {
        const box = $('#search-results').empty();
        const message = $('<div class="p-3 crm-search-empty"></div>');
        if (!response.kind) {
            message.text('Axtarış formatı: mobil nömrə (0103227575), Ad Soyad və ya .FİN');
            box.append(message).show();
            return;
        }
        message.text(response.kind === 'phone' ? 'Bu nömrə ilə müştəri tapılmadı.' : 'Müştəri tapılmadı.');
        box.append(message);
        if (response.kind === 'phone' && response.mobile) {
            const create = $('<button type="button" class="btn btn-primary btn-sm mt-2 d-block">Yeni müştəri yarat</button>');
            create.on('click', function () { openCreateCustomer(response.mobile); });
            message.append(create);
        }
        box.show();
    }

    function openCreateCustomer(mobile) {
        const modalEl = document.getElementById('createCustomerModal');
        if (!modalEl) return;
        const form = modalEl.querySelector('form');
        form.reset();
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        modalEl.querySelector('.alert')?.remove();
        form.querySelector('[name="mobile"]').value = mobile;
        $('#search-results').hide();
        const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        modal.show();
        modalEl.addEventListener('shown.bs.modal', () => form.querySelector('[name="name"]').focus(), { once: true });
    }

    // Yaratma xətası olanda modal yenidən açılır (daxil edilənlər old() ilə qalır)
    const reopen = document.querySelector('#createCustomerModal[data-open-on-load]');
    if (reopen) (bootstrap.Modal.getInstance(reopen) || new bootstrap.Modal(reopen)).show();

    // Operator panelindən (WhatsApp): /admin/crm?new_customer=994501234567 — "Yeni müştəri" nömrə ilə açılır
    const newCustomer = new URLSearchParams(window.location.search).get('new_customer');
    if (!reopen && newCustomer && /^994[1-9]\d{8}$/.test(newCustomer)) openCreateCustomer(newCustomer);
    $('#createCustomerModal form').on('submit', function () { $(this).find('button.btn-primary').prop('disabled', true); });

// Nəticəyə klik
    $(document).on('click', '.search-item', function () {

        window.location.href = ajax_url.crm.customer.replace(':id', $(this).data('id'));
    });

// Kənarı klikləyəndə bağla
    $(document).on('click', function (e) {
        if (!$(e.target).closest('#crm-search, #search-results').length) {
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

    $(document).on('click', '.btn-refund', function () {
        $('#refund_payment_id').val($(this).data('id'));
        $('#refund_payment_amount').val($(this).data('amount') + ' ' + $(this).data('currency'));
        $('#refund_refundable_amount').val($(this).data('refundable') + ' ' + $(this).data('currency'));
        $('#refund_amount').val('').attr('max', $(this).data('refundable'));

        const modal = new bootstrap.Modal(document.getElementById('refundModal'));
        modal.show();
    });

    $(document).on('click', '#refundSubmit', function () {
        const btn = $(this);
        const paymentId = $('#refund_payment_id').val();
        const amount = parseFloat($('#refund_amount').val());
        const refundable = parseFloat($('#refund_amount').attr('max'));

        if (!amount || amount <= 0) {
            jQuery.notify(
                {title: 'Bildiriş!', message: 'Qaytarılacaq məbləği daxil edin.'},
                {type: 'danger', delay: 3000, allow_dismiss: false, z_index: 99999}
            );
            return;
        }

        if (amount > refundable) {
            jQuery.notify(
                {title: 'Bildiriş!', message: 'Məbləğ qaytarıla bilən məbləğdən çox ola bilməz.'},
                {type: 'danger', delay: 3000, allow_dismiss: false, z_index: 99999}
            );
            return;
        }

        if (!confirm(amount.toFixed(2) + ' ₼ geri qaytarılsın?')) return;

        btn.prop('disabled', true);

        ajaxPost(ajax_url.crm.refund, {
            payment_id: paymentId,
            amount: amount.toFixed(2),
        }, function () {
            bootstrap.Modal.getInstance(document.getElementById('refundModal')).hide();
            $('#customerTabs .nav-link[data-tab="payments"]').trigger('click');
        }, false);

        setTimeout(function () {
            btn.prop('disabled', false);
        }, 1000);
    });

    $(document).on('click', '.btn-view-refunds', function () {
        const paymentId = $(this).data('id');
        const modal = new bootstrap.Modal(document.getElementById('refundViewModal'));
        modal.show();

        $('#refundViewBody').html('<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>');
        modal.show();

        $.get(ajax_url.crm.refund_payment.replace(':id', paymentId), function (html) {
            $('#refundViewBody').html(html);
        }).fail(function () {
            $('#refundViewBody').html('<div class="alert alert-danger mb-0">Geri ödəmələr yüklənmədi.</div>');
        });
    });

});
