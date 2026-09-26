<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="light">
<head>
    @include('frontend.partials.head')
</head>
<body data-auth="{{ auth()->check() ? 1 : 0 }}">
@include('frontend.partials.nav')

<div class="wrap">
    @yield('content')
</div>

@include('frontend.partials.footer')
<script>
    window.parfumshopFlash = {
        success: @json(session('success') ?? session('review_success')),
        error: @json(session('error') ?? ($errors->any() ? $errors->first() : null)),
        warning: @json(session('warning')),
        info: @json(session('info'))
    };
    window.parfumshopMessages = {
        cartAdded: @json(__('notification_product_added_to_cart')),
        favoriteAdded: @json(__('notification_added_to_favorites')),
        favoriteRemoved: @json(__('notification_removed_from_favorites'))
    };
</script>
@include('frontend.partials.theme-script')
@yield('page-scripts')
</body>
</html>
