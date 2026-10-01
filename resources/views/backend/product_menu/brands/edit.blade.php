@php
    $html_tag_data = [];
    $title = 'Brend edit';
    $breadcrumbs = ["/"=>"ParfumShop", ""=>"Brend edit"]
@endphp
@extends('backend.layout',['html_tag_data'=>$html_tag_data, 'title'=>$title])

@section('css')

@endsection

@section('js_page')
    <script src="{{ asset_v('backend/js/brand-aliases.js') }}"></script>
@endsection

@section('content')
    <div class="container">
        <div class="page-title-container">
            <div class="row">
                <!-- Title Start -->
                <div class="col-12 col-sm-6">
                    <h1 class="mb-0 pb-0 display-4" id="title">{{$title}}</h1>
                    @include('backend._layout.breadcrumb',['breadcrumbs'=>$breadcrumbs])
                </div>
                <!-- Title End -->

                <!-- Top Buttons Start -->
                <div class="col-12 col-sm-6 d-flex align-items-start justify-content-end">
                    <!-- Tour Button Start -->

                    <!-- Tour Button End -->
                </div>
                <!-- Top Buttons End -->
            </div>
        </div>
        <div class="row">
            <div class="col">
                <section class="scroll-section" id="userButtons">
                    <div class="card h-100-card">
                        <div class="card-body">
                            <form action="{{route('admin.brand.update',$brand->id)}}" method="post" enctype="multipart/form-data">
                                @csrf
                                <div class="mb-3 row">
                                    <label class="col-lg-2 col-md-3 col-sm-4 col-form-label">Ad</label>
                                    <div class="col-sm-8 col-md-9 col-lg-10">
                                        <input type="text" class="form-control" name="name" value="{{$brand->name}}" required>
                                    </div>
                                </div>

                                <div class="mb-3 row">
                                    <label class="col-lg-2 col-md-3 col-sm-4 col-form-label">Slug (URL)</label>
                                    <div class="col-sm-8 col-md-9 col-lg-10">
                                        <input type="text" name="slug" class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug', $brand->slug) }}">
                                        @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        <small class="text-muted">Dəyişdirildikdə əvvəlki ID-siz link işləməyəcək.</small>
                                    </div>
                                </div>
                                <div class="mb-3 row">
                                    <label class="col-lg-2 col-md-3 col-sm-4 col-form-label">Şəkil (Seçilmədikdə dəyişdirilmir)</label>
                                    <div class="col-sm-8 col-md-9 col-lg-10">
                                        <img src="{{ asset('frontend/uploads/brands/' . $brand->image) }}" class="card-img rounded-xl sh-6 sw-6" alt="thumb" />
                                        <input type="file" name="image" class="form-control">
                                    </div>
                                </div>

                                <div class="mb-3 row">
                                    <label class="col-lg-2 col-md-3 col-sm-4 col-form-label">Aktivlik</label>
                                    <div class="col-sm-8 col-md-9 col-lg-10">
                                        <select class="form-select" name="active">
                                            <option value="1" {{ $brand->active ? 'selected' : '' }}>Aktiv</option>
                                            <option value="0" {{ !$brand->active ? 'selected' : '' }}>Deaktiv</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3 row mt-5">
                                    <div class="col-sm-8 col-md-9 col-lg-10 ms-auto">
                                        <button type="submit" class="btn btn-primary">Yenilə</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </section>
            </div>
        </div>
        <section class="scroll-section mt-4" id="brandAliases" data-brand-aliases
                 data-suggest-url="{{ route('admin.brand.aliases.suggest', $brand) }}"
                 data-store-url="{{ route('admin.brand.aliases.store', $brand) }}"
                 data-delete-url="{{ route('admin.brand.aliases.destroy', ['brand' => $brand, 'alias' => '__ALIAS__']) }}">
            <h2 class="small-title">{{ $brand->name }} — axtarış aliasları</h2>
            <div class="card mb-5"><div class="card-body">
                <p class="text-muted">Brendin səhv yazılışlarını və qısaltmalarını lüğətə əlavə edin. AI təkliflərindən yalnız təsdiqlədiyiniz variantlar yadda saxlanılır.</p>
                <div class="d-flex flex-wrap gap-2 mb-3" data-existing-aliases>
                    @forelse($brand->searchAliases as $alias)
                        <span class="badge bg-outline-primary d-inline-flex align-items-center gap-2" data-alias-id="{{ $alias->id }}">
                            <span>{{ $alias->alias }}</span>
                            @can('product.search')
                                <button type="button" class="btn btn-sm btn-link p-0" data-alias-delete="{{ $alias->id }}" aria-label="{{ $alias->alias }} aliasını sil">×</button>
                            @endcan
                        </span>
                    @empty
                        <span class="text-muted" data-alias-empty>Bu brend üçün alias yoxdur.</span>
                    @endforelse
                </div>
                @can('product.search')
                    <button type="button" class="btn btn-outline-primary mb-3" data-alias-suggest>AI ilə yazılış variantları təklif et</button>
                    <div role="status" aria-live="polite" class="mb-3" data-alias-status></div>
                    <form data-brand-alias-form hidden>
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="brandAliasesAll" data-alias-all>
                            <label for="brandAliasesAll" class="form-check-label">Hamısını seç</label>
                        </div>
                        <div class="row g-2 mb-3" data-alias-candidates></div>
                        <button type="submit" class="btn btn-primary" data-alias-save disabled>Seçilənləri təsdiqlə (<span data-alias-count>0</span>)</button>
                    </form>
                @endcan
            </div></div>
        </section>
    </div>
@endsection
