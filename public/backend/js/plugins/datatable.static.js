/**
 * Ajax-sız DataTable: sətirlər serverdə (Blade) çəkilir.
 * Kart içində boxed görünüş (banners/list kimi): axtarış, export, nəticə sayı,
 * "Cəmi N" yazısı və səhifələmə. Toolbar: backend._layout.datatable-toolbar.
 *
 * İstifadə: <table class="data-table nowrap w-100 data-table-static" id="..."
 *                 data-noun="ölçü" data-noun-from="ölçüdən">
 *   - data-noun / data-noun-from: "Cəmi 12 ölçüdən 1–10 göstərilir" yazısı üçün (ahəng qanunu əl ilə);
 *   - birinci sütun (#) göstərilən sıraya görə avtomatik nömrələnir;
 *   - sıralanmayan/axtarılmayan sütunlara th-də class="no-sort" qoyun.
 */

document.addEventListener('DOMContentLoaded', function () {
    if (!jQuery().DataTable) {
        console.log('DataTable is null!');
        return;
    }

    document.querySelectorAll('table.data-table-static').forEach(function (element) {
        const table = jQuery(element);
        const noun = element.dataset.noun || 'qeyd';
        const nounFrom = element.dataset.nounFrom || 'qeyddən';

        const datatable = table.DataTable({
            responsive: true,
            buttons: ['copy', 'excel', 'csv', 'print'],
            info: true,
            order: [],
            pageLength: 50,
            sDom: '<"row"<"col-sm-12"<"table-container"t>r>><"row align-items-center mt-3"<"col-12 col-md-5 text-muted text-small"i><"col-12 col-md-7"p>>',
            language: {
                info: `Cəmi _TOTAL_ ${nounFrom} _START_–_END_ göstərilir`,
                infoEmpty: `${noun.charAt(0).toUpperCase() + noun.slice(1)} tapılmadı`,
                infoFiltered: `(ümumi _MAX_ ${noun} içində axtarış)`,
                zeroRecords: 'Axtarışa uyğun nəticə tapılmadı',
                emptyTable: 'Siyahı boşdur',
                paginate: {
                    previous: '<i class="cs-chevron-left"></i>',
                    next: '<i class="cs-chevron-right"></i>'
                }
            },
            columnDefs: [
                {targets: 0, orderable: false, searchable: false},
                {targets: 'no-sort', orderable: false, searchable: false}
            ],
            drawCallback: function () {
                const api = this.api();
                const start = api.page.info().start;

                api.column(0, {search: 'applied', order: 'applied', page: 'current'}).nodes().each(function (cell, index) {
                    cell.textContent = start + index + 1;
                });
            }
        });

        // Sətir seçimi linkə klikləməyə mane olmasın
        table.on('click', 'a', function (event) {
            event.stopImmediatePropagation();
        });

        new DatatableExtend({
            datatable: datatable,
            singleSelectCallback: function () {},
            multipleSelectCallback: function () {},
            anySelectCallback: function () {},
            noneSelectCallback: function () {}
        });
    });
});
