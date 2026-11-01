{{-- Hədiyyə kartı: məlumat, satış qeydi / stoka qaytarma və tarixçə --}}
@use('App\Models\Stock\GiftCard')
@php
    $title = GiftCard::label($card->denomination).' · '.$card->barcode;
    $breadcrumbs = ['/admin' => 'ParfumShop', route('admin.stock.cards') => 'Hədiyyə kartları', '#' => $card->barcode];
    $purchase = $card->purchaseItem?->purchase;
@endphp
@extends('backend.layout', ['title' => $title])

@section('content')
<div class="container">
    <div class="page-title-container">
        <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }}</h1>
        @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
    </div>
    @include('backend.procurement.feedback')

    <div class="row g-3 mb-5">
        <div class="col-12 col-lg-5">
            <div class="card h-100"><div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="heading mb-0">Kart</div>
                    <span class="badge {{ $card->status === GiftCard::SOLD ? 'bg-primary' : 'bg-outline-success' }}">{{ $card->statusLabel() }}</span>
                </div>
                <div class="mb-2"><span class="text-muted">Barkod:</span> {{ $card->barcode }}</div>
                <div class="mb-2"><span class="text-muted">Nominal:</span> {{ number_format((float) $card->denomination, 2) }} ₼</div>
                @if($purchase)<div class="mb-2"><span class="text-muted">Alış:</span> <a href="{{ route('admin.stock.purchases.show', $purchase) }}">#{{ $purchase->id }} · {{ $purchase->purchased_at->format('d.m.Y') }}</a></div>@endif
                @if($card->status === GiftCard::SOLD)
                    <div class="mb-2"><span class="text-muted">Satılıb:</span> {{ $card->sold_at?->format('d.m.Y H:i') }}@if($card->soldOrder) · {{ $card->soldOrder->order_no }}@endif</div>
                @endif

                <hr>
                @if($card->status === GiftCard::IN_STOCK)
                    <form method="POST" action="{{ route('admin.stock.cards.sell', $card) }}" data-once>
                        @csrf
                        <label class="form-label">Sifariş nömrəsi (varsa)</label>
                        <input type="text" name="order_no" class="form-control mb-2" maxlength="30" placeholder="PS…" value="{{ old('order_no') }}">
                        <input type="text" name="note" class="form-control mb-2" maxlength="255" placeholder="Qeyd (istəyə görə)" value="{{ old('note') }}">
                        <button class="btn btn-primary w-100">Satıldı</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.stock.cards.return', $card) }}" data-once>
                        @csrf
                        <label class="form-label">Stoka qaytar — səbəb</label>
                        <input type="text" name="note" class="form-control mb-2" maxlength="255" required placeholder="Məs. satış səhv qeyd olunub" value="{{ old('note') }}">
                        <button class="btn btn-outline-primary w-100">Stoka qaytar</button>
                    </form>
                @endif
            </div></div>
        </div>
        <div class="col-12 col-lg-7">
            <div class="card h-100"><div class="card-body">
                <div class="heading mb-3">Tarixçə</div>
                @foreach($card->events as $event)
                    <div class="d-flex justify-content-between gap-3 {{ $loop->last ? '' : 'border-bottom border-separator-light pb-2 mb-2' }}">
                        <div>
                            <div class="fw-bold">{{ $event->label() }}@if($event->order) · {{ $event->order->order_no }}@endif</div>
                            @if($event->note)<div class="text-muted text-small">{{ $event->note }}</div>@endif
                        </div>
                        <div class="text-muted text-small text-end text-nowrap">{{ $event->created_at->format('d.m.Y H:i') }}<br>{{ trim((string) $event->user?->full_name) }}</div>
                    </div>
                @endforeach
            </div></div>
        </div>
    </div>
</div>
@endsection
