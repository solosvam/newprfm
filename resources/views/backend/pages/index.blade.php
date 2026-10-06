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


@section('content')
    <div class="container">
        {{-- Kuryer səhifəsinin başlığı; admin panelinin başlığı (salam + tarix) öz şablonundadır --}}
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
