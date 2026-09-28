@extends('frontend.layouts.app')

@section('page-css')
<link rel="stylesheet" href="{{ asset_v('frontend/css/pages/checkout.css') }}">
@endsection

@section('content')
    @php
        $checkoutI18n = [
            'free'              => __('cart_free'),
            'bonusBalance'      => __('checkout_bonus_balance'),
            'bonusInsufficient' => __('checkout_bonus_insufficient'),
            'perMonth'          => __('checkout_credit_per_month'),
            'earnedBonus'       => __('cart_bonus_hint'),
            'errPayment'        => __('checkout_select_payment_error'),
            'errProfile'        => __('credit_application_complete_profile'),
            'errPeriod'         => __('checkout_credit_select_period_error'),
            'errTerms'          => __('checkout_credit_accept_terms_error'),
        ];
        $selectedPayment = old('payment_method', $paymentMethods->first()?->id);
    @endphp

    <main>
        <div class="checkout-page" data-i18n="{{ json_encode($checkoutI18n, JSON_UNESCAPED_UNICODE) }}">
            <h1 class="cart-title">{{ __('cart_checkout') }}</h1>

            <div class="checkout-grid">
                <div class="checkout-main">

                    {{-- 1. Ünvan --}}
                    <section class="checkout-card">
                        <div class="checkout-step">
                            <span class="checkout-step__num">1</span>
                            <h2 class="checkout-step__title">{{ __('checkout_delivery_address') }}</h2>
                        </div>

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
                                <div class="checkout-field checkout-field--full"><input type="text" id="addressTitle" placeholder="{{ __('checkout_address_name_home_work') }}"></div>
                                <div class="checkout-field"><input type="text" id="city" placeholder="{{ __('checkout_city') }}"></div>
                                <div class="checkout-field"><input type="text" id="district" placeholder="{{ __('checkout_district') }}"></div>
                                <div class="checkout-field checkout-field--full"><input type="text" id="address" placeholder="{{ __('checkout_street_and_address') }}"></div>
                                <div class="checkout-field"><input type="text" id="building" placeholder="{{ __('checkout_building') }}"></div>
                                <div class="checkout-field"><input type="text" id="entrance" placeholder="{{ __('checkout_entrance') }}"></div>
                                <div class="checkout-field"><input type="text" id="floor" placeholder="{{ __('checkout_floor') }}"></div>
                                <div class="checkout-field"><input type="text" id="apartment" placeholder="{{ __('checkout_apartment') }}"></div>
                                <div class="checkout-field checkout-field--full"><textarea id="addressNote" placeholder="{{ __('checkout_address_note') }}"></textarea></div>
                            </div>
                        </div>
                    </section>

                    {{-- 2. Ödəniş --}}
                    <section class="checkout-card">
                        <div class="checkout-step">
                            <span class="checkout-step__num">2</span>
                            <h2 class="checkout-step__title">{{ __('checkout_payment_method') }}</h2>
                        </div>

                        <div class="checkout-payment" role="radiogroup">
                            @foreach($paymentMethods as $m)
                                @php
                                    $hintKey = "checkout_payment_hint_{$m->code}";
                                    $hint = \Illuminate\Support\Facades\Lang::has($hintKey) ? __($hintKey) : null;
                                @endphp
                                <label class="pay-option" data-payment-code="{{ $m->code }}">
                                    <input type="radio" name="payment_method" value="{{ $m->id }}" data-code="{{ $m->code }}"
                                        @checked($selectedPayment == $m->id)>
                                    <span class="pay-option__icon" aria-hidden="true">
                                        @switch($m->code)
                                            @case('cash')
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/></svg>
                                                @break
                                            @case('card_online')
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/></svg>
                                                @break
                                            @case('installment')
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4M8 14h2M14 14h2M8 17h2"/></svg>
                                                @break
                                            @case('bonus_balance')
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 12v9H4v-9M2 7h20v5H2zM12 21V7M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7z"/></svg>
                                                @break
                                            @default
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 7H5a2 2 0 0 1 0-4h13v4zM3 5v14a2 2 0 0 0 2 2h15V7"/><circle cx="16" cy="14" r="1.5"/></svg>
                                        @endswitch
                                    </span>
                                    <span class="pay-option__text">
                                        <span class="pay-option__title">{{ $m->localized_name }}</span>
                                        @if($m->code === 'bonus_balance')
                                            <span class="pay-option__hint" data-bonus-hint>
                                                {{ str_replace(':amount', number_format($bonusBalance, 2) . ' ₼', __('checkout_bonus_balance')) }}
                                            </span>
                                        @elseif($hint)
                                            <span class="pay-option__hint">{{ $hint }}</span>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        <div id="birbankInstallmentDetails" class="installment" hidden>
                            <div class="installment__label">Birbank taksit müddəti</div>
                            <div class="installment__periods">
                                @foreach([2, 3, 6] as $months)
                                    <label class="installment__period">
                                        <input type="radio" name="birbank_installment_months" value="{{ $months }}">
                                        <span class="installment__months">{{ $months }} ay</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <p id="bonusWarning" class="form-alert form-alert-error" hidden></p>

                        <div id="installmentDetails" class="installment" hidden>
                            @if(!$creditProfileComplete)
                                <div class="installment__notice">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>
                                    <span>{{ __('credit_application_complete_profile') }}</span>
                                    <a href="{{ route('profile.credit') }}" class="installment__notice-link">{{ __('credit_complete_profile_link') }} →</a>
                                </div>
                            @else
                                <div class="installment__label">{{ __('checkout_credit_choose_period') }}</div>

                                {{-- checkout.js bu input-un value-sunu oxuyur --}}
                                <input type="hidden" id="creditPeriod" value="">

                                <div class="installment__periods">
                                    @foreach($creditPeriods as $period)
                                        <label class="installment__period">
                                            <input type="radio" name="credit_period_choice" value="{{ $period->id }}"
                                                   data-months="{{ $period->month }}" data-rate="{{ $period->interest_rate }}">
                                            <span class="installment__months">{{ $period->month }} {{ __('checkout_months') }}</span>
                                            <span class="installment__monthly" data-monthly>—</span>
                                            <span class="installment__rate">
                                                @if((float) $period->interest_rate == 0)
                                                    {{ __('checkout_credit_no_interest') }}
                                                @endif
                                            </span>
                                        </label>
                                    @endforeach
                                </div>

                                <dl class="installment__summary" hidden>
                                    <div><dt>{{ __('checkout_credit_monthly') }}</dt><dd data-credit-monthly></dd></div>
                                    <div><dt>{{ __('checkout_credit_total') }}</dt><dd data-credit-total></dd></div>
                                    <div><dt>{{ __('checkout_credit_overpay') }}</dt><dd data-credit-overpay></dd></div>
                                </dl>

                                <p id="creditEstimate" hidden></p>

                                @php $creditTermsUrl = \App\Models\Setting::valueOf('credit_terms_url', ''); @endphp
                                <div class="checkout-check installment__terms">
                                    <input type="checkbox" id="creditTerms" aria-labelledby="creditTermsText">
                                    @if($creditTermsUrl)
                                        <a id="creditTermsText" href="{{ $creditTermsUrl }}" target="_blank" rel="noopener noreferrer" style="text-decoration: underline; text-underline-offset: 2px;">{{ __('checkout_credit_accept_terms') }}</a>
                                    @else
                                        <label id="creditTermsText" for="creditTerms">{{ __('checkout_credit_accept_terms') }}</label>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </section>

                    {{-- 3. Əlavə --}}
                    <section class="checkout-card">
                        <div class="checkout-step">
                            <span class="checkout-step__num">3</span>
                            <h2 class="checkout-step__title">{{ __('checkout_additional_options') }}</h2>
                        </div>

                        <label class="checkout-check">
                            <input type="checkbox" id="giftWrap" value="1">
                            <span>{{ __('checkout_gift_wrap_the_order') }}</span>
                        </label>
                        <textarea id="customerNote" class="checkout-note" placeholder="{{ __('checkout_order_note') }}"></textarea>
                    </section>

                    <div id="checkoutError" class="form-alert form-alert-error checkout-error" hidden></div>
                </div>

                {{-- Xülasə --}}
                <aside class="cart-summary checkout-summary">
                    <h2 class="cart-summary__title">{{ __('checkout_your_order') }}</h2>
                    <div id="checkoutItems" class="checkout-items"></div>

                    @include('frontend.partials.promo-code')

                    <div class="cart-summary__rows">
                        <div id="checkoutDiscountRow" class="cart-summary__row cart-summary__row--discount" hidden>
                            <span>{{ __('cart_discount') }}</span>
                            <strong id="checkoutDiscount">0.00 ₼</strong>
                        </div>
                        <div class="cart-summary__row">
                            <span>{{ __('cart_delivery') }}</span>
                            <strong id="checkoutDelivery">0.00 ₼</strong>
                        </div>
                        <div id="checkoutGiftWrapRow" class="cart-summary__row" hidden>
                            <span>{{ __('checkout_gift_wrap_label') }}</span>
                            <strong id="checkoutGiftWrapFee"></strong>
                        </div>
                    </div>

                    <div class="cart-summary__total">
                        <span>{{ __('checkout_total') }}</span>
                        <strong id="checkoutTotal">0.00 ₼</strong>
                    </div>

                    <p id="checkoutEarnedBonus" class="cart-bonus" style="color:#16803c;" hidden>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 12v9H4v-9M2 7h20v5H2zM12 21V7M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7z"/></svg>
                        <span id="checkoutEarnedBonusText"></span>
                    </p>

                    <button id="placeOrder" type="button" class="btn btn-dark">{{ __('checkout_confirm_order') }}</button>

                    @php $orderTermsUrl = \App\Models\Setting::valueOf('order_terms_url', ''); @endphp
                    @if($orderTermsUrl)
                        <p class="checkout-terms-notice">
                            {{ __('checkout_terms_before') }}
                            <a href="{{ $orderTermsUrl }}" target="_blank" rel="noopener noreferrer">{{ __('checkout_terms_link') }}</a>{{ __('checkout_terms_after') }}
                        </p>
                    @endif

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

            <div id="checkoutMobilebar" class="checkout-mobilebar">
                <div class="checkout-mobilebar__total">
                    <span>{{ __('checkout_total') }}</span>
                    <strong id="checkoutMobileTotal">0.00 ₼</strong>
                </div>
                <button type="button" id="checkoutMobileSubmit" class="btn btn-dark">
                    {{ __('checkout_confirm_order') }}
                </button>
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
            bonusBalance: @json($bonusBalance),
            bonusRate: @json(app(\App\Services\ShopPricing::class)->bonusRate()),
            delivery: @json(app(\App\Services\ShopPricing::class)->delivery()),
            giftWrap: @json(app(\App\Services\ShopPricing::class)->giftWrap()),
            creditProfileComplete: @json($creditProfileComplete),
            messages: {
                error: @json(__('auth_something_went_wrong')),
            },
        };
    </script>
    <script src="{{ asset_v('frontend/js/promo.js') }}" defer></script>
    <script src="{{ asset_v('frontend/js/checkout.js') }}" defer></script>
@endsection
