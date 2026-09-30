{{--
  Rolun icazələri: bölmələrə qruplaşdırılmış switch-lər (App\Support\PermissionGroups).
  Dəyişiklik dərhal saxlanır (POST admin.role.permissions.toggle), bölmədə "Hamısı" — toplu.
  JS: backend/js/role-permissions.js · CSS: backend/css/role-permissions.css
--}}
@php
    $html_tag_data = [];
    $title = $role->name.' — icazələr';
    $breadcrumbs = ['/admin' => 'ParfumShop', '/admin/role/list' => 'Rollar', '' => 'İcazələr'];
@endphp
@extends('backend.layout', ['html_tag_data' => $html_tag_data, 'title' => $title])

@section('css')
    <link rel="stylesheet" href="{{ asset_v('backend/css/role-permissions.css') }}">
@endsection

@section('js_page')
    <script src="{{ asset_v('backend/js/role-permissions.js') }}"></script>
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
                    <a href="{{ route('admin.role.list') }}" class="btn btn-outline-primary btn-icon btn-icon-start w-100 w-sm-auto">
                        <i data-acorn-icon="arrow-left"></i><span>Rollar</span>
                    </a>
                </div>
            </div>
        </div>

        @if($guardMismatch)
            <div class="alert alert-warning">Bu rol <b>{{ $role->guard_name }}</b> guard-ı ilə yaradılıb, admin icazələri isə <b>admin</b> guard-ındadır — icazələr bu rola təsir etməyəcək.</div>
        @endif

        <div class="rp" data-rp data-url="{{ route('admin.role.permissions.toggle', $role->id) }}">
            <div class="card mb-3">
                <div class="card-body rp-toolbar">
                    <div class="search-input-container border border-separator bg-foreground search-sm rp-search">
                        <input class="form-control form-control-sm" type="search" placeholder="İcazə axtar…" autocomplete="off" data-rp-search>
                        <span class="search-magnifier-icon"><i data-acorn-icon="search"></i></span>
                    </div>
                    <div class="btn-group btn-group-sm rp-filter" role="group" aria-label="Süzgəc">
                        <input type="radio" class="btn-check" name="rp-filter" id="rpAll" value="all" checked data-rp-filter>
                        <label class="btn btn-outline-primary" for="rpAll">Hamısı</label>
                        <input type="radio" class="btn-check" name="rp-filter" id="rpOn" value="on" data-rp-filter>
                        <label class="btn btn-outline-primary" for="rpOn">Verilən</label>
                        <input type="radio" class="btn-check" name="rp-filter" id="rpOff" value="off" data-rp-filter>
                        <label class="btn btn-outline-primary" for="rpOff">Verilməyən</label>
                    </div>
                    <div class="rp-summary"><b data-rp-count>{{ count($granted) }}</b> / {{ $total }} icazə verilib</div>
                </div>
            </div>

            <div class="alert alert-danger d-none" role="alert" data-rp-alert></div>

            <div class="row g-3">
                @foreach($groups as $group)
                    <div class="col-12 col-xl-6" data-rp-group>
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="rp-group__head">
                                    <span class="rp-group__icon"><i data-acorn-icon="{{ $group['icon'] }}" data-acorn-size="18"></i></span>
                                    <h2 class="rp-group__title">{{ $group['label'] }}</h2>
                                    <span class="badge bg-outline-primary rp-group__count" data-rp-group-count></span>
                                    <div class="form-check form-switch mb-0 ms-auto" title="Bölmədəki bütün icazələr">
                                        <input class="form-check-input" type="checkbox" id="rpAll{{ $group['key'] }}" data-rp-all>
                                        <label class="form-check-label small text-muted" for="rpAll{{ $group['key'] }}">Hamısı</label>
                                    </div>
                                </div>
                                <div class="rp-list">
                                    @foreach($group['items'] as $permission)
                                        <label class="rp-item" data-rp-item data-search="{{ mb_strtolower($permission->name.' '.$permission->description) }}">
                                            <span class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" value="{{ $permission->id }}" @checked(in_array($permission->id, $granted, true))>
                                            </span>
                                            <span class="rp-item__text">
                                                <span class="rp-item__desc">{{ $permission->description ?: $permission->name }}</span>
                                                <code class="rp-item__code">{{ $permission->name }}</code>
                                            </span>
                                            <span class="rp-item__state" aria-live="polite"></span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="card d-none" data-rp-empty>
                <div class="card-body text-center text-muted py-5">Axtarışa uyğun icazə tapılmadı.</div>
            </div>
        </div>
    </div>
@endsection
