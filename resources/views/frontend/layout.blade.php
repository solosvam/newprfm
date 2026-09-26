<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ParfumShop.az</title>
    <link rel="stylesheet" href="{{ asset('frontend/css/frontend.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/css/responsive.css') }}">
</head>
<body>
<!-- Header section -->
<header>
    <div class="container">
        <div class="header-all">
            <div class="lang">
                <div class="language-select">
                    <span class="language-select__globe">◎</span>
                    <select id="languageSwitcher"
                            aria-label="{{ __('navigation_select_language') }}"
                            data-current-locale="{{ app()->getLocale() }}"
                            data-change-url="{{ route('language.change') }}">
                        <option value="az" @if(App::isLocale('az')) selected @endif>AZ</option>
                        <option value="en" @if(App::isLocale('en')) selected @endif>EN</option>
                        <option value="ru" @if(App::isLocale('ru')) selected @endif>RU</option>
                    </select>
                </div>
            </div>
            <div class="logo">
                <a href="{{route('home')}}">
                    <img src="{{asset('frontend/images/logo.svg')}}" alt="parfumshop logo" />
                </a>
            </div>
            <div class="header-actions">
                <ul>
                    <li>
                        <img class="search-icon" src="{{asset('frontend/images/search.svg')}}" alt="" />
                    </li>
                    <li class="header-cart">
                        <a href="{{ route('cart') }}" class="header-cart__link" aria-label="{{ __('cart_cart') }}">
                            <img src="{{asset('frontend/images/cart.svg')}}" alt="Səbət" />
                            <span class="header-cart__count is-empty" id="headerCartCount">0</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ auth()->check() ? route('profile.wishlist') : route('wishlist') }}" class="wishlist-page-link header-wishlist__link"><img src="{{asset('frontend/images/wishlist.svg')}}" alt="Bəyəndiyim ətirlər" /><span class="header-wishlist__count is-empty" id="headerWishlistCount">0</span></a>
                    </li>
                    <li class="header-account">
                        @auth
                            <a href="{{ route('profile') }}" class="header-account__user">
                                <img src="{{ asset('frontend/images/signin.svg') }}" alt="" />
                                <span>{{ auth()->user()->name }} {{ auth()->user()->surname }}</span>
                            </a>
                        @else
                            <a href="{{ route('front.login') }}">
                                <img src="{{ asset('frontend/images/signin.svg') }}" alt="" />
                                <span>{{ __('login') }}</span>
                            </a>
                        @endauth
                    </li>
                    <li class="menu-icon">
                        <img src="{{asset('frontend/images/menu.svg')}}" alt="" />
                    </li>
                </ul>
            </div>
        </div>
    </div>
</header>
<!-- Header section -->
<!-- Search Form Header -->
<div class="container">
    <div class="search-form">
        <div class="search-close">
            <img src="{{asset('frontend/images/close.svg')}}" alt="" />
        </div>
        <div class="search-form__wrap">
            <form action="">
                <input type="text" placeholder="{{ __('catalog_search') }}" />
                <img src="{{asset('frontend/images/search.svg')}}" alt="" />
            </form>
            <div class="prev-search">
                <span>{{ __('catalog_your_recent_searches') }}</span>
                <ul>
                    <li>
                        <a href="">
                            <div class="img">
                                <img src="{{asset('frontend/images/products/parfum.png')}}" alt="" />
                            </div>
                            <div class="content">
                                <div class="info">
                                    <h1>Cartier La Panthere Edition Soir</h1>
                                    <p>{{ __('navigation_men_eau_de_toilette') }}</p>
                                    <span>199.00 ₼ / <span>100 ml</span></span>
                                </div>
                                <div class="category">
                                    <span>{{ __('navigation_for_women') }}</span>
                                </div>
                            </div>
                        </a>
                    </li>
                    <li>
                        <a href="">
                            <div class="img">
                                <img src="{{asset('frontend/images/products/parfum.png')}}" alt="" />
                            </div>
                            <div class="content">
                                <div class="info">
                                    <h1>Cartier La Panthere Edition Soir</h1>
                                    <p>Kişi, Eau de Toilette</p>
                                    <span>199.00 ₼ / <span>100 ml</span></span>
                                </div>
                                <div class="category">
                                    <span>Qadın üçün</span>
                                </div>
                            </div>
                        </a>
                    </li>
                    <li>
                        <a href="">
                            <div class="img">
                                <img src="{{asset('frontend/images/products/parfum.png')}}" alt="" />
                            </div>
                            <div class="content">
                                <div class="info">
                                    <h1>Cartier La Panthere Edition Soir</h1>
                                    <p>Kişi, Eau de Toilette</p>
                                    <span>199.00 ₼ / <span>100 ml</span></span>
                                </div>
                                <div class="category">
                                    <span>Qadın üçün</span>
                                </div>
                            </div>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
