@extends('frontend.new.layouts.app')

@section('content')
    <main>
        <div class="wrap">
            <div class="account-layout">
                @include('frontend.new.partials.cabinet-sidebar', ['pageTitle' => __('wishlist_my_favorites')])

                <div class="account-panel">
                    <h1 class="account-panel-title">{{ __('wishlist_my_favorites') }}</h1>

                    @if($products->isEmpty())
                        <p class="account-card-empty">{{ __('wishlist_you_have_no_favorites_yet') }}</p>
                    @else
                        <div class="grid wishlist-grid">
                            @foreach($products as $product)
                                @php($image = $product->images->first())
                                <article class="card wishlist-card product-item" data-product-id="{{ $product->id }}">
                                    <div class="thumb">
                                        <button type="button" class="icon-btn favorite-toggle is-favorite" data-product-id="{{ $product->id }}" aria-label="{{ __('wishlist_remove_from_favorites') }}" aria-pressed="true">
                                            <img src="{{ asset('frontend/images/product-card-wishlist.svg') }}" alt="">
                                        </button>
                                        <a href="{{ route('product', $product->slug) }}">
                                            @if($image)
                                                <img class="product-main-image" src="{{ asset('frontend/uploads/products/' . $image->image) }}" alt="{{ $product->brand?->name }} {{ $product->name }}">
                                            @endif
                                        </a>
                                    </div>
                                    <p class="brandname">{{ $product->brand?->name }}</p>
                                    <p class="pname"><a href="{{ route('product', $product->slug) }}">{{ $product->name }}</a></p>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </main>
@endsection
