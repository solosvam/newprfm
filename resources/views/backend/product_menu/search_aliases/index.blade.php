@php
    $html_tag_data = [];
    $title = 'Axtarış idarəetməsi';
    $breadcrumbs = ['/admin' => 'ParfumShop', '' => 'Axtarış idarəetməsi'];
    $createErrors = $errors->getBag('createAlias');
    $typeBadges = ['brand' => 'bg-outline-primary', 'model' => 'bg-outline-success', 'ignore' => 'bg-outline-muted'];
@endphp

@extends('backend.layout', ['html_tag_data' => $html_tag_data, 'title' => $title])

@section('css')
    <link rel="stylesheet" href="{{ asset('backend/css/vendor/datatables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/css/vendor/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/css/vendor/select2-bootstrap4.min.css') }}">
@endsection

@section('js_page')
    <script src="{{ asset('backend/js/vendor/datatables.min.js') }}"></script>
    <script src="{{ asset('backend/js/cs/scrollspy.js') }}"></script>
    <script src="{{ asset('backend/js/cs/datatable.extend.js') }}"></script>
    <script src="{{ asset('backend/js/plugins/datatable.boxedvariations.js') }}"></script>
    <script src="{{ asset_v('backend/js/search-aliases.js') }}"></script>
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
                    <button type="button" class="btn btn-outline-primary btn-icon btn-icon-end w-100 w-sm-auto" data-alias-open>
                        <span>Yeni alias</span>
                        <i data-acorn-icon="plus"></i>
                    </button>
                </div>
            </div>
        </div>

        {{-- Son 30 gün --}}
        <div class="row g-3 mb-4">
            <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="text-muted text-small">Son 30 gündə axtarış</div><div class="display-6">{{ number_format($analytics['total']) }}</div></div></div></div>
            <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="text-muted text-small">Nəticə çıxan</div><div class="display-6 text-success">{{ number_format($analytics['with_results']) }}</div></div></div></div>
            <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="text-muted text-small">Nəticəsiz</div><div class="display-6 text-danger">{{ number_format($analytics['without_results']) }}</div></div></div></div>
            <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="text-muted text-small">Kliklənən axtarış</div><div class="display-6 text-primary">{{ number_format($analytics['clicked']) }}</div></div></div></div>
        </div>

        <div class="row g-3 mb-4">
            {{-- Lüğət buradan böyüyür: tapılmayan söz → alias --}}
            <div class="col-12 col-xl-5"><div class="card h-100"><div class="card-body"
                 data-no-result data-url="{{ route('admin.product.search-aliases.no-result.destroy') }}">
                <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                    <h2 class="h5 mb-0">Nəticəsiz axtarışlar</h2>
                    @if($noResultQueries->isNotEmpty())
                        <button type="button" class="btn btn-sm btn-outline-danger" data-no-result-bulk disabled>
                            Seçilənləri sil (<span data-no-result-count>0</span>)
                        </button>
                    @endif
                </div>
                @if($noResultQueries->isNotEmpty())
                    <div class="form-check border-bottom pb-2 mb-1">
                        <input class="form-check-input" type="checkbox" id="noResultAll" data-no-result-all>
                        <label class="form-check-label text-muted text-small" for="noResultAll">Hamısını seç</label>
                    </div>
                @endif
                @forelse($noResultQueries as $item)
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2 gap-2" data-no-result-row="{{ $item->query }}">
                        <div class="form-check d-flex align-items-center gap-2 min-w-0 mb-0">
                            <input class="form-check-input mt-0" type="checkbox" id="noResult{{ $loop->index }}" value="{{ $item->query }}" data-no-result-check>
                            <label class="form-check-label text-truncate" for="noResult{{ $loop->index }}">{{ $item->query }}</label>
                            <span class="badge bg-danger rounded-pill">{{ $item->search_count }}</span>
                        </div>
                        <div class="d-flex gap-1 flex-shrink-0">
                            <button class="btn btn-sm btn-primary" type="button" data-alias-open data-alias-query="{{ $item->query }}">Alias əlavə et</button>
                            <button class="btn btn-sm btn-outline-danger" type="button" data-no-result-delete="{{ $item->query }}">Sil</button>
                        </div>
                    </div>
                @empty
                    <div class="text-muted">Nəticəsiz axtarış yoxdur.</div>
                @endforelse
                <div class="text-muted" data-no-result-empty hidden>Nəticəsiz axtarış yoxdur.</div>
            </div></div></div>
            <div class="col-12 col-md-6 col-xl-3"><div class="card h-100"><div class="card-body">
                <h2 class="h5 mb-3">Ən çox axtarılanlar</h2>
                @forelse($popularQueries as $item)
                    <div class="d-flex justify-content-between border-bottom py-2 gap-2"><span class="text-truncate">{{ $item->query }}</span><span class="badge bg-primary">{{ $item->search_count }}</span></div>
                @empty
                    <div class="text-muted">Hələ məlumat yoxdur.</div>
                @endforelse
            </div></div></div>
            <div class="col-12 col-md-6 col-xl-4"><div class="card h-100"><div class="card-body">
                <h2 class="h5 mb-3">Ən çox kliklənən məhsullar</h2>
                @forelse($popularProducts as $item)
                    <div class="d-flex justify-content-between border-bottom py-2 gap-2"><span class="text-truncate">{{ $item->product?->brand?->name }} {{ $item->product?->name ?? 'Silinmiş məhsul' }}</span><span class="badge bg-success">{{ $item->click_count }}</span></div>
                @empty
                    <div class="text-muted">Hələ klik məlumatı yoxdur.</div>
                @endforelse
            </div></div></div>
        </div>

        {{-- Lüğət --}}
        <section class="scroll-section" id="hover">
            <h2 class="small-title">Lüğət</h2>
            <div class="card mb-5">
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 col-sm-5 col-lg-3 col-xxl-2 mb-1">
                            <div class="d-inline-block float-md-start me-1 mb-1 search-input-container w-100 border border-separator bg-foreground search-sm">
                                <input class="form-control form-control-sm datatable-search" placeholder="Axtar" data-datatable="#datatableHover">
                                <span class="search-magnifier-icon"><i data-acorn-icon="search"></i></span>
                                <span class="search-delete-icon d-none"><i data-acorn-icon="close"></i></span>
                            </div>
                        </div>
                        <div class="col-12 col-sm-7 col-lg-9 col-xxl-10 text-end mb-1">
                            <div class="d-inline-block">
                                <div class="d-inline-block datatable-export" data-datatable="#datatableHover">
                                    <button class="btn btn-icon btn-icon-only btn-outline-muted btn-sm dropdown"
                                            data-bs-toggle="dropdown" type="button" data-bs-offset="0,3" aria-label="İxrac et">
                                        <i data-acorn-icon="download"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-sm dropdown-menu-end">
                                        <button class="dropdown-item export-excel" type="button">Excel</button>
                                        <button class="dropdown-item export-cvs" type="button">CSV</button>
                                    </div>
                                </div>
                                <div class="dropdown-as-select d-inline-block datatable-length" data-datatable="#datatableHover">
                                    <button class="btn btn-outline-muted btn-sm dropdown-toggle" type="button"
                                            data-bs-toggle="dropdown" aria-expanded="false" data-bs-offset="0,3">
                                        20 Nəticə
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-sm dropdown-menu-end">
                                        @foreach([10, 20, 50, 100] as $limit)
                                            <a class="dropdown-item {{ $limit === 20 ? 'active' : '' }}" href="#">{{ $limit }} Nəticə</a>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <table class="data-table data-table-pagination data-table-standard responsive nowrap hover" id="datatableHover" data-page-length="20">
                        <thead>
                        <tr>
                            <th class="text-muted text-small text-uppercase">Alias</th>
                            <th class="text-muted text-small text-uppercase">Növ</th>
                            <th class="text-muted text-small text-uppercase">Nəyə bağlıdır</th>
                            <th class="text-muted text-small text-uppercase">Tarix</th>
                            <th class="text-muted text-small text-uppercase">Əməliyyat</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($aliases as $alias)
                            <tr>
                                <td class="fw-semibold">{{ $alias->alias }}</td>
                                <td><span class="badge {{ $typeBadges[$alias->type] ?? 'bg-outline-muted' }}">{{ $types[$alias->type] ?? $alias->type }}</span></td>
                                <td>
                                    @if($alias->type === 'model')
                                        {{ $alias->original }}
                                        @if($alias->brand)<span class="text-alternate">· {{ $alias->brand->name }}</span>@endif
                                    @elseif($alias->type === 'brand')
                                        {{ $alias->brand?->name ?? '—' }}
                                    @else
                                        <span class="text-alternate">axtarışda nəzərə alınmır</span>
                                    @endif
                                </td>
                                <td class="text-alternate">{{ $alias->created_at?->format('d.m.Y') }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.product.search-aliases.destroy', $alias) }}" class="m-0"
                                          data-confirm="“{{ $alias->alias }}” alias-ı silinsin?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Sil</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        {{-- Yeni alias --}}
        <div class="modal modal-right fade" id="newAlias" tabindex="-1" aria-labelledby="newAliasTitle" aria-hidden="true"
             @if($createErrors->any()) data-open-on-load @endif>
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="newAliasTitle">Yeni alias</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="{{ route('admin.product.search-aliases.store') }}" data-alias-form>
                            @csrf
                            <input type="hidden" name="from_query" value="{{ old('from_query') }}" data-alias-from>
                            <div class="mb-3">
                                <label class="form-label" for="aliasText">Alias (səhv yazılış, qısaltma)</label>
                                <input type="text" id="aliasText" name="alias" value="{{ old('alias') }}" maxlength="100" required
                                       placeholder="diyor, savaj, ysl, ermani kod" data-alias-input
                                       class="form-control @if($createErrors->has('alias') || $createErrors->has('alias_normalized')) is-invalid @endif">
                                @if($createErrors->has('alias') || $createErrors->has('alias_normalized'))
                                    <div class="invalid-feedback">{{ $createErrors->first('alias') ?: $createErrors->first('alias_normalized') }}</div>
                                @endif
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="aliasType">Növ</label>
                                <select id="aliasType" name="type" class="form-select @if($createErrors->has('type')) is-invalid @endif" data-alias-type>
                                    @foreach($types as $value => $label)
                                        <option value="{{ $value }}" @selected(old('type', 'brand') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @if($createErrors->has('type'))<div class="invalid-feedback">{{ $createErrors->first('type') }}</div>@endif
                                <div class="form-text" data-alias-hint="brand">Səhv yazılış brendə bağlanır: diyor → Christian Dior.</div>
                                <div class="form-text" data-alias-hint="model">Səhv yazılış modelin düzgün adına bağlanır: savaj → Sauvage. Brend seçilsə, yalnız o brenddə axtarılır.</div>
                                <div class="form-text" data-alias-hint="ignore">Söz axtarışda atılır: orijinal, qiymət, salam.</div>
                            </div>
                            <div class="mb-3" data-alias-field="brand">
                                <label class="form-label" for="aliasBrand">
                                    Brend <span class="text-muted" data-alias-optional>(istəyə bağlı)</span>
                                </label>
                                <select id="aliasBrand" name="brand_id" class="form-select @if($createErrors->has('brand_id')) is-invalid @endif" data-alias-brand>
                                    <option value="">Brend seçin</option>
                                    @foreach($brands as $brand)
                                        <option value="{{ $brand->id }}" @selected((string) old('brand_id') === (string) $brand->id)>{{ $brand->name }}</option>
                                    @endforeach
                                </select>
                                @if($createErrors->has('brand_id'))<div class="invalid-feedback d-block">{{ $createErrors->first('brand_id') }}</div>@endif
                            </div>
                            <div class="mb-3" data-alias-field="model">
                                <label class="form-label" for="aliasOriginal">Modelin düzgün adı</label>
                                <input type="text" id="aliasOriginal" name="original" value="{{ old('original') }}" maxlength="150"
                                       placeholder="Sauvage" class="form-control @if($createErrors->has('original')) is-invalid @endif">
                                @if($createErrors->has('original'))<div class="invalid-feedback">{{ $createErrors->first('original') }}</div>@endif
                            </div>
                            <hr>
                            <button type="submit" class="btn btn-primary">Əlavə et</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
