<div class="p-3">
    @if($orders->isEmpty())
        <div class="text-center text-muted py-5">Bu müştərinin kredit sifarişi yoxdur.</div>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th style="width:60px;">No</th>
                    <th>Sifariş No</th>
                    <th>Tarix</th>
                    <th>Müddət</th>
                    <th>Faiz</th>
                    <th>Aylıq</th>
                    <th>Kredit məbləği</th>
                    <th>Kredit statusu</th>
                    <th>Sifariş statusu</th>
                    <th class="text-end" style="width:80px;">Edit</th>
                </tr>
                </thead>
                <tbody>
                @foreach($orders as $order)
                    @php
                        $credit = $order->creditApplication;
                        $orderStatusBadge = match ($order->status?->code) {
                            'new' => 'bg-info',
                            'confirmed' => 'bg-primary',
                            'preparing' => 'bg-warning text-dark',
                            'sent', 'courier' => 'bg-secondary',
                            'delivered' => 'bg-success',
                            'cancelled' => 'bg-danger',
                            default => 'bg-light text-dark',
                        };
                    @endphp
                    <tr>
                        <td class="text-muted">{{ $orders->firstItem() + $loop->index }}</td>
                        <td class="fw-semibold">{{ $order->order_no }}</td>
                        <td class="text-nowrap">{{ $order->created_at?->format('d.m.Y H:i') }}</td>
                        <td>{{ $credit?->period?->name_az ?? $credit?->period?->name ?? '—' }}</td>
                        <td>{{ $credit ? number_format((float)$credit->interest_rate, 2) . '%' : '—' }}</td>
                        <td class="text-nowrap">{{ $credit ? number_format((float)$credit->monthly, 2) . ' ₼' : '—' }}</td>
                        <td class="fw-semibold text-nowrap">{{ number_format((float)($credit?->total ?? $order->total), 2) }} ₼</td>
                        <td><span class="badge bg-warning text-dark">{{ $credit?->status?->name_az ?? '—' }}</span></td>
                        <td><span class="badge {{ $orderStatusBadge }}">{{ $order->status?->name_az ?? '—' }}</span></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary crm-order-detail"
                                    data-url="{{ route('admin.crm.order', ['customer' => $order->customer_id, 'order' => $order->id]) }}"
                                    data-bs-toggle="modal" data-bs-target="#orderModal" title="Sifarişi redaktə et">
                                <i data-acorn-icon="edit" data-acorn-size="15"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-center mt-3">{{ $orders->links('backend.pagination') }}</div>
    @endif
</div>
