<div class="cabinet__aside">
    <div class="cabinet__aside__top"><ul>
        <li><a href="{{ url()->previous() }}"><img src="{{ asset('frontend/images/arrow-left.svg') }}" alt=""><span>{{ __('Geri qayıt') }}</span></a></li>
        <li><ul><li><a href="{{ route('home') }}"><img src="{{ asset('frontend/images/home.svg') }}" alt=""></a></li><li><img src="{{ asset('frontend/images/arrow-right.svg') }}" alt=""></li><li><a href="{{ route('profile') }}">{{ __('Şəxsi kabinet') }}</a></li>@isset($pageTitle)<li><img src="{{ asset('frontend/images/arrow-right.svg') }}" alt=""></li><li><span>{{ $pageTitle }}</span></li>@endisset</ul></li>
    </ul></div>
    <div class="cabinet__aside__nav">
        <h1>{{ __('Salam, :name', ['name' => trim(auth()->user()->name.' '.auth()->user()->surname)]) }}</h1>
        <ul>
            <li><a href="{{ route('profile') }}" class="{{ request()->routeIs('profile') ? 'cabinet-active' : '' }}">{{ __('Hesab məlumatları') }}</a></li>
            <li><a href="{{ route('profile.personal') }}" class="{{ request()->routeIs('profile.personal*') ? 'cabinet-active' : '' }}">{{ __('Şəxsi məlumatlar') }}</a></li>
            <li><a href="{{ route('profile.credit') }}" class="{{ request()->routeIs('profile.credit*') ? 'cabinet-active' : '' }}">{{ __('credit_title') }} @if(!auth()->user()->creditProfile?->isComplete()) <small>({{ __('credit_sidebar_incomplete') }})</small> @endif</a></li>
            <li><a href="{{ route('profile.orders') }}" class="{{ request()->routeIs('profile.orders') ? 'cabinet-active' : '' }}">{{ __('Sifarişlərimin tarixçəsi') }}</a></li>
            <li><a href="{{ route('profile.bonuses') }}" class="{{ request()->routeIs('profile.bonuses') ? 'cabinet-active' : '' }}">{{ __('Bonus tarixçəsi') }}</a></li>
            <li><a href="{{ route('profile.wishlist') }}" class="{{ request()->routeIs('profile.wishlist') ? 'cabinet-active' : '' }}">{{ __('Bəyəndiyim ətirlər') }} <span id="cabinetWishlistCount">{{ auth()->user()->favoriteProducts()->count() }}</span></a></li>
            <li><a href="{{ route('profile.reviews') }}" class="{{ request()->routeIs('profile.reviews') ? 'cabinet-active' : '' }}">{{ __('Rəylərim') }}</a></li>
        </ul>
        <form method="POST" action="{{ route('front.logout') }}" class="cabinet-logout-form">@csrf
            <button type="submit" class="cabinet-logout"><img src="{{ asset('frontend/images/signin.svg') }}" alt=""><span>{{ __('Hesabdan çıx') }}</span></button>
        </form>
    </div>
</div>