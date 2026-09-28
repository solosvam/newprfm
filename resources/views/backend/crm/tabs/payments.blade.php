<div class="p-3">
    @if($payments->isEmpty())
        <div class="text-center text-muted py-4">Heç bir ödəniş yoxdur</div>
    @else
        <table class="table table-sm table-hover align-middle">
            <thead>
            <tr>
                <th>#</th>
                <th>Tarix</th>
                <th>Sifariş no</th>
                <th>Provider</th>
                <th>Məbləğ</th>
                <th>Status</th>
                <th>Refund</th>
            </tr>
            </thead>
            <tbody>
            @foreach($payments as $payment)
                @php
                    $statusLabels = [
                        0 => ['label' => 'Gözləyir',    'class' => 'bg-warning text-dark'],
                        1 => ['label' => 'Təsdiqlənib', 'class' => 'bg-success'],
                        2 => ['label' => 'Ləğv edilib', 'class' => 'bg-danger'],
                        3 => ['label' => 'Qaytarılıb',  'class' => 'bg-secondary'],
                    ];
                    $status = $statusLabels[$payment->status] ?? ['label' => '—', 'class' => 'bg-secondary'];

                    $currency = match(true) {
                        $payment->provider === 'paytr'                                         => '₺',
                        $payment->provider === 'birbank' && $payment->type === 0               => '₺',
                        default                                                                => '₼',
                    };
                @endphp
                <tr>
                    <td>{{ $payment->id }}</td>
                    <td>{{ $payment->created_at->format('d.m.Y H:i') }}</td>
                    <td>order</td>
                    <td>
                        <span class="badge bg-outline-dark text-dark border">
                            {{ strtoupper($payment->provider) }}
                        </span>
                    </td>
                    <td class="fw-bold">{{ $payment->amount }} {{ $currency }}</td>
                    <td>
                        <span class="badge {{ $status['class'] }}">
                            {{ $status['label'] }}
                        </span>
                    </td>
                    <td>
                        @if($payment->refunds->isNotEmpty())
                            <button class="btn btn-sm btn-icon btn-icon-only btn-outline-secondary me-1 btn-view-refunds"
                                    data-id="{{ $payment->id }}"
                                    data-bs-toggle="tooltip"
                                    title="Geri ödəmələrə bax ({{ $payment->refunds->count() }})">
                                <i data-acorn-icon="eye" class="icon" data-acorn-size="18"></i>
                            </button>
                        @endif

                        @if($payment->status === \App\Models\Payment::STATUS_APPROVED && in_array($payment->provider, [\App\Models\Payment::PROVIDER_PAYTR, \App\Models\Payment::PROVIDER_BIRBANK]))
                            <button class="btn btn-sm btn-icon btn-icon-only btn-outline-danger btn-refund"
                                    data-id="{{ $payment->id }}"
                                    data-amount="{{ $payment->amount }}"
                                    data-refundable="{{ $payment->refundableAmount() }}"
                                    data-currency="{{ $currency }}"
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

        <div class="d-flex justify-content-center mt-3">
            {{ $payments->links('admin.common.pagination') }}
        </div>
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
<script>
    (function () {
        var tooltipEls = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipEls.forEach(function (el) { new bootstrap.Tooltip(el); });
    })();
</script>
