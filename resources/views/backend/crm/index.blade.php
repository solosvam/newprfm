@php
    $html_tag_data = [];
    $title = 'CRM';
    $breadcrumbs = [
        '/admin' => 'ParfumShop',
        '#' => 'CRM',
    ];
@endphp

@extends('backend.layout', ['title' => $title])

@section('js_page')
    <script src="{{asset('backend/js/crm.js')}}"></script>
@endsection

@section('content')
    <div class="container">
        <!-- Title and Top Buttons Start -->
        <div class="page-title-container">
            <div class="row">
                <!-- Title Start -->
                <div class="col-12 col-md-7">
                    <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }}</h1>
                    @include('backend._layout.breadcrumb',['breadcrumbs'=>$breadcrumbs])
                </div>
                <!-- Title End -->

                <!-- Top Buttons Start -->
                <div class="col-12 col-md-5 d-flex align-items-start justify-content-end">

                </div>
                <!-- Top Buttons End -->
            </div>
        </div>
        <!-- Title and Top Buttons End -->

        <div class="row gx-4 gy-5">
            <div class="col-12">
                <!-- Biography Start -->
                <div class="card mb-5">
                    <div class="card-body">
                        <div class="position-relative">
                            <input type="text"
                                   id="crm-search"
                                   class="form-control form-control-lg"
                                   placeholder="Mobil no, Ad Soyad, .FIN"
                                   autocomplete="off"
                                   data-bs-toggle="popover"
                                   data-bs-placement="bottom"
                                   data-bs-trigger="focus"
                                   data-bs-html="true"
                                   data-bs-content="
                                   <ul class='mb-0 ps-3 small'>
                                       <li><b>Mobil no:</b> 0 ilə başlayan 10 rəqəm (0103227575)</li>
                                       <li><b>Ad Soyad:</b> boşluqla ayır (Ruf Ibr)</li>
                                       <li><b>FİN:</b> nöqtə ilə başlayan 8 simvol (.A1B2C34)</li>
                                   </ul>
                                   ">
                            <div id="search-results"
                                 class="position-absolute w-100 bg-white border rounded shadow-sm z-3"
                                 style="display:none; top: 100%; left:0; max-height: 300px; overflow-y: auto;z-index:1"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
