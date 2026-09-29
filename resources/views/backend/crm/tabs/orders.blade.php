<div class="p-3">
    @if($orders->isEmpty())
        <div class="text-center text-muted py-5">Bu müştərinin sifarişi yoxdur.</div>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th style="width:60px;">No</th>
                    <th>Sifariş No</th>
                    <th>Tarix</th>
                    <th>Ödəniş üsulu</th>
                    <th>Ödəniş statusu</th>
                    <th>Sifariş statusu</th>
                    <th>Source</th>
                    <th class="text-end">Məbləğ</th>
                    <th class="text-end" style="width:80px;">Edit</th>
                </tr>
                </thead>
                <tbody>
                @foreach($orders as $order)
                    @php
                        // Ödəniş statusu: dolu badge — cədvəldə yeganə "diqqət" rəngi
                        $paymentStatus = match ($order->payment_status) {
                            'paid' => ['Ödənilib', 'bg-success'],
                            'pending' => ['Gözləyir', 'bg-warning'],
                            'failed' => ['Uğursuz', 'bg-danger'],
                            'cancelled' => ['Ləğv edilib', 'bg-danger'],
                            'cod' => ['Qapıda ödəniş', 'bg-outline-muted'],
                            default => [$order->payment_status ?: '—', 'bg-outline-muted'],
                        };
                        // Sifariş statusu: outline badge — mərhələni göstərir, göz yormur
                        $orderStatusBadge = match ($order->status?->code) {
                            'new', 'confirmed' => 'bg-outline-primary',
                            'preparing' => 'bg-outline-warning',
                            'sent', 'courier' => 'bg-outline-quaternary',
                            'delivered' => 'bg-outline-success',
                            'cancelled' => 'bg-outline-danger',
                            default => 'bg-outline-muted',
                        };
                        $source = match ($order->source) {
                            'website', 'customer' => 'Müştəri',
                            'operator', 'admin' => 'Operator',
                            default => $order->source ?: '—',
                        };
                    @endphp
                    <tr>
                        <td class="text-muted">{{ $orders->firstItem() + $loop->index }}</td>
                        <td class="fw-semibold">{{ $order->order_no }}</td>
                        <td class="text-nowrap">{{ $order->created_at?->format('d.m.Y H:i') }}</td>
                        <td class="text-nowrap">{{ $order->paymentMethod?->name_az ?? '—' }}</td>
                        <td><span class="badge {{ $paymentStatus[1] }}">{{ $paymentStatus[0] }}</span></td>
                        <td><span class="badge {{ $orderStatusBadge }}">{{ $order->status?->name_az ?? '—' }}</span></td>
                        <td>{{ $source }}</td>
                        <td class="text-end text-nowrap">
                            <div class="fw-semibold">{{ number_format((float)$order->total, 2) }} ₼</div>
                            @if((float)$order->bonus_earned > 0)
                                <small class="text-success">+{{ number_format((float)$order->bonus_earned, 2) }} ₼ bonus</small>
                            @endif
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary"
                                    href="{{ route('admin.crm.order', ['customer' => $order->customer_id, 'order' => $order->id]) }}"
                                    title="Sifariş detalları" aria-label="Sifariş detalları">
                                <i data-acorn-icon="edit" data-acorn-size="15"></i>
                            </a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-center mt-3">{{ $orders->links('backend.pagination') }}</div>
    @endif
</div>
