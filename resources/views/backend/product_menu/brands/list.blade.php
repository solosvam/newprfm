@php
    $html_tag_data = [];
    $title = 'Brendlər';
    $breadcrumbs = ["/"=>"ParfumShop", ""=>"Brendlər"]
@endphp
@extends('backend.layout',['html_tag_data'=>$html_tag_data, 'title'=>$title])

@section('js_page')
    <script src="{{asset('backend/js/vendor/datatables.min.js')}}"></script>
    <script src="{{asset('backend/js/cs/scrollspy.js')}}"></script>
    <script src="{{asset('backend/js/cs/datatable.extend.js')}}"></script>
    <script src="{{asset('backend/js/plugins/datatable.boxedvariations.js')}}"></script>
    <script src="{{ asset_v('backend/js/brand-logos.js') }}"></script>
@endsection

@section('css')
<link rel="stylesheet" href="{{asset('backend/css/vendor/datatables.min.css')}}"/>
<style>
    .brand-logo-thumb { width: 96px; height: 48px; display: flex; align-items: center; justify-content: center; border: 1px solid var(--separator); border-radius: var(--border-radius-md); background: #fff; overflow: hidden; }
    .brand-logo-thumb img { max-width: 84px; max-height: 36px; object-fit: contain; }
    .brand-logo-thumb--empty { font-size: 11px; color: var(--muted); background: var(--background); }
    .logo-candidates { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; }
    .logo-candidate { display: flex; flex-direction: column; gap: 6px; padding: 8px; border: 1px solid var(--separator); border-radius: var(--border-radius-md); background: var(--foreground); cursor: pointer; text-align: left; }
    .logo-candidate:hover { border-color: var(--primary); }
    .logo-candidate__img { height: 90px; display: flex; align-items: center; justify-content: center; background: #fff; border-radius: 6px; overflow: hidden; }
    .logo-candidate__img img { max-width: 100%; max-height: 90px; object-fit: contain; }
    .logo-candidate__img img[src$=".svg"] { width: 100%; height: 80px; padding: 6px; }
    .logo-candidate__src { font-size: 11px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .logo-candidate.is-loading { opacity: .5; pointer-events: none; }
</style>
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
                    <button type="button" class="btn btn-outline-primary btn-icon btn-icon-end w-100 w-sm-auto" data-bs-toggle="modal" data-bs-target="#newAdmin">
                        <span>Yeni Brend</span>
                        <i data-acorn-icon="plus"></i>
                    </button>
                    <!-- Tour Button End -->
                </div>
                <!-- Top Buttons End -->
            </div>
        </div>
        <div class="row">
            <div class="col">
                <section class="scroll-section" id="hover">
                    <div class="card mb-5">
                        <div class="card-body">
                            <!-- Controls -->
                            <div class="row">
                                <div class="col-12 col-sm-5 col-lg-3 col-xxl-2 mb-1">
                                    <div class="d-inline-block float-md-start me-1 mb-1 search-input-container w-100 border border-separator bg-foreground search-sm">
                                        <input class="form-control form-control-sm datatable-search" placeholder="Axtar" data-datatable="#datatableHover" />
                                        <span class="search-magnifier-icon"><i data-acorn-icon="search"></i></span>
                                        <span class="search-delete-icon d-none"><i data-acorn-icon="close"></i></span>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-7 col-lg-9 col-xxl-10 text-end mb-1">
                                    <div class="d-inline-block me-1">
                                        <a href="{{ route('admin.brand.list') }}" class="btn btn-sm {{ request('logo') === 'missing' || request('aliases') === 'missing' ? 'btn-outline-muted' : 'btn-primary' }}">Hamısı</a>
                                        <a href="{{ route('admin.brand.list', ['aliases' => 'missing']) }}" class="btn btn-sm {{ request('aliases') === 'missing' ? 'btn-primary' : 'btn-outline-muted' }}">Aliası olmayanlar</a>
                                        <a href="{{ route('admin.brand.list', ['logo' => 'missing']) }}" class="btn btn-sm {{ request('logo') === 'missing' ? 'btn-primary' : 'btn-outline-muted' }}">Loqosu olmayanlar</a>
                                    </div>
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

                            <!-- Table -->
                            <table class="data-table data-table-pagination data-table-standard responsive nowrap hover" id="datatableHover" data-order='[[ 3, "desc" ]]'>
                                <thead>
                                <tr>
                                    <th class="text-muted text-small text-uppercase">#</th>
                                    <th class="text-muted text-small text-uppercase" data-orderable="false">LOQO</th>
                                    <th class="text-muted text-small text-uppercase">AD</th>
                                    <th class="text-muted text-small text-uppercase">MƏHSUL</th>
                                    <th class="text-muted text-small text-uppercase">ALİAS SAYI</th>
                                    <th class="text-muted text-small text-uppercase">STATUS</th>
                                    <th class="text-muted text-small text-uppercase" data-orderable="false">ƏMƏLİYYAT</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($brands as $brand)
                                    @php $logoUrl = \App\Services\BrandLogoService::svgUrl($brand->image) ?? \App\Services\BrandLogoService::url($brand->image); @endphp
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>
                                            <div class="brand-logo-thumb {{ $logoUrl ? '' : 'brand-logo-thumb--empty' }}" data-logo-thumb="{{ $brand->id }}">
                                                @if($logoUrl)<img src="{{ $logoUrl }}" alt="{{ $brand->name }}">@else Loqo yoxdur @endif
                                            </div>
                                        </td>
                                        <td>{{ $brand->name }}</td>
                                        <td class="text-alternate" data-order="{{ $brand->products_count }}">{{ $brand->products_count }}</td>
                                        <td data-order="{{ $brand->search_aliases_count }}"><a href="{{ route('admin.brand.edit', $brand->id) }}#brandAliases" class="badge {{ $brand->search_aliases_count ? 'bg-outline-primary' : 'bg-outline-warning' }}">{{ $brand->search_aliases_count }}</a></td>
                                        <td class="text-alternate">{{ $brand->active ? 'Aktiv' : 'Deaktiv' }}</td>
                                        <td class="text-alternate">
                                            <button type="button" class="btn btn-outline-primary btn-sm"
                                                    data-logo-search="{{ route('admin.brand.logo.search', $brand) }}"
                                                    data-logo-apply="{{ route('admin.brand.logo.apply', $brand) }}"
                                                    data-brand-id="{{ $brand->id }}" data-brand-name="{{ $brand->name }}">Loqo axtar</button>
                                            <a href="{{ route('admin.brand.edit', $brand->id) }}" class="btn btn-primary btn-sm ms-1">Edit</a>
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

        {{-- Loqo axtarışı: Google şəkillərindən (Serper) seçim --}}
        <div class="modal fade" id="logoSearchModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Loqo axtar: <span data-logo-brand></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="btn-group mb-3" role="group" data-logo-sources>
                            <input type="radio" class="btn-check" name="logo_source" id="logoSourceVector" value="vector" checked>
                            <label class="btn btn-outline-primary" for="logoSourceVector">Vektor (Worldvectorlogo)</label>
                            <input type="radio" class="btn-check" name="logo_source" id="logoSourceGoogle" value="google">
                            <label class="btn btn-outline-primary" for="logoSourceGoogle">Google şəkilləri</label>
                        </div>
                        <form class="d-flex gap-2 mb-3" data-logo-form>
                            <input type="text" class="form-control" name="q" placeholder="Brend adı">
                            <button type="submit" class="btn btn-primary text-nowrap">Axtar</button>
                        </form>
                        <p class="text-muted small mb-3">Əvvəlcə vektor loqolara baxın — ən təmiz nəticə oradan gəlir. Tapılmasa, Google şəkillərinə keçin və ağ/şəffaf fonlu, tək loqolu variant seçin. Seçilən şəkil avtomatik kəsilib ölçüləndirilir.</p>
                        <div class="logo-candidates" data-logo-results></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal modal-right fade" id="newAdmin" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Yeni Brend</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="{{route('admin.brand.add')}}" enctype="multipart/form-data">
                            @csrf
                            <label>Brend adı</label>
                            <input type="text" name="name" class="form-control" placeholder="Brend adı" value="{{old('name')}}" required>
                            <label>Slug (URL)</label>
                            <input type="text" name="slug" class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug') }}" placeholder="Boş saxlasanız avtomatik yaradılacaq">
                            @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <label>Şəkil</label>
                            <input type="file" name="image" class="form-control" required>
                            <hr>
                            <button type="submit" class="btn btn-primary">Əlavə et</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
