@php
    $pageName = isset($selectedBrand) && $selectedBrand
        ? $selectedBrand->name
        : (isset($selectedCategory) && $selectedCategory
            ? ($selectedCategory->{'name_' . app()->getLocale()} ?: $selectedCategory->name_az)
            : null);
    $pageUrl = isset($selectedBrand) && $selectedBrand
        ? route('brand.products', $selectedBrand->slug)
        : (isset($selectedCategory) && $selectedCategory
            ? route('category', $selectedCategory->slug)
            : route('home'));
@endphp
@section('title', $pageName ? $pageName . ' | Parfumshop.az' : __('home_title'))
@section('og_title', $pageName ? $pageName . ' | Parfumshop.az' : __('home_title'))
@section('canonical_url', $pageUrl)
@if($pageName)
@section('meta_description', $pageName . ' ' . __('home_meta'))
@endif

