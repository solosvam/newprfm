@php
    $title = 'Hədiyyə kartları';
    $breadcrumbs = ['/' => 'ParfumShop', '' => $title];
    $statusClass = ['pending' => 'warning', 'active' => 'success', 'used' => 'primary', 'expired' => 'muted', 'refunded' => 'danger'];
@endphp
@extends('backend.layout', ['title' => $title])

@section('content')
<div class="container">
    <div class="page-title-container">
        <h1 class="mb-0 pb-0 display-4">{{ $title }}</h1>
        @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card h-100"><div class="card-body">
            <div class="text-muted small">Müştərilərə borc (aktiv kartlar)</div>
            <div class="h3 mb-0">{{ number_format($outstanding, 2) }} AZN</div>
            <div class="small text-muted">{{ $outstandingCount }} kart · müştəri bu məbləği hələ məhsul kimi almayıb</div>
        </div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-body">
            <div class="text-muted small">Satılmış kartların cəmi</div>
            <div class="h3 mb-0">{{ number_format($soldTotal, 2) }} AZN</div>
            <div class="small text-muted">Ödənişi təsdiqlənmiş bütün kartlar</div>
        </div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-body">
            <div class="text-muted small">Qaydalar</div>
            <div class="small">{{ \App\Services\GiftCard\GiftCardService::MIN }}–{{ \App\Services\GiftCard\GiftCardService::MAX }} AZN ({{ \App\Services\GiftCard\GiftCardService::STEP }} addımla), {{ \App\Services\GiftCard\GiftCardService::VALID_MONTHS }} ay, yalnız onlayn. Müştəri səhifəsi: <a href="{{ route('gift-card.index') }}" target="_blank">{{ route('gift-card.index') }}</a></div>
        </div></div></div>
    </div>

    <div class="card mb-4"><div class="card-body">
        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-5">
                <label class="form-label small text-muted">Kod, müştəri adı və ya nömrəsi</label>
                <input type="text" name="q" value="{{ $q }}" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted">Status</label>
                <select name="status" class="form-select">
                    <option value="">Hamısı</option>
                    @foreach(\App\Models\GiftCard\GiftCard::STATUSES as $key => $label)
                        <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3"><button class="btn btn-primary w-100">Filter</button></div>
        </form>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>#</th><th>Kod</th><th>Məbləğ</th><th>Alan müştəri</th><th>Status</th><th>Ödənilib</th><th>Etibarlıdır</th><th>Sifariş</th></tr></thead>
                <tbody>
                @forelse($cards as $card)
                    @php $key = $card->status === 'active' && $card->isExpired() ? 'expired' : $card->status; @endphp
                    <tr>
                        <td><a href="{{ route('admin.gift-cards.show', $card) }}">HK{{ $card->id }}</a></td>
                        <td class="font-monospace">{{ $card->formattedCode() ?? '—' }}</td>
                        <td>{{ number_format((float) $card->amount, 2) }} AZN</td>
                        <td>
                            @if($card->customer)
                                <a href="{{ route('admin.crm.customer', $card->customer_id) }}">{{ trim($card->customer->name.' '.$card->customer->surname) }}</a>
                                <div class="small text-muted">{{ $card->customer->mobile }}</div>
                            @else — @endif
                        </td>
                        <td><span class="badge bg-outline-{{ $statusClass[$key] ?? 'muted' }}">{{ $card->statusLabel() }}</span></td>
                        <td>{{ $card->paid_at?->format('d.m.Y H:i') ?? '—' }}</td>
                        <td>{{ $card->expires_at?->format('d.m.Y') ?? '—' }}</td>
                        <td>
                            @if($card->usedOrder)
                                <a href="{{ route('admin.crm.order', [$card->usedOrder->customer_id, $card->usedOrder->id]) }}">{{ $card->usedOrder->order_no }}</a>
                            @else — @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">Kart yoxdur.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $cards->links() }}
    </div></div>

</div>
@endsection