<!-- Search Form Header -->

<!--SEARCH FOR MOBILE-->
<div class="container">
    <div class="search-form-mobile">
        <div class="search-close">
            <img src="{{asset('frontend/images/close.svg')}}" alt="" />
        </div>
        <div class="search-form__wrap">
            <form action="">
                <input type="text" placeholder="{{ __('catalog_search') }}" />
                <img src="{{asset('frontend/images/search.svg')}}" alt="" />
            </form>
            <div class="prev-search">
                <span>{{ __('catalog_your_recent_searches') }}</span>
                <ul>
                    <li>
                        <a href="">
                            <div class="img">
                                <img src="{{asset('frontend/images/products/parfum.png')}}" alt="" />
                            </div>
                            <div class="content">
                                <div class="info">
                                    <h1>Cartier La Panthere Edition Soir</h1>
                                    <p>Kişi, Eau de Toilette</p>
                                    <span>199.00 ₼ / <span>100 ml</span></span>
                                </div>
                                <div class="category">
                                    <span>Qadın üçün</span>
                                </div>
                            </div>
                        </a>
                    </li>
                    <li>
                        <a href="">
                            <div class="img">
                                <img src="{{asset('frontend/images/products/parfum.png')}}" alt="" />
                            </div>
                            <div class="content">
                                <div class="info">
                                    <h1>Cartier La Panthere Edition Soir</h1>
                                    <p>Kişi, Eau de Toilette</p>
                                    <span>199.00 ₼ / <span>100 ml</span></span>
                                </div>
                                <div class="category">
                                    <span>Qadın üçün</span>
                                </div>
                            </div>
                        </a>
                    </li>
                    <li>
                        <a href="">
                            <div class="img">
                                <img src="{{asset('frontend/images/products/parfum.png')}}" alt="" />
                            </div>
                            <div class="content">
                                <div class="info">
                                    <h1>Cartier La Panthere Edition Soir</h1>
                                    <p>Kişi, Eau de Toilette</p>
                                    <span>199.00 ₼ / <span>100 ml</span></span>
                                </div>
                                <div class="category">
                                    <span>Qadın üçün</span>
                                </div>
                            </div>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
<!--SEARCH FOR MOBILE-->

<!-- Search Form Header NO RESULT -->
<div class="container">
    <div class="search-form-no-result">
        <div class="search-close">
            <img src="{{asset('frontend/images/close.svg')}}" alt="Close" />
        </div>
        <div class="search-form__wrap">
            <form action="">
                <input type="text" placeholder="{{ __('catalog_search') }}" />
                <img src="{{asset('frontend/images/search.svg')}}" alt="Search" />
            </form>

            <div class="no-results">
                <p>{{ __('catalog_product_not_found_did_you_mean') }} <span>Cartier</span> {{ __('catalog_') }}</p>
                <img src="{{asset('frontend/images/search.svg')}}" alt="No Results" />
            </div>
        </div>
    </div>
</div>
<!-- Search Form Header NO RESULT -->

