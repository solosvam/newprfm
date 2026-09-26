<meta charset="UTF-8">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>@yield('title', 'parfumshop')</title>

{{-- Flash-ın qarşısını almaq üçün tema CSS-dən əvvəl tətbiq olunur --}}
<script>
    (function () {
        var saved = localStorage.getItem('theme');
        var theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        document.documentElement.setAttribute('data-theme', theme);
    })();
</script>

<link rel="stylesheet" href="{{ asset('frontend/new/css/theme-light.css') }}">
<link rel="stylesheet" href="{{ asset('frontend/new/css/theme-dark.css') }}">
<link rel="stylesheet" href="{{ asset('frontend/new/css/main.css') }}">
<link rel="stylesheet" href="{{ asset('frontend/new/css/responsive.css') }}">
<link rel="stylesheet" href="{{ asset('frontend/new/css/vendor/select2.min.css') }}"/>
<link rel="stylesheet" href="{{ asset('frontend/new/css/vendor/select2-bootstrap4.min.css') }}"/>
@yield('css')
