@extends('frontend.layouts.app')

@section('content')
    <main>
        <div class="account-layout">
            @include('frontend.partials.cabinet-sidebar', ['pageTitle' => __('wishlist_my_favorites')])

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
                                    <div class="thumb-actions">
                                        <button type="button" class="icon-btn fav-btn active is-favorite" data-product-id="{{ $product->id }}" aria-label="{{ __('wishlist_remove_from_favorites') }}" aria-pressed="true">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
                                        </button>
                                    </div>
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
    </main>
@endsection
