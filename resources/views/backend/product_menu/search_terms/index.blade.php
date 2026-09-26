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

        <div class="modal fade" id="attachNoResultModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('admin.product.search-terms.no-result.attach') }}" class="modal-content" id="attachNoResultForm">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Nəticəsiz sorğunu məhsula bağla</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="query" id="noResultQuery">
                        <div class="mb-3">
                            <label class="form-label">Axtarılan ifadə</label>
                            <input class="form-control" id="noResultQueryText" readonly>
                        </div>
                        <div>
                            <label class="form-label">Məhsulu axtar və seç</label>
                            <input class="form-control" id="noResultProductSearch" autocomplete="off" placeholder="Brend və ya məhsul adı">
                            <input type="hidden" name="product_id" id="noResultProductId">
                            <div class="list-group mt-2" id="noResultProducts"></div>
                            <div class="form-text" id="noResultSelectedProduct">Məhsul seçilməyib.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Bağla</button>
                        <button type="submit" class="btn btn-primary">Alias kimi əlavə et</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const modal = document.getElementById('attachNoResultModal');
        const form = document.getElementById('attachNoResultForm');
        const query = document.getElementById('noResultQuery');
        const queryText = document.getElementById('noResultQueryText');
        const productSearch = document.getElementById('noResultProductSearch');
        const productId = document.getElementById('noResultProductId');
        const products = document.getElementById('noResultProducts');
        const selected = document.getElementById('noResultSelectedProduct');
        if (!modal || !form) return;

        const modalInstance = new bootstrap.Modal(modal);
        let timer;

        const openModal = value => {
            query.value = value;
            queryText.value = value;
            productSearch.value = '';
            productId.value = '';
            products.replaceChildren();
            selected.textContent = 'Məhsul seçilməyib.';
            selected.classList.remove('text-danger');
            modalInstance.show();
        };

        document.querySelectorAll('.no-result-attach-button').forEach(button => {
            button.addEventListener('click', () => openModal(button.dataset.noResultQuery || ''));
        });

        productSearch.addEventListener('input', () => {
            clearTimeout(timer);
            const value = productSearch.value.trim();
            productId.value = '';
            selected.textContent = 'Məhsul seçilməyib.';
            products.replaceChildren();
            if (value.length < 2) return;

            timer = setTimeout(async () => {
                const url = new URL('{{ route('admin.product.search-terms.products') }}', window.location.origin);
                url.searchParams.set('q', value);

                try {
                    const response = await fetch(url, {headers: {'Accept': 'application/json'}});
                    const data = await response.json();
                    (data.products || []).forEach(product => {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'list-group-item list-group-item-action';
                        button.textContent = product.name;
                        button.addEventListener('click', () => {
                            productId.value = product.id;
                            selected.textContent = 'Seçilən məhsul: ' + product.name;
                            products.replaceChildren();
                        });
                        products.append(button);
                    });
                } catch {
                    selected.textContent = 'Məhsullar yüklənmədi.';
                    selected.classList.add('text-danger');
                }
            }, 250);
        });

        form.addEventListener('submit', event => {
            if (productId.value) return;
            event.preventDefault();
            selected.textContent = 'Əvvəl məhsulu seç.';
            selected.classList.add('text-danger');
        });
    });
</script>
