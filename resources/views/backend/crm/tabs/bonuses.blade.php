<div class="p-3">
    <div class="alert alert-success d-flex justify-content-between align-items-center">
        <span>Hazırkı bonus balansı</span>
        <strong class="fs-5">{{ number_format((float) $customer->bonus_balance, 2) }} ₼</strong>
    </div>

    @if($transactions->isEmpty())
        <div class="text-center text-muted py-4">Bonus əməliyyatı yoxdur.</div>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>Tarix</th>
                    <th>Növ</th>
                    <th>Sifariş</th>
                    <th>Qeyd</th>
                    <th class="text-end">Məbləğ</th>
                </tr>
                </thead>
                <tbody>
                @foreach($transactions as $transaction)
                    <tr>
                        <td>{{ $transaction->created_at?->format('d.m.Y H:i') }}</td>
                        <td>{{ ['earn' => 'Qazanıldı', 'spend' => 'Xərcləndi', 'register' => 'Qeydiyyat', 'adjustment' => 'Düzəliş'][$transaction->type] ?? $transaction->type }}</td>
                        <td>{{ $transaction->order?->order_no ?? '—' }}</td>
                        <td>{{ $transaction->note ?: '—' }}</td>
                        <td class="text-end fw-semibold text-{{ $transaction->amount >= 0 ? 'success' : 'danger' }}">
                            {{ $transaction->amount >= 0 ? '+' : '' }}{{ number_format((float) $transaction->amount, 2) }} ₼
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-center mt-3">
            {{ $transactions->links('backend.pagination') }}
        </div>
    @endif
</div>
