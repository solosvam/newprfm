@php
    $html_tag_data = [];
    $title = 'Qısa yollar';
    $breadcrumbs = ["/"=>"ParfumShop", ""=> $title]
@endphp
@extends('backend.layout',['html_tag_data'=>$html_tag_data, 'title'=>$title])


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

                    <!-- Tour Button End -->
                </div>
                <!-- Top Buttons End -->
            </div>
        </div>
        <div class="row">
            <div class="col">
                <section class="scroll-section" id="hover">
                    <div class="card mb-5">
                        <div class="card-body">
                            <p>Klavyaturada aşağıda göstərilən hərflərdən istifadə edərək sistem üzərində qısa yollar kəşf edin.</p>
                            <p>Bu qısayollar sistemin istənilən səhifəsində işləyir.</p>
                            <br>
                            <p>
                                <kbd>s</kbd> düyməsi axtarış qutusunu açır.
                            </p>
                            <p>
                                <kbd>a</kbd> düyməsi sağdakı ayarlar panelini açır, təkrar basıldıqda isə bağlanır.
                            </p>
                            <p>
                                <kbd>l</kbd> düyməsi ilə sayt qaranlıq və işıqlı rejimə keçir. Light Mode
                            </p>
                            <p>
                                <kbd>c</kbd> düyməsi "CRM" səhifəsini açır.
                            </p>
                        </div>
                    </div>
                </section>
            </div>
        </div>

    </div>
@endsection
