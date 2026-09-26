@extends('frontend.layout')

@section('content')
    @php
        $selectedCategory = $selectedCategory ?? null;
    @endphp
    <main>
        <div class="container">
            @include('frontend.banners')

            <div class="home-page">
                @include('frontend.include.left')

                <div class="main-products">
                    <div class="main-products__top">
                        @if($selectedCategory)
                            <h1 style="font-size: 20px; font-weight: 500; margin: 0;">{{ $selectedCategory->{'name_'.app()->getLocale()} ?: $selectedCategory->name_az }}</h1>
                        @else
                        <ul>
                            <li class="{{ request('gender') ? '' : 'active' }}">
                                <a href="{{ route('home', array_filter(['sort' => request('sort')])) }}">{{ __('catalog_all_perfumes') }}</a>
                            </li>
                            <li class="{{ request('gender') === 'women' ? 'active' : '' }}">
                                <a href="{{ route('home', array_filter(['gender' => 'women', 'sort' => request('sort')])) }}">{{ __('catalog_women_s_perfumes') }}</a>
                            </li>
                            <li class="{{ request('gender') === 'men' ? 'active' : '' }}">
                                <a href="{{ route('home', array_filter(['gender' => 'men', 'sort' => request('sort')])) }}">{{ __('catalog_men_s_perfumes') }}</a>
                            </li>
                            <li class="{{ request('gender') === 'unisex' ? 'active' : '' }}">
                                <a href="{{ route('home', array_filter(['gender' => 'unisex', 'sort' => request('sort')])) }}">{{ __('catalog_unisex_perfumes') }}</a>
                            </li>
                        </ul>
                        @endif

                        <form method="GET" action="{{ $selectedCategory ? route('category', ['category' => $selectedCategory->id, 'slug' => \Illuminate\Support\Str::slug($selectedCategory->{'name_'.app()->getLocale()} ?: $selectedCategory->name_az)]) : route('home') }}" class="product-sort-form">
                            @if(request('gender'))
                                <input type="hidden" name="gender" value="{{ request('gender') }}">
                            @endif

                            <select name="sort" onchange="this.form.submit()">
                                <option value="">{{ __('catalog_sort_by') }}</option>
                                <option value="newest" @selected(request('sort') === 'newest')>{{ __('catalog_newest') }}</option>
                                <option value="oldest" @selected(request('sort') === 'oldest')>{{ __('catalog_oldest') }}</option>
                                <option value="price_asc" @selected(request('sort') === 'price_asc')>{{ __('catalog_price_low_to_high') }}</option>
                                <option value="price_desc" @selected(request('sort') === 'price_desc')>{{ __('catalog_price_high_to_low') }}</option>
                            </select>
                        </form>
                    </div>

                    <div class="main-products__list">
                        @foreach($products as $product)
                            @php
                                $image = $product->images->first();
                                $gender = $product->genders->first();
                                $variants = $product->variants->where('active', 1);
                                $firstVariant = $variants->first();
                                $locale = app()->getLocale();

                                $genderName = $gender
                                    ? ($gender->{'name_' . $locale} ?? $gender->name_az)
                                    : null;

                                $typeName = $product->type
                                    ? ($product->type->{'name_' . $locale} ?? $product->type->name_az)
                                    : null;
                            @endphp

                            <div class="product-item" data-product-id="{{ $product->id }}">
                                <div class="product-item__image">
                                    <div class="product-item__image__actions">
                                        <img
                                            class="shareBtn"
                                            src="{{ asset('frontend/images/share.svg') }}"
                                            alt="Paylaş"
                                            data-title="{{ $product->brand?->name }} {{ $product->name }}"
                                            data-url="{{ route('product', $product->slug) }}"
                                        />

                                        <button type="button" class="favorite-toggle" data-product-id="{{ $product->id }}" aria-label="{{ __('wishlist_add_to_favorites') }}">
                                            <img src="{{ asset('frontend/images/product-card-wishlist.svg') }}" alt="Seçilmişlər" />
                                        </button>
                                    </div>

                                    <a href="{{ route('product', $product->slug) }}">
                                        @if($image)
                                            <img
                                                class="product-main-image"
                                                src="{{ asset('frontend/uploads/products/' . $image->image) }}"
                                                alt="{{ $product->brand?->name }} {{ $product->name }}"
                                            />
                                        @endif
                                    </a>
                                </div>

                                <div class="product-item__info">
                                    <div class="title">
                                        <h1>
                                            <a href="{{ route('product', $product->slug) }}">
                                                {{ $product->name }}
                                            </a>
                                        </h1>

                                        <span class="product-brand">
                                        {{ $product->brand?->name }}
                                    </span>

                                        @if($genderName || $typeName)
                                            <span class="product-type">
                                                @if($genderName)
                                                    {{ $genderName }}
                                                @endif

                                                @if($genderName && $typeName)
                                                    |
                                                @endif

                                                @if($typeName)
                                                    {{ $typeName }}
                                                @endif
                                            </span>
                                        @endif

                                        @if($variants->count())
                                            <ul class="price-ul">
                                                @foreach($variants as $variant)
                                                    <li
                                                        class="{{ $loop->first ? 'active-li' : '' }}"
                                                        data-price="{{ $variant->price }}"
                                                        data-variant-id="{{ $variant->id }}"
                                                    >
                                                    <span class="product-price">
                                                        {{ $variant->size?->{'name_' . $locale} ?? $variant->size?->name_az }}
                                                        /
                                                        <span>{{ number_format($variant->price, 2) }} ₼</span>
                                                    </span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>

                                    @if($firstVariant)
                                        <div class="taksit" data-price="{{ $firstVariant->price }}">
                                            <ul class="months">
                                                <li data-month="3">{{ __('3 ay') }}</li>
                                                <li data-month="6" class="active-taksit">{{ __('6 ay') }}</li>
                                                <li data-month="9">{{ __('9 ay') }}</li>
                                            </ul>

                                            <div class="taksit-price">
                                                <p>{{ number_format($firstVariant->price / 6, 2) }} AZN</p>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{ $products->links('frontend.include.pagination') }}
                </div>
            </div>

            @if(!empty($banners['bottomweb']))
                <div class="home-bottom-banner">
                    <div class="home-bottom-banner__wrap">
                        <div class="banner-bt-image">
                            <img
                                src="{{ asset('frontend/uploads/banners/' . $banners['bottomweb']) }}"
                                alt="Bottom Banner"
                            />
                        </div>
                    </div>
                </div>
            @endif

            <div class="home-page-mobile">
                @if(!empty($banners['bottommobile']))
                    <div class="home-bottom-banners-mobile">
                        <div class="home-bottom-banner">
                            <div class="home-bottom-banner__wrap">
                                <div class="banner-bt-image">
                                    <img
                                        src="{{ asset('frontend/uploads/banners/' . $banners['bottommobile']) }}"
                                        alt="Bottom Banner"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </main>
