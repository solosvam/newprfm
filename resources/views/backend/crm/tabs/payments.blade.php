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
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-center mt-3">{{ $payments->links('backend.pagination') }}</div>
    @endif
</div>
