<div class="hero-banner">
    @if(!empty($banners['topweb']['image']))
        @if(!empty($banners['topweb']['url']))
            <a class="hero-banner__link hero-banner__link--desktop" href="{{ $banners['topweb']['url'] }}" aria-label="Banner linki"><img class="hero-banner__desktop" src="{{ asset('frontend/uploads/banners/' . $banners['topweb']['image']) }}" alt="Parfumshop banner"></a>
        @else
            <img class="hero-banner__desktop" src="{{ asset('frontend/uploads/banners/' . $banners['topweb']['image']) }}" alt="Parfumshop banner">
        @endif
    @endif
    @if(!empty($banners['topmobile']['image']))
        @if(!empty($banners['topmobile']['url']))
            <a class="hero-banner__link hero-banner__link--mobile" href="{{ $banners['topmobile']['url'] }}" aria-label="Banner linki"><img class="hero-banner__mobile" src="{{ asset('frontend/uploads/banners/' . $banners['topmobile']['image']) }}" alt="Parfumshop mobil banner"></a>
        @else
            <img class="hero-banner__mobile" src="{{ asset('frontend/uploads/banners/' . $banners['topmobile']['image']) }}" alt="Parfumshop mobil banner">
        @endif
    @endif
</div>
