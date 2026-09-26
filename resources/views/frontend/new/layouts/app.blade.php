<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="light">
<head>
    @include('frontend.new.partials.head')
</head>
<body data-auth="{{ auth()->check() ? 1 : 0 }}">
@include('frontend.new.partials.nav')

<div class="wrap">
    @yield('content')
</div>

@include('frontend.new.partials.footer')
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
@include('frontend.new.partials.theme-script')
</body>
</html>
