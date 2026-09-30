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
