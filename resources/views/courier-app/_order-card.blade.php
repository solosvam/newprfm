{{-- Sifariş kartı (siyahıda): $order, $statuses (OrderStatusService), $href — keçid (yoxdursa — kart link deyil) --}}
@php
    $code = $order->status?->code;
    $need = $order->items->sum(fn ($i) => $i->activeQuantity());
    $picked = (int) $order->items->flatMap->allocations->where('status', 'picked')->sum('quantity');
    $collect = $statuses->collectAmount($order);
    $toReturn = (int) $order->items->flatMap->allocations->where('status', 'returning')->sum('quantity');
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif class="{{ empty($plain) ? 'card ' : '' }}order">
    <div class="order__top">
        <strong class="order__no">{{ $order->order_no }}</strong>
        @if($code === 'courier_assigned')<span class="badge badge--outline">Toplanır</span>
        @elseif($code === 'sent')<span class="badge">Yoldadır</span>
        @else<span class="badge badge--muted">{{ $order->status?->name_az }}</span>@endif
        @if($toReturn)<span class="badge badge--warn">Depoya qaytar: {{ $toReturn }}</span>@endif
        @if($href)<span class="order__go">@include('courier-app._icon', ['name' => 'chevron'])</span>@endif
    </div>
    <div class="order__who">{{ trim($order->customer?->name.' '.$order->customer?->surname) }}</div>
    <div class="order__where">{{ $order->address?->shortLine() }}@if($order->meeting_place) · <b>{{ $order->meeting_place }}</b>@endif</div>
    <div class="order__meta">
        <span>Götürülüb: <b>{{ $picked }} / {{ $need }}</b></span>
        @if($collect > 0)<span>Alınacaq: <b>{{ number_format($collect, 2) }} AZN</b></span>@else<span class="ok">Ödənilib</span>@endif
        @if($order->gift_wrap)<span class="badge badge--outline">Hədiyyəlik</span>@endif
    </div>
    @if($order->courier_note)<div class="note note--compact"><b>Qeyd:</b> {{ $order->courier_note }}</div>@endif
</{{ $tag }}>
