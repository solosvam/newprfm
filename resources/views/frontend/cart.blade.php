@extends('frontend.layouts.app')

@section('page-css')
<link rel="stylesheet" href="{{ asset_v('frontend/css/components/order-summary.css') }}">
<link rel="stylesheet" href="{{ asset_v('frontend/css/pages/cart.css') }}">
@endsection

@section('content')
    @php
        $checkoutUrl = auth()->check()
            ? route('checkout')
            : route('front.login', ['redirect' => route('checkout')]);

        $cartI18n = [
            'remove'       => __('cart_remove_from_cart'),
            'removed'      => __('cart_item_removed'),
            'free'         => __('cart_free'),
            'itemsCount'   => __('cart_items_count'),
            'freeshipLeft' => __('cart_free_delivery_left'),
            'freeshipDone' => __('cart_free_delivery_done'),
            'installment'  => __('cart_installment_hint'),
            'installmentWithInterest' => __('cart_installment_with_interest'),
            'installmentHow' => __('cart_installment_how'),
            'bonus'        => __('cart_bonus_hint'),
            'decrease'     => __('cart_decrease'),
            'increase'     => __('cart_increase'),
        ];
        $delivery = app(\App\Services\ShopPricing::class)->delivery();
        $creditPeriod = \App\Models\Credit\CreditPeriod::where('active',1)->orderBy('sort_order')->first();
    @endphp

    <main>
        <div id="cartPage"
             class="cart-page is-loading"
             data-products-url="{{ route('cart.products') }}"
             data-delivery-fee="{{ $delivery['fee'] }}"
             data-free-delivery-from="{{ $delivery['free_from'] }}"
             data-bonus-rate="{{ app(\App\Services\ShopPricing::class)->bonusRate() }}"
             data-installment-months="{{ $creditPeriod?->month ?? 0 }}"
             data-installment-min="{{ 0 }}"
             data-installment-markup="{{ $creditPeriod?->interest_rate ?? 0 }}"
             data-i18n="{{ json_encode($cartI18n, JSON_UNESCAPED_UNICODE) }}">

            <a href="{{ route('home') }}" class="account-back">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                <span>{{ __('cart_go_back') }}</span>
            </a>

            <h1 class="cart-title">
                {{ __('cart_cart') }}
                <span id="cartCount" class="cart-title__count"></span>
            </h1>

            <div class="cart-layout">
                <section class="cart-main">
                    <div id="cartFreeship" class="cart-freeship" hidden>
                        <div id="cartFreeshipText" class="cart-freeship__text"></div>
                        <div class="cart-freeship__bar"><span id="cartFreeshipBar"></span></div>
                    </div>

                    <div id="cartItems" class="cart-items"></div>
                </section>

                <aside class="cart-summary">
                    <h2 class="cart-summary__title">{{ __('cart_order_summary') }}</h2>

                    @include('frontend.partials.promo-code')

                    <div class="cart-summary__rows">
                        <div class="cart-summary__row">
                            <span id="cartItemsLabel"></span>
                            <strong id="cartSubtotal">0.00 ₼</strong>
                        </div>
                        <div id="cartDiscountRow" class="cart-summary__row cart-summary__row--discount" hidden>
                            <span>{{ __('cart_discount') }} <em id="cartDiscountCode" class="cart-summary__code"></em></span>
                            <strong id="cartDiscount"></strong>
                        </div>
                        <div class="cart-summary__row">
                            <span>{{ __('cart_delivery') }}</span>
                            <strong id="cartDelivery"></strong>
                        </div>
                    </div>

                    <div class="cart-summary__total">
                        <span>{{ __('cart_total') }}</span>
                        <strong id="cartTotal">0.00 ₼</strong>
                    </div>

                    <p id="cartInstallment" class="cart-installment" hidden>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4"/></svg>
                        <span id="cartInstallmentText"></span>
                    </p>
                    <div class="cart-summary__sk cart-summary__sk--installment sk" aria-hidden="true"></div>
                    <a id="cartCheckout" class="btn btn-dark" href="{{ $checkoutUrl }}">
                        {{ __('cart_checkout') }}
                    </a>

                    <p id="cartBonus" class="cart-bonus" hidden>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 12v9H4v-9M2 7h20v5H2zM12 21V7M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7z"/></svg>
                        <span id="cartBonusText"></span>
                    </p>
                    <div class="cart-summary__sk cart-summary__sk--bonus sk" aria-hidden="true"></div>
                    <ul class="cart-trust">
                        <li>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                            {{ __('cart_trust_secure') }}
                        </li>
                        <li>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 2 4 5v6c0 5 3.4 9.3 8 11 4.6-1.7 8-6 8-11V5l-8-3z"/><path d="m9 12 2 2 4-4"/></svg>
                            {{ __('cart_trust_original') }}
                        </li>
                    </ul>
                </aside>
            </div>

            <div class="cart-empty">
                <div class="cart-empty__icon">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M6 7h12l-1 13H7L6 7z"/><path d="M9 7a3 3 0 0 1 6 0"/></svg>
                </div>
                <p>{{ __('cart_your_cart_is_empty') }}</p>
                <a href="{{ route('home') }}" class="btn btn-dark cart-empty__link">{{ __('cart_browse_products') }}</a>
            </div>

            <div id="cartMobilebar" class="cart-mobilebar">
                <div class="cart-mobilebar__total">
                    <span>{{ __('cart_total') }}</span>
                    <strong id="cartMobileTotal">0.00 ₼</strong>
                </div>
                <a class="btn btn-dark" href="{{ $checkoutUrl }}">{{ __('cart_checkout') }}</a>
            </div>

            <div id="cartToast" class="cart-toast" role="status" aria-live="polite">
                <span id="cartToastText"></span>
                <button type="button" id="cartToastUndo">{{ __('cart_undo') }}</button>
            </div>
        </div>

        {{-- defer-siz: məhsul sorğusu cart.js-i gözləmədən dərhal başlasın --}}
        <script src="{{ asset_v('frontend/js/pages/cart-prefetch.js') }}"></script>
    </main>
@endsection

@section('page-scripts')
    <script src="{{ asset_v('frontend/js/promo.js') }}" defer></script>
    <script src="{{ asset_v('frontend/js/cart.js') }}" defer></script>
@endsection
