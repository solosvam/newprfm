{{--
  İcazələr (permissions) — hamısı bir cədvəldə (əvvəl paginate(20) idi, səhifələmə yox idi).
  Hər icazə: açıqlama + kod, bölmə (App\Support\PermissionGroups), hansı rollarda var.
  Yeni icazə — sağ modal; xəta olsa modal yenidən açılır (backend/js/permissions-list.js).
--}}
@php
    $html_tag_data = [];
    $title = 'İcazələr';
    $breadcrumbs = ['/admin' => 'ParfumShop', '' => 'İcazələr'];
    $createErrors = $errors->getBag('create');
@endphp
@extends('backend.layout', ['html_tag_data' => $html_tag_data, 'title' => $title])

@section('css')
    <link rel="stylesheet" href="{{ asset('backend/css/vendor/datatables.min.css') }}"/>
    <link rel="stylesheet" href="{{ asset_v('backend/css/role-permissions.css') }}">
@endsection

@section('js_page')
    <script src="{{ asset('backend/js/vendor/datatables.min.js') }}"></script>
    <script src="{{ asset('backend/js/cs/scrollspy.js') }}"></script>
    <script src="{{ asset('backend/js/cs/datatable.extend.js') }}"></script>
    <script src="{{ asset('backend/js/plugins/datatable.boxedvariations.js') }}"></script>
    <script src="{{ asset_v('backend/js/permissions-list.js') }}"></script>
@endsection

@section('content')
    <div class="container">
        <div class="page-title-container">
            <div class="row">
                <div class="col-12 col-sm-6">
                    <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }}</h1>
                    @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
                </div>
                <div class="col-12 col-sm-6 d-flex align-items-start justify-content-end">
                    <button type="button" class="btn btn-outline-primary btn-icon btn-icon-end w-100 w-sm-auto" data-bs-toggle="modal" data-bs-target="#newPermission">
                        <span>Yeni icazə</span>
                        <i data-acorn-icon="plus"></i>
                    </button>
                </div>
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
                                        <input class="form-control form-control-sm datatable-search" placeholder="Axtar" data-datatable="#datatableHover" />
                                        <span class="search-magnifier-icon"><i data-acorn-icon="search"></i></span>
                                        <span class="search-delete-icon d-none"><i data-acorn-icon="close"></i></span>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-7 col-lg-9 col-xxl-10 text-end mb-1">
                                    <div class="d-inline-block">
                                        <div class="d-inline-block datatable-export" data-datatable="#datatableHover">
                                            <button class="btn btn-icon btn-icon-only btn-outline-muted btn-sm dropdown" data-bs-toggle="dropdown" type="button" data-bs-offset="0,3">
                                                <i data-acorn-icon="download"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-sm dropdown-menu-end">
                                                <button class="dropdown-item export-excel" type="button">Excel</button>
                                                <button class="dropdown-item export-cvs" type="button">Cvs</button>
                                            </div>
                                        </div>
                                        <div class="dropdown-as-select d-inline-block datatable-length" data-datatable="#datatableHover">
                                            <button class="btn btn-outline-muted btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" data-bs-offset="0,3">
                                                50 Nəticə
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-sm dropdown-menu-end">
                                                <a class="dropdown-item" href="#">10 Nəticə</a>
                                                <a class="dropdown-item" href="#">20 Nəticə</a>
                                                <a class="dropdown-item active" href="#">50 Nəticə</a>
                                                <a class="dropdown-item" href="#">100 Nəticə</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <table class="data-table data-table-pagination data-table-standard responsive nowrap hover" id="datatableHover" data-order='[[ 0, "asc" ]]' data-page-length="50">
                                <thead>
                                <tr>
                                    <th class="text-muted text-small text-uppercase">#</th>
                                    <th class="text-muted text-small text-uppercase">İCAZƏ</th>
                                    <th class="text-muted text-small text-uppercase">BÖLMƏ</th>
                                    <th class="text-muted text-small text-uppercase">ROLLAR</th>
                                    <th class="text-muted text-small text-uppercase">ƏMƏLİYYAT</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($permissions as $permission)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td data-order="{{ $permission->name }}">
                                            <div class="rp-item__text">
                                                <span class="rp-item__desc">{{ $permission->description ?: '—' }}</span>
                                                <code class="rp-item__code">{{ $permission->name }}</code>
                                            </div>
                                        </td>
                                        <td class="text-alternate">{{ $groupLabel($permission->name) }}</td>
                                        <td class="text-alternate">
                                            @forelse($permission->roles as $role)
                                                <a href="{{ route('admin.role.permissions', $role->id) }}" class="badge bg-outline-primary me-1">{{ $role->name }}</a>
                                            @empty
                                                <span class="text-muted small">heç bir rolda yoxdur</span>
                                            @endforelse
                                            @if($permission->guard_name !== 'admin')
                                                <span class="badge bg-outline-warning" title="Admin icazələri admin guard-ında olmalıdır">guard: {{ $permission->guard_name }}</span>
                                            @endif
                                        </td>
                                        <td class="text-alternate">
                                            <a href="{{ route('admin.permission.edit', $permission->id) }}" class="btn btn-primary btn-sm">Edit</a>
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

        <div class="modal modal-right fade" id="newPermission" tabindex="-1" role="dialog" aria-hidden="true" @if($createErrors->any()) data-open-on-load @endif>
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Yeni icazə</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="{{ route('admin.permission.add') }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label" for="permName">Kod</label>
                                <input type="text" id="permName" name="name" class="form-control @if($createErrors->has('name')) is-invalid @endif" placeholder="məs. site.banners" value="{{ old('name') }}" required>
                                @if($createErrors->has('name'))<div class="invalid-feedback">{{ $createErrors->first('name') }}</div>@endif
                                <div class="form-text">Kodda istifadə olunur: <code>can:site.banners</code>. Nöqtədən əvvəlki hissə bölməni təyin edir.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="permDescription">Açıqlama</label>
                                <input type="text" id="permDescription" name="description" class="form-control @if($createErrors->has('description')) is-invalid @endif" placeholder="məs. Sayt — Bannerlər" value="{{ old('description') }}" required>
                                @if($createErrors->has('description'))<div class="invalid-feedback">{{ $createErrors->first('description') }}</div>@endif
                            </div>
                            <button type="submit" class="btn btn-primary">Əlavə et</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
