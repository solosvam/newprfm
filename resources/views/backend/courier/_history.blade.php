{{--
  Kuryerin haqq-hesab tarixçəsi (FinanceService::courierHistory). $self — kuryerin özü baxır ("aldınız"),
  yoxsa baş kuryer başqasının tarixçəsinə baxır ("aldı").
--}}
@php
    $money ??= fn ($cents) => number_format(abs($cents) / 100, 2).' AZN';
    $explain = function ($row) use ($self) {
        $m = $row['m'];
        $other = $row['other']?->name;
        return match ($m->kind) {
            'customer_payment' => $self ? 'Müştəridən nağd aldınız' : 'Müştəridən nağd aldı',
            'warehouse_payment' => ($self ? 'Depoya ödədiniz — ' : 'Depoya ödədi — ').$other,
            'warehouse_refund' => ($self ? 'Depo pulu qaytardı — ' : 'Depo pulu qaytardı — ').$other,
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
    <div class="card"><div class="card-body text-center text-muted py-4">Hələ hərəkət yoxdur.</div></div>
@else
    <div class="card"><div class="card-body courier-history">
        @foreach($history as $row)
            @php $m = $row['m']; @endphp
            <div class="courier-history__row">
                <div class="courier-history__main">
                    <div class="courier-history__what">{{ $explain($row) }}</div>
                    <div class="courier-history__meta">
                        {{ $m->occurred_at->format('d.m.Y H:i') }}
                        @if($m->order) · {{ $m->order->order_no }}@endif
                        @if($m->kind !== 'outgo' && $m->note && $m->kind !== 'reversal') · {{ $m->note }}@endif
                        @if(!$self && $m->user) · {{ trim($m->user->full_name) }}@endif
                    </div>
                </div>
                <div class="courier-history__sum">
                    <div class="{{ $row['delta'] > 0 ? 'text-warning' : 'text-success' }}">{{ $row['delta'] > 0 ? '+' : '−' }}{{ $money($row['delta']) }}</div>
                    <div class="courier-history__after">qalıq {{ $row['after'] < 0 ? '−' : '' }}{{ $money($row['after']) }}</div>
                </div>
            </div>
        @endforeach
        <div class="courier-history__hint">
            @if($self)
                <b>+</b> — sizdə olan şirkət pulu artır (müştəridən nağd, avans). <b>−</b> — azalır (depoya ödəniş, kassaya təhvil).
                Qalıq müsbətdirsə şirkətə təhvil verməlisiniz, mənfidirsə şirkət sizə borcludur.
            @else
                <b>+</b> — kuryerdə olan şirkət pulu artır, <b>−</b> — azalır. Qalıq müsbətdirsə kuryer şirkətə təhvil verməlidir, mənfidirsə şirkət ona borcludur.
            @endif
        </div>
    </div></div>
@endif
