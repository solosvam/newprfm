@extends('frontend.layouts.app')

@include('frontend.includes.home-seo')

@section('subnav')
    @include('frontend.partials.subnav')
@endsection

@section('content')
    @include('frontend.includes.top-banners')
    @include('frontend.includes.brand-list')

    <div class="layout">
        @include('frontend.includes.catalog-sidebar')

        <div>
            @include('frontend.includes.catalog-toolbar')
            <div class="grid">
                @foreach ($products ?? [] as $product)
                    @include('frontend.includes.product-card', ['product' => $product])
                @endforeach
            </div>
        </div>
    </div>
    {{ $products->links('frontend.includes.pagination') }}

    <div id="sidebarExtrasMobileSlot"></div>

    @include('frontend.includes.bottom-banners')
@endsection

@section('page-scripts')
    <script src="{{ asset_v('frontend/js/banner-slider.js') }}" defer></script>
@endsection
