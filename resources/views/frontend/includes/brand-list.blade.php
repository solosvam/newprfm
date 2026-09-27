    <div class="brands">
        @foreach ($brands as $brand)
            <a class="brand-card {{ (isset($selectedBrand) && $selectedBrand?->id === $brand->id) ? 'active' : '' }}"
               href="{{ route('brand.products', ['slug' => $brand->slug]) }}">
                {{ $brand->name }}
            </a>
        @endforeach
        <a class="brand-card more" href="{{ route('brands') }}">{{ __('brands') }} →</a>
    </div>

