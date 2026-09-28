<div class="p-3">
    @if($payments->isEmpty())
        <div class="text-center text-muted py-5">Heç bir onlayn ödəniş yoxdur.</div>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th style="width:60px;">No</th>
                    <th>Tarix</th>
                    <th>Sifariş No</th>
                    <th>Provider</th>
                    <th>Provider sifariş ID</th>
                    <th>Kart</th>
                    <th class="text-end">Məbləğ</th>
                    <th>Status</th>
                    <th>Refund</th>
                </tr>
                </thead>
                <tbody>
                @foreach($payments as $payment)
                    @php
                        [$statusLabel, $statusClass] = match ($payment->status) {
                            'paid' => ['Ödənilib', 'bg-success'],
                            'pending' => ['Gözləyir', 'bg-warning text-dark'],
                            'failed' => ['Uğursuz', 'bg-danger'],
                            'cancelled' => ['Ləğv edilib', 'bg-danger'],
                            default => [$payment->status ?: '—', 'bg-light text-dark'],
                        };

                        $refundedAmount = (float) $payment->refunds->sum('amount');
                        $refundableAmount = max(0, (float) $payment->amount - $refundedAmount);
                    @endphp
                    <tr>
                        <td class="text-muted">{{ $payments->firstItem() + $loop->index }}</td>
                        <td class="text-nowrap">{{ $payment->created_at?->format('d.m.Y H:i') }}</td>
                        <td class="fw-semibold">{{ $payment->order?->order_no ?? '—' }}</td>
                        <td><span class="badge bg-light text-dark border">{{ strtoupper($payment->provider ?? '—') }}</span></td>
                        <td>{{ $payment->provider_order_id ?: '—' }}</td>
                        <td>{{ $payment->card_pan ?: '—' }}</td>
                        <td class="text-end fw-semibold text-nowrap">{{ number_format((float)$payment->amount, 2) }} ₼</td>
                        <td><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                        <td class="text-nowrap">
                            @if($payment->refunds->isNotEmpty())
                                <button class="btn btn-sm btn-icon btn-icon-only btn-outline-secondary me-1 btn-view-refunds"
                                        data-id="{{ $payment->id }}"
                                        data-bs-toggle="tooltip"
                                        title="Geri ödəmələrə bax ({{ $payment->refunds->count() }})">
                                    <i data-acorn-icon="eye" class="icon" data-acorn-size="18"></i>
                                </button>
                            @endif

                            @if($payment->status === \App\Models\Payment::PAID && $payment->provider === 'birbank' && $refundableAmount > 0)
                                <button class="btn btn-sm btn-icon btn-icon-only btn-outline-danger btn-refund"
                                        data-id="{{ $payment->id }}"
                                        data-amount="{{ $payment->amount }}"
                                        data-refundable="{{ number_format($refundableAmount, 2, '.', '') }}"
                                        data-currency="₼"
                                        data-bs-toggle="tooltip"
                                        title="Geri ödə">
                                    <i data-acorn-icon="arrow-left" class="icon" data-acorn-size="18"></i>
                                </button>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-center mt-3">{{ $payments->links('backend.pagination') }}</div>
    @endif
</div>

<div class="modal modal-right fade" id="refundModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Geri ödəmə</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="refund_payment_id">
                <div class="mb-3">
                    <label class="form-label">Ödəniş məbləği</label>
                    <input type="text" class="form-control" id="refund_payment_amount" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label">Qaytarıla bilən məbləğ</label>
                    <input type="text" class="form-control" id="refund_refundable_amount" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label">Qaytarılacaq məbləğ</label>
                    <input type="number" step="0.01" min="0.01" class="form-control" id="refund_amount" placeholder="Məbləğ daxil edin">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Bağla</button>
                <button type="button" class="btn btn-danger" id="refundSubmit">Geri ödə</button>
            </div>
        </div>
    </div>
</div>

<div class="modal modal-close-out fade" id="refundViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header p-3">
                <h5 class="modal-title">Geri ödəmələr</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="refundViewBody">
                <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
            </div>
        </div>
    </div>
</div>
