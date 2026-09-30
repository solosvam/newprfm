        <div class="sidebar-stack">
            <select class="brand-select select2" aria-label="{{ __('home_select_brand') }}" data-navigate-on-change data-placeholder="{{ __('home_select_brand') }}">
                <option value="">{{ __('home_select_brand') }}</option>
                @foreach($allBrands as $brand)
                    <option value="{{ route('brand.products', ['slug' => $brand->slug]) }}"
                        @selected(isset($selectedBrand) && $selectedBrand->id === $brand->id)>{{ $brand->name }}</option>
                @endforeach
            </select>

            @include('frontend.includes.filter-form')

            @if($showExtras ?? true)
            <div id="sidebarExtras">
                @include('frontend.includes.sidebar-products', ['items' => $recommendedProducts, 'title' => __('home_recommended'), 'panelId' => 'recommendedPanelBody', 'dynamic' => !auth('web')->check()])
                @include('frontend.includes.sidebar-products', ['items' => $bestSellers, 'title' => __('home_bestsellers'), 'panelId' => 'bestSellersPanelBody', 'ranked' => true])
            </div>
            @endif
        </div>

