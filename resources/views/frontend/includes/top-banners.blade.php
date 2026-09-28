@php
    $desktopSize = $bannerDimensions['topweb'];
    $mobileSize = $bannerDimensions['topmobile'];
@endphp
<div class="hero-banner" style="--banner-desktop-ratio: {{ $desktopSize['width'] }} / {{ $desktopSize['height'] }}; --banner-mobile-ratio: {{ $mobileSize['width'] }} / {{ $mobileSize['height'] }};">
    @if(!empty($banners['topweb']['image']))
        @if(!empty($banners['topweb']['url']))
            <a class="hero-banner__link hero-banner__link--desktop" href="{{ $banners['topweb']['url'] }}" aria-label="Banner linki"><img class="hero-banner__desktop" src="{{ asset('frontend/uploads/banners/' . $banners['topweb']['image']) }}" alt="Parfumshop banner" width="{{ $desktopSize['width'] }}" height="{{ $desktopSize['height'] }}" loading="eager" fetchpriority="high"></a>
        @else
            <img class="hero-banner__desktop" src="{{ asset('frontend/uploads/banners/' . $banners['topweb']['image']) }}" alt="Parfumshop banner" width="{{ $desktopSize['width'] }}" height="{{ $desktopSize['height'] }}" loading="eager" fetchpriority="high">
        @endif
    @endif
    @if(!empty($banners['topmobile']['image']))
        @if(!empty($banners['topmobile']['url']))
            <a class="hero-banner__link hero-banner__link--mobile" href="{{ $banners['topmobile']['url'] }}" aria-label="Banner linki"><img class="hero-banner__mobile" src="{{ asset('frontend/uploads/banners/' . $banners['topmobile']['image']) }}" alt="Parfumshop mobil banner" width="{{ $mobileSize['width'] }}" height="{{ $mobileSize['height'] }}" loading="eager" fetchpriority="high"></a>
        @else
            <img class="hero-banner__mobile" src="{{ asset('frontend/uploads/banners/' . $banners['topmobile']['image']) }}" alt="Parfumshop mobil banner" width="{{ $mobileSize['width'] }}" height="{{ $mobileSize['height'] }}" loading="eager" fetchpriority="high">
        @endif
    @endif
</div>
