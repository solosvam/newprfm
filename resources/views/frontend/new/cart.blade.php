@extends('frontend.new.layouts.app')

@section('content')
    <main>
        <div class="wrap">
            <div id="cartPage"
                 class="cart-page"
                 data-products-url="{{ route('cart.products') }}"
                 data-remove-label="{{ __('cart_remove_from_cart') }}">

                <a href="{{ route('home') }}" class="account-back">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                    <span>{{ __('cart_go_back') }}</span>
                </a>

                <h1 class="cart-title">{{ __('cart_cart') }}</h1>

                <div class="cart-layout">
                    <div id="cartItems" class="cart-items"></div>

                    <aside class="cart-summary">
                        <div id="cartSummaryLines" class="cart-summary__lines"></div>

                        <div class="cart-summary__row cart-summary__subtotal">
                            <span>{{ __('cart_subtotal') }}</span>
                            <strong id="cartSubtotal">0.00 ₼</strong>
                        </div>
                        <div class="cart-summary__row cart-summary__discount">
                            <span>{{ __('cart_discount') }}</span>
                            <strong>0.00 ₼</strong>
                        </div>
                        <div class="cart-summary__row cart-summary__total">
                            <span>{{ __('cart_total') }}</span>
                            <strong id="cartTotal">0.00 ₼</strong>
                        </div>

                        @auth
                            <a class="btn btn-dark cart-checkout-button" href="{{ route('checkout') }}">{{ __('cart_checkout') }}</a>
                        @else
                            <a class="btn btn-dark cart-checkout-button" href="{{ route('front.login', ['redirect' => route('checkout')]) }}">{{ __('cart_checkout') }}</a>
                        @endauth
                    </aside>
                </div>

                <div id="cartEmpty" class="cart-empty">
                    <p>{{ __('cart_your_cart_is_empty') }}</p>
                    <a href="{{ route('home') }}" class="btn btn-dark cart-empty__link">{{ __('cart_browse_products') }}</a>
                </div>
            </div>
        </div>
    </main>
@endsection

@section('page-scripts')
    <script src="{{ asset('frontend/js/cart.js') }}" defer></script>
@endsection
