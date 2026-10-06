{{-- SMS ödəniş linki: /p/{token} — PayLinkController. Login tələb olunmur. --}}
@extends('frontend.layouts.app')

@section('page-css')
<link rel="stylesheet" href="{{ asset_v('frontend/css/pages/order-detail.css') }}">
<link rel="stylesheet" href="{{ asset_v('frontend/css/pages/pay-link.css') }}">
@endsection

@section('content')
    @php
        $isInstallment = $order->paymentMethod?->code === 'birbank_installment';
        $months = (int) $order->birbank_installment_months;
        $total = (float) $order->total;
        $canPay = in_array($state, ['pay', 'failed'], true);
        $firstName = trim(explode(' ', (string) $order->customer?->name)[0] ?? '');
        $states = [
            'pay'         => ['awaiting', __('orders_payment_awaiting'), __('paylink_pay_hint')],
            'failed'      => ['danger',   __('orders_payment_failed'), __('orders_payment_failed_hint')],
            'paid'        => ['success',  __('paylink_paid'), __('paylink_paid_hint')],
            'checking'    => ['awaiting', __('paylink_checking'), __('paylink_checking_hint')],
            'cancelled'   => ['muted',    __('paylink_cancelled'), __('paylink_cancelled_hint')],
            'unavailable' => ['muted',    __('paylink_unavailable'), __('paylink_unavailable_hint')],
        ];
        [$tone, $stateTitle, $stateHint] = $states[$state];
        // Link hamı üçün açıqdır: yalnız şəhər və küçə (mənzil, telefon göstərilmir)
        $addressShort = collect([$order->address?->city, $order->address?->address])->filter()->implode(', ');
    @endphp

    <main class="paylink">
        <header class="paylink__head">
            @if($firstName)<p class="paylink__hello">{{ __('paylink_hello', ['name' => $firstName]) }}</p>@endif
            <h1 class="paylink__title">{{ __('orders_order') }} № {{ $order->order_no }}</h1>
            <p class="paylink__meta">
                <time datetime="{{ $order->created_at->toIso8601String() }}">{{ $order->created_at->format('d.m.Y, H:i') }}</time>
                <span>{{ $order->items->sum('quantity') }} {{ __('orders_products') }}</span>
            </p>
        </header>

        <div class="paylink__state paylink__state--{{ $tone }}" role="status">
            <span class="paylink__state-icon" aria-hidden="true">
                @if($state === 'paid')
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                @elseif(in_array($state, ['failed', 'cancelled', 'unavailable'], true))
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.01"/></svg>
                @else
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                @endif
            </span>
            <div>
                <strong>{{ $stateTitle }}</strong>
                <span>{{ $stateHint }}</span>
            </div>
        </div>

        {{-- Məhsullar --}}
        <section class="od-card">
            <div class="od-card__head">
                <h2 class="od-card__title">{{ __('orders_items') }}</h2>
                <span class="od-card__aside">{{ $order->items->count() }}</span>
            </div>
            <ul class="od-items">
                @foreach($order->items as $item)
                    @php
                        $image = $item->product?->images?->first();
                        $size = $item->variant?->size?->{'name_' . app()->getLocale()} ?: $item->variant?->size?->name_az;
                    @endphp
                    <li class="od-item">
                        <div class="od-item__img">
                            @if($image)
                                <img src="{{ asset('frontend/uploads/products/' . $image->image) }}" alt="{{ $item->product?->name }}" loading="lazy">
                            @endif
                        </div>
                        <div class="od-item__info">
                            <div class="od-item__name">{{ $item->product?->name ?? __('orders_product') }}</div>
                            <div class="od-item__meta">
                                @if($item->product?->brand)<span>{{ $item->product->brand->name }}</span>@endif
                                @if($size)<span>{{ $size }}</span>@endif
                            </div>
                        </div>
                        <div class="od-item__price">
                            <div class="od-item__total">{{ number_format((float) $item->total, 2) }} ₼</div>
                            <div class="od-item__unit">
                                {{ $item->quantity }} ×
                                @if($item->list_price > $item->unit_price)<s>{{ number_format((float) $item->list_price, 2) }}</s>@endif
                                {{ number_format((float) $item->unit_price, 2) }} ₼
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- Ödəniş --}}
        <section class="od-card">
            <div class="od-card__head">
                <h2 class="od-card__title">{{ __('orders_payment_details') }}</h2>
            </div>
            <div class="od-card__body">
                <div class="od-pay-method">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/></svg>
                    {{ $order->paymentMethod?->localized_name }}
                    @if($isInstallment && $months) · {{ $months }} {{ __('orders_months') }}@endif
                </div>
                @if($isInstallment && $months)
                    <div class="paylink__monthly">
                        <span>{{ __('orders_monthly_payment') }}</span>
                        <strong>{{ number_format($total / $months, 2) }} ₼ × {{ $months }}</strong>
                    </div>
                @endif

                @include('frontend.partials.order-totals', ['order' => $order, 'total' => $total])

                @if($bonus > 0)
                    <div class="od-bonus">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 12v9H4v-9M2 7h20v5H2zM12 21V7M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7z"/></svg>
                        @if((float) $order->bonus_earned > 0)
                            +{{ number_format($bonus, 2) }} ₼ {{ __('orders_bonus_earned_note') }}
                        @else
                            {{ __('orders_bonus_on_delivery', ['amount' => number_format($bonus, 2)]) }}
                        @endif
                    </div>
                @endif
            </div>
        </section>

        @if($addressShort)
            <section class="od-card">
                <div class="od-card__head">
                    <h2 class="od-card__title">{{ __('paylink_address') }}</h2>
                </div>
                <div class="od-card__body">
                    <div class="od-address">{{ $addressShort }}</div>
                </div>
            </section>
        @endif

        @if($canPay)
            {{-- Mobildə ekranın altına yapışır --}}
            <form method="POST" action="{{ route('pay.link.start', $token) }}" class="paylink__bar">
                @csrf
                <button type="submit" class="btn btn-dark paylink__btn">
                    {{ $state === 'failed' ? __('orders_payment_retry') : __('orders_payment_pay') }} · {{ number_format($total, 2) }} ₼
                </button>
                <p class="paylink__secure">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                    {{ __('paylink_secure') }}
                </p>
            </form>
        @elseif($state === 'checking')
            <a href="{{ route('pay.link', $token) }}" class="btn btn-outline paylink__btn">{{ __('paylink_refresh') }}</a>
        @endif
    </main>
@endsection
