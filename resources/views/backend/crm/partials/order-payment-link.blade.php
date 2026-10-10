<div class="row">
    @if($payLinkUrl)
        {{-- SMS ödəniş linki: müştəri login olmadan ödəyir (OrderPayLinkService) --}}
        <div class="col-12">
            <div class="text-muted small mb-1">Ödəniş linki</div>
            <div class="input-group">
                <input type="text" class="form-control" value="{{ $payLinkUrl }}" readonly data-pay-link-url>
                <button type="button" class="btn btn-outline-primary" data-pay-link-copy>Kopyala</button>
                <button type="button" class="btn btn-primary" data-pay-link-sms
                        data-url="{{ route('admin.crm.order.pay-link', [$customer, $order]) }}">SMS ilə göndər</button>
            </div>
            @php $payLinkExpired = !$order->pay_token_expires_at || $order->pay_token_expires_at->isPast(); @endphp
            <div class="form-text d-flex flex-wrap align-items-center gap-2">
                @if($payLinkExpired)
                    <span class="text-danger">Linkin vaxtı bitib — müştəri sifarişi görmür.</span>
                @else
                    <span>Link {{ $order->pay_token_expires_at->timezone('Asia/Baku')->format('d.m.Y H:i') }}-dək aktivdir.</span>
                @endif
                {{-- SMS göndərmədən müddəti yenidən sayır (linki başqa yolla atanda) --}}
                <button type="button" class="btn btn-link btn-sm p-0" data-pay-link-renew
                        data-url="{{ route('admin.crm.order.pay-link-renew', [$customer, $order]) }}">Müddəti yenilə</button>
            </div>
            <div class="form-text">"SMS ilə göndər" müddəti də yeniləyir. SMS {{ $order->customer?->mobile ?? $customer->mobile }} nömrəsinə gedir.</div>
        </div>
    @endif

</div>