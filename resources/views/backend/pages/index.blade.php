@php
    $html_tag_data = [];
    $title = ($courier ?? null) ? 'Tapşırıqlarım' : 'Əsas səhifə';
    $breadcrumbs = ["/x"=>"ParfumShop", "/"=>"Əsas səhifə"]
@endphp
@extends('backend.layout',['html_tag_data'=>$html_tag_data, 'title'=>$title])

@section('css')
    @if($courier ?? null)<link rel="stylesheet" href="{{ asset_v('backend/css/courier.css') }}">@endif

@endsection

@section('js_vendor')
    <script src="{{ asset('backend/js/vendor/intro.min.js') }}"></script>
@endsection

@section('js_page')
    <script>
        if (typeof introJs !== 'undefined' && document.getElementById('dashboardTourButton') !== null) {
            document.getElementById('dashboardTourButton').addEventListener('click', (event) => {
                introJs()
                    .setOption('nextLabel', '<span>Next</span><i class="cs-chevron-right"></i>')
                    .setOption('prevLabel', '<i class="cs-chevron-left"></i><span>Prev</span>')
                    .setOption('skipLabel', '<i class="cs-close"></i>')
                    .setOption('doneLabel', '<i class="cs-check"></i><span>Done</span>')
                    .setOption('overlayOpacity', 0.5)
                    .start();
            });
        }
    </script>
@endsection

@section('content')
    <div class="container">
        <div class="page-title-container">
            <div class="row">
                <!-- Title Start -->
                <div class="col-12 col-sm-6">
                    <h1 class="mb-0 pb-0 display-4" id="title">{{$title}}</h1>
                    @include('backend._layout.breadcrumb',['breadcrumbs'=>$breadcrumbs])
                </div>
                <!-- Title End -->

                <!-- Top Buttons Start -->
                @unless($courier ?? null)
                <div class="col-12 col-sm-6 d-flex align-items-start justify-content-end">
                    <!-- Tour Button Start -->
                    <button type="button" class="btn btn-outline-primary btn-icon btn-icon-end w-100 w-sm-auto" id="dashboardTourButton">
                        <span>Take a Tour</span>
                        <i data-acorn-icon="flag"></i>
                    </button>
                    <!-- Tour Button End -->
                </div>
                @endunless
                <!-- Top Buttons End -->
            </div>
        </div>
        {{-- Rola görə: kuryer — öz sifarişləri, qalanları — ümumi panel --}}
        @if($courier ?? null)
            @include('backend.pages.dashboard.courier')
        @else
            @include('backend.pages.dashboard.admin')
        @endif
    </div>
@endsection
