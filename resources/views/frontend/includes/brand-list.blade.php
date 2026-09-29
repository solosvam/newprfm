{{-- Ana səhifə: brend zolağı. Loqo varsa loqo, yoxdursa adı. Desktopda oxlar main.js-dən idarə olunur. --}}
<div class="brands-strip" data-brands-strip>
    <button type="button" class="brands-strip__nav brands-strip__nav--prev" data-brands-nav="-1" aria-label="←" tabindex="-1" hidden>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
    </button>

    <div class="brands">
        @foreach ($brands as $brand)
            @php
                $svgLogo = \App\Services\BrandLogoService::svgUrl($brand->image);
                $logo = $svgLogo ? null : \App\Services\BrandLogoService::url($brand->image);
            @endphp
            <a class="brand-card {{ ($svgLogo || $logo) ? 'brand-card--logo' : '' }} {{ (isset($selectedBrand) && $selectedBrand?->id === $brand->id) ? 'active' : '' }}"
               href="{{ route('brand.products', ['slug' => $brand->slug]) }}" title="{{ $brand->name }}">
                @if($svgLogo)
                    <span class="brand-logo" role="img" aria-label="{{ $brand->name }}" style="--logo: url('{{ $svgLogo }}')"></span>
                @elseif($logo)
                    <img src="{{ $logo }}" alt="{{ $brand->name }}" loading="lazy" decoding="async" draggable="false">
                @else
                    {{ $brand->name }}
                @endif
            </a>
        @endforeach
        <a class="brand-card more" href="{{ route('brands') }}">{{ __('brands') }} →</a>
    </div>

    <button type="button" class="brands-strip__nav brands-strip__nav--next" data-brands-nav="1" aria-label="→" tabindex="-1" hidden>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
    </button>
</div>
