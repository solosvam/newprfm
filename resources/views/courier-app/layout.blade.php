{{--
  Kuryer tətbiqi (PWA, /kuryer): admin panelin şablonlarından, CSS və JS-indən asılı deyil.
  Yalnız public/courier-app/app.css və app.js. Səhifə: @section('title'), istəyə görə @section('back') — geri linki.
  Aşağı menyu: Sifarişlər, Hesab, Kuryerlər (baş kuryer).
  Telefonda / planşetdə tətbiq yalnız quraşdırılmış halda (ana ekrandakı ikondan) işləyir: brauzerdə açılanda
  .gate ekranı göstərilir (app.css — display-mode, app.js — quraşdırma düyməsi). Kompüterdə məhdudiyyət yoxdur.
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
    {{-- Kuryerin öz ikonu: loqolu hədiyyə çantası (public/courier-app/icons, mənbə — icon.svg / icon-maskable.svg) --}}
    <link rel="icon" type="image/png" sizes="96x96" href="{{ asset('courier-app/icons/favicon-96.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('courier-app/icons/apple-touch-icon.png') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Kuryer">
    <link rel="stylesheet" href="{{ asset_v('courier-app/app.css') }}">
    <script src="{{ asset_v('courier-app/app.js') }}" defer></script>
</head>
<body data-sw="{{ route('kuryer.sw') }}" @if(!empty($refresh)) data-refresh @endif>
    {{-- Quraşdırma ekranı: telefonda brauzerdə açılanda yalnız bu görünür --}}
    <section class="gate" aria-labelledby="gateTitle">
        <img class="gate__logo" src="{{ asset('courier-app/icons/icon-192.png') }}" alt="" width="88" height="88">
        <h1 class="gate__title" id="gateTitle">Kuryer tətbiqini quraşdırın</h1>
        <p class="gate__text">Sifarişlərlə işləmək üçün tətbiqi telefonun ana ekranına əlavə edin və ikondan açın.</p>

        {{-- Android (Chrome): brauzer quraşdırmağa icazə verəndə düymə görünür --}}
        <button type="button" class="btn btn--primary btn--block btn--lg" data-install hidden>@include('courier-app._icon', ['name' => 'download']) Quraşdır</button>

        {{-- iPhone / iPad: düymə mümkün deyil — Safari-də addımlar --}}
        <ol class="gate__steps" data-gate-ios hidden>
            <li><span class="gate__num">1</span><span>Safari-nin aşağısında <b>Paylaş</b> düyməsinə basın @include('courier-app._icon', ['name' => 'share'])</span></li>
            <li><span class="gate__num">2</span><span>Siyahıda <b>“Ana ekrana əlavə et”</b> seçin @include('courier-app._icon', ['name' => 'plus'])</span></li>
            <li><span class="gate__num">3</span><span><b>Əlavə et</b> basın, sonra ana ekranda <b>Kuryer</b> ikonundan açın</span></li>
        </ol>

        {{-- Digər hallar (düymə gəlməyibsə) --}}
        <ol class="gate__steps" data-gate-other>
            <li><span class="gate__num">1</span><span>Brauzerin menyusunu (<b>⋮</b>) açın</span></li>
            <li><span class="gate__num">2</span><span><b>“Tətbiqi quraşdır”</b> və ya <b>“Ana ekrana əlavə et”</b> seçin</span></li>
            <li><span class="gate__num">3</span><span>Ana ekranda <b>Kuryer</b> ikonundan açın</span></li>
        </ol>

        <p class="gate__hint">Artıq quraşdırmısınızsa — ana ekrandakı <b>Kuryer</b> ikonundan açın. Telegram-dan açılıbsa, linki Chrome-da açın.</p>
    </section>

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
