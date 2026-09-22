<div class="p-3">
    @if($orders->isEmpty())
        <div class="text-center text-muted py-5">Ödəniş məlumatı yoxdur.</div>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>Sifariş №</th>
                    <th>Tarix</th>
                    <th>Ödəniş üsulu</th>
                    <th>Status</th>
                    <th class="text-end">Məbləğ</th>
                </tr>
                </thead>
                <tbody>
                @foreach($orders as $order)
                    <tr>
                        <td class="fw-semibold">{{ $order->order_no }}</td>
                        <td>{{ $order->created_at?->format('d.m.Y H:i') }}</td>
                        <td>{{ $order->paymentMethod?->name ?? '—' }}</td>
                        <td>{{ $order->status?->name ?? '—' }}</td>
                        <td class="text-end fw-semibold">{{ number_format((float) $order->total, 2) }} ₼</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-center mt-3">
            {{ $orders->links('backend.pagination') }}
        </div>
    @endif
</div>
