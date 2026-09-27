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
                                        @include('frontend.includes.favorite-button', ['product' => $product, 'selected' => true])
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
