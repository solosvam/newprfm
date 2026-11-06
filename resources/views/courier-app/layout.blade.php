{{--
  Kuryer tətbiqi (PWA, /kuryer): admin panelin şablonlarından, CSS və JS-indən asılı deyil.
  Yalnız public/courier-app/app.css və app.js. Səhifə: @section('title'), istəyə görə @section('back') — geri linki.
  Aşağı menyu: Sifarişlər, Hesab, Kuryerlər (baş kuryer).
--}}
@php
    $viewer = auth('admin')->user();
    $tab = $tab ?? null;
@endphp
<!doctype html>
<html lang="az">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#5b2a86">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') · Kuryer</title>
    <link rel="manifest" href="{{ asset('courier-app/manifest.webmanifest') }}">
    <link rel="icon" type="image/png" sizes="96x96" href="{{ asset('favicon/favicon-96x96.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('pwa/apple-touch-icon.png') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Kuryer">
    <link rel="stylesheet" href="{{ asset_v('courier-app/app.css') }}">
    <script src="{{ asset_v('courier-app/app.js') }}" defer></script>
</head>
<body data-sw="{{ route('kuryer.sw') }}" @if(!empty($refresh)) data-refresh @endif>
    <header class="bar">
        @hasSection('back')
            <a class="bar__back" href="@yield('back')" aria-label="Geri">@include('courier-app._icon', ['name' => 'back'])</a>
        @endif
        <h1 class="bar__title">@yield('title')</h1>
        <span class="bar__user">{{ trim($viewer->full_name) }}</span>
    </header>

    <main class="page">
        @include('courier-app._flash')
        @yield('content')
    </main>

    <nav class="tabs" aria-label="Menyu">
        <a href="{{ route('kuryer.home') }}" class="tabs__item {{ $tab === 'orders' ? 'is-active' : '' }}" @if($tab === 'orders') aria-current="page" @endif>
            @include('courier-app._icon', ['name' => 'box'])<span>Sifarişlər</span>
        </a>
        <a href="{{ route('kuryer.account') }}" class="tabs__item {{ $tab === 'account' ? 'is-active' : '' }}" @if($tab === 'account') aria-current="page" @endif>
            @include('courier-app._icon', ['name' => 'wallet'])<span>Hesab</span>
        </a>
        @can('courier.team')
            <a href="{{ route('kuryer.team') }}" class="tabs__item {{ $tab === 'team' ? 'is-active' : '' }}" @if($tab === 'team') aria-current="page" @endif>
                @include('courier-app._icon', ['name' => 'users'])<span>Kuryerlər</span>
            </a>
        @endcan
    </nav>
</body>
</html>
