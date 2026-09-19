@php
    $html_tag_data = ["scrollspy"=>"true"];
    $title = 'Charts';
    $description= 'Chart.js provides simple yet flexible JavaScript charting for designers & developers.';
    $breadcrumbs = ["/"=>"Home","/Interface"=>"Interface","/Interface/Plugins"=>"Plugins"]
@endphp
@extends('backend.layout',['html_tag_data'=>$html_tag_data, 'title'=>$title, 'description'=>$description])

@section('js_vendor')
    <script src="{{ asset('backend/js/vendor/Chart.bundle.min.js') }}"></script>
@endsection

@section('js_page')
    <script src="{{ asset('backend/js/cs/charts.extend.js') }}"></script>
    <script src="{{ asset('backend/js/plugins/charts.js') }}"></script>
    <script>

    </script>
@endsection

@section('content')
    <div class="container">
        <div class="row">
            <div class="col">
                <!-- Title Start -->
                <section class="scroll-section" id="title">
                    <div class="page-title-container">
                        <h1 class="mb-0 pb-0 display-4">{{ $title }} </h1>
                        @include('backend._layout.breadcrumb',['breadcrumbs'=>$breadcrumbs])
                    </div>
                </section>
                <!-- Title End -->

                <!-- Content Start -->
                <div>
                    <div class="row">
                        <!-- Area Chart Start -->
                        <div class="col-12">
                            <section class="scroll-section" id="areaChartTitle">
                                <h2 class="small-title">Area Chart</h2>
                                <div class="card mb-5">
                                    <div class="card-body">
                                        <div class="sh-35">
                                            <canvas id="areaChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                        <!-- Area Chart End -->
                    </div>
                </div>
                <!-- Content End -->
            </div>
        </div>
    </div>
@endsection
