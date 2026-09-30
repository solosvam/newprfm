{{--
  Kuryerin əsas səhifəsi: haqq-hesab və ona təyin olunmuş aktiv sifarişlər.
  $courier = ['orders', 'balance' (qəpik), 'deliveredToday', 'statuses' (OrderStatusService)]
--}}
@php
    $money = fn ($cents) => number_format(abs($cents) / 100, 2).' AZN';
    $stepLabels = ['courier_assigned' => 'Toplanır', 'sent' => 'Yoldadır', 'at_address' => 'Ünvandasınız'];
@endphp
<div class="courier-summary mb-4">
    <div class="card"><div class="card-body">
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
        <div class="courier-summary__label">Bu gün təhvil verilib</div>
        <div class="courier-summary__value">{{ $courier['deliveredToday'] }}</div>
    </div></div>
</div>

<h2 class="small-title">Aktiv sifarişlər</h2>
@forelse($courier['orders'] as $order)
    @php
        $need = $order->items->sum(fn ($i) => $i->activeQuantity());
        $picked = (int) $order->items->flatMap->allocations->where('status', 'picked')->sum('quantity');
        $collect = $courier['statuses']->collectAmount($order);
        $code = $order->status?->code;
    @endphp
    <a href="{{ route('admin.courier.order', $order) }}" class="card courier-order mb-3">
        <div class="card-body">
            <div class="courier-order__top">
                <strong>{{ $order->order_no }}</strong>
                <span class="badge {{ $code === 'courier_assigned' ? 'bg-outline-primary' : 'bg-primary' }}">{{ $stepLabels[$code] ?? $order->status?->name_az }}</span>
            </div>
            <div class="courier-order__who">{{ trim($order->customer?->name.' '.$order->customer?->surname) }}</div>
            <div class="courier-order__where">{{ $order->address?->city }}{{ $order->address?->address ? ', '.$order->address->address : '' }}</div>
            <div class="courier-order__meta">
                <span>Götürülüb: <b>{{ $picked }} / {{ $need }}</b></span>
                <span>{!! $collect > 0 ? 'Alınacaq: <b>'.number_format($collect, 2).' AZN</b>' : '<span class="text-success">Ödənilib</span>' !!}</span>
                @if($order->gift_wrap)<span class="badge bg-outline-secondary">Hədiyyəlik</span>@endif
            </div>
        </div>
    </a>
@empty
    <div class="card"><div class="card-body text-center text-muted py-5">Hazırda sizə təyin olunmuş sifariş yoxdur.</div></div>
@endforelse

{{-- Haqq-hesab: qalığın necə yarandığı (+ sizə gələn, − sizdən çıxan) --}}
<h2 class="small-title mt-5" id="balance-history">Haqq-hesab tarixçəsi</h2>
@php
    // Kuryer baxımından izah
    $explain = function ($row) {
        $m = $row['m'];
        return match ($m->kind) {
            'customer_payment' => 'Müştəridən nağd aldınız',
            'warehouse_payment' => 'Anbara ödədiniz — '.$row['other']?->name,
            'courier_handover' => 'Kassaya təhvil verdiniz',
            'courier_advance' => $row['delta'] > 0 ? 'Avans / qaytarma aldınız' : 'Avans qaytarıldı',
            'expense' => 'Xərc: '.$m->note,
            'reversal' => 'Düzəliş (əks əməliyyat #'.$m->reversal_of_id.')',
            default => $m->kindLabel(),
        };
    };
@endphp
@if($courier['history']->isEmpty())
    <div class="card"><div class="card-body text-center text-muted py-4">Hələ hərəkət yoxdur.</div></div>
@else
    <div class="card"><div class="card-body courier-history">
        @foreach($courier['history'] as $row)
            @php $m = $row['m']; @endphp
            <div class="courier-history__row">
                <div class="courier-history__main">
                    <div class="courier-history__what">{{ $explain($row) }}</div>
                    <div class="courier-history__meta">
                        {{ $m->occurred_at->format('d.m.Y H:i') }}
                        @if($m->order) · {{ $m->order->order_no }}@endif
                        @if($m->kind !== 'expense' && $m->note && $m->kind !== 'reversal') · {{ $m->note }}@endif
                    </div>
                </div>
                <div class="courier-history__sum">
                    <div class="{{ $row['delta'] > 0 ? 'text-warning' : 'text-success' }}">{{ $row['delta'] > 0 ? '+' : '−' }}{{ $money($row['delta']) }}</div>
                    <div class="courier-history__after">qalıq {{ $row['after'] < 0 ? '−' : '' }}{{ $money($row['after']) }}</div>
                </div>
            </div>
        @endforeach
        <div class="courier-history__hint">
            <b>+</b> — sizdə olan şirkət pulu artır (müştəridən nağd, avans). <b>−</b> — azalır (anbara ödəniş, kassaya təhvil).
            Qalıq müsbətdirsə şirkətə təhvil verməlisiniz, mənfidirsə şirkət sizə borcludur.
        </div>
    </div></div>
@endif
