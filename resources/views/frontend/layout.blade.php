<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ParfumShop.az - онлайн заказ парфюмерии, мужские женские аро</title>
    <link rel="stylesheet" href="{{ asset('frontend/css/style.css?v=' . filemtime(public_path('frontend/css/style.css'))) }}">
    <link rel="stylesheet" href="{{ asset('frontend/css/app.css?v=' . filemtime(public_path('frontend/css/app.css'))) }}">
    @yield('page-styles')
</head>
<body>
<!-- Header section -->
<header>
    <div class="container">
        <div class="header-all">
            <div class="lang">
                <div class="language-select">
                    <span class="language-select__globe">◎</span>
                    <select id="languageSwitcher" aria-label="Dil seçimi">
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
                        <a href="{{ route('cart') }}" class="header-cart__link" aria-label="Səbət">
                            <img src="{{asset('frontend/images/cart.svg')}}" alt="Səbət" />
                            <span class="header-cart__count is-empty" id="headerCartCount">0</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ auth()->check() ? route('profile.wishlist') : route('home').'#favorites' }}" class="wishlist-page-link"><img src="{{asset('frontend/images/wishlist.svg')}}" alt="Bəyəndiyim ətirlər" /></a>
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
                <input type="text" placeholder="Axtarış" />
                <img src="{{asset('frontend/images/search.svg')}}" alt="" />
            </form>
            <div class="prev-search">
                <span>Daha öncəki axtarışlarınız</span>
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
<!-- Search Form Header -->

<!--SEARCH FOR MOBILE-->
<div class="container">
    <div class="search-form-mobile">
        <div class="search-close">
            <img src="{{asset('frontend/images/close.svg')}}" alt="" />
        </div>
        <div class="search-form__wrap">
            <form action="">
                <input type="text" placeholder="Axtarış" />
                <img src="{{asset('frontend/images/search.svg')}}" alt="" />
            </form>
            <div class="prev-search">
                <span>Daha öncəki axtarışlarınız</span>
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
                <input type="text" placeholder="Axtarış" />
                <img src="{{asset('frontend/images/search.svg')}}" alt="Search" />
            </form>

            <div class="no-results">
                <p>Axtardığınız məhsul mövcud deyil. Bəlkə <span>Cartier</span> nəzərdə tutursunuz?</p>
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
            <span>Filter</span>
        </li>
        <li>
            <img src="{{asset('frontend/images/brands.svg')}}" alt="brand icon" />
            <a href="{{route('brands')}}"><span>Brendlər</span></a>
        </li>
        <li class="terms-link">
            <img src="{{asset('frontend/images/terms.svg')}}" alt="terms icon" />
            <span>Qaydalar</span>
        </li>
        <li>
            <img src="{{asset('frontend/images/gift_card.svg')}}" alt="services icon" />
            <span>Xidmətlər və şərtlər</span>
        </li>
        <li class="terms-link">
            <img src="{{asset('frontend/images/wishlist.svg')}}" alt="wishlist icon" />
            <a href="{{ auth()->check() ? route('profile.wishlist') : route('home').'#favorites' }}" class="wishlist-page-link"><span>Bəyəndiyim ətirlər</span></a>
        </li>
    </ul>
</div>
<!-- Sidebar for Mobile -->

<!-- Search Form for Mobile -->
<div class="mobile-search-home">
    <form>
        <input type="text" placeholder="Nə axtarırsan?" />
        <img src="{{asset('frontend/images/search.svg')}}" alt="" />
    </form>
    <div class="mobile-search__filter">
        <img src="{{asset('frontend/images/filter.svg')}}" alt="" />
    </div>
</div>
<!-- Search Form for Mobile -->

@yield('content')
<script>
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
                <p>ParfumShop.az internet mağazası</p>
                <p>25 ilin təcrübəsi ilə xidmətinizdə olmaqdan qürur duyuruq!</p>
                <p>Həyatınızın ən ətirli səhifəsi...</p>
            </div>
            <div class="links">
                <ul>
                    <li>
                        <span>Kateqoriyalar</span>
                    </li>
                    <li>
                        <span>Brendlər</span>
                    </li>
                    <li>
                        <span>Qadın ətirləri</span>
                    </li>
                    <li>
                        <span>Kişi ətirləri</span>
                    </li>
                    <li>
                        <span>Unisex</span>
                    </li>
                    <li>
                        <span>Hamam dəstləri</span>
                    </li>
                    <li>
                        <span>Testerlər</span>
                    </li>
                    
                    <li>
                        <span>Daxili kredit</span>
                    </li>
                </ul>
                <ul>
                    <li>
                        <span>Xidmət saatlarımız</span>
                    </li>
                    <li>
                        <span>9:00 - 19:00. B.e. - Ş.</span>
                        <span>Bazar istirahət günüdür.</span>
                    </li>
                    <li>
              <span>
                Sayt üzərindən edilən sifarişlər bütün sutka ərzində növbəyə
                alınır və iş saatı ərzində baxılır.
              </span>
                    </li>
                </ul>
                <ul>
                    <li>
                        <span>Sualınız yaranıb?</span>
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
                    <h3>Kateqoriyalar</h3>
                    <ul>
                        <li>Brendlər</li>
                        <li>Qadın ətirləri</li>
                        <li> Kişi ətirləri</li>
                        <li>Unisex</li>
                        <li>Hamam dəstləri</li>
                        <li>Testerlər</li>
                        
                        <li>Daxili kredit</li>
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
            <h3>Xidmət saatlarımız</h3>

            <p>9:00 - 19:00. B.e. - Ş.
                Bazar istirahət günüdür.

                Sayt üzərindən edilən sifarişlər bütün sutka ərzində növbəyə alınır və iş saatı ərzində baxılır.</p>
            <div class="question">
                <ul>
                    <li>Sualınız yaranıb?</li>
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

            <p>Bütün hüquqlar qorunur.</p>

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
