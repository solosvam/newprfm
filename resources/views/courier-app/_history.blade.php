{{--
  Haqq-hesab tarixçəsi (FinanceService::courierHistory / courierLedger sətirləri).
  $self — kuryerin özü baxır ("aldınız"), yoxsa baş kuryer başqasınınkına ("aldı").
--}}
@php
    $money = fn ($cents) => number_format(abs($cents) / 100, 2).' AZN';
    $explain = function ($row) use ($self) {
        $m = $row['m'];
        $other = $row['other']?->name;
        return match ($m->kind) {
            'customer_payment' => $self ? 'Müştəridən nağd aldınız' : 'Müştəridən nağd aldı',
            'warehouse_payment' => ($self ? 'Depoya ödədiniz — ' : 'Depoya ödədi — ').$other,
            'warehouse_refund' => 'Depo pulu qaytardı — '.$other,
            'courier_handover' => $self ? 'Kassaya təhvil verdiniz' : 'Kassaya təhvil verdi',
            'courier_advance' => $row['delta'] > 0 ? ($self ? 'Avans / qaytarma aldınız' : 'Avans / qaytarma aldı') : 'Avans qaytarıldı',
            'courier_collect' => $row['delta'] < 0 ? 'Baş kuryerə təhvil — '.$other : 'Kuryerdən götürüldü — '.$other,
            'outgo' => 'Xərc: '.($m->category?->name ?? '').($m->note ? ' — '.$m->note : ''),
            'reversal' => 'Düzəliş (əks əməliyyat #'.$m->reversal_of_id.')',
            default => $m->kindLabel(),
        };
    };
@endphp
@if($history->isEmpty())
    <div class="empty">{{ $empty ?? 'Hələ hərəkət yoxdur.' }}</div>
@else
    <div class="card list">
        @foreach($history as $row)
            @php $m = $row['m']; @endphp
            <div class="list__row">
                <div class="list__main">
                    <div class="list__what">{{ $explain($row) }}</div>
                    <div class="list__meta">
                        {{ $m->occurred_at->format('d.m.Y H:i') }}
                        @if($m->order) · {{ $m->order->order_no }}@endif
                        @if($m->kind !== 'outgo' && $m->note && $m->kind !== 'reversal') · {{ $m->note }}@endif
                        @if(!$self && $m->user) · {{ trim($m->user->full_name) }}@endif
                    </div>
                </div>
                <div class="list__sum">
                    <div class="{{ $row['delta'] > 0 ? 'warn' : 'ok' }}">{{ $row['delta'] > 0 ? '+' : '−' }}{{ $money($row['delta']) }}</div>
                    <div class="list__after">qalıq {{ $row['after'] < 0 ? '−' : '' }}{{ $money($row['after']) }}</div>
                </div>
            </div>
        @endforeach
    </div>
    <p class="hint">
        @if($self)
            <b>+</b> — sizdə olan şirkət pulu artır (müştəridən nağd, avans), <b>−</b> — azalır (depoya ödəniş, təhvil).
            Qalıq müsbətdirsə şirkətə təhvil verməlisiniz, mənfidirsə şirkət sizə borcludur.
        @else
            <b>+</b> — kuryerdə olan şirkət pulu artır, <b>−</b> — azalır. Qalıq müsbətdirsə kuryer şirkətə təhvil verməlidir, mənfidirsə şirkət ona borcludur.
        @endif
    </p>
@endif
