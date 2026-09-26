@extends('frontend.layout')

@section('content')
<main>
    <div class="container">
        <div id="cartPage"
             class="cart-page"
             data-products-url="{{ route('cart.products') }}"
             data-remove-label="{{ __('cart_remove_from_cart') }}">
            <div class="cart-breadcrumb">
                <a href="{{ route('home') }}">← {{ __('cart_go_back') }}</a>
                <h1>{{ __('cart_cart') }}</h1>
            </div>

            <div class="cart-layout">
                <div id="cartItems" class="cart-items"></div>

                <aside class="cart-summary">
                    <div id="cartSummaryLines"></div>
                    <hr>
                    <div class="cart-summary__subtotal">
                        <span>{{ __('cart_subtotal') }}</span>
                        <strong id="cartSubtotal">0.00 ₼</strong>
                    </div>
                    <div class="cart-summary__discount">
                        <span>{{ __('cart_discount') }}</span>
                        <strong>0.00 ₼</strong>
                    </div>
                    <div class="cart-summary__total">
                        <span>{{ __('cart_total') }}</span>
                        <strong id="cartTotal">0.00 ₼</strong>
                    </div>

                    @auth
                        <a class="cart-checkout-button" href="{{ route('checkout') }}">{{ __('cart_checkout') }}</a>
                    @else
                        <a class="cart-checkout-button" href="{{ route('front.login', ['redirect' => route('checkout')]) }}">{{ __('cart_checkout') }}</a>
                    @endauth
                </aside>
            </div>

            <div id="cartEmpty" class="cart-empty">
                <p>{{ __('cart_your_cart_is_empty') }}</p>
                <a href="{{ route('home') }}">{{ __('cart_browse_products') }}</a>
            </div>
        </div>
    </div>
</main>
@endsection

@section('page-scripts')
    <script src="{{ asset('frontend/js/cart.js') }}" defer></script>
@endsection