@endsection

@section('modal')
    <div id="myModal" class="terms-modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close">&times;</span>
                <p>{{ __('info_terms') }}</p>
            </div>

            <div class="modal-body">
                <div class="modal-tablinks">
                    <button data-id="1" class="modal-tab-active">
                        Necə sifariş edim
                    </button>

                    <button data-id="2">
                        Hissə-hissə ödəniş qaydaları
                    </button>

                    <button data-id="3">
                        Bonus pul nədir?
                    </button>
                </div>

                <div class="modal-tabcontents">
                    <div class="tabcontent" id="1">
                        <div class="shipping">
                            <h1>{{ __('info_delivery') }}</h1>

                            <p>
                                <strong>{{ __('info_within_baku') }}</strong>
                                çatdırılma ödənişsizdir.
                            </p>

                            <p>
                                Ünvandan asılı olaraq çatdırılma zamanı
                                30 dəqiqədən 2 saata qədər dəyişə bilər.
                            </p>

                            <p>
                                <strong>{{ __('info_to_regions_nationwide') }}</strong>
                                və
                                <strong>{{ __('info_to_cities') }}</strong>
                                çatdırılma ödənişlidir (10 AZN).
                            </p>
                        </div>

                        <div class="payment-types">
                            <h1>{{ __('info_payment_methods') }}</h1>

                            <ol>
                                <li>{{ __('info_installment_payments_without_a_bank_card') }}</li>
                                <li>{{ __('info_pay_cash_or_by_card_on_delivery') }}</li>
                                <li>{{ __('info_online_payment_by_bank_card') }}</li>
                                <li>{{ __('info_interest_free_installments_for_2_3_or_6_months_with_birbank') }}</li>
                                <li>{{ __('info_with_the_m10_e_wallet') }}</li>
                            </ol>
                        </div>

                        <div class="modal-footer">
                            <p>{{ __('info_always_at_your_service_with_24_years_of_experience') }}</p>
                        </div>
                    </div>

                    <div class="tabcontent" id="2">
                        content2
                    </div>

                    <div class="tabcontent" id="3">
                        content3
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="basket-brand-modal-container">
        <div class="modal-overlay"></div>

        <div class="basket-brand-modal">
            <div class="basket-brand-modal-heading">
                <h3>Narciso Rodriguez</h3>

                <img
                    class="modal-section__close"
                    src="{{ asset('frontend/images/close.svg') }}"
                    alt=""
                />
            </div>

            <div class="basket-brand-modal-body">
                <img
                    src="{{ asset('frontend/images/products/parfum.png') }}"
                    alt=""
                />

                <div>
                    <div class="authors">
                        <span>Narciso Rodriguez</span>
                        <h4>Narciso Poudree</h4>
                    </div>

                    <h1>
                        96.00
                        <sup>
                            <img
                                src="{{ asset('frontend/images/manat.svg') }}"
                                alt=""
                            >
                        </sup>
                    </h1>

                    <div class="amount">
                        <div class="amount-input">
                            <span id="decrease">-</span>
                            <span class="count">1</span>
                            <span id="increase">+</span>
                        </div>
                    </div>

                    <div class="size-dropdown">
                        <select id="size-select">
                            <option value="50ml" selected>
                                50 ml 96.00 &#8380;
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="basket-brand-modal-footer">
                <button>{{ __('product_add_to_cart') }}</button>
                <a>{{ __('product_services_and_terms') }}</a>
            </div>
        </div>
    </div>

    <div class="other-sizes-container-mobile">
        <div class="other-sizes-overlay"></div>

        <div class="other-sizes-content">
            <div class="other-sizes-heading">
                <img
                    src="{{ asset('frontend/images/close.svg') }}"
                    alt=""
                >

                <span>{{ __('product_other_sizes') }}</span>
            </div>

            <div class="other-sizes-body">
                <ul>
                    <li>
                        30 ml /
                        <span>66.00 &#8380;</span>
                    </li>

                    <li>
                        50 ml /
                        <span>96.00 &#8380;</span>
                    </li>

                    <li>
                        90 ml /
                        <span>218.00 &#8380;</span>
                    </li>

                    <li>
                        110 ml /
                        <span>251.00 &#8380;</span>
                    </li>

                    <li>
                        130 ml /
                        <span>294.00 &#8380;</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div id="shareModal" class="share-modal">
        <div class="modal-content">
            <div class="modal-header">
                <span id="share-modal-close" class="close">&times;</span>
                <p></p>
            </div>

            <div class="modal-body">
                <div class="share-form">
                    <input
                        type="url"
                        readonly
                        value=""
                    >

                    <div class="copy-share-link">
                        <img
                            src="{{ asset('frontend/images/copy.svg') }}"
                            alt="Kopyala"
                        >
                    </div>
                </div>

                <div class="share-socials">
                <span>
                    Yuxarıdakı linki kopyalayın və ya aşağıdakı kanallardan biri ilə paylaşın.
                </span>

                    <ul>
                        <li>
                            <img
                                src="{{ asset('frontend/images/wp-share.svg') }}"
                                alt="WhatsApp"
                            >
                        </li>

                        <li>
                            <img
                                src="{{ asset('frontend/images/tg-share.svg') }}"
                                alt="Telegram"
                            >
                        </li>

                        <li>
                            <img
                                src="{{ asset('frontend/images/mail-share.svg') }}"
                                alt="E-mail"
                            >
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('page-scripts')
    <script src="{{ asset('frontend/js/common.js') }}"></script>
    <script src="{{ asset('frontend/js/terms.js') }}"></script>
    <script src="{{ asset('frontend/js/share-modal.js') }}"></script>
    <script src="{{ asset('frontend/js/taksit.js') }}"></script>
@endsection
