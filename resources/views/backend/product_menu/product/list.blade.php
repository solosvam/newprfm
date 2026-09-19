@php
    $html_tag_data = [];
    $title = 'Məhsullar';
    $breadcrumbs = [
        "/admin" => "ParfumShop",
        "#" => "Məhsullar"
    ];
@endphp

@extends('backend.layout', ['title' => $title])

@section('css')
    <link rel="stylesheet" href="{{ asset('backend/css/vendor/datatables.min.css') }}"/>
@endsection

@section('js_page')
    <script src="{{ asset('backend/js/vendor/datatables.min.js') }}"></script>
    <script src="{{ asset('backend/js/cs/datatable.extend.js') }}"></script>
    <script src="{{ asset('backend/js/plugins/datatable.products.ajax.js')}}"></script>
@endsection

@section('content')
    <div class="container">
        <div class="page-title-container">
            <div class="row">
                <div class="col-12 col-md-7">
                    <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }}</h1>
                    @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
                </div>

                <div class="col-12 col-md-5 d-flex align-items-start justify-content-end">
                    <a href="{{ route('admin.product.add') }}"
                       class="btn btn-outline-primary btn-icon btn-icon-end w-100 w-sm-auto">
                        <span>Yeni Məhsul</span>
                        <i data-acorn-icon="plus"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="data-table-rows slim">
            <div class="row">
                <div class="col-sm-12 col-md-5 col-lg-3 col-xxl-2 mb-1">
                    <div class="d-inline-block float-md-start me-1 mb-1 search-input-container w-100 shadow bg-foreground">
                        <input class="form-control datatable-search"
                               placeholder="Axtar"
                               data-datatable="#datatableProductsAjax">

                        <span class="search-magnifier-icon">
                            <i data-acorn-icon="search"></i>
                        </span>

                        <span class="search-delete-icon d-none">
                            <i data-acorn-icon="close"></i>
                        </span>
                    </div>
                </div>

                <div class="col-sm-12 col-md-7 col-lg-9 col-xxl-10 text-end mb-1">
                    <div class="d-inline-block">
                        <button class="btn btn-icon btn-icon-only btn-foreground-alternate shadow datatable-print"
                                data-bs-delay="0"
                                data-datatable="#datatableProductsAjax"
                                data-bs-toggle="tooltip"
                                data-bs-placement="top"
                                title="Çap et"
                                type="button">
                            <i data-acorn-icon="print"></i>
                        </button>

                        <div class="d-inline-block datatable-export"
                             data-datatable="#datatableProductsAjax">

                            <button class="btn p-0"
                                    data-bs-toggle="dropdown"
                                    type="button"
                                    data-bs-offset="0,3">

                                <span class="btn btn-icon btn-icon-only btn-foreground-alternate shadow dropdown"
                                      data-bs-delay="0"
                                      data-bs-placement="top"
                                      data-bs-toggle="tooltip"
                                      title="Export">
                                    <i data-acorn-icon="download"></i>
                                </span>
                            </button>

                            <div class="dropdown-menu shadow dropdown-menu-end">
                                <button class="dropdown-item export-copy" type="button">Copy</button>
                                <button class="dropdown-item export-excel" type="button">Excel</button>
                                <button class="dropdown-item export-cvs" type="button">Cvs</button>
                            </div>
                        </div>

                        <div class="dropdown-as-select d-inline-block datatable-length"
                             data-datatable="#datatableProductsAjax"
                             data-childSelector="span">

                            <button class="btn p-0 shadow"
                                    type="button"
                                    data-bs-toggle="dropdown"
                                    aria-haspopup="true"
                                    aria-expanded="false"
                                    data-bs-offset="0,3">

                                <span class="btn btn-foreground-alternate dropdown-toggle"
                                      data-bs-toggle="tooltip"
                                      data-bs-placement="top"
                                      data-bs-delay="0"
                                      title="Nəticə sayı">
                                    10 Nəticə
                                </span>
                            </button>

                            <div class="dropdown-menu shadow dropdown-menu-end">
                                <a class="dropdown-item" href="#">5 Nəticə</a>
                                <a class="dropdown-item active" href="#">10 Nəticə</a>
                                <a class="dropdown-item" href="#">20 Nəticə</a>
                                <a class="dropdown-item" href="#">50 Nəticə</a>
                                <a class="dropdown-item" href="#">100 Nəticə</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="data-table-responsive-wrapper">
                <table id="datatableProductsAjax" class="data-table nowrap w-100">
                    <thead>
                    <tr>
                        <th class="text-muted text-small text-uppercase">#</th>
                        <th class="text-muted text-small text-uppercase">Şəkil</th>
                        <th class="text-muted text-small text-uppercase">Brend</th>
                        <th class="text-muted text-small text-uppercase">Ad</th>
                        <th class="text-muted text-small text-uppercase">Tip</th>
                        <th class="text-muted text-small text-uppercase">Ölçü</th>
                        <th class="text-muted text-small text-uppercase">Qiymət</th>
                        <th class="text-muted text-small text-uppercase">Kateqoriya</th>
                        <th class="text-muted text-small text-uppercase">Aktiv</th>
                        <th class="text-muted text-small text-uppercase">Əməliyyat</th>
                    </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
@endsection
