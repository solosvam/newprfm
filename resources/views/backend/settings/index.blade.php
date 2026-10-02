{{--
  Ayarlar: hər bölmə ayrıca səhifədir (Admin → Ayarlar → Bonuslar / Referal / Sifariş və çatdırılma / Bannerlər).
  $section — SettingsController::SECTIONS açarı; məzmun partials/{section}.blade.php-dədir, yalnız o bölmənin sahələri saxlanılır.
--}}
@php
    $html_tag_data = [];
    $title = $sectionTitle;
    $breadcrumbs = ['/admin' => 'ParfumShop', '' => 'Ayarlar', '#' => $sectionTitle];
@endphp
@extends('backend.layout', ['html_tag_data' => $html_tag_data, 'title' => 'Ayarlar — '.$sectionTitle])

@section('css')
    <link rel="stylesheet" href="{{ asset_v('backend/css/settings.css') }}">
@endsection

@section('js_page')
    <script src="{{ asset_v('backend/js/settings.js') }}"></script>
@endsection

@section('content')
    <div class="container">
        <div class="page-title-container">
            <div class="row">
                <div class="col-12 col-sm-6">
                    <h1 class="mb-0 pb-0 display-4" id="title">{{ $sectionTitle }}</h1>
                    @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
                </div>
                <div class="col-12 col-sm-6 d-flex align-items-start justify-content-end">
                    <button type="submit" form="settingsForm" class="btn btn-primary w-100 w-sm-auto">Yadda saxla</button>
                </div>
            </div>
        </div>

        <form id="settingsForm" method="POST" action="{{ route('admin.settings.'.$section.'.update') }}" enctype="multipart/form-data" class="mb-5">
            @csrf
            @include('backend.settings.partials.'.$section)
        </form>
    </div>
@endsection
