@extends('frontend.layouts.app')
{{-- Deaktiv ətir (ProductController::product): qiymət və alış yoxdur, oxşar ətirlər; axtarış sistemləri indeksləmir --}}

@section('page-css')
    <link rel="stylesheet" href="{{ asset_v('frontend/css/pages/product.css') }}">
@endsection

@section('title', trim(($product->brand?->name ?? '').' '.$product->name).' — '.__('product_unavailable_title').' | Parfumshop.az')
@section('meta_robots', 'noindex, follow')

@section('subnav')
    @include('frontend.partials.subnav')
@endsection

@section('content')
    @php $image = $product->images->first(); @endphp

    <p class="crumb">
        <a href="{{ route('home') }}">{{ __('product_home') }}</a> /
        @if($product->brand)
            <a href="{{ route('brand.products', $product->brand->slug) }}">{{ $product->brand->name }}</a> /
        @endif
        {{ $product->name }}
    </p>

    <section class="unavailable">
        <div class="unavailable__image">
            @if($image)
                <img src="{{ route('product.image', ['size' => 400, 'image' => $image->image]) }}" alt="{{ $product->name }}" width="400" height="400">
            @endif
        </div>
        <div class="unavailable__info">
            <span class="unavailable__badge">{{ __('product_unavailable_title') }}</span>
            <h1 class="title">
                @if($product->brand)<span class="unavailable__brand">{{ $product->brand->name }}</span>@endif
                {{ $product->name }}
            </h1>
            <p class="unavailable__text">{{ __('product_unavailable_text') }}</p>
            <div class="unavailable__actions">
                @if($product->brand)
                    <a href="{{ route('brand.products', $product->brand->slug) }}" class="btn btn-dark">{{ __('product_unavailable_brand', ['brand' => $product->brand->name]) }}</a>
                @endif
                <a href="{{ route('home') }}" class="btn btn-outline">{{ __('product_unavailable_catalog') }}</a>
            </div>
        </div>
    </section>

    @if($alternatives->isNotEmpty())
        <div class="section-head section-head--spaced">
            <h2>{{ __('product_unavailable_alternatives') }}</h2>
        </div>
        <div class="grid">
            @foreach($alternatives as $alternative)
                @include('frontend.includes.product-card', ['product' => $alternative])
            @endforeach
        </div>
    @endif
@endsection
