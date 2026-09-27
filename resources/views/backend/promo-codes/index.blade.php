@php $title = 'Promo kodlar'; @endphp
@extends('backend.layout', ['title' => $title])

@section('content')
<div class="container">
    <div class="page-title-container d-flex align-items-center justify-content-between mb-4">
        <h1 class="mb-0 pb-0 display-4">Promo kodlar</h1>
        @can('promo.manage')
            <a class="btn btn-primary" href="{{ route('admin.promo-codes.create') }}">Yeni promo kod</a>
        @endcan
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <div class="card"><div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-5"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Promo kod axtar"></div>
            <div class="col-auto"><button class="btn btn-outline-primary">Axtar</button></div>
        </form>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Kod</th><th>Endirim</th><th>Minimum sifariş</th><th>İstifadə</th><th>Müddət</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse($promos as $promo)
                    <tr>
                        <td><strong>{{ $promo->code }}</strong></td>
                        <td>{{ number_format((float)$promo->value, 2) }}{{ $promo->type === 'percent' ? '%' : ' ₼' }}
                            @if($promo->max_discount !== null)<div class="small text-muted">Maks: {{ number_format((float)$promo->max_discount,2) }} ₼</div>@endif
                        </td>
                        <td>{{ $promo->min_amount !== null ? number_format((float)$promo->min_amount,2).' ₼' : '—' }}</td>
                        <td>{{ $promo->used_count }} / {{ $promo->usage_limit ?? '∞' }}</td>
                        <td><span class="small">{{ $promo->starts_at?->format('d.m.Y H:i') ?? '—' }}<br>{{ $promo->expires_at?->format('d.m.Y H:i') ?? '—' }}</span></td>
                        <td><span class="badge {{ $promo->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $promo->is_active ? 'Aktiv' : 'Deaktiv' }}</span></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.promo-codes.history', $promo) }}">Tarixçə</a>
                            @can('promo.manage')<a class="btn btn-sm btn-primary" href="{{ route('admin.promo-codes.edit', $promo) }}">Düzəliş</a>@endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">Promo kod tapılmadı.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $promos->links() }}
    </div></div>
</div>
@endsection
