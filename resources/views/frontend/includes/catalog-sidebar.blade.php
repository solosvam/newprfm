        <div class="sidebar-stack">
            <select class="brand-select select2" aria-label="{{ __('home_select_brand') }}" onchange="if(this.value) window.location.href=this.value">
                <option value="">{{ __('home_select_brand') }}</option>
                @foreach($allBrands as $brand)
                    <option value="{{ route('brand.products', ['slug' => $brand->slug]) }}">{{ $brand->name }}</option>
                @endforeach
            </select>

            @include('frontend.includes.filter-form')

            <div id="sidebarExtras">
                @include('frontend.includes.sidebar-products', ['items' => $recommendedProducts, 'title' => __('home_recommended'), 'panelId' => 'recommendedPanelBody'])
                @include('frontend.includes.sidebar-products', ['items' => $bestSellers, 'title' => __('home_bestsellers'), 'panelId' => 'bestSellersPanelBody'])
            </div>
        </div>

