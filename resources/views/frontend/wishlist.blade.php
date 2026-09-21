@extends('frontend.layout')
@section('page-styles')
<link rel="stylesheet" href="{{ asset('frontend/css/components/product-card.css?v=' . filemtime(public_path('frontend/css/components/product-card.css'))) }}">
@endsection
@section('content')
<main><div class="container"><div class="cabinet">
@include('frontend.partials.cabinet-sidebar',['pageTitle'=>'Bəyəndiyim ətirlər'])
<div class="cabinet-content">
    <h2>Bəyəndiyim ətirlər</h2>
    @if($products->isEmpty())
        <div class="cabinet-content-empty"><p>Hələ bəyəndiyiniz ətir yoxdur.</p></div>
    @else
        <div class="main-products__list">
            @foreach($products as $product)
                @php($image = $product->images->first())
                <div class="product-item" data-product-id="{{ $product->id }}">
                    <div class="product-item__image">
                        <div class="product-item__image__actions">
                            <button type="button" class="favorite-toggle is-favorite" data-product-id="{{ $product->id }}" aria-label="Bəyəndiyim ətirlərdən sil"><img src="{{ asset('frontend/images/product-card-wishlist.svg') }}" alt=""></button>
                        </div>
                        <a href="{{ route('product', $product->slug) }}">@if($image)<img class="product-main-image" src="{{ asset('frontend/uploads/products/'.$image->image) }}" alt="{{ $product->brand?->name }} {{ $product->name }}">@endif</a>
                    </div>
                    <div class="product-item__info"><div class="title"><h1><a href="{{ route('product', $product->slug) }}">{{ $product->name }}</a></h1><span class="product-brand">{{ $product->brand?->name }}</span></div></div>
                </div>
            @endforeach
        </div>
    @endif
</div></div></div></main>
@endsection