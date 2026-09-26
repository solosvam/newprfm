<header>
    <div class="wrap header-row">
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
        <a href="{{ route('newhome') }}" class="logo">parfumshop</a>

        <form class="search" method="GET" action="{{ route('newhome') }}" id="searchForm" autocomplete="off"
              data-suggestions-url="{{ route('search.suggestions') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" name="q" id="searchInput" value="{{ request('q') }}" placeholder="Brend və ya ətir axtar..." aria-label="Məhsul axtar">
        </form>

        <div class="search-results" id="searchResults" hidden aria-live="polite">
            {{-- Statik demo nəticələri API ilə əvəz olunub. --}}
            {{--
            <a class="search-result-item" href="#">
                <div class="search-result-thumb">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="26" height="26"><path d="M9 3h6l1 4H8l1-4Z"/><path d="M8 7h8l1 13a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L8 7Z"/></svg>
                </div>
                <div class="search-result-info">
                    <p class="search-result-brand">Amouage</p>
                    <p class="search-result-name">Opus XV — King Blue</p>
                    <div class="search-result-meta">Eau de Parfum <span class="dot">&middot;</span> Unisex</div>
                </div>
                <span class="search-result-price">110 ml / 340.00 ₼</span>
            </a>

            <a class="search-result-item" href="#">
                <div class="search-result-thumb">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="26" height="26"><path d="M9 3h6l1 4H8l1-4Z"/><path d="M8 7h8l1 13a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L8 7Z"/></svg>
                </div>
                <div class="search-result-info">
                    <p class="search-result-brand">Creed</p>
                    <p class="search-result-name">Aventus</p>
                    <div class="search-result-meta">Eau de Parfum <span class="dot">&middot;</span> Kişi</div>
                </div>
                <span class="search-result-price">100 ml / 748.00 ₼</span>
            </a>

            <a class="search-result-item" href="#">
                <div class="search-result-thumb">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="26" height="26"><path d="M9 3h6l1 4H8l1-4Z"/><path d="M8 7h8l1 13a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L8 7Z"/></svg>
                </div>
                <div class="search-result-info">
                    <p class="search-result-brand">Burberry</p>
                    <p class="search-result-name">Her Intense</p>
                    <div class="search-result-meta">Eau de Parfum <span class="dot">&middot;</span> Qadın</div>
                </div>
                <span class="search-result-price">100 ml / 299.00 ₼</span>
            </a>

            <a class="search-result-item" href="#">
                <div class="search-result-thumb">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="26" height="26"><path d="M9 3h6l1 4H8l1-4Z"/><path d="M8 7h8l1 13a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L8 7Z"/></svg>
                </div>
                <div class="search-result-info">
                    <p class="search-result-brand">Issey Miyake</p>
                    <p class="search-result-name">L'Eau d'Issey</p>
                    <div class="search-result-meta">Eau de Toilette <span class="dot">&middot;</span> Kişi</div>
                </div>
                <span class="search-result-price">75 ml / 146.00 ₼</span>
            </a>

            <a class="search-results-more" href="#">Bütün nəticələrə bax</a>
            --}}
        </div>

        <div class="icons">
            <button id="theme-toggle" type="button" aria-label="Rejimi dəyiş">
                <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="19" height="19"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="19" height="19"><path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8Z"/></svg>
            </button>
            <a href="{{ auth()->check() ? route('profile.wishlist') : route('wishlist') }}" class="header-icon-link" aria-label="Seçilmişlər">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
                <span class="header-count is-empty" id="headerWishlistCount">0</span>
            </a>
            <a href="{{ route('cart') }}" class="header-icon-link" aria-label="Səbət">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                <span class="header-count is-empty" id="headerCartCount">0</span>
            </a>
            <a href="{{ auth()->check() ? route('profile') : route('front.login') }}" class="header-icon-link header-profile" aria-label="{{ auth()->check() ? __('auth_my_account') : __('auth_login') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><circle cx="12" cy="8" r="4"/><path d="M4 22a8 8 0 0 1 16 0"/></svg>
                @auth
                    <span class="header-profile-name">{{ auth()->user()->name }} {{ mb_substr(auth()->user()->surname, 0, 1) }}.</span>
                @else
                    <span class="header-profile-name">{{ __('auth_sign_in') }}</span>
                @endauth
            </a>
        </div>
    </div>

    @yield('subnav')
</header>
