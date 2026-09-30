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
            <div class="form-text">Link ödəniş edilənə qədər aktivdir. SMS {{ $order->customer?->mobile ?? $customer->mobile }} nömrəsinə gedir.</div>
        </div>
    @endif

</div>