{{--
  Əlavə çatdırılma qazancı (CourierFees::daily) — bir kuryerin günləri: 5 + 10 + 5 = 20 AZN və sifarişlər.
  $earnings — günlər (yeni yuxarıda), $self — kuryerin özü baxır, $days — neçə gün göstərilir.
  Qapıda ödənişdə kuryer pulu müştəridən alıb özündə saxlayır; kartla ödənilibsə — balansına yazılır (şirkət ona borcludur).
--}}
@php
    $todayRow = $earnings->first(fn ($row) => $row['date']->isToday());
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
@endphp
<div class="stats stats--two">
    <div class="stat">
        <div class="stat__label">Bu gün</div>
        <div class="stat__value ok">{{ number_format($todayRow['total'] ?? 0, 2) }} AZN</div>
    </div>
    <div class="stat">
        <div class="stat__label">Son {{ $days }} gün</div>
        <div class="stat__value">{{ number_format($earnings->sum('total'), 2) }} AZN</div>
    </div>
</div>
@if($earnings->isEmpty())
    <div class="empty">{{ $self ? 'Son '.$days.' gündə əlavə çatdırılma olmayıb.' : 'Son '.$days.' gündə əlavə çatdırılma yoxdur.' }}</div>
@else
    <div class="card list">
        @foreach($earnings as $row)
            <div class="list__row">
                <div class="list__main">
                    <div class="list__what">{{ $row['date']->isToday() ? 'Bu gün' : ($row['date']->isYesterday() ? 'Dünən' : $row['date']->format('d.m.Y')) }}
                        · {{ $row['orders']->count() > 1 ? $row['orders']->reverse()->map(fn ($m) => $fmt($m->amount))->implode(' + ').' =' : '' }} {{ $fmt($row['total']) }} AZN</div>
                    <div class="list__meta">
                        @foreach($row['orders']->reverse() as $m)
                            {{ $m->order?->order_no ?? '#'.$m->order_id }} — {{ number_format((float) $m->amount, 2) }}{{ $m->order?->paymentMethod?->code === 'cash' ? ' (nağd alınıb)' : ' (balansa)' }}@if(!$loop->last) · @endif
                        @endforeach
                    </div>
                </div>
                <div class="list__sum"><div class="ok">+{{ number_format($row['total'], 2) }} AZN</div></div>
            </div>
        @endforeach
    </div>
@endif
<p class="hint">Qapıda ödənişdə əlavə məbləğ müştəridən alınan pula daxildir və {{ $self ? 'sizdə' : 'kuryerdə' }} qalır — təhvil veriləcək pula düşmür. Müştəri kartla ödəyibsə, məbləğ balansa yazılır (şirkətin borcu).</p>
