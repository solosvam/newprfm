@php
    $title = 'Axtarış idarəetməsi';
    $breadcrumbs = [
        '/admin' => 'ParfumShop',
        '#' => 'Axtarış idarəetməsi',
    ];
@endphp

@extends('backend.layout', ['title' => $title])

@section('content')
    <div class="container">
        <div class="page-title-container">
            <div class="row">
                <div class="col-12">
                    <h1 class="mb-0 pb-0 display-4">{{ $title }}</h1>
                    @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="text-muted text-small">Son 30 gündə axtarış</div><div class="display-6">{{ number_format($analytics['total']) }}</div></div></div></div>
            <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="text-muted text-small">Nəticə çıxan</div><div class="display-6 text-success">{{ number_format($analytics['with_results']) }}</div></div></div></div>
            <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="text-muted text-small">Nəticəsiz</div><div class="display-6 text-danger">{{ number_format($analytics['without_results']) }}</div></div></div></div>
            <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="text-muted text-small">Kliklənən axtarış</div><div class="display-6 text-primary">{{ number_format($analytics['clicked']) }}</div></div></div></div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-xl-4"><div class="card h-100"><div class="card-body">
                <h2 class="h5 mb-3">Ən çox axtarılanlar</h2>
                @forelse($popularQueries as $item)
                    <div class="d-flex justify-content-between border-bottom py-2 gap-2"><span>{{ $item->query }}</span><span class="badge bg-primary">{{ $item->search_count }}</span></div>
                @empty
                    <div class="text-muted">Hələ məlumat yoxdur.</div>
                @endforelse
            </div></div></div>
            <div class="col-12 col-xl-4"><div class="card h-100"><div class="card-body">
                <h2 class="h5 mb-3">Nəticəsiz axtarışlar</h2>
                @forelse($noResultQueries as $item)
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2 gap-2">
                        <span>{{ $item->query }}</span>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-danger">{{ $item->search_count }}</span>
                            <button class="btn btn-sm btn-outline-primary no-result-attach-button" type="button" data-no-result-query="{{ $item->query }}">Məhsula bağla</button>
                        </div>
                    </div>
                @empty
                    <div class="text-muted">Hələ nəticəsiz axtarış yoxdur.</div>
                @endforelse
            </div></div></div>
            <div class="col-12 col-xl-4"><div class="card h-100"><div class="card-body">
                <h2 class="h5 mb-3">Ən çox kliklənən məhsullar</h2>
                @forelse($popularProducts as $item)
                    <div class="d-flex justify-content-between border-bottom py-2 gap-2"><span>{{ $item->product?->brand?->name }} {{ $item->product?->name ?? 'Silinmiş məhsul' }}</span><span class="badge bg-success">{{ $item->click_count }}</span></div>
                @empty
                    <div class="text-muted">Hələ klik məlumatı yoxdur.</div>
                @endforelse
            </div></div></div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-12 col-md-8 col-lg-6">
                        <label class="form-label">Məhsul və ya brend axtar</label>
                        <input class="form-control" name="q" value="{{ $search }}" placeholder="Məsələn: Calvin Klein Eternity Moment">
                    </div>
                    <div class="col-12 col-md-auto">
                        <button class="btn btn-primary" type="submit">Axtar</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Brend</th>
                            <th>Məhsul</th>
                            <th class="text-end">Əməliyyat</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($products as $product)
                            <tr>
                                <td>{{ $product->id }}</td>
                                <td>{{ $product->brand?->name ?: '-' }}</td>
                                <td>{{ $product->name }}</td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.product.search-terms.show', $product) }}">
                                        Aliasları idarə et
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">Məhsul tapılmadı.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3">
            {{ $products->links('backend.pagination') }}
        </div>
    </div>
@endsection
