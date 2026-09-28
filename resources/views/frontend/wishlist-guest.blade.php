@extends('frontend.layouts.app')

@section('page-css')
    <link rel="stylesheet" href="{{ asset_v('frontend/css/pages/wishlist.css') }}">
@endsection

@section('content')
    <main>
        <section class="wishlist-page wishlist-page--guest">
            <div class="wishlist-head">
                <h1 class="wishlist-title">{{ __('wishlist_my_favorites') }}</h1>
                <span id="guestWishlistCount" class="wishlist-count" hidden></span>
            </div>

            <div id="guestWishlist"
                 class="grid wishlist-grid"
                 data-products-url="{{ route('wishlist.products') }}"
                 data-added-label="{{ __('wishlist_added') }}"
                 data-added-message="{{ __('wishlist_added_message') }}"
                 data-i18n="{{ json_encode([
                     'addToCart'  => __('product_add_to_cart'),
                     'chooseSize' => __('wishlist_choose_size'),
                     'outOfStock' => __('wishlist_out_of_stock'),
                     'remove'     => __('wishlist_remove_from_favorites'),
                 ], JSON_UNESCAPED_UNICODE) }}"></div>

            <p id="guestWishlistEmpty" class="account-card-empty" hidden>{{ __('wishlist_you_have_no_favorites_yet') }}</p>
        </section>
    </main>
@endsection

@section('page-scripts')
    <script src="{{ asset_v('frontend/js/pages/wishlist-guest.js') }}" defer></script>
    <script src="{{ asset_v('frontend/js/pages/wishlist.js') }}" defer></script>
@endsection
