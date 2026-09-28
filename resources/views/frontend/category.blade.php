@extends('frontend.layouts.app')

@include('frontend.includes.home-seo')

@section('subnav')
    @include('frontend.partials.subnav')
@endsection

@section('content')
    @php
        $locale = app()->getLocale();
        $categoryName = $selectedCategory->{'name_' . $locale} ?: ($selectedCategory->name_az ?? $selectedCategory->name);
    @endphp

    @include('frontend.includes.catalog-page', [
        'title'       => $categoryName,
        'total'       => $products->total(),
        'crumbs'      => [],
        'image'       => null,
        'description' => $selectedCategory->{'content_' . $locale} ?: ($selectedCategory->content_az ?? null),
        'hideBrand'   => false,
    ])
@endsection
