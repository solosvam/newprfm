@php
    $desktopSize = $bannerDimensions['bottomweb'];
    $mobileSize = $bannerDimensions['bottommobile'];
@endphp
<div class="hero-banner" style="--banner-desktop-ratio: {{ $desktopSize['width'] }} / {{ $desktopSize['height'] }}; --banner-mobile-ratio: {{ $mobileSize['width'] }} / {{ $mobileSize['height'] }};">
    @if(!empty($banners['bottomweb']['image']))
        @if(!empty($banners['bottomweb']['url']))
            <a class="hero-banner__link hero-banner__link--desktop" href="{{ $banners['bottomweb']['url'] }}" aria-label="Banner linki"><img class="hero-banner__desktop" src="{{ asset('frontend/uploads/banners/' . $banners['bottomweb']['image']) }}" alt="Parfumshop banner" width="{{ $desktopSize['width'] }}" height="{{ $desktopSize['height'] }}" loading="lazy" decoding="async"></a>
        @else
            <img class="hero-banner__desktop" src="{{ asset('frontend/uploads/banners/' . $banners['bottomweb']['image']) }}" alt="Parfumshop banner" width="{{ $desktopSize['width'] }}" height="{{ $desktopSize['height'] }}" loading="lazy" decoding="async">
        @endif
    @endif
    @if(!empty($banners['bottommobile']['image']))
        @if(!empty($banners['bottommobile']['url']))
            <a class="hero-banner__link hero-banner__link--mobile" href="{{ $banners['bottommobile']['url'] }}" aria-label="Banner linki"><img class="hero-banner__mobile" src="{{ asset('frontend/uploads/banners/' . $banners['bottommobile']['image']) }}" alt="Parfumshop mobil banner" width="{{ $mobileSize['width'] }}" height="{{ $mobileSize['height'] }}" loading="lazy" decoding="async"></a>
        @else
            <img class="hero-banner__mobile" src="{{ asset('frontend/uploads/banners/' . $banners['bottommobile']['image']) }}" alt="Parfumshop mobil banner" width="{{ $mobileSize['width'] }}" height="{{ $mobileSize['height'] }}" loading="lazy" decoding="async">
        @endif
    @endif
</div>
