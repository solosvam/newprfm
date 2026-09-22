<div class="row g-3">
    <div class="col-md-6"><div class="text-muted small">Sifariş nömrəsi</div><div class="fw-semibold">{{ $order->order_no }}</div></div>
    <div class="col-md-6"><div class="text-muted small">Tarix</div><div>{{ $order->created_at?->format('d.m.Y H:i') ?? '—' }}</div></div>
    <div class="col-md-6"><div class="text-muted small">Status</div><div>{{ $order->status?->name ?? '—' }}</div></div>
    <div class="col-md-6"><div class="text-muted small">Ödəniş üsulu</div><div>{{ $order->paymentMethod?->name ?? '—' }}</div></div>
    @if($order->address)
        <div class="col-12"><div class="text-muted small">Çatdırılma ünvanı</div><div>{{ $order->address->label }}</div></div>
    @endif
    <div class="col-12">
        <div class="text-muted small mb-2">Məhsullar</div>
        <div class="border rounded">
            @foreach($order->items as $item)
                <div class="d-flex justify-content-between gap-3 p-2 border-bottom">
                    <span>{{ $item->product?->name ?? 'Silinmiş məhsul' }} ×{{ $item->quantity }}</span>
                    <span class="text-nowrap">{{ number_format((float) $item->total, 2) }} ₼</span>
                </div>
            @endforeach
            <div class="d-flex justify-content-between p-2 fw-bold"><span>Yekun</span><span>{{ number_format((float) $order->total, 2) }} ₼</span></div>
        </div>
    </div>
</div>
