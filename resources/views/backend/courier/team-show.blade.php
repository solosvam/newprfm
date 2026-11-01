{{--
  Baş kuryer → kuryer: balans və "Pulu özümə köçür" (courier.collect), aktiv sifarişlər anbarlar üzrə vəziyyətlə,
  sifariş arxivi və haqq-hesab tarixçəsi.
--}}
@use('App\Models\Procurement\OrderItemAllocation', 'Part')
@php
    $html_tag_data = [];
    $title = trim($courier->full_name);
    $breadcrumbs = [route('admin.dashboard') => 'Tapşırıqlarım', route('admin.courier.team') => 'Kuryerlər', '' => $title];
    $money = fn ($cents) => number_format(abs($cents) / 100, 2).' AZN';
    $stepLabels = ['courier_assigned' => 'Toplanır', 'sent' => 'Yoldadır', 'at_address' => 'Ünvandadır'];
    $partClass = [Part::PICKED => 'text-success', Part::PROBLEM => 'text-danger', Part::RETURNING => 'text-warning'];
@endphp
@extends('backend.layout', ['html_tag_data' => $html_tag_data, 'title' => $title])

@section('css')
    <link rel="stylesheet" href="{{ asset_v('backend/css/courier.css') }}">
@endsection

@section('content')
<div class="container courier-page">
    <div class="page-title-container">
        <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }}</h1>
        @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
    </div>
    @include('backend.procurement.feedback')

    <div class="card mb-4"><div class="card-body">
        @if($balance > 0)
            <div class="courier-summary__label">Şirkətə təhvil verməlidir</div>
            <div class="courier-summary__value text-warning">{{ $money($balance) }}</div>
        @elseif($balance < 0)
            <div class="courier-summary__label">Şirkətin ona borcu</div>
            <div class="courier-summary__value text-success">{{ $money($balance) }}</div>
        @else
            <div class="courier-summary__label">Hesablaşma</div>
            <div class="courier-summary__value">0.00 AZN</div>
        @endif
        @if($canCollect)
            <form method="post" action="{{ route('admin.courier.team.collect', $courier) }}" class="courier-deliver mt-3"
                  onsubmit="return confirm('Kuryerin balansından ' + this.amount.value + ' AZN hesabınıza köçürülsün?')">
                @csrf
                <label class="form-label" for="collectAmount">Pulu özümə köçür (AZN)</label>
                <input type="number" step="0.01" min="0.01" max="{{ number_format($balance / 100, 2, '.', '') }}" name="amount" id="collectAmount"
                       class="form-control mb-2" value="{{ old('amount', number_format($balance / 100, 2, '.', '')) }}" inputmode="decimal" required>
                <input type="text" name="note" class="form-control mb-2" maxlength="200" placeholder="Qeyd (istəyə görə)" value="{{ old('note') }}">
                <button type="submit" class="btn btn-primary w-100">Hesabıma köçür</button>
            </form>
        @endif
    </div></div>

    <h2 class="small-title">Aktiv sifarişlər</h2>
    @forelse($active as $order)
        @php
            $code = $order->status?->code;
            $collect = $statuses->collectAmount($order);
            $parts = $order->items->flatMap(fn ($item) => $item->allocations->where('status', '!=', Part::CANCELLED)->map(fn ($a) => ['item' => $item, 'a' => $a]));
        @endphp
        <div class="card courier-order mb-3">
            <div class="card-body">
                <div class="courier-order__top">
                    <strong>{{ $order->order_no }}</strong>
                    <span class="badge {{ $code === 'courier_assigned' ? 'bg-outline-primary' : 'bg-primary' }}">{{ $stepLabels[$code] ?? $order->status?->name_az }}</span>
                </div>
                <div class="courier-order__who">{{ trim($order->customer?->name.' '.$order->customer?->surname) }}</div>
                <div class="courier-order__where">{{ $order->address?->shortLine() }}@if($order->meeting_place) · <b>{{ $order->meeting_place }}</b>@endif</div>
                <div class="courier-order__meta">
                    <span>{!! $collect > 0 ? 'Alınacaq: <b>'.number_format($collect, 2).' AZN</b>' : '<span class="text-success">Ödənilib</span>' !!}</span>
                </div>
                @foreach($parts->groupBy(fn ($row) => $row['a']->warehouse_id) as $rows)
                    <div class="courier-part">
                        <div class="courier-part__name">{{ $rows->first()['a']->warehouse?->name_az ?? 'Anbar' }}</div>
                        @foreach($rows as $row)
                            <div class="courier-part__info">
                                <span>{{ $row['item']->product?->name ?? 'Məhsul' }} × {{ $row['a']->quantity }}</span>
                                <span class="{{ $partClass[$row['a']->status] ?? 'text-muted' }}">{{ $row['a']->label() }}</span>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="card"><div class="card-body text-center text-muted py-4">Aktiv sifariş yoxdur.</div></div>
    @endforelse

    <h2 class="small-title mt-5">Sifariş arxivi</h2>
    @if($archive->isEmpty())
        <div class="card"><div class="card-body text-center text-muted py-4">Arxiv boşdur.</div></div>
    @else
        <div class="card"><div class="card-body courier-history">
            @foreach($archive as $order)
                <div class="courier-history__row">
                    <div class="courier-history__main">
                        <div class="courier-history__what">{{ $order->order_no }} · {{ trim($order->customer?->name.' '.$order->customer?->surname) }}</div>
                        <div class="courier-history__meta">{{ $order->updated_at?->format('d.m.Y H:i') }} · {{ $order->status?->name_az }}</div>
                    </div>
                    <div class="courier-history__sum">{{ number_format((float) $order->total, 2) }} AZN</div>
                </div>
            @endforeach
        </div></div>
        <div class="mt-3">@include('backend._layout.simple-pagination', ['paginator' => $archive])</div>
    @endif

    <h2 class="small-title mt-5">Haqq-hesab tarixçəsi</h2>
    @include('backend.courier._history', ['history' => $history, 'self' => false])
</div>
@endsection
