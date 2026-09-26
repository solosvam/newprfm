@extends('frontend.layouts.app')

@section('content')
    <main>
        <div class="wrap">
            <div class="checkout-page">
                <h1 class="cart-title">{{ __('cart_checkout') }}</h1>

                <div class="checkout-grid">
                    <section class="account-panel">
                        <h2 class="checkout-section-title">{{ __('checkout_delivery_address') }}</h2>

                        @if($addresses->isNotEmpty())
                            <select id="addressSelect" class="brand-select checkout-address-select select2">
                                @foreach($addresses as $a)
                                    <option value="{{ $a->id }}">{{ $a->label }}</option>
                                @endforeach
                                <option value="new">{{ __('checkout_add_new_address') }}</option>
                            </select>
                        @else
                            <input type="hidden" id="addressSelect" value="new">
                        @endif

                        <div id="newAddress" class="checkout-address-form {{ $addresses->isNotEmpty() ? 'checkout-hidden' : '' }}">
                            <div class="checkout-fields">
                                <div class="checkout-field">
                                    <input type="text" id="addressTitle" placeholder="{{ __('checkout_address_name_home_work') }}">
                                </div>
                                <div class="checkout-field">
                                    <input type="text" id="city" placeholder="{{ __('checkout_city') }}">
                                </div>
                                <div class="checkout-field">
                                    <input type="text" id="district" placeholder="{{ __('checkout_district') }}">
                                </div>
                                <div class="checkout-field">
                                    <input type="text" id="address" placeholder="{{ __('checkout_street_and_address') }}">
                                </div>
                                <div class="checkout-field">
                                    <input type="text" id="building" placeholder="{{ __('checkout_building') }}">
                                </div>
                                <div class="checkout-field">
                                    <input type="text" id="entrance" placeholder="{{ __('checkout_entrance') }}">
                                </div>
                                <div class="checkout-field">
                                    <input type="text" id="floor" placeholder="{{ __('checkout_floor') }}">
                                </div>
                                <div class="checkout-field">
                                    <input type="text" id="apartment" placeholder="{{ __('checkout_apartment') }}">
                                </div>
                            </div>
                            <div class="checkout-field checkout-field--textarea">
                                <textarea id="addressNote" placeholder="{{ __('checkout_address_note') }}"></textarea>
                            </div>
                        </div>

                        <h2 class="checkout-section-title">{{ __('checkout_payment_method') }}</h2>
                        <div class="checkout-payment">
                            @foreach($paymentMethods as $m)
                                <label class="checkout-payment-option">
                                    <input type="radio" name="payment_method" value="{{ $m->id }}" @checked($loop->first)>
                                    <span>{{ $m->name }}</span>
                                </label>
                            @endforeach
                        </div>

                        <h2 class="checkout-section-title">{{ __('checkout_additional_options') }}</h2>
                        <label class="checkout-check">
                            <input type="checkbox" id="giftWrap" value="1">
                            <span>{{ __('checkout_gift_wrap_the_order') }}</span>
                        </label>
                        <textarea id="customerNote" class="checkout-note" placeholder="{{ __('checkout_order_note') }}"></textarea>

                        <div id="checkoutError" class="form-alert form-alert-error checkout-error" hidden></div>
                    </section>

                    <aside class="cart-summary checkout-summary">
                        <h2 class="checkout-section-title">{{ __('checkout_your_order') }}</h2>
                        <div id="checkoutItems" class="checkout-items"></div>
                        <div class="cart-summary__row cart-summary__total checkout-total">
                            <span>{{ __('checkout_total') }}</span>
                            <strong id="checkoutTotal">0.00 ₼</strong>
                        </div>
                        <button id="placeOrder" type="button" class="btn btn-dark checkout-submit">{{ __('checkout_confirm_order') }}</button>
                    </aside>
                </div>
            </div>
        </div>
    </main>
@endsection

@section('page-scripts')
    <script>
        window.checkoutConfig = {
            storeUrl: @json(route('checkout.store')),
            cartProductsUrl: @json(route('cart.products')),
            cartUrl: @json(route('cart')),
            csrf: @json(csrf_token()),
            messages: {
                error: @json(__('auth_something_went_wrong')),
            },
        };
    </script>
    <script src="{{ asset('frontend/new/js/checkout.js') }}" defer></script>
@endsection
