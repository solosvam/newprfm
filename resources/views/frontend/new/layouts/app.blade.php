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
@include('frontend.new.partials.theme-script')
</body>
</html>
