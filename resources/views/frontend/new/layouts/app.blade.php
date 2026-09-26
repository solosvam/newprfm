<!DOCTYPE html>
<html lang="az" data-theme="light">
<head>
    @include('frontend.new.partials.head')
</head>
<body>
@include('frontend.new.partials.nav')

<div class="wrap">
    @yield('content')
</div>

@include('frontend.new.partials.footer')
@include('frontend.new.partials.theme-script')
</body>
</html>
