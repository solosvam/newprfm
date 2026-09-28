@extends('frontend.layouts.app')

@section('page-css')
    <link rel="stylesheet" href="{{ asset_v('frontend/css/pages/product.css') }}">
@endsection

@php
    $seoTitle = trim(($product->brand?->name ? $product->brand->name . ' ' : '') . $product->name);
    $seoDescription = \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags($product->{'content_' . app()->getLocale()} ?: ($product->content_az ?: $seoTitle)))), 160, '');
    $seoImage = $product->images->first() ? asset('frontend/uploads/products/' . $product->images->first()->image) : null;
    $seoUrl = route('product', $product->slug);
    $seoVariants = $product->variants->where('active', 1);
    $seoSchema = ['@context' => 'https://schema.org', '@type' => 'Product', 'name' => $seoTitle, 'description' => $seoDescription, 'url' => $seoUrl, 'image' => $seoImage ? [$seoImage] : []];
    if ($product->brand) $seoSchema['brand'] = ['@type' => 'Brand', 'name' => $product->brand->name];
    if ($seoVariants->isNotEmpty()) $seoSchema['offers'] = ['@type' => 'AggregateOffer', 'lowPrice' => number_format((float) $seoVariants->min('price'), 2, '.', ''), 'highPrice' => number_format((float) $seoVariants->max('price'), 2, '.', ''), 'priceCurrency' => 'AZN', 'offerCount' => $seoVariants->count(), 'url' => $seoUrl];
@endphp
@section('title', $seoTitle . ' | Parfumshop.az')
@section('meta_description', $seoDescription)
@section('canonical_url', $seoUrl)
@section('og_type', 'product')
@section('og_title', $seoTitle)
@if($seoImage)
    @section('og_image', $seoImage)
