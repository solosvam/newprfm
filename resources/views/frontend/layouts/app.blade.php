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
        ],
        // hissə-hissə: ≤ limit məbləğdə yalnız bu aylar (CreditPeriod::availableFor ilə eyni)
        'creditRule' => \App\Models\Credit\CreditPeriod::amountRule(),
    ];
@endphp
<script type="application/json" id="app-data">@json($appData)</script>
@include('frontend.partials.theme-script')
@yield('page-scripts')
</body>
</html>
