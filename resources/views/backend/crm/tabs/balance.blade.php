<div class="p-3">
    <div class="alert alert-success d-flex justify-content-between align-items-center">
        <span>Hazırkı bonus balansı</span>
        <strong class="fs-5">{{ number_format((float) $customer->bonus_balance, 2) }} ₼</strong>
    </div>

    @if($transactions->isEmpty())
        <div class="text-center text-muted py-4">Heç bir qeyd yoxdur</div>
    @else
        <table class="table table-sm table-hover">
            <thead>
            <tr>
                <th>#</th>
                <th>Tarix</th>
                <th>Tip</th>
                <th>Məbləğ</th>
                <th>Sifariş</th>
                <th>Qeyd</th>
            </tr>
            </thead>
            <tbody>
            @foreach($transactions as $transaction)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $transaction->created_at?->format('d.m.Y H:i') }}</td>
                    <td>{{ ['earn' => 'Qazanıldı', 'spend' => 'Xərcləndi', 'register' => 'Qeydiyyat', 'adjustment' => 'Düzəliş'][$transaction->type] ?? $transaction->type }}</td>

                    <td class="{{ $transaction->amount >= 0 ? 'text-success' : 'text-danger' }} fw-bold">
                        {{ $transaction->amount >= 0 ? '+' : '' }}{{ $transaction->amount }}
                    </td>
                    <td>{{ $transaction->order?->order_no ?? '—' }}</td>
                    <td>{{ $transaction->note ?: '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        {{-- Pagination --}}
        <div class="d-flex justify-content-center mt-3">
            {{ $transactions->links('backend.pagination') }}
        </div>
    @endif

</div>
