{{--
  Sifarişin hesabı (sifariş detalı, ödəniş linki): Məhsullar − Endirim + Çatdırılma + Qablaşdırma − Ləğv olunan = Toplam.
  Order::totalsBreakdown(); $total — ödəniş linkində kredit faizi ilə yekun (verilməyibsə sifarişin yekunu).
--}}
@php
    $sum = $order->totalsBreakdown();
    $grand = $total ?? $sum['total'];
@endphp
<div class="od-totals">
    <div class="od-row"><span>{{ __('orders_subtotal') }}</span><span>{{ number_format($sum['goods'], 2) }} ₼</span></div>
    @if($sum['discount'] > 0)
        <div class="od-row od-row--discount"><span>{{ __('orders_discount') }}</span><span>−{{ number_format($sum['discount'], 2) }} ₼</span></div>
    @endif
    @if($sum['referral'] > 0)
        <div class="od-row od-row--discount"><span>{{ __('cart_referral_discount') }}</span><span>−{{ number_format($sum['referral'], 2) }} ₼</span></div>
    @endif
    @if((float) $order->delivery_fee > 0)
        <div class="od-row"><span>{{ __('orders_delivery') }}</span><span>{{ number_format((float) $order->delivery_fee, 2) }} ₼</span></div>
    @endif
    @if((float) $order->gift_wrap_fee > 0)
        <div class="od-row"><span>{{ __('paylink_gift_wrap') }}</span><span>{{ number_format((float) $order->gift_wrap_fee, 2) }} ₼</span></div>
    @endif
    @if($sum['cancelled'] > 0)
        <div class="od-row od-row--cancelled"><span>{{ __('orders_cancelled_amount') }}</span><span>−{{ number_format($sum['cancelled'], 2) }} ₼</span></div>
    @endif
    @if((float) $order->bonus_used > 0)
        <div class="od-row od-row--discount"><span>{{ __('orders_paid_with_bonuses') }}</span><span>−{{ number_format((float) $order->bonus_used, 2) }} ₼</span></div>
    @endif
    <div class="od-row od-row--total"><span>{{ __('orders_total') }}</span><span>{{ number_format($grand, 2) }} ₼</span></div>
</div>
