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

@if($order->one_click)
    <div class="alert alert-info mt-3">
        Bir kliklə al · Əlaqə nömrəsi: <strong>{{ $order->guest_mobile }}</strong>
        @if(!$order->customer_address_id) · Ünvan dəqiqləşdirilməyib. @endif
    </div>
    @if($order->payment_status !== 'paid')
        <form method="POST" action="{{ route('admin.crm.one-click.confirm', [$customer, $order]) }}" class="border rounded p-3 mt-3">
            @csrf
            <h5>Ünvan və ödəniş üsulunu təsdiqlə</h5>
            <div class="mb-3">
                <label for="oneClickAddress" class="form-label">Çatdırılma ünvanı</label>
                <select id="oneClickAddress" name="customer_address_id" class="form-select" required>
                    <option value="">Seçin</option>
                    @foreach($addresses as $address)
                        <option value="{{ $address->id }}" @selected($order->customer_address_id === $address->id)>
                            {{ $address->title }} — {{ $address->city }}, {{ $address->address }}
                        </option>
                    @endforeach
                </select>
                <small class="text-muted">Ünvan siyahıda yoxdursa, əvvəlcə müştərinin Tənzimləmələr bölməsində əlavə edin.</small>
            </div>
            <div class="mb-3">
                <label for="oneClickPayment" class="form-label">Ödəniş üsulu</label>
                <select id="oneClickPayment" name="payment_method_id" class="form-select" required>
                    @foreach($oneClickPaymentMethods as $method)
                        <option value="{{ $method->id }}" @selected($order->payment_method_id === $method->id)>
                            {{ $method->name_az ?? $method->name }}
                        </option>
                    @endforeach
                </select>
                <small class="text-muted">Onlayn kart seçilərsə, sifariş ödəniş gözləyəcək; müştəri öz profilindən ödəniş edə bilər.</small>
            </div>
            <button type="submit" class="btn btn-primary">Təsdiqlə</button>
        </form>
    @endif
@endif
