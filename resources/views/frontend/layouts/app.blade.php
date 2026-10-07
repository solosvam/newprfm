<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="light">
<head>
    @include('frontend.partials.head')
</head>
<body data-auth="{{ auth()->check() ? 1 : 0 }}" data-customer-id="{{ auth()->id() }}">
@include('frontend.partials.nav')

<div class="wrap">
    @yield('content')
</div>

@include('frontend.partials.footer')
@php
    $appData = [
        'cart' => [
            'indexUrl' => route('cart.items'),
            'mergeUrl' => route('cart.merge'),
            'changeUrl' => route('cart.change'),
        ],
        'flash' => [
            'success' => session('success') ?? session('review_success'),
            'error' => session('error') ?? ($errors->any() ? $errors->first() : null),
            'warning' => session('warning'),
            'info' => session('info'),
        ],
        'messages' => [
            'cartAdded' => __('notification_product_added_to_cart'),
            'favoriteAdded' => __('notification_added_to_favorites'),
            'favoriteRemoved' => __('notification_removed_from_favorites'),
            'selectBrand' => __('home_select_brand'),
            'passwordShow' => __('auth_password_show'),
            'passwordHide' => __('auth_password_hide'),
        ],
        // Web push (OneSignal): promptNow — səhifə özü icazə istəməyi tələb edir (məs. sifariş tamamlandı)
        'push' => config('services.onesignal.app_id') ? [
            'appId' => config('services.onesignal.app_id'),
            'customerId' => auth()->id(),
            'promptNow' => trim($__env->yieldContent('push-prompt')) === '1',
            'ios' => [
                'title' => __('push_ios_title'),
                'text' => __('push_ios_text'),
                'step1' => __('push_ios_step1'),
                'step2' => __('push_ios_step2'),
                'step3' => __('push_ios_step3'),
                'close' => __('push_ios_close'),
            ],
        ] : null,
        // hissə-hissə: hər müddətin minimum məbləği {ay: min} (CreditPeriod::availableFor ilə eyni) — yalnız @section('credit-rule') olan səhifələrdə
        'creditRule' => View::hasSection('credit-rule') ? \App\Models\Credit\CreditPeriod::amountRule() : null,
    ];
@endphp
<script type="application/json" id="app-data">@json($appData)</script>
@include('frontend.partials.theme-script')
@yield('page-scripts')
@include('frontend.partials.cookie-bar')
@include('frontend.partials.popups')
</body>
</html>