<!-- Sidebar for Mobile -->
<div class="mobile-sidebar">
    <img class="sidebar-close-icon" src="{{asset('frontend/images/close.svg')}}" alt="" />
    <ul class="mobile-sidebar__nav">
        <li>
            <img src="{{asset('frontend/images/filter.svg')}}" alt="filter icon" />
            <span>{{ __('catalog_filter') }}</span>
        </li>
        @foreach(\App\Models\Product\Category::where('active', 1)->orderBy('id')->get() as $menuCategory)
        <li>
            <img src="{{asset('frontend/images/terms.svg')}}" alt="category icon" />
            <a href="{{ route('home', ['category' => $menuCategory->id]) }}"><span>{{ $menuCategory->{'name_'.app()->getLocale()} ?: $menuCategory->name_az }}</span></a>
        </li>
        @endforeach
        <li>
            <img src="{{asset('frontend/images/brands.svg')}}" alt="brand icon" />
            <a href="{{route('brands')}}"><span>{{ __('catalog_brands') }}</span></a>
        </li>
        <li class="terms-link">
            <img src="{{asset('frontend/images/terms.svg')}}" alt="terms icon" />
            <span>{{ __('info_terms') }}</span>
        </li>
        <li>
            <img src="{{asset('frontend/images/gift_card.svg')}}" alt="services icon" />
            <span>{{ __('product_services_and_terms') }}</span>
        </li>
        <li class="terms-link">
            <img src="{{asset('frontend/images/wishlist.svg')}}" alt="wishlist icon" />
            <a href="{{ auth()->check() ? route('profile.wishlist') : route('wishlist') }}" class="wishlist-page-link"><span>{{ __('wishlist_my_favorites') }}</span></a>
        </li>
    </ul>
</div>
<!-- Sidebar for Mobile -->

<!-- Search Form for Mobile -->
<div class="mobile-search-home">
    <form>
        <input type="text" placeholder="{{ __('catalog_what_are_you_looking_for') }}" />
        <img src="{{asset('frontend/images/search.svg')}}" alt="" />
    </form>
    <div class="mobile-search__filter">
        <img src="{{asset('frontend/images/filter.svg')}}" alt="" />
    </div>
</div>
<!-- Search Form for Mobile -->

@yield('content')
<script>
window.parfumshopMessages = {
    cartAdded: @json(__('notification_product_added_to_cart')),
    favoriteAdded: @json(__('notification_added_to_favorites')),
    favoriteRemoved: @json(__('notification_removed_from_favorites'))
};
window.parfumshopFavoriteConfig = {
    authenticated: @json(auth()->check()),
    idsUrl: @json(auth()->check() ? route('favorites.ids') : null),
    syncUrl: @json(auth()->check() ? route('favorites.sync') : null),
    storeUrl: @json(auth()->check() ? url('/favorites') : null),
    csrf: @json(csrf_token())
};
</script>

<footer>
    <div class="container">
        <div class="footer-all">
            <div class="left">
                <a href="">
                    <img src="{{asset('frontend/images/logo.svg')}}" alt="" />
                </a>
                <p>{{ __('navigation_parfumshop_az_online_store') }}</p>
                <p>{{ __('navigation_proud_to_serve_you_with_25_years_of_experience') }}</p>
                <p>{{ __('navigation_the_most_fragrant_chapter_of_your_life') }}</p>
            </div>
            <div class="links">
                <ul>
                    <li>
                        <span>{{ __('catalog_categories') }}</span>
                    </li>
                    <li>
                        <span>{{ __('catalog_brands') }}</span>
                    </li>
                    <li>
                        <span>{{ __('catalog_women_s_perfumes') }}</span>
                    </li>
                    <li>
                        <span>{{ __('catalog_men_s_perfumes') }}</span>
                    </li>
                    <li>
                        <span>Unisex</span>
                    </li>
                    <li>
                        <span>{{ __('navigation_bath_sets') }}</span>
                    </li>
                    <li>
                        <span>{{ __('navigation_testers') }}</span>
                    </li>
                    
                    <li>
                        <span>{{ __('navigation_installment_payments') }}</span>
                    </li>
                </ul>
                <ul>
                    <li>
                        <span>{{ __('navigation_business_hours') }}</span>
                    </li>
                    <li>
                        <span>{{ __('navigation_9_00_am_7_00_pm_mon_sat') }}</span>
                        <span>{{ __('navigation_closed_on_sundays') }}</span>
                    </li>
                    <li>
              <span>
                {{ __('navigation_online_orders_are_accepted_around_the_clock_and_processed_du') }}
              </span>
                    </li>
                </ul>
                <ul>
                    <li>
                        <span>{{ __('navigation_have_a_question') }}</span>
                    </li>
                    <li>
                        <span>(055) 55 10 700</span>
                        <span>(012) 310 22 55</span>
                        <span>info@parfumshop.az</span>
                    </li>
                    <li class="footer-social">
                        <a href="">
                            <img src="{{asset('frontend/images/fb.svg')}}" alt="" />
                        </a>
                        <a href="">
                            <img src="{{asset('frontend/images/instagram.svg')}}" alt="" />
                        </a>
                        <a href="">
                            <img src="{{asset('frontend/images/telegram.svg')}}" alt="" />
                        </a>
                    </li>
                </ul>
            </div>
            <div class="right">
                <ul>
                    <li>
                        <img src="{{asset('frontend/images/cards/visa.png')}}" alt="" />
                    </li>
                    <li>
                        <img src="{{asset('frontend/images/cards/master.png')}}" alt="" />
                    </li>
                    <li>
                        <img src="{{asset('frontend/images/cards/m10.png')}}" alt="" />
                    </li>
                    <li>
                        <img src="{{asset('frontend/images/cards/birbank.png')}}" alt="" />
                    </li>
                </ul>
            </div>
        </div>
    </div>
