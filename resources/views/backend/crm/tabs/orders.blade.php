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
                    <th>Status</th>
                    <th>Source</th>
                    <th class="text-end">Qazanılan bonus</th>
                    <th class="text-end" style="width: 80px;">Edit</th>
                </tr>
                </thead>
                <tbody>
                @foreach($orders as $order)
                    <tr>
                        <td class="text-muted">
                            {{ $orders->firstItem() + $loop->index }}
                        </td>
                        <td class="fw-semibold">
                            {{ $order->order_no }}
                        </td>
                        <td class="text-nowrap">
                            {{ $order->created_at?->format('d.m.Y H:i') }}
                        </td>
                        <td>
                            {{ $order->paymentMethod?->name ?? '—' }}
                        </td>
                        <td>
                            <span class="badge bg-light text-dark">
                                {{ $order->status?->name ?? '—' }}
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark">
                                {{ $order->source ?: '—' }}
                            </span>
                        </td>
                        <td class="text-end fw-semibold text-nowrap">
                            {{ number_format((float) $order->bonus_earned, 2) }} ₼
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
