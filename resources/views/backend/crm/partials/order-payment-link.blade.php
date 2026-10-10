{{-- SMS ödəniş linki: müştəri login olmadan ödəyir (OrderPayLinkService). "Ödənişlər" tabının sol sütunu. --}}
@php
    $pendingPayment = $order->payments->firstWhere('status', \App\Models\Payment\Payment::PENDING);
    $payLinkExpired = !$order->pay_token_expires_at || $order->pay_token_expires_at->isPast();
@endphp
<h2 class="small-title">Ödəniş linki</h2>
<div class="card" data-pay-link>
    <div class="card-body">
        <input type="text" class="form-control mb-2" value="{{ $payLinkUrl }}" readonly data-pay-link-url aria-label="Ödəniş linki">
        <div class="d-grid gap-2">
            <button type="button" class="btn btn-primary" data-pay-link-sms
                    data-url="{{ route('admin.crm.order.pay-link', [$customer, $order]) }}">SMS ilə göndər</button>
            <button type="button" class="btn btn-outline-primary" data-pay-link-copy>Kopyala</button>
        </div>
        <div class="form-text mt-2">SMS {{ $order->customer?->mobile ?? $customer->mobile }} nömrəsinə gedir və linkin müddətini yeniləyir.</div>

        <hr>

        <div class="text-muted small mb-1">Müddət</div>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            @if($payLinkExpired)
                <span class="badge bg-outline-danger">Vaxtı bitib</span>
            @else
                <span>{{ $order->pay_token_expires_at->timezone('Asia/Baku')->format('d.m.Y H:i') }}-dək aktivdir</span>
            @endif
            {{-- SMS göndərmədən müddəti yenidən sayır (linki başqa yolla atanda) --}}
            <button type="button" class="btn btn-sm btn-outline-secondary" data-pay-link-renew
                    data-url="{{ route('admin.crm.order.pay-link-renew', [$customer, $order]) }}">Müddəti yenilə</button>
        </div>
        @if($payLinkExpired)
            <div class="form-text text-danger">Müştəri bu linklə sifarişi görmür.</div>
        @endif

        @if($pendingPayment)
            {{-- Yarımçıq cəhd: müştəri linkdən eyni bank səhifəsinə davam edə bilər; nəticəni operator dərhal yoxlaya bilər --}}
            <div class="alert alert-warning mt-3 mb-0">
                <div class="mb-2">Bankda nəticəsi bəlli olmayan ödəniş var ({{ $pendingPayment->created_at?->timezone('Asia/Baku')->format('d.m.Y H:i') }}).
                    Müştəri linkə keçib həmin ödənişə davam edə bilər.</div>
                <button type="button" class="btn btn-sm btn-outline-dark" data-payment-check
                        data-url="{{ route('admin.crm.order.payment-check', [$customer, $order]) }}">Bankdan yoxla</button>
            </div>
        @endif
    </div>
</div>