</footer>
<!-- Footer section -->

<!--FOOTER-FOR-MOBILE-->
<footer class="footer-for-mobile-home">
    <div class="footer-wrapper">
        <div class="column1">
            <div>
                <img src="{{asset('frontend/images/footer-icon.svg')}}" alt="">
                <div class="categories">
                    <h3>{{ __('catalog_categories') }}</h3>
                    <ul>
                        <li>{{ __('catalog_brands') }}</li>
                        <li>{{ __('catalog_women_s_perfumes') }}</li>
                        <li> {{ __('catalog_men_s_perfumes') }}</li>
                        <li>Unisex</li>
                        <li>{{ __('navigation_bath_sets') }}</li>
                        <li>{{ __('navigation_testers') }}</li>
                        
                        <li>{{ __('navigation_installment_payments') }}</li>
                    </ul>
                </div>
            </div>
            <div class="banking-icons">
          <span>
            <img src="{{asset('frontend/images/logos_visa.svg')}}" class="visa" alt="">
            <img src="{{asset('frontend/images/master.svg')}}" class="master" alt="">
          </span>
                <span><img src="{{asset('frontend/images/m10.svg')}}" class="m10" alt="">
            <img src="{{asset('frontend/images/bribank.svg')}}" class="birbank" alt=""></span>
            </div>
        </div>
        <div class="column2">
            <h3>{{ __('navigation_business_hours') }}</h3>

            <p>9:00 - 19:00. B.e. - Ş.
                Bazar istirahət günüdür.

                {{ __('navigation_online_orders_are_accepted_around_the_clock_and_processed_du') }}</p>
            <div class="question">
                <ul>
                    <li>{{ __('navigation_have_a_question') }}</li>
                    <li>(055) 55 10 70</li>
                    <li>(012) 310 22 55</li>
                    <li>info@parfumshop.az</li>
                </ul>
            </div>

            <div class="icons-wrapper">
                <img src="{{asset('frontend/images/fb.svg')}}" alt="">
                <img src="{{asset('frontend/images/instagram.svg')}}" alt="">
                <img src="{{asset('frontend/images/telegram.svg')}}" alt="">
                <img src="{{asset('frontend/images/whatsapp.svg')}}" alt="">
            </div>

            <p>{{ __('navigation_all_rights_reserved') }}</p>

        </div>
    </div>
</footer>
<!--FOOTER-FOR-MOBILE-->
<div class="whatsapp">
    <a href="tel:0502656463">
        <img src="{{asset('frontend/images/wp.svg')}}" alt />
    </a>
</div>
@yield('modal')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/notify/0.4.2/notify.min.js"></script>
<script src="{{asset('frontend/js/main.js')}}"></script>
@yield('page-scripts')
</body>

</html>
