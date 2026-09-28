@php
    $desktopSize = $bannerDimensions['bottomweb'];
    $mobileSize = $bannerDimensions['bottommobile'];
    $desktopBanner = $banners['bottomweb'] ?? [];
    $mobileBanner = $banners['bottommobile'] ?? [];
    $desktopImage = !empty($desktopBanner['image']) ? asset('frontend/uploads/banners/' . $desktopBanner['image']) : null;
    $mobileImage = !empty($mobileBanner['image']) ? asset('frontend/uploads/banners/' . $mobileBanner['image']) : null;
@endphp
@if($desktopImage || $mobileImage)
    <div class="hero-banner" style="--banner-desktop-ratio: {{ $desktopSize['width'] }} / {{ $desktopSize['height'] }}; --banner-mobile-ratio: {{ $mobileSize['width'] }} / {{ $mobileSize['height'] }};">
        @if(!empty($desktopBanner['url']))
            <a class="hero-banner__link" href="{{ $desktopBanner['url'] }}" aria-label="Banner linki">
        @endif
        <picture class="hero-banner__picture">
            @if($mobileImage)
                <source media="(max-width: 760px)" srcset="{{ $mobileImage }}" width="{{ $mobileSize['width'] }}" height="{{ $mobileSize['height'] }}">
            @endif
            <img
                src="{{ $desktopImage ?: $mobileImage }}"
                alt="{{ $mobileImage ? 'Parfumshop banner' : 'Parfumshop banner' }}"
                width="{{ $desktopImage ? $desktopSize['width'] : $mobileSize['width'] }}"
                height="{{ $desktopImage ? $desktopSize['height'] : $mobileSize['height'] }}"
                loading="lazy"
                decoding="async"
            >
        </picture>
        @if(!empty($desktopBanner['url']))
            </a>
        @endif
        @if(!empty($mobileBanner['url']))
            <a class="hero-banner__mobile-overlay" href="{{ $mobileBanner['url'] }}" aria-label="Mobil banner linki"></a>
        @endif
    </div>
@endif
