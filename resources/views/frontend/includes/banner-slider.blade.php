{{--
  Banner slayderi. Parametrlər: $location (top | bottom), $banners (CatalogService), $bannerDimensions.
  Veb və mobil bannerlər ayrı slayderdir (ölçüləri fərqlidir); ≤760px mobil göstərilir.
  Bir növ yoxdursa, digəri hər iki ekranda görünür. Bir banner — sadə şəkil, idarəetmə yoxdur.
  JS: frontend/js/banner-slider.js (avtomatik keçid, nöqtələr, oxlar, sürüşdürmə).
  Keçid müddəti: $bannerInterval (saniyə) — Admin → Ayarlar → Bannerlər.
--}}
@php
    $sets = [
        'web' => ['items' => $banners[$location.'web'] ?? [], 'size' => $bannerDimensions[$location.'web']],
        'mobile' => ['items' => $banners[$location.'mobile'] ?? [], 'size' => $bannerDimensions[$location.'mobile']],
    ];
    $hasWeb = !empty($sets['web']['items']);
    $hasMobile = !empty($sets['mobile']['items']);
@endphp
@foreach($sets as $device => $set)
    @continue(empty($set['items']))
    @php
        $only = match (true) {
            $device === 'web' && $hasMobile => 'desktop',
            $device === 'mobile' && $hasWeb => 'mobile',
            default => 'all',
        };
        $count = count($set['items']);
    @endphp
    <section class="hero-banner hero-banner--{{ $only }}" style="--banner-ratio: {{ $set['size']['width'] }} / {{ $set['size']['height'] }};"
             aria-roledescription="carousel" aria-label="Bannerlər" @if($count > 1) data-banner-slider data-interval="{{ ($bannerInterval ?? 5) * 1000 }}" @endif>
        <div class="hero-banner__track" @if($count > 1) data-slider-track tabindex="0" @endif>
            @foreach($set['items'] as $i => $banner)
                @php
                    $tag = $banner['url'] ? 'a' : 'div';
                    $eager = $location === 'top' && $i === 0;
                @endphp
                <{{ $tag }} class="hero-banner__slide" @if($banner['url']) href="{{ $banner['url'] }}" @endif
                    @if($count > 1) aria-roledescription="slide" aria-label="{{ $i + 1 }} / {{ $count }}" @endif>
                    <img src="{{ asset('frontend/uploads/banners/'.$banner['image']) }}" alt="Parfumshop banner"
                         width="{{ $set['size']['width'] }}" height="{{ $set['size']['height'] }}" draggable="false"
                         @if($eager) loading="eager" fetchpriority="high" @else loading="lazy" decoding="async" @endif>
                </{{ $tag }}>
            @endforeach
        </div>
        @if($count > 1)
            <button type="button" class="hero-banner__arrow hero-banner__arrow--prev" data-slider-prev aria-label="Əvvəlki banner">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
            </button>
            <button type="button" class="hero-banner__arrow hero-banner__arrow--next" data-slider-next aria-label="Növbəti banner">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
            </button>
            <div class="hero-banner__dots" role="tablist">
                @for($i = 0; $i < $count; $i++)
                    <button type="button" class="hero-banner__dot" data-slider-dot="{{ $i }}" aria-label="Banner {{ $i + 1 }}" @if($i === 0) aria-current="true" @endif></button>
                @endfor
            </div>
        @endif
    </section>
@endforeach
