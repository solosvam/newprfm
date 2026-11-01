{{-- Stok → Hədiyyə kartları: nominallar üzrə qalıq, filtr (nominal, status, barkod — skanerlə tam barkod kartı açır) --}}
@use('App\Models\Stock\GiftCard')
@php
    $title = 'Hədiyyə kartları';
    $breadcrumbs = ['/admin' => 'ParfumShop', '' => 'Stok', '#' => $title];
@endphp
@extends('backend.layout', ['title' => $title])

@section('content')
<div class="container">
    <div class="page-title-container">
        <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }}</h1>
        @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
    </div>
    @include('backend.procurement.feedback')

    <div class="row g-2 mb-4">
        @foreach(GiftCard::DENOMINATIONS as $d)
            @php $row = $summary[$d] ?? collect(); @endphp
            <div class="col-6 col-xl-3">
                <a href="{{ route('admin.stock.cards', ['denomination' => $d]) }}" class="card h-100 {{ (int) ($filters['denomination'] ?? 0) === $d ? 'border border-primary' : '' }}">
                    <div class="card-body py-3">
                        <div class="heading mb-1">{{ GiftCard::label($d) }}</div>
                        <div class="cta-3 text-primary">{{ (int) ($row[GiftCard::IN_STOCK] ?? 0) }} <span class="text-muted text-small fw-normal">stokda</span></div>
                        <div class="text-muted text-small">Satılıb: {{ (int) ($row[GiftCard::SOLD] ?? 0) }} · Cəmi: {{ $row->sum() }}</div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="card mb-5"><div class="card-body">
        <form method="get" class="row g-2 mb-3">
            <div class="col-sm-4"><input type="search" name="q" class="form-control" placeholder="Barkod (skanerlə oxudun)" value="{{ $filters['q'] ?? '' }}" autofocus inputmode="numeric"></div>
            <div class="col-sm-3"><select name="denomination" class="form-select">
                <option value="">Bütün nominallar</option>
                @foreach(GiftCard::DENOMINATIONS as $d)<option value="{{ $d }}" @selected((int) ($filters['denomination'] ?? 0) === $d)>{{ GiftCard::label($d) }}</option>@endforeach
            </select></div>
            <div class="col-sm-3"><select name="status" class="form-select">
                <option value="">Bütün statuslar</option>
                @foreach(GiftCard::STATUSES as $code => $label)<option value="{{ $code }}" @selected(($filters['status'] ?? '') === $code)>{{ $label }}</option>@endforeach
            </select></div>
            <div class="col-sm-2 d-flex gap-2"><button class="btn btn-primary flex-grow-1">Filter</button>
                @if(array_filter($filters))<a href="{{ route('admin.stock.cards') }}" class="btn btn-outline-secondary" title="Təmizlə">×</a>@endif</div>
        </form>

        @if($cards->isEmpty())
            <div class="text-muted text-center py-5">Kart tapılmadı. Kartlar "Alışlar"da stoka daxil edilir.</div>
        @else
            <table class="table mb-3">
                <thead><tr>
                    <th class="text-muted text-small text-uppercase">Barkod</th>
                    <th class="text-muted text-small text-uppercase">Nominal</th>
                    <th class="text-muted text-small text-uppercase">Status</th>
                    <th class="text-muted text-small text-uppercase">Satış</th>
                </tr></thead>
                <tbody>
                @foreach($cards as $card)
                    <tr class="cursor-pointer" onclick="location.href='{{ route('admin.stock.cards.show', $card) }}'">
                        <td><a href="{{ route('admin.stock.cards.show', $card) }}">{{ $card->barcode }}</a></td>
                        <td>{{ GiftCard::label($card->denomination) }}</td>
                        <td><span class="badge {{ $card->status === GiftCard::SOLD ? 'bg-primary' : 'bg-outline-success' }}">{{ $card->statusLabel() }}</span></td>
                        <td class="text-alternate">{{ $card->sold_at?->format('d.m.Y') }}{{ $card->soldOrder ? ' · '.$card->soldOrder->order_no : '' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            @include('backend._layout.simple-pagination', ['paginator' => $cards])
        @endif
    </div></div>
</div>
@endsection
