<div class="hero-banner">
    @if(!empty($banners['bottomweb']['image']))
        @if(!empty($banners['bottomweb']['url']))
            <a class="hero-banner__link hero-banner__link--desktop" href="{{ $banners['bottomweb']['url'] }}" aria-label="Banner linki"><img class="hero-banner__desktop" src="{{ asset('frontend/uploads/banners/' . $banners['bottomweb']['image']) }}" alt="Parfumshop banner"></a>
        @else
            <img class="hero-banner__desktop" src="{{ asset('frontend/uploads/banners/' . $banners['bottomweb']['image']) }}" alt="Parfumshop banner">
        @endif
    @endif
    @if(!empty($banners['bottommobile']['image']))
        @if(!empty($banners['bottommobile']['url']))
            <a class="hero-banner__link hero-banner__link--mobile" href="{{ $banners['bottommobile']['url'] }}" aria-label="Banner linki"><img class="hero-banner__mobile" src="{{ asset('frontend/uploads/banners/' . $banners['bottommobile']['image']) }}" alt="Parfumshop mobil banner"></a>
        @else
            <img class="hero-banner__mobile" src="{{ asset('frontend/uploads/banners/' . $banners['bottommobile']['image']) }}" alt="Parfumshop mobil banner">
        @endif
    @endif
</div>
