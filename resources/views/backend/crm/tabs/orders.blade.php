<div class="p-3">
    @if($orders->isEmpty())
        <div class="text-center text-muted py-5">Bu müştərinin sifarişi yoxdur.</div>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th style="width: 60px;">No</th>
                    <th>Sifariş No</th>
                    <th>Tarix</th>
                    <th>Ödəniş üsulu</th>
                    <th>Ödəniş statusu</th>
                    <th>Sifariş statusu</th>
                    <th>Source</th>
                    <th class="text-end">Məbləğ</th>
                    <th class="text-end" style="width: 80px;">Edit</th>
                </tr>
                </thead>
                <tbody>
                @foreach($orders as $order)
                    @php
                        $paymentCode = $order->paymentMethod?->code;

                        $paymentBadge = match ($paymentCode) {
                            'cash' => 'bg-success',
                            'birbank_installment' => 'bg-primary',
                            'card_online' => 'bg-info',
                            'installment' => 'bg-warning text-dark',
                            'bonus_balance' => 'bg-secondary',
                            default => 'bg-light text-dark',
                        };

                        $paymentStatus = null;
                        $paymentStatusBadge = 'bg-light text-dark';

                        if (in_array($paymentCode, ['birbank_installment', 'card_online'], true)) {
                            $paymentStatus = match ($order->payment_status) {
                                'paid' => 'Ödənilib',
                                'pending' => 'Gözləyir',
                                'failed' => 'Uğursuz',
                                'cancelled' => 'Ləğv edilib',
                                'cod' => 'Qapıda ödəniş',
                                default => $order->payment_status ?: '—',
                            };

                            $paymentStatusBadge = match ($order->payment_status) {
                                'paid' => 'bg-success',
                                'pending' => 'bg-warning text-dark',
                                'failed', 'cancelled' => 'bg-danger',
                                default => 'bg-light text-dark',
                            };
                        } elseif ($paymentCode === 'installment') {
                            $paymentStatus = $order->creditApplication?->status?->name_az ?? '—';
                            $paymentStatusBadge = $order->creditApplication ? 'bg-warning text-dark' : 'bg-light text-dark';
                        }

                        $orderStatusBadge = match ($order->status?->code) {
                            'new' => 'bg-info',
                            'confirmed' => 'bg-primary',
                            'preparing' => 'bg-warning text-dark',
                            'sent', 'courier' => 'bg-secondary',
                            'delivered' => 'bg-success',
                            'cancelled' => 'bg-danger',
                            default => 'bg-light text-dark',
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
                        <td>
                            <span class="badge {{ $paymentBadge }}">
                                {{ $order->paymentMethod?->name_az ?? '—' }}
                            </span>
                        </td>
                        <td>
                            @if($paymentStatus)
                                <span class="badge {{ $paymentStatusBadge }}">{{ $paymentStatus }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $orderStatusBadge }}">
                                {{ $order->status?->name_az ?? '—' }}
                            </span>
                        </td>
                        <td>{{ $source }}</td>
                        <td class="text-end text-nowrap">
                            <div class="fw-semibold">{{ number_format((float) $order->total, 2) }} ₼</div>
                            @if((float) $order->bonus_earned > 0)
                                <small class="text-success">+{{ number_format((float) $order->bonus_earned, 2) }} ₼ bonus</small>
                            @endif
                        </td>
                        <td class="text-end">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary crm-order-detail"
                                data-url="{{ route('admin.crm.order', ['customer' => $order->customer_id, 'order' => $order->id]) }}"
                                data-bs-toggle="modal"
                                data-bs-target="#orderModal"
                                title="Sifarişi redaktə et"
                            >
                                <i data-acorn-icon="edit" data-acorn-size="15"></i>
                            </button>
                        </td>
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
