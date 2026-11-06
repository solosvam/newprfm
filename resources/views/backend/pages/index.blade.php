@php
    $html_tag_data = [];
    $title = 'Əsas səhifə';
    $breadcrumbs = ["/x"=>"ParfumShop", "/"=>"Əsas səhifə"]
@endphp
@extends('backend.layout',['html_tag_data'=>$html_tag_data, 'title'=>$title])

@section('css')
    @if($dashboard ?? null)<link rel="stylesheet" href="{{ asset_v('backend/css/dashboard.css') }}">@endif

@endsection


@section('content')
    <div class="container">
        {{-- Panelin başlığı (salam + tarix) öz şablonundadır; kuryerlər — kuryer tətbiqində (/kuryer) --}}
        @include('backend.pages.dashboard.admin')
    </div>
@endsection
