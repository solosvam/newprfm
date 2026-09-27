<meta charset="UTF-8">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>@yield('title', 'Parfumshop.az | Ətirlər')</title>
<meta name="description" content="@yield('meta_description', 'Parfumshop.az — ətirlər və parfümeriya')">
<meta name="robots" content="@yield('meta_robots', 'index, follow')">
<link rel="canonical" href="@yield('canonical_url', url()->current())">
<meta property="og:site_name" content="Parfumshop.az">
<meta property="og:type" content="@yield('og_type', 'website')">
<meta property="og:title" content="@yield('og_title', 'Parfumshop.az | Ətirlər')">
<meta property="og:description" content="@yield('meta_description', 'Parfumshop.az — ətirlər və parfümeriya')">
<meta property="og:url" content="@yield('canonical_url', url()->current())">
@hasSection('og_image')
<meta property="og:image" content="@yield('og_image')">
<meta property="og:image:alt" content="@yield('og_title', 'Parfumshop.az')">
<meta name="twitter:image" content="@yield('og_image')">
@endif
<meta name="twitter:card" content="@hasSection('og_image')summary_large_image@else summary@endif">
<meta name="twitter:title" content="@yield('og_title', 'Parfumshop.az | Ətirlər')">
<meta name="twitter:description" content="@yield('meta_description', 'Parfumshop.az — ətirlər və parfümeriya')">
@yield('structured_data')

{{-- Flash-ın qarşısını almaq üçün tema CSS-dən əvvəl tətbiq olunur --}}
<script>
    (function () {
        var saved = localStorage.getItem('theme');
        var theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        document.documentElement.setAttribute('data-theme', theme);
    })();
</script>

<link rel="stylesheet" href="{{ asset('frontend/css/theme-light.css') }}">
<link rel="stylesheet" href="{{ asset('frontend/css/theme-dark.css') }}">
<link rel="stylesheet" href="{{ asset('frontend/css/main.css') }}">
<link rel="stylesheet" href="{{ asset('frontend/css/responsive.css') }}">
<link rel="stylesheet" href="{{ asset('frontend/css/vendor/select2.min.css') }}"/>
<link rel="stylesheet" href="{{ asset('frontend/css/vendor/select2-bootstrap4.min.css') }}"/>
@yield('css')
