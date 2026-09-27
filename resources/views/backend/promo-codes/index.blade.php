@php
    $title = 'Promo kodlar';
    $breadcrumbs = ['/' => 'ParfumShop', '' => 'Promo kodlar'];
@endphp
@extends('backend.layout', ['title' => $title])

@section('css')
    <link rel="stylesheet" href="{{ asset('backend/css/vendor/datatables.min.css') }}"/>
@endsection

@section('js_page')
    <script src="{{ asset('backend/js/vendor/datatables.min.js') }}"></script>
    <script src="{{ asset('backend/js/cs/scrollspy.js') }}"></script>
    <script src="{{ asset('backend/js/cs/datatable.extend.js') }}"></script>
    <script src="{{ asset('backend/js/plugins/datatable.boxedvariations.js') }}"></script>
@endsection

@section('content')
    <div class="container">
        <div class="page-title-container">
            <div class="row">
                <div class="col-12 col-sm-6">
                    <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }}</h1>
                    @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
                </div>
                @can('promo.manage')
                    <div class="col-12 col-sm-6 d-flex align-items-start justify-content-end">
                        <a class="btn btn-outline-primary btn-icon btn-icon-end w-100 w-sm-auto"
                           href="{{ route('admin.promo-codes.create') }}">
                            <span>Yeni promo kod</span>
                            <i data-acorn-icon="plus"></i>
                        </a>
                    </div>
                @endcan
            </div>
        </div>

        <div class="row">
            <div class="col">
                <section class="scroll-section" id="hover">
                    <div class="card mb-5">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12 col-sm-5 col-lg-3 col-xxl-2 mb-1">
                                    <div class="d-inline-block float-md-start me-1 mb-1 search-input-container w-100 border border-separator bg-foreground search-sm">
                                        <input class="form-control form-control-sm datatable-search"
                                               placeholder="Axtar" data-datatable="#datatableHover"/>
                                        <span class="search-magnifier-icon"><i data-acorn-icon="search"></i></span>
                                        <span class="search-delete-icon d-none"><i data-acorn-icon="close"></i></span>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-7 col-lg-9 col-xxl-10 text-end mb-1">
                                    <div class="d-inline-block">
                                        <div class="d-inline-block datatable-export" data-datatable="#datatableHover">
                                            <button class="btn btn-icon btn-icon-only btn-outline-muted btn-sm dropdown"
                                                    data-bs-toggle="dropdown" type="button" data-bs-offset="0,3">
                                                <i data-acorn-icon="download"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-sm dropdown-menu-end">
                                                <button class="dropdown-item export-excel" type="button">Excel</button>
                                                <button class="dropdown-item export-cvs" type="button">Csv</button>
                                            </div>
                                        </div>
                                        <div class="dropdown-as-select d-inline-block datatable-length" data-datatable="#datatableHover">
                                            <button class="btn btn-outline-muted btn-sm dropdown-toggle" type="button"
                                                    data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" data-bs-offset="0,3">
                                                10 Nəticə
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-sm dropdown-menu-end">
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

                            <table class="data-table data-table-pagination data-table-standard responsive nowrap hover"
                                   id="datatableHover" data-order='[[ 0, "desc" ]]'>
                                <thead>
                                <tr>
                                    <th class="text-muted text-small text-uppercase">#</th>
                                    <th class="text-muted text-small text-uppercase">KOD</th>
                                    <th class="text-muted text-small text-uppercase">ENDİRİM</th>
                                    <th class="text-muted text-small text-uppercase">MİNİMUM SİFARİŞ</th>
                                    <th class="text-muted text-small text-uppercase">İSTİFADƏ</th>
                                    <th class="text-muted text-small text-uppercase">MÜDDƏT</th>
                                    <th class="text-muted text-small text-uppercase">STATUS</th>
                                    <th class="text-muted text-small text-uppercase">ƏMƏLİYYAT</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($promos as $promo)
                                    <tr>
                                        <td>{{ $promo->id }}</td>
                                        <td><strong>{{ $promo->code }}</strong></td>
                                        <td>
                                            {{ number_format((float) $promo->value, 2) }}{{ $promo->type === 'percent' ? '%' : ' ₼' }}
                                            @if($promo->max_discount !== null)
                                                <div class="small text-muted">Maks: {{ number_format((float) $promo->max_discount, 2) }} ₼</div>
                                            @endif
                                        </td>
                                        <td>{{ $promo->min_amount !== null ? number_format((float) $promo->min_amount, 2).' ₼' : '—' }}</td>
                                        <td>{{ $promo->used_count }} / {{ $promo->usage_limit ?? '∞' }}</td>
                                        <td>
                                            <span class="small">
                                                {{ $promo->starts_at?->format('d.m.Y H:i') ?? '—' }}<br>
                                                {{ $promo->expires_at?->format('d.m.Y H:i') ?? '—' }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge {{ $promo->is_active ? 'bg-success' : 'bg-secondary' }}">
                                                {{ $promo->is_active ? 'Aktiv' : 'Deaktiv' }}
                                            </span>
                                        </td>
                                        <td class="text-nowrap">
                                            <a class="btn btn-outline-primary btn-sm"
                                               href="{{ route('admin.promo-codes.history', $promo) }}">Tarixçə</a>
                                            @can('promo.manage')
                                                <a class="btn btn-primary btn-sm"
                                                   href="{{ route('admin.promo-codes.edit', $promo) }}">Düzəliş</a>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection
