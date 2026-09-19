@php
    $html_tag_data = [];
    $title = 'Əsas səhifə';
    $breadcrumbs = ["/x"=>"Sürət Panel", "/"=>"Əsas səhifə"]
@endphp
@extends('backend.layout',['html_tag_data'=>$html_tag_data, 'title'=>$title])

@section('css')

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
                <div class="col-12 col-sm-6 d-flex align-items-start justify-content-end">
                    <!-- Tour Button Start -->
                    <button type="button" class="btn btn-outline-primary btn-icon btn-icon-end w-100 w-sm-auto" id="dashboardTourButton">
                        <span>Take a Tour</span>
                        <i data-acorn-icon="flag"></i>
                    </button>
                    <!-- Tour Button End -->
                </div>
                <!-- Top Buttons End -->
            </div>
        </div>
        <div class="row">
            <div class="col">
                <section class="scroll-section" id="textContent">
                    <h2 class="small-title">Header</h2>
                    <div class="card mb-5">
                        <div class="card-body d-flex flex-column scroll-out" data-title="Step 1" data-intro="Salamlar" data-step="1">
                            <div class="scroll">
                                <h3 class="card-title mb-4">Sistem hazırlanır</h3>
                                ParfumShop.az sistemi yenilənir
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection
