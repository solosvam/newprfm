@extends('frontend.layouts.app')

@include('frontend.includes.home-seo')

@section('subnav')
    @include('frontend.partials.subnav')
@endsection

@section('content')
    @php
        $locale = app()->getLocale();
    @endphp

    @include('frontend.includes.catalog-page', [
        'title'       => $selectedBrand->name,
        'total'       => $products->total(),
        'crumbs'      => [['label' => __('brands_all'), 'url' => route('brands')]],
        'image'       => $selectedBrand->image ? asset('frontend/uploads/brands/' . $selectedBrand->image) : null,
        'description' => $selectedBrand->{'content_' . $locale} ?: ($selectedBrand->content_az ?? null),
        'hideBrand'   => true,
    ])
@endsection
