<div class="account-sidebar brand-sidebar">
    <a href="#" onclick="window.history.back()" class="account-back">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        <span>{{ __('cart_go_back') }}</span>
    </a>

    <p class="account-crumb">
        <a href="{{ route('home') }}">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9Z"/></svg>
        </a>
        <span>/</span>
        <span>{{ __('reviews_brands') }}</span>
    </p>

    <nav class="account-nav">
        <a class="account-nav-item account-nav-item--filter">
            <img src="{{ asset('frontend/images/filter.svg') }}" alt="">
            <span>{{ __('filter') }}</span>
        </a>

        @foreach(\App\Models\Product\Category::where('active', 1)->orderBy('id')->get() as $menuCategory)
            @php
                $menuCategoryName = $menuCategory->{'name_'.app()->getLocale()} ?: $menuCategory->name_az;
            @endphp
            <a href="{{ route('category', ['category' => $menuCategory->id, 'slug' => \Illuminate\Support\Str::slug($menuCategoryName)]) }}"
               class="account-nav-item {{ isset($selectedCategory) && $selectedCategory?->id === $menuCategory->id ? 'active' : '' }}">
                <img src="{{ asset('frontend/images/terms.svg') }}" alt="">
                <span>{{ $menuCategoryName }}</span>
            </a>
        @endforeach

        <a href="{{ route('brands') }}" class="account-nav-item">
            <img src="{{ asset('frontend/images/brands.svg') }}" alt="">
            <span>{{ __('brands') }}</span>
        </a>
        <a class="account-nav-item">
            <img src="{{ asset('frontend/images/terms.svg') }}" alt="">
            <span>{{ __('rules') }}</span>
        </a>
        <a class="account-nav-item">
            <img src="{{ asset('frontend/images/gift_card.svg') }}" alt="">
            <span>{{ __('product_services_and_terms') }}</span>
        </a>
        <a href="{{ auth()->check() ? route('profile.wishlist') : route('home').'#favorites' }}" class="account-nav-item wishlist-page-link">
            <img src="{{ asset('frontend/images/wishlist.svg') }}" alt="">
            <span>{{ __('wishlist_my_favorites') }}</span>
        </a>
    </nav>

    <a href="{{ route('internal-credit') }}" class="account-nav-credit btn btn-dark">{{ __('internal_credit') }}</a>
</div>
