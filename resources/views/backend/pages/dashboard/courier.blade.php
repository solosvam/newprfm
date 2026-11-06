{{--
  Kuryerin əsas səhifəsi: haqq-hesab və ona təyin olunmuş aktiv sifarişlər.
  $courier = ['orders', 'balance' (qəpik), 'deliveredToday', 'statuses' (OrderStatusService)]
--}}
@php
    $money = fn ($cents) => number_format(abs($cents) / 100, 2).' AZN';
    $stepLabels = ['courier_assigned' => 'Toplanır', 'sent' => 'Yoldadır'];
    // aktiv — hələ təhvil verilməyib (təhvildən sonra yalnız anbara qaytarılası qalanlar sayılmır)
    $activeCount = $courier['orders']->filter(fn ($o) => in_array($o->status?->code, ['courier_assigned', 'sent'], true))->count();
@endphp
<div class="courier-summary mb-4">
    <div class="card courier-summary__wide"><div class="card-body">
        @if($courier['balance'] > 0)
            <div class="courier-summary__label">Şirkətə təhvil verməlisiniz</div>
            <div class="courier-summary__value text-warning">{{ $money($courier['balance']) }}</div>
        @elseif($courier['balance'] < 0)
            <div class="courier-summary__label">Şirkətin sizə borcu</div>
            <div class="courier-summary__value text-success">{{ $money($courier['balance']) }}</div>
        @else
            <div class="courier-summary__label">Hesablaşma</div>
            <div class="courier-summary__value">0.00 AZN</div>
        @endif
    </div></div>
    <div class="card"><div class="card-body">
        <div class="courier-summary__label">Aktiv sifarişlər</div>
        <div class="courier-summary__value {{ $activeCount ? 'text-primary' : '' }}">{{ $activeCount }}</div>
    </div></div>
    <div class="card"><div class="card-body">
        <div class="courier-summary__label">Bu gün təhvil verilib</div>
        <div class="courier-summary__value">{{ $courier['deliveredToday'] }}</div>
    </div></div>
</div>

@can('courier.team')
    <a href="{{ route('admin.courier.team') }}" class="btn btn-outline-primary btn-icon btn-icon-start w-100 mb-4">
        <i data-acorn-icon="user"></i><span>Kuryerlər</span>
    </a>
@endcan

<h2 class="small-title">Aktiv sifarişlər</h2>
@forelse($courier['orders'] as $order)
    @php
        $need = $order->items->sum(fn ($i) => $i->activeQuantity());
        $picked = (int) $order->items->flatMap->allocations->where('status', 'picked')->sum('quantity');
        $collect = $courier['statuses']->collectAmount($order);
        $code = $order->status?->code;
        $toReturn = (int) $order->items->flatMap->allocations->where('status', 'returning')->sum('quantity');
    @endphp
    <a href="{{ route('admin.courier.order', $order) }}" class="card courier-order mb-3">
        <div class="card-body">
            <div class="courier-order__top">
                <strong>{{ $order->order_no }}</strong>
                <span class="badge {{ $code === 'courier_assigned' ? 'bg-outline-primary' : 'bg-primary' }}">{{ $stepLabels[$code] ?? $order->status?->name_az }}</span>
                @if($toReturn)<span class="badge bg-warning">Anbara qaytar: {{ $toReturn }} ədəd</span>@endif
            </div>
            <div class="courier-order__who">{{ trim($order->customer?->name.' '.$order->customer?->surname) }}</div>
            <div class="courier-order__where">{{ $order->address?->shortLine() }}@if($order->meeting_place) · <b>{{ $order->meeting_place }}</b>@endif</div>
            <div class="courier-order__meta">
                <span>Götürülüb: <b>{{ $picked }} / {{ $need }}</b></span>
                <span>{!! $collect > 0 ? 'Alınacaq: <b>'.number_format($collect, 2).' AZN</b>' : '<span class="text-success">Ödənilib</span>' !!}</span>
                @if($order->gift_wrap)<span class="badge bg-outline-secondary">Hədiyyəlik</span>@endif
            </div>
            @if($order->courier_note)<div class="courier-note courier-note--compact mt-2"><b>Qeyd:</b> {{ $order->courier_note }}</div>@endif
        </div>
    </a>
@empty
    <div class="card"><div class="card-body text-center text-muted py-5">Hazırda sizə təyin olunmuş sifariş yoxdur.</div></div>
@endforelse

{{-- Haqq-hesab: qalığın necə yarandığı (+ sizə gələn, − sizdən çıxan) --}}
<h2 class="small-title mt-5" id="balance-history">Haqq-hesab tarixçəsi</h2>
@include('backend.courier._history', ['history' => $courier['history'], 'self' => true])