@endif
@section('structured_data')
    <script type="application/ld+json">{!! json_encode($seoSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endsection

@section('subnav')
    @include('frontend.partials.subnav')
@endsection

@section('content')
    @php
        $locale = app()->getLocale();
        $gender = $product->genders->first();
        $firstImage = $product->images->first();
        $variants = $product->variants->where('active', 1)->sortBy('price')->values();
        $firstVariant = $variants->first();
        $genderName = $gender ? ($gender->{'name_' . $locale} ?? $gender->name_az) : null;
        $typeName = $product->type ? ($product->type->{'name_' . $locale} ?? $product->type->name_az) : null;
        $subtitle = collect([$genderName, $typeName])->filter()->implode(' · ');
        $initialPrice = (float) ($firstVariant?->price ?? 0);
        $firstPeriod = $creditPeriods->first();
    @endphp

    <p class="crumb">
        <a href="{{ route('home') }}">{{ __('product_home') }}</a> /
        @if($product->brand)
            <a href="{{ route('brand.products', $product->brand->slug) }}">{{ $product->brand->name }}</a> /
        @endif
        {{ $product->name }}
    </p>

    <div class="product-top">
        <div class="product-header">
            <h1 class="title">
                @if($product->brand)
                    <a href="{{ route('brand.products', $product->brand->slug) }}" class="title__brand">{{ $product->brand->name }}</a>
                    <span class="title__sep" aria-hidden="true">/</span>
                @endif
                <span class="title__name">{{ $product->name }}</span>
            </h1>
            @if($subtitle)
                <p class="subtitle">{{ $subtitle }}</p>
            @endif
        </div>

        <div class="thumbs">
            @foreach ($product->images as $image)
                <div class="t {{ $loop->first ? 'active' : '' }}" data-thumb data-full="{{ asset('frontend/uploads/products/' . $image->image) }}">
                    <img src="{{ asset('frontend/uploads/products/' . $image->image) }}" alt="">
                </div>
            @endforeach
        </div>

        <div class="main-image">
            @if ($firstImage)
                <img data-main-image src="{{ asset('frontend/uploads/products/' . $firstImage->image) }}" alt="{{ $product->brand?->name }} {{ $product->name }}">
            @endif

            <div class="thumb-actions">
                <button type="button" class="icon-btn fav-btn" data-product-id="{{ $product->id }}" aria-label="{{ __('common_favorite') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
                </button>
                <button type="button" class="icon-btn share-btn" data-url="{{ route('product', $product->slug) }}" data-title="{{ $product->name }}" aria-label="{{ __('common_share') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5 15.4 17.5M15.4 6.5 8.6 10.5"/></svg>
                </button>
            </div>
        </div>

        <div class="buy-core" data-buybox data-base-price="{{ $initialPrice }}">
            <div class="stars" aria-label="{{ $ratingAverage }} / 5">
                @for($star = 1; $star <= 5; $star++)
                    <span>{{ $star <= round($ratingAverage) ? '★' : '☆' }}</span>
                @endfor
                <span class="rating-count">({{ $product->reviews->count() }} {{ __('review_count') }})</span>
            </div>

            <div class="price-qty-row">
                <p class="price-row" data-price-display>{{ number_format($initialPrice, 2) }} ₼</p>

                <div class="qty">
                    <button type="button" data-qty-action="minus">−</button>
                    <span data-qty-value>1</span>
                    <button type="button" data-qty-action="plus">+</button>
                </div>
            </div>

            <div class="size-pills">
                @foreach ($variants as $variant)
                    <span
                        class="size-pill {{ $loop->first ? 'active-size-amount' : '' }}"
                        data-variant-id="{{ $variant->id }}"
                        data-price="{{ $variant->price }}"
                    >{{ $variant->size?->{'name_' . $locale} ?? $variant->size?->name_az }}</span>
                @endforeach
            </div>

            <button type="button" class="btn btn-dark" data-add-to-cart data-variant-id="{{ $firstVariant?->id }}" data-product-id="{{ $product->id }}" @disabled(!$firstVariant)>{{ __('product_add_to_cart') }}</button>
            <button type="button" class="btn btn-outline" data-one-click data-url="{{ route('one-click.store') }}" data-auth="{{ auth()->check() ? 1 : 0 }}" @disabled(!$firstVariant)>{{ __('product_one_click_buy') }}</button>
            @if($creditPeriods->isNotEmpty() && $variants->isNotEmpty())
                @if(!auth()->check())
                    <a class="btn btn-dark product-mobile-credit" href="{{ route('front.login', ['redirect' => route('product', $product->slug)]) }}">{{ __('product_pay_in_installments') }}</a>
                @elseif(!$creditProfileComplete)
                    <button type="button" class="btn btn-dark product-mobile-credit" data-credit-profile-required>{{ __('product_pay_in_installments') }}</button>
                @else
                    <button type="button" class="btn btn-dark product-mobile-credit" data-credit-apply>{{ __('product_pay_in_installments') }}</button>
                @endif
            @endif
        </div>

        <div class="installment-full" data-installment>
            <h2 class="installment-heading">{{ __('product_installment_schedule') }}</h2>
            <table class="installment-table">
                <thead>
                <tr><th></th><th>{{ __('product_duration') }}</th><th>{{ __('product_monthly') }}</th><th>{{ __('product_price') }}</th></tr>
                </thead>
                <tbody>
                @foreach ($creditPeriods as $period)
                    @php
                        $rate = (float) $period->interest_rate;
                        $installmentTotal = $initialPrice + (($initialPrice * $rate) / 100);
                        $installmentMonthly = $installmentTotal / $period->month;
                    @endphp
                    <tr class="{{ $loop->first ? 'active' : '' }}" data-month="{{ $period->month }}" data-rate="{{ $rate }}">
                        <td>
                            <input
                                type="radio"
                                name="installment"
                                value="{{ $period->month }}"
                                data-monthly="{{ $installmentMonthly }}"
                                data-total="{{ $installmentTotal }}"
                                {{ $loop->first ? 'checked' : '' }}
                            >
                        </td>
                        <td>{{ $period->month }} {{ __('product_month') }}{{ $rate == 0 ? ' ' . __('product_interest_free') : '' }}</td>
                        <td data-installment-monthly>{{ number_format($installmentMonthly, 2) }} ₼</td>
                        <td data-installment-total>{{ number_format($installmentTotal, 2) }} ₼</td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            @if(!auth()->check())
                <a class="btn btn-dark" href="{{ route('front.login', ['redirect' => route('product', $product->slug)]) }}">{{ __('product_apply') }}</a>
            @elseif(!$creditProfileComplete)
                <button type="button" class="btn btn-dark" data-credit-profile-required>{{ __('product_apply') }}</button>
            @else
                <button type="button" class="btn btn-dark" data-credit-apply @disabled($variants->isEmpty() || $creditPeriods->isEmpty())>{{ __('product_apply') }}</button>
            @endif
        </div>

    </div>


    <div class="tabs" data-tabs @if(session('review_success') || $errors->has('rating') || $errors->has('comment')) data-show-reviews @endif>
        <div class="tab-list">
            <button type="button" class="tab-btn active" data-tab="about">{{ __('product_about_the_perfume') }}</button>
            <button type="button" class="tab-btn" data-tab="reviews">
                {{ __('product_reviews') }} <span class="tab-count">{{ $product->reviews->count() }}</span>
            </button>
        </div>

        <div class="tab-panel" data-tab-panel="about">
            @php
                $description = $product->{'content_' . $locale} ?: $product->content_az;
                $ingredientNames = $product->ingredients
                    ->map(fn ($ingredient) => $ingredient->{'name_' . $locale} ?: $ingredient->name_az)
                    ->filter();
            @endphp
            @if($description)
                <div class="product-description">{!! nl2br(e($description)) !!}</div>
            @else
                <p>{{ __('product_description_empty') }}</p>
            @endif
            @if($ingredientNames->isNotEmpty())
                <div class="notes">
                    <div><p class="k">{{ __('product_notes') }}</p><p class="v">{{ $ingredientNames->join(', ') }}</p></div>
                </div>
            @endif
        </div>

        <div class="tab-panel" data-tab-panel="reviews" hidden>
            @include('frontend.partials.product-reviews', ['product' => $product])
        </div>
    </div>

    <div class="section-head section-head--spaced">
        <h2>{{ __('product_similar') }}</h2>
    </div>
    <div class="grid similar-products">
        @foreach ($similarProducts ?? [] as $item)
            <div class="card" data-href="{{ route('product', $item->slug) }}">
                <div class="thumb">
                    <div class="thumb-actions">
                        {{-- DÜZƏLİŞ: $product əvəzinə $item --}}
                        <button
                            type="button"
                            class="icon-btn fav-btn"
                            data-product-id="{{ $item->id }}"
                            aria-label="{{ __('common_favorite') }}"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
                        </button>

                        <button
                            type="button"
                            class="icon-btn share-btn"
                            data-url="{{ route('product', $item->slug) }}"
                            data-title="{{ $item->name }}"
                            aria-label="{{ __('common_share') }}"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5 15.4 17.5M15.4 6.5 8.6 10.5"/></svg>
                        </button>
                    </div>

                    @if ($item->images->first())
                        <img src="{{ asset('frontend/uploads/products/' . $item->images->first()->image) }}" alt="{{ $item->name }}" loading="lazy">
                    @else
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="34" height="34"><path d="M9 3h6l1 4H8l1-4Z"/><path d="M8 7h8l1 13a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L8 7Z"/></svg>
                    @endif
                </div>
                <p class="brandname">{{ $item->brand?->name }}</p>
                <p class="pname">{{ $item->name }}</p>
                <p class="price">{{ number_format((float) ($item->variants->first()?->price ?? 0), 2) }} ₼</p>
            </div>
        @endforeach
    </div>

    {{-- Mobil: yapışan "Səbətə əlavə edin" paneli --}}
    @if($firstVariant)
        <div id="productSticky" class="product-sticky" inert>
            <div class="product-sticky__info">
                <span class="product-sticky__size" data-sticky-size></span>
                <strong class="product-sticky__price" data-sticky-price>{{ number_format($initialPrice, 2) }} ₼</strong>
            </div>
            <button type="button" class="btn btn-dark product-sticky__btn" data-sticky-add>{{ __('product_add_to_cart') }}</button>
        </div>
    @endif

    <dialog id="oneClickDialog" class="credit-profile-dialog" aria-labelledby="oneClickTitle">
        <form id="oneClickForm" class="credit-profile-dialog__body">
            <button type="button" class="credit-profile-dialog__close" data-one-click-close aria-label="{{ __('common_close') }}">×</button>
            <h2 id="oneClickTitle" class="credit-profile-dialog__title">{{ __('product_one_click_buy') }}</h2>
            <p class="credit-profile-dialog__text">{{ __('product_one_click_phone_hint') }}</p>
            <label for="oneClickMobile">{{ __('product_one_click_mobile') }}</label>
            <input id="oneClickMobile" type="tel" inputmode="numeric" autocomplete="tel" placeholder="994 __ ___ __ __" maxlength="16" required class="auth-input one-click-mobile">
            <p id="oneClickError" class="one-click-error" role="alert"></p>
            <button type="submit" class="btn btn-dark">{{ __('product_one_click_submit') }}</button>
        </form>
    </dialog>
    @if(auth()->check() && !$creditProfileComplete)
        <dialog id="creditProfileRequiredDialog" class="credit-profile-dialog" aria-labelledby="creditProfileRequiredTitle">
            <div class="credit-profile-dialog__body">
                <button type="button" class="credit-profile-dialog__close" data-credit-profile-close aria-label="{{ __('common_close') }}">×</button>
                <h2 id="creditProfileRequiredTitle" class="credit-profile-dialog__title">{{ __('credit_profile_required_title') }}</h2>
                <p class="credit-profile-dialog__text">{{ __('credit_profile_required_message') }}</p>
                <a class="btn btn-dark" href="{{ route('profile.credit', ['return' => route('product', $product->slug)]) }}">{{ __('credit_profile_required_action') }}</a>
            </div>
        </dialog>
    @endif
    @if(auth()->check() && $creditProfileComplete && $variants->isNotEmpty() && $creditPeriods->isNotEmpty())
        @include('frontend.partials.credit-application-modal')
    @endif
@endsection

@section('page-scripts')
    <script src="{{ asset_v('frontend/js/credit-application.js') }}" defer></script>
    <script src="{{ asset_v('frontend/js/vendor/jquery.inputmask.min.js') }}" defer></script>
    <script src="{{ asset_v('frontend/js/one-click.js') }}" defer></script>

    <script>
        (() => {
            const bar = document.getElementById('productSticky');
            const mainBtn = document.querySelector('.buy-core [data-add-to-cart]');
            const buyCore = document.querySelector('.buy-core');
            if (!bar || !mainBtn || !buyCore) return;

            const stickyBtn = bar.querySelector('[data-sticky-add]');
            const sizeEl = bar.querySelector('[data-sticky-size]');
            const priceEl = bar.querySelector('[data-sticky-price]');

            // Ölçü, qiymət, düymə mətni və vəziyyəti əsas blokla eyni qalsın
            function sync() {
                sizeEl.textContent = buyCore.querySelector('.size-pill.active-size-amount')?.textContent.trim() || '';
                priceEl.textContent = buyCore.querySelector('[data-price-display]')?.textContent.trim() || '';
                stickyBtn.textContent = mainBtn.textContent.trim();
                stickyBtn.disabled = mainBtn.disabled;
            }

            new MutationObserver(sync).observe(buyCore, {
                subtree: true, childList: true, characterData: true,
                attributes: true, attributeFilter: ['class', 'disabled'],
            });
            sync();

            // Əsas düyməni işə salırıq — main.js-in səbət məntiqi eyni qalır
            stickyBtn.addEventListener('click', () => mainBtn.click());

            // Panel yalnız əsas düymə yuxarıda, ekrandan çıxanda görünür
            if ('IntersectionObserver' in window) {
                new IntersectionObserver(([entry]) => {
                    const show = !entry.isIntersecting && entry.boundingClientRect.top < 0;
                    bar.classList.toggle('is-visible', show);
                    bar.inert = !show;
                }).observe(mainBtn);
            }
        })();
    </script>
@endsection
