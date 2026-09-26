@php
    $title = 'Axtarış idarəetməsi';
    $productTitle = trim(($product->brand?->name ?? '') . ' ' . $product->name);
    $breadcrumbs = [
        '/admin' => 'ParfumShop',
        route('admin.product.search-terms.index') => 'Axtarış idarəetməsi',
        '#' => $productTitle,
    ];
@endphp

@extends('backend.layout', ['title' => $title])

@section('content')
    <div class="container">
        <div class="page-title-container">
            <div class="row">
                <div class="col-12 col-md-8">
                    <h1 class="mb-0 pb-0 display-4">{{ $title }}</h1>
                    @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
                </div>
                <div class="col-12 col-md-4 text-md-end mt-3 mt-md-0">
                    <a href="{{ route('admin.product.search-terms.index') }}" class="btn btn-outline-primary">Siyahıya qayıt</a>
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card mb-4">
            <div class="card-body">
                <div class="text-muted mb-1">Məhsul</div>
                <h3 class="mb-0">{{ $productTitle }}</h3>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-3">Əl ilə alias əlavə et</h5>
                <form method="POST" action="{{ route('admin.product.search-terms.store', $product) }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-12 col-md-7">
                        <label class="form-label">Axtarış ifadəsi</label>
                        <input class="form-control @error('term') is-invalid @enderror" name="term" value="{{ old('term') }}" placeholder="Məsələn: kalvin klayn moment">
                        @error('term')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-8 col-md-2">
                        <label class="form-label">Prioritet</label>
                        <input class="form-control" type="number" name="priority" value="{{ old('priority', 950) }}" min="1" max="1000">
                    </div>
                    <div class="col-4 col-md-auto">
                        <button class="btn btn-primary w-100" type="submit">Əlavə et</button>
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
                            <th>Alias</th>
                            <th>Mənbə</th>
                            <th>Prioritet</th>
                            <th>Status</th>
                            <th class="text-end">Əməliyyat</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($product->searchTerms as $term)
                            <tr>
                                <td>
                                    <div>{{ $term->term }}</div>
                                    <small class="text-muted">{{ $term->phonetic_term }}</small>
                                </td>
                                <td>
                                    @if($term->source === 'manual')
                                        <span class="badge bg-primary">Əl ilə</span>
                                    @elseif($term->source === 'ai')
                                        <span class="badge bg-info">AI</span>
                                    @else
                                        <span class="badge bg-secondary">Sistem</span>
                                    @endif
                                </td>
                                <td>
                                    <input form="term-update-{{ $term->id }}" class="form-control form-control-sm" style="width: 82px" type="number" name="priority" value="{{ $term->priority }}" min="1" max="1000">
                                </td>
                                <td>
                                    <input form="term-update-{{ $term->id }}" type="hidden" name="active" value="0">
                                    <label class="form-check mb-0">
                                        <input form="term-update-{{ $term->id }}" class="form-check-input" type="checkbox" name="active" value="1" @checked($term->active)>
                                        <span class="form-check-label">Aktiv</span>
                                    </label>
                                </td>
                                <td class="text-end">
                                    <form id="term-update-{{ $term->id }}" method="POST" action="{{ route('admin.product.search-terms.term.update', $term) }}" class="d-inline">
                                        @csrf
                                        @method('PUT')
                                        <button class="btn btn-sm btn-outline-primary" type="submit">Saxla</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.product.search-terms.destroy', $term) }}" class="d-inline" onsubmit="return confirm('Bu alias silinsin?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Sil</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Bu məhsul üçün alias yoxdur.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
