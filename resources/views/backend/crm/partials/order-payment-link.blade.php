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
            @php $pendingPayment = $order->payments->firstWhere('status', \App\Models\Payment\Payment::PENDING); @endphp
            @if($pendingPayment)
                {{-- Yarımçıq cəhd: müştəri linkdən eyni bank səhifəsinə davam edə bilər; nəticəni operator dərhal yoxlaya bilər --}}
                <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2 py-2 mt-2 mb-0">
                    <span>Bankda nəticəsi bəlli olmayan ödəniş var ({{ $pendingPayment->created_at?->timezone('Asia/Baku')->format('d.m.Y H:i') }}).
                        Müştəri linkə keçib həmin ödənişə davam edə bilər.</span>
                    <button type="button" class="btn btn-sm btn-outline-dark" data-payment-check
                            data-url="{{ route('admin.crm.order.payment-check', [$customer, $order]) }}">Bankdan yoxla</button>
                </div>
            @endif
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