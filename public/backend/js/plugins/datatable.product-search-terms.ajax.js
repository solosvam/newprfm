/**
 * Search term products DataTable
 */

class ProductSearchTermsTable {
    constructor() {
        if (!jQuery().DataTable) {
            return;
        }

        this._staticHeight = 62;

        const _this = this;

        this._datatable = jQuery('#datatableProductSearchTerms').DataTable({
            scrollX: true,
            info: false,
            processing: true,
            ajax: {
                url: '/admin/product/search-terms/products-data',
                type: 'GET',
                dataSrc: ''
            },
            order: [],
            sDom: '<"row"<"col-sm-12"<"table-container"t>r>><"row"<"col-12"p>>',
            pageLength: 10,
            initComplete: function () {
                _this._setInlineHeight();
            },
            drawCallback: function () {
                _this._setInlineHeight();
            },
            columns: [
                {data: null},
                {data: 'brand'},
                {data: 'name'},
                {data: null}
            ],
            language: {
                emptyTable: 'Məhsul tapılmadı.',
                paginate: {
                    previous: '<i class="cs-chevron-left"></i>',
                    next: '<i class="cs-chevron-right"></i>'
                }
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
                    className: 'text-end',
                    render: function (data, type, row) {
                        return `<a class="btn btn-sm btn-outline-primary" href="/admin/product/search-terms/${row.id}">Aliasları idarə et</a>`;
                    }
                }
            ]
        });

        new DatatableExtend({
            datatable: this._datatable,
            singleSelectCallback: function () {},
            multipleSelectCallback: function () {},
            anySelectCallback: function () {},
            noneSelectCallback: function () {}
        });
    }

    _setInlineHeight() {
        const scrollBody = document.querySelector(
            '#datatableProductSearchTerms_wrapper .dataTables_scrollBody'
        );

        if (scrollBody) {
            scrollBody.style.height = this._staticHeight * this._datatable.page.len() + 'px';
        }
    }
}

document.addEventListener('DOMContentLoaded', function () {
    new ProductSearchTermsTable();
});
