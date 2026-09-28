<meta charset="UTF-8">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('frontend/images/favicon/apple-touch-icon.png') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('frontend/images/favicon/favicon-32x32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('frontend/images/favicon/favicon-16x16.png') }}">
<link rel="icon" type="image/x-icon" href="{{ asset('frontend/images/favicon/favicon.ico') }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('frontend/images/favicon/android-chrome-192x192.png') }}">
<link rel="icon" type="image/png" sizes="512x512" href="{{ asset('frontend/images/favicon/android-chrome-512x512.png') }}">

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
@hasSection('og_image')
<meta name="twitter:card" content="summary_large_image">
@else
<meta name="twitter:card" content="summary">
@endif
<meta name="twitter:title" content="@yield('og_title', 'Parfumshop.az | Ətirlər')">
<meta name="twitter:description" content="@yield('meta_description', 'Parfumshop.az — ətirlər və parfümeriya')">
@yield('structured_data')

@if(request()->routeIs('home') && isset($banners))
    @if(!empty($banners['topmobile']['image']))
        <link rel="preload" as="image" media="(max-width: 760px)" href="{{ asset('frontend/uploads/banners/' . $banners['topmobile']['image']) }}" fetchpriority="high">
    @endif
    @if(!empty($banners['topweb']['image']))
        <link rel="preload" as="image" media="(min-width: 761px)" href="{{ asset('frontend/uploads/banners/' . $banners['topweb']['image']) }}" fetchpriority="high">
    @endif
@endif

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
<link rel="preload" as="style" href="{{ asset('frontend/css/vendor/select2.min.css') }}" onload="this.onload=null;this.rel='stylesheet'">
<link rel="preload" as="style" href="{{ asset('frontend/css/vendor/select2-bootstrap4.min.css') }}" onload="this.onload=null;this.rel='stylesheet'">
<noscript>
    <link rel="stylesheet" href="{{ asset('frontend/css/vendor/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/css/vendor/select2-bootstrap4.min.css') }}">
</noscript>
@yield('css')
