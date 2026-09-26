@php
    $title = 'Axtarış aliasları';
    $breadcrumbs = [
        '/admin' => 'ParfumShop',
        '#' => 'Axtarış aliasları',
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
            {{ $products->links() }}
        </div>
    </div>
@endsection
