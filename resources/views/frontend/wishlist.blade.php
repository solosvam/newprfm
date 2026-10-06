@extends('frontend.layouts.app')

@section('page-css')
    <link rel="stylesheet" href="{{ asset_v('frontend/css/pages/account.css') }}">
    <link rel="stylesheet" href="{{ asset_v('frontend/css/pages/wishlist.css') }}">
@endsection

@section('content')
    @php
        $locale = app()->getLocale();
    @endphp
    <main>
        <div class="account-layout">
            @include('frontend.partials.cabinet-sidebar', ['pageTitle' => __('wishlist_my_favorites')])

            <div class="account-panel account-panel--flush">
                <div class="wishlist-head">
                    <h1 class="account-panel-title">{{ __('wishlist_my_favorites') }}</h1>
                    @if($products->isNotEmpty())
                        <span class="wishlist-count">{{ $products->count() }}</span>
                    @endif
                </div>

                @if($products->isEmpty())
                    <p class="account-card-empty">{{ __('wishlist_you_have_no_favorites_yet') }}</p>
                @else
                    <div class="grid wishlist-grid"
                         data-added-label="{{ __('wishlist_added') }}"
                         data-added-message="{{ __('wishlist_added_message') }}">
                        @foreach($products as $product)
                            @php
                                $image = $product->images->first();
                                $variants = $product->variants->where('active', 1)->sortBy('price')->values();
                                $firstVariant = $variants->first();
                                $sizeName = fn ($variant) => $variant->size?->{'name_' . $locale} ?: $variant->size?->name_az;
                            @endphp

                            <article class="card wishlist-card product-item" data-product-id="{{ $product->id }}">
                                <div class="thumb">
                                    <div class="thumb-actions">
                                        @include('frontend.includes.favorite-button', ['product' => $product, 'selected' => true])
                                    </div>
                                    <a href="{{ route('product', $product->slug) }}">
                                        @if($image)
                                            <img class="product-main-image" src="{{ asset('frontend/uploads/products/' . $image->image) }}" alt="{{ $product->brand?->name }} {{ $product->name }}" loading="lazy">
                                        @endif
                                    </a>
                                </div>

                                <p class="brandname">{{ $product->brand?->name }}</p>
                                <p class="pname"><a href="{{ route('product', $product->slug) }}">{{ $product->name }}</a></p>

                                @if($firstVariant)
                                    <div class="wishlist-variant">
                                        @if($variants->count() > 1)
                                            <select class="wishlist-select" data-wishlist-size aria-label="{{ __('wishlist_choose_size') }}">
                                                @foreach($variants as $variant)
                                                    <option value="{{ $variant->id }}" data-price="{{ $variant->salePrice() }}">{{ $sizeName($variant) }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <span class="wishlist-select is-single">{{ $sizeName($firstVariant) ?: '—' }}</span>
                                        @endif
                                    </div>

                                    <div class="wishlist-buy">
                                        <span class="wishlist-price" data-wishlist-price>{{ number_format($firstVariant->salePrice(), 2) }} ₼</span>
                                        <button type="button"
                                                class="wishlist-add"
                                                data-wishlist-add
                                                data-product-id="{{ $product->id }}"
                                                data-variant-id="{{ $firstVariant->id }}">
                                            <span>{{ __('product_add_to_cart') }}</span>
                                        </button>
                                    </div>
                                @else
                                    <p class="wishlist-out">{{ __('wishlist_out_of_stock') }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </main>
@endsection

@section('page-scripts')
    <script src="{{ asset_v('frontend/js/pages/wishlist.js') }}" defer></script>
@endsection
