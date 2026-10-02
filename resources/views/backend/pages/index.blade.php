@php
    $html_tag_data = [];
    $title = ($courier ?? null) ? 'Tapşırıqlarım' : 'Əsas səhifə';
    $breadcrumbs = ["/x"=>"ParfumShop", "/"=>"Əsas səhifə"]
@endphp
@extends('backend.layout',['html_tag_data'=>$html_tag_data, 'title'=>$title])

@section('css')
    @if($courier ?? null)<link rel="stylesheet" href="{{ asset_v('backend/css/courier.css') }}">@endif
    @if($dashboard ?? null)<link rel="stylesheet" href="{{ asset_v('backend/css/dashboard.css') }}">@endif

@endsection

@section('js_vendor')
    @if($dashboard ?? null)
        <script src="{{ asset('backend/js/vendor/Chart.bundle.min.js') }}"></script>
        <script src="{{ asset('backend/js/vendor/chartjs-plugin-datalabels.js') }}"></script>
    @endif
@endsection

@section('js_page')
    @if($dashboard ?? null)
        <script src="{{ asset_v('backend/js/cs/charts.extend.js') }}"></script>
        <script src="{{ asset_v('backend/js/dashboard.js') }}"></script>
    @endif
@endsection

@section('content')
    <div class="container">
        {{-- Başlıq yalnız kuryer səhifəsində; admin dashboard-u birbaşa göstəricilərlə başlayır --}}
        @if($courier ?? null)
        <div class="page-title-container">
            <div class="row">
                <!-- Title Start -->
                <div class="col-12 col-sm-6">
                    <h1 class="mb-0 pb-0 display-4" id="title">{{$title}}</h1>
                    @include('backend._layout.breadcrumb',['breadcrumbs'=>$breadcrumbs])
                </div>
                <!-- Title End -->

            </div>
        </div>
        @endif
        {{-- Rola görə: kuryer — öz sifarişləri, qalanları — ümumi panel --}}
        @if($courier ?? null)
            @include('backend.pages.dashboard.courier')
        @else
            @include('backend.pages.dashboard.admin')
        @endif
    </div>
@endsection
