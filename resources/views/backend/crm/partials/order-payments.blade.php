<div>
        <h2 class="small-title">Ödəniş cəhdləri</h2>
        <div class="card"><div class="card-body">
            <div class="table-responsive"><table class="table align-middle mb-0">
                <thead><tr><th class="text-muted text-small text-uppercase">Tarix</th><th class="text-muted text-small text-uppercase">Provayder</th><th class="text-muted text-small text-uppercase">Əməliyyat №</th><th class="text-muted text-small text-uppercase">Məbləğ</th><th class="text-muted text-small text-uppercase">Status</th></tr></thead>
                <tbody>@forelse($order->payments as $payment)
                    <tr><td>{{ $payment->created_at?->format('d.m.Y H:i') }}</td><td>{{ strtoupper($payment->provider) }}</td><td>{{ $payment->provider_order_id ?: '—' }}</td><td>{{ number_format((float) $payment->amount, 2) }} AZN</td><td>{{ $paymentLabels[$payment->status] ?? $payment->status }}</td></tr>
                    @if($payment->status === \App\Models\Payment\Payment::PAID && $payment->items->isNotEmpty())
                        {{-- Ödənişə daxil olanlar: geri ödəniş bu sətirlərə bağlanır. Yalnız ödənilmiş cəhddə göstərilir —
                             gözləyən / uğursuz cəhdlərdə məhsullar onsuz da "Məhsullar" tabındadır. --}}
                        <tr class="od-pay-items"><td></td><td colspan="4">
                            @foreach($payment->items as $line)
                                @php $orderItem = $line->order_item_id ? $order->items->firstWhere('id', $line->order_item_id) : null; @endphp
                                <div class="d-flex justify-content-between gap-3">
                                    <span>
                                        @if($line->type === 'delivery') Çatdırılma
                                        @elseif($line->type === 'gift_wrap') Hədiyyəlik qablaşdırma
                                        @else {{ $orderItem?->product?->name ?? 'Silinmiş məhsul' }}@if($orderItem?->variant?->size) · {{ $orderItem->variant->size->name_az }}@endif × {{ $line->quantity }}
                                        @endif
                                    </span>
                                    @php $lineRefunded = (float) $line->refundItems->filter(fn ($r) => in_array($r->operation?->status, ['pending', 'succeeded'], true))->sum('amount'); @endphp
                                    <span class="text-nowrap">{{ number_format((float) $line->amount, 2) }} AZN @if($lineRefunded > 0) <span class="text-danger">· qaytarılıb {{ number_format($lineRefunded, 2) }}</span>@endif</span>
                                </div>
                            @endforeach
                        </td></tr>
                    @endif
                    @foreach($payment->operations->sortByDesc('id') as $operation)
                        <tr><td>{{ $operation->created_at?->format('d.m.Y H:i') }}</td><td colspan="2">{{ $operationLabels[$operation->type] ?? $operation->type }}</td><td>{{ $operation->amount !== null ? number_format((float) $operation->amount, 2).' AZN' : '—' }}</td><td>{{ $operationStatuses[$operation->status] ?? $operation->status }}</td></tr>
                    @endforeach
                @empty<tr><td colspan="5" class="text-muted">Onlayn ödəniş cəhdi yoxdur.</td></tr>@endforelse</tbody>
            </table></div>
        </div></div>
</div>