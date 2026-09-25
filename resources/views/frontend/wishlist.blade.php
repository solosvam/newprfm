@extends('frontend.layout')
@section('content')
<main>
    <div class="container">
        <div class="cabinet">
            @include('frontend.partials.cabinet-sidebar',['pageTitle'=>__('wishlist_my_favorites')])

            <section class="wishlist-page">
                <h2 class="wishlist-page__title">{{ __('wishlist_my_favorites') }}</h2>

                @if($products->isEmpty())
                    <div class="wishlist-empty">{{ __('wishlist_you_have_no_favorites_yet') }}</div>
                @else
                    <div class="wishlist-grid">
                        @foreach($products as $product)
                            @php($image = $product->images->first())
                            <article class="wishlist-card product-item" data-product-id="{{ $product->id }}">
                                <div class="wishlist-card__image">
                                    <button type="button" class="favorite-toggle is-favorite" data-product-id="{{ $product->id }}" aria-label="{{ __('wishlist_remove_from_favorites') }}" aria-pressed="true">
                                        <img src="{{ asset('frontend/images/product-card-wishlist.svg') }}" alt="">
                                    </button>
                                    <a href="{{ route('product', $product->slug) }}">
                                        @if($image)
                                            <img class="product-main-image" src="{{ asset('frontend/uploads/products/'.$image->image) }}" alt="{{ $product->brand?->name }} {{ $product->name }}">
                                        @endif
                                    </a>
                                </div>
                                <div class="wishlist-card__info">
                                    <h3><a href="{{ route('product', $product->slug) }}">{{ $product->name }}</a></h3>
                                    <p>{{ $product->brand?->name }}</p>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
</main>
@endsection