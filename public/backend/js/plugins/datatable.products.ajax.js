/**
 * Products Ajax DataTable
 */

class ProductsAjax {
    constructor() {
        if (!jQuery().DataTable) {
            console.log('DataTable is null!');
            return;
        }

        this._datatable = null;
        this._datatableExtend = null;

        this._createInstance();
        this._addListeners();
        this._extend();
    }

    _createInstance() {
        const _this = this;

        this._datatable = jQuery('#datatableProductsAjax').DataTable({
            scrollX: true,
            buttons: ['copy', 'excel', 'csv', 'print'],
            info: true,
            processing: true,

            ajax: {
                url: jQuery('#datatableProductsAjax').data('url'),
                type: 'GET',
                dataSrc: ''
            },

            order: [],

            sDom: '<"row"<"col-sm-12"<"table-container"t>r>><"row align-items-center mt-3"<"col-12 col-md-5 text-muted text-small"i><"col-12 col-md-7"p>>',

            pageLength: 10,

            columns: [
                {data: null},
                {data: 'image'},
                {data: 'brand'},
                {data: 'name'},
                {data: 'type'},
                {data: 'variant_count'},
                {data: 'price'},
                {data: 'category_count'},
                {data: 'ingredient_count'},
                {data: 'active'},
                {data: null}
            ],

            language: {
                info: 'Cəmi _TOTAL_ məhsuldan _START_–_END_ göstərilir',
                infoEmpty: 'Məhsul tapılmadı',
                infoFiltered: '(ümumi _MAX_ məhsul içində axtarış)',
                zeroRecords: 'Axtarışa uyğun məhsul tapılmadı',
                emptyTable: 'Məhsul yoxdur',
                processing: 'Yüklənir...',
                paginate: {
                    previous: '<i class="cs-chevron-left"></i>',
                    next: '<i class="cs-chevron-right"></i>'
                }
            },

            initComplete: function () {
                _this._setInlineHeight();
            },

            drawCallback: function () {
                _this._setInlineHeight();
            },

            columnDefs: [
                {
                    targets: 0,
                    orderable: false,
                    searchable: false,
                    render: function (data, type, row, meta) {
                        return meta.row + meta.settings._iDisplayStart + 1;
                    }
                },
                {
                    targets: 1,
                    orderable: false,
                    searchable: false,
                    render: function (data, type, row) {
                        if (!data) {
                            return `
                                <div class="sh-6 sw-6 rounded-xl bg-light d-flex align-items-center justify-content-center">
                                    -
                                </div>
                            `;
                        }

                        return `
                            <img
                                src="/frontend/uploads/products/${data}"
                                class="card-img rounded-xl sh-6 sw-6"
                                alt="${row.name || ''}"
                            >
                        `;
                    }
                },
                {
                    // Uzun brend/ad/tip sətir keçirsin — cədvəl ekrandan daşıb üfüqi scroll yaratmasın
                    targets: [2, 3, 4],
                    render: function (data, type) {
                        if (type !== 'display') {
                            return data;
                        }

                        const text = jQuery('<div>').text(data || '').html();

                        return `<span class="d-inline-block text-wrap" style="min-width: 80px; max-width: 240px; overflow-wrap: anywhere">${text}</span>`;
                    }
                },
                {
                    targets: 5,
                    render: function (data) {
                        return `${data || 0} ölçü`;
                    }
                },
                {
                    targets: 6,
                    render: function (data) {
                        if (data === null || data === undefined || data === '') {
                            return '-';
                        }

                        return `${parseFloat(data).toFixed(2)} AZN`;
                    }
                },
                {
                    targets: 7,
                    render: function (data) {
                        return `${data || 0} kateqoriya`;
                    }
                },
                {
                    targets: 8,
                    render: function (data) {
                        const count = parseInt(data) || 0;

                        if (count === 0) {
                            return '<span class="badge bg-outline-danger">0 not</span>';
                        }

                        return `${count} not`;
                    }
                },
                {
                    targets: 9,
                    render: function (data) {
                        if (parseInt(data) === 1) {
                            return '<span class="badge bg-outline-success">Aktiv</span>';
                        }

                        return '<span class="badge bg-outline-danger">Deaktiv</span>';
                    }
                },
                {
                    targets: 10,
                    orderable: false,
                    searchable: false,
                    render: function (data, type, row) {
                        return `
                            <a
                                href="/admin/product/edit/${row.id}"
                                class="btn btn-primary btn-sm product-edit"
                            >
                                Edit
                            </a>
                            <button type="button" class="btn btn-outline-primary btn-sm ms-1"
                                    data-product-poster-url="/admin/product/${Number(row.id)}/poster">
                                Poster
                            </button>
                        `;
                    }
                }
            ]
        });
    }

    _addListeners() {
        const table = jQuery('#datatableProductsAjax');

        table.on('click', '.product-edit', function (e) {
            e.preventDefault();
            e.stopImmediatePropagation();

            window.location.href = this.href;
        });

        // Filtrlər (brend, kateqoriya, tip, ölçü): seçim dəyişən kimi siyahı serverdən həmin şərtlərlə yenidən yüklənir
        const filters = jQuery('[data-product-filter]');
        const clear = jQuery('[data-product-filters-clear]');
        const baseUrl = table.data('url');
        const reload = () => {
            const params = filters.toArray().filter(el => el.value !== '').map(el => encodeURIComponent(el.dataset.productFilter) + '=' + encodeURIComponent(el.value));
            clear.toggleClass('d-none', params.length === 0);
            this._datatable.ajax.url(baseUrl + (params.length ? (baseUrl.includes('?') ? '&' : '?') + params.join('&') : '')).load();
        };
        filters.each(function () {
            jQuery(this).select2({width: '100%', allowClear: true, placeholder: this.dataset.placeholder});
        });
        filters.on('change', reload);
        clear.on('click', () => {
            filters.val('').trigger('change.select2'); // hər select üçün ayrıca yükləmə olmasın
            reload();
        });
    }

    _extend() {
        this._datatableExtend = new DatatableExtend({
            datatable: this._datatable,

            singleSelectCallback: function () {},
            multipleSelectCallback: function () {},
            anySelectCallback: function () {},
            noneSelectCallback: function () {}
        });
    }

    _setInlineHeight() {
        if (!this._datatable) {
            return;
        }

        const scrollBody = document.querySelector(
            '#datatableProductsAjax_wrapper .dataTables_scrollBody'
        );

        // Sabit hündürlük (sətir × 62px) real sətirdən kiçik çıxıb şaquli scroll yaradırdı — hündürlük məzmuna görədir
        if (scrollBody) {
            scrollBody.style.height = 'auto';
        }
    }
}

document.addEventListener('DOMContentLoaded', function () {
    new ProductsAjax();
});
