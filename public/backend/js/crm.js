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


});
