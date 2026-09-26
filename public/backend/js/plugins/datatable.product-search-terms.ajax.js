/**
 * Search term products DataTable
 */

class ProductSearchTermsAjax {
    constructor() {
        if (!jQuery().DataTable) {
            console.log('DataTable is null!');
            return;
        }

        this._datatable = null;
        this._datatableExtend = null;
        this._staticHeight = 62;

        this._createInstance();
        this._addListeners();
        this._extend();
    }

    _createInstance() {
        const _this = this;

        this._datatable = jQuery('#datatableProductSearchTerms').DataTable({
            scrollX: true,
            buttons: ['copy', 'excel', 'csv', 'print'],
            info: false,
            processing: true,

            ajax: {
                url: '/admin/product/list-data',
                type: 'GET',
                dataSrc: ''
            },

            order: [],
            sDom: '<"row"<"col-sm-12"<"table-container"t>r>><"row"<"col-12"p>>',
            pageLength: 10,

            columns: [
                {data: null},
                {data: 'brand'},
                {data: 'name'},
                {data: null}
            ],

            language: {
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
                    targets: 3,
                    orderable: false,
                    searchable: false,
                    render: function (data, type, row) {
                        return `
                            <a href="/admin/product/search-terms/${row.id}"
                               class="btn btn-primary btn-sm product-search-terms-edit">
                                Aliasları idarə et
                            </a>
                        `;
                    }
                }
            ]
        });
    }

    _addListeners() {
        const table = jQuery('#datatableProductSearchTerms');

        table.on('click', '.product-search-terms-edit', function (e) {
            e.preventDefault();
            e.stopImmediatePropagation();

            window.location.href = this.href;
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

        const pageLength = this._datatable.page.len();
        const scrollBody = document.querySelector(
            '#datatableProductSearchTerms_wrapper .dataTables_scrollBody'
        );

        if (scrollBody) {
            scrollBody.style.height = this._staticHeight * pageLength + 'px';
        }
    }
}

document.addEventListener('DOMContentLoaded', function () {
    new ProductSearchTermsAjax();
});
