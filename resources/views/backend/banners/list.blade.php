@php
    $html_tag_data = [];
    $title = 'Bannerlər';
    $breadcrumbs = ['/' => 'ParfumShop', '' => 'Bannerlər'];
@endphp

@extends('backend.layout', ['html_tag_data' => $html_tag_data, 'title' => $title])

@section('css')
    <link rel="stylesheet" href="{{ asset('backend/css/vendor/datatables.min.css') }}">
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
                <div class="col-12 col-sm-6 d-flex align-items-start justify-content-end">
                    <button type="button"
                            class="btn btn-outline-primary btn-icon btn-icon-end w-100 w-sm-auto"
                            data-bs-toggle="modal" data-bs-target="#newBanner">
                        <span>Yeni Banner</span>
                        <i data-acorn-icon="plus"></i>
                    </button>
                </div>
            </div>
        </div>

        <section class="scroll-section" id="hover">
            <div class="card mb-5">
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 col-sm-5 col-lg-3 col-xxl-2 mb-1">
                            <div class="d-inline-block float-md-start me-1 mb-1 search-input-container w-100 border border-separator bg-foreground search-sm">
                                <input class="form-control form-control-sm datatable-search"
                                       placeholder="Axtar" data-datatable="#datatableHover">
                                <span class="search-magnifier-icon"><i data-acorn-icon="search"></i></span>
                                <span class="search-delete-icon d-none"><i data-acorn-icon="close"></i></span>
                            </div>
                        </div>
                        <div class="col-12 col-sm-7 col-lg-9 col-xxl-10 text-end mb-1">
                            <div class="d-inline-block">
                                <div class="d-inline-block datatable-export" data-datatable="#datatableHover">
                                    <button class="btn btn-icon btn-icon-only btn-outline-muted btn-sm dropdown"
                                            data-bs-toggle="dropdown" type="button" data-bs-offset="0,3"
                                            aria-label="İxrac et">
                                        <i data-acorn-icon="download"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-sm dropdown-menu-end">
                                        <button class="dropdown-item export-excel" type="button">Excel</button>
                                        <button class="dropdown-item export-cvs" type="button">CSV</button>
                                    </div>
                                </div>
                                <div class="dropdown-as-select d-inline-block datatable-length"
                                     data-datatable="#datatableHover">
                                    <button class="btn btn-outline-muted btn-sm dropdown-toggle" type="button"
                                            data-bs-toggle="dropdown" aria-expanded="false" data-bs-offset="0,3">
                                        10 Nəticə
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-sm dropdown-menu-end">
                                        @foreach([5, 10, 20, 50, 100] as $limit)
                                            <a class="dropdown-item {{ $limit === 10 ? 'active' : '' }}" href="#">
                                                {{ $limit }} Nəticə
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <table class="data-table data-table-pagination data-table-standard responsive nowrap hover"
                           id="datatableHover">
                        <thead>
                        <tr>
                            <th class="text-muted text-small text-uppercase">#</th>
                            <th class="text-muted text-small text-uppercase">Şəkil</th>
                            <th class="text-muted text-small text-uppercase">Cihaz</th>
                            <th class="text-muted text-small text-uppercase">Yerləşmə</th>
                            <th class="text-muted text-small text-uppercase">Dillər</th>
                            <th class="text-muted text-small text-uppercase">Status</th>
                            <th class="text-muted text-small text-uppercase">Əməliyyat</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($banners as $banner)
                            @php $thumbnail = $banner->imageForLocale('az'); @endphp
                            <tr>
                                <td>{{ $banner->id }}</td>
                                <td>
                                    @if($thumbnail)
                                        <img src="{{ asset('frontend/uploads/banners/' . basename($thumbnail)) }}"
                                             alt="Banner #{{ $banner->id }}"
                                             class="rounded" style="width:100px;height:55px;object-fit:cover">
                                    @else
                                        <span class="text-muted">Şəkil yoxdur</span>
                                    @endif
                                </td>
                                <td>{{ $banner->device === 'mobile' ? 'Mobil' : 'Veb' }}</td>
                                <td>{{ $banner->location === 'top' ? 'Yuxarı' : 'Aşağı' }}</td>
                                <td>
                                    @foreach(['az' => 'AZ', 'en' => 'EN', 'ru' => 'RU'] as $locale => $label)
                                        <span class="badge {{ $banner->{'url_' . $locale} ? 'bg-success' : 'bg-warning text-dark' }}">
                                            {{ $label }} {{ $banner->{'url_' . $locale} ? '✓' : '—' }}
                                        </span>
                                    @endforeach
                                </td>
                                <td>
                                    <span class="badge {{ $banner->active ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $banner->active ? 'Aktiv' : 'Deaktiv' }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ route('admin.banner.edit', $banner->id) }}"
                                       class="btn btn-primary btn-sm">Redaktə et</a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <div class="modal modal-right fade" id="newBanner" tabindex="-1"
             aria-labelledby="newBannerTitle" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="newBannerTitle">Yeni Banner</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="{{ route('admin.banner.add') }}"
                              enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label" for="bannerDevice">Cihaz</label>
                                <select id="bannerDevice" class="form-select @error('device') is-invalid @enderror"
                                        name="device" required>
                                    <option value="web" {{ old('device', 'web') === 'web' ? 'selected' : '' }}>Veb</option>
                                    <option value="mobile" {{ old('device') === 'mobile' ? 'selected' : '' }}>Mobil</option>
                                </select>
                                @error('device') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="bannerLocation">Yerləşmə</label>
                                <select id="bannerLocation" class="form-select @error('location') is-invalid @enderror"
                                        name="location" required>
                                    <option value="top" {{ old('location', 'top') === 'top' ? 'selected' : '' }}>Yuxarı</option>
                                    <option value="bottom" {{ old('location') === 'bottom' ? 'selected' : '' }}>Aşağı</option>
                                </select>
                                @error('location') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            @foreach(['az' => 'Azərbaycan', 'en' => 'English', 'ru' => 'Русский'] as $locale => $label)
                                <div class="mb-3">
                                    <label class="form-label" for="image_{{ $locale }}">Şəkil — {{ $label }} *</label>
                                    <input type="file" id="image_{{ $locale }}" name="image_{{ $locale }}"
                                           accept="image/*"
                                           class="form-control @error('image_' . $locale) is-invalid @enderror" required>
                                    @error('image_' . $locale)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            @endforeach
                            <hr>
                            <button type="submit" class="btn btn-primary">Əlavə et</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@if($errors->any())
    @section('js_page')
        @parent
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modal = document.getElementById('newBanner');
                if (modal && window.bootstrap) bootstrap.Modal.getOrCreateInstance(modal).show();
            });
        </script>
    @endsection
@endif
