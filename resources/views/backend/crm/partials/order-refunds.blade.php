{{-- Geri qaytarmalar: məhsul ləğvinə bağlı (OrderRefundService). Kartla ödənilibsə "Karta qaytar" --}}
@use('App\Models\Order\OrderItemCancellation', 'Cancellation')
@php
    $refundRows = $order->itemCancellations->whereNotNull('refund_status');
    $refundBadges = [
        Cancellation::REFUND_PENDING => 'bg-outline-warning',
        Cancellation::REFUND_PROCESSING => 'bg-outline-danger',
        Cancellation::REFUND_DONE => 'bg-success',
        Cancellation::REFUND_BONUS => 'bg-outline-success',
    ];
@endphp
@if($refundRows->isNotEmpty())
    <h2 class="small-title mt-5">Geri qaytarmalar</h2>
    <div class="card"><div class="card-body">
        <div class="table-responsive"><table class="table align-middle mb-0 od-table">
            <thead><tr><th>Ləğv tarixi</th><th>Məhsul</th><th class="od-num">Say</th><th class="od-num">Məbləğ</th><th>Vəziyyət</th><th></th></tr></thead>
            <tbody>
            @foreach($refundRows as $c)
                <tr>
                    <td>{{ $c->created_at->format('d.m.Y H:i') }}<span class="od-sub">{{ $c->reasonLabel() }}</span></td>
                    <td>{{ $c->orderItem?->product?->name ?? 'Məhsul' }}</td>
                    <td class="od-num">{{ $c->quantity }}</td>
                    <td class="od-num">{{ number_format((float) $c->amount, 2) }} AZN</td>
                    <td>
                        <span class="badge {{ $refundBadges[$c->refund_status] ?? 'bg-outline-secondary' }}">{{ Cancellation::REFUND_LABELS[$c->refund_status] ?? $c->refund_status }}</span>
                        @if($c->refunded_at)<span class="od-sub">{{ $c->refunded_at->format('d.m.Y H:i') }}</span>@endif
                        @if($c->refund_status === Cancellation::REFUND_PROCESSING)<span class="od-sub">Təkrar göndərməyin — Birbank kabinetindən yoxlayın</span>@endif
                    </td>
                    <td class="text-end">
                        @if($c->refund_status === Cancellation::REFUND_PENDING)
                            @can('refund')
                                <button type="button" class="btn btn-sm btn-primary text-nowrap" data-bs-toggle="modal" data-bs-target="#refundConfirmModal"
                                        data-action="{{ route('admin.crm.order.cancellation.refund', [$customer, $order, $c]) }}"
                                        data-text="{{ ($c->orderItem?->product?->name ?? 'Məhsul').' × '.$c->quantity.' — '.number_format((float) $c->amount, 2).' AZN' }}">Karta qaytar</button>
                            @else
                                <span class="text-muted small">Qaytarma icazəsi yoxdur</span>
                            @endcan
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </div></div>

    @can('refund')
        <div class="modal fade" id="refundConfirmModal" tabindex="-1" aria-labelledby="refundConfirmTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="POST" action="">
                @csrf
                <div class="modal-header"><h5 class="modal-title" id="refundConfirmTitle">Karta qaytarılsın?</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button></div>
                <div class="modal-body">
                    <p class="fw-bold mb-2" data-refund-text></p>
                    <p class="text-muted mb-0">Məbləğ Birbank vasitəsilə müştərinin kartına qaytarılacaq. Bu əməliyyat geri alınmır.</p>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Bağla</button><button class="btn btn-primary">Qaytar</button></div>
            </form></div>
        </div>
    @endcan
@endif
