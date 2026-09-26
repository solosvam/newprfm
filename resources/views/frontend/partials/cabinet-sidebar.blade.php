<div class="account-sidebar">
    <a href="{{ url()->previous() }}" class="account-back">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        <span>{{ __('cart_go_back') }}</span>
    </a>

    <p class="account-crumb">
        <a href="{{ route('home') }}">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9Z"/></svg>
        </a>
        <span>/</span>
        <a href="{{ route('profile') }}">{{ __('auth_my_account') }}</a>
        @isset($pageTitle)
            <span>/</span>
            <span>{{ $pageTitle }}</span>
        @endisset
    </p>

    <h1 class="account-greeting">{{ __('navigation_hello_name', ['name' => trim(auth()->user()->name.' '.auth()->user()->surname)]) }}</h1>

    <nav class="account-nav">
        <a href="{{ route('profile') }}" class="{{ request()->routeIs('profile') ? 'active' : '' }}">{{ __('catalog_account_information') }}</a>
        <a href="{{ route('profile.personal') }}" class="{{ request()->routeIs('profile.personal*') ? 'active' : '' }}">{{ __('catalog_personal_details') }}</a>
        <a href="{{ route('profile.credit') }}" class="{{ request()->routeIs('profile.credit*') ? 'active' : '' }}">
            {{ __('credit_title') }}
            @if(!auth()->user()->creditProfile?->isComplete())
                <small class="account-nav-note">({{ __('credit_sidebar_incomplete') }})</small>
            @endif
        </a>
        <a href="{{ route('profile.orders') }}" class="{{ request()->routeIs('profile.orders') ? 'active' : '' }}">{{ __('orders_history') }}</a>
        <a href="{{ route('profile.bonus') }}" class="{{ request()->routeIs('profile.bonus') ? 'active' : '' }}">{{ __('bonus_bonus_history') }}</a>
        <a href="{{ route('profile.wishlist') }}" class="{{ request()->routeIs('profile.wishlist') ? 'active' : '' }}">
            {{ __('wishlist_my_favorites') }}
            <span id="cabinetWishlistCount" class="account-nav-count">{{ auth()->user()->favoriteProducts()->count() }}</span>
        </a>
        <a href="{{ route('profile.reviews') }}" class="{{ request()->routeIs('profile.reviews') ? 'active' : '' }}">{{ __('reviews_my_reviews') }}</a>
    </nav>

    <form method="POST" action="{{ route('front.logout') }}" class="cabinet-logout-form">
        @csrf
        <button type="submit" class="account-logout">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
            <span>{{ __('catalog_sign_out') }}</span>
        </button>
    </form>
</div>
