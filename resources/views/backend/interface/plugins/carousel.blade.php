@php
    $html_tag_data = ["scrollspy"=>"true"];
    $title = 'Carousel';
    $description= 'Glidejs is a dependency-free JavaScript ES6 slider and carousel. It’s lightweight, flexible and fast. Designed to slide. No less, no more.';
    $breadcrumbs = ["/"=>"Home","/Interface"=>"Interface","/Interface/Plugins"=>"Plugins"]
@endphp
@extends('layout',['html_tag_data'=>$html_tag_data, 'title'=>$title, 'description'=>$description])

@section('css')
    <link rel="stylesheet" href="/css/vendor/glide.core.min.css"/>
    <link rel="stylesheet" href="/css/vendor/baguetteBox.min.css"/>
@endsection

@section('js_vendor')
    <script src="/js/cs/scrollspy.js"></script>
    <script src="/js/vendor/glide.min.js"></script>
    <script src="/js/vendor/baguetteBox.min.js"></script>
@endsection

@section('js_page')
    <script src="/js/cs/glide.custom.js"></script>
    <script src="/js/plugins/carousels.js"></script>
@endsection

@section('content')
    <div class="container">
        <div class="row">
            <div class="col">
                <!-- Title Start -->
                <section class="scroll-section" id="title">
                    <div class="page-title-container">
                        <h1 class="mb-0 pb-0 display-4">{{ $title }}</h1>
                        @include('_layout.breadcrumb',['breadcrumbs'=>$breadcrumbs])
                    </div>
                </section>
                <!-- Title End -->

                <!-- Content Start -->
                <div>
                    <div class="card mb-5">
                        <div class="card-body">
                            <p class="mb-0">{{ $description }}</p>
                        </div>
                    </div>

                    <!-- Basic Start -->
                    <section class="scroll-section" id="basic">
                        <h2 class="small-title">Basic</h2>
                        <div class="row">
                            <div class="col-12 p-0 mb-5">
                                <div class="glide" id="glideBasic">
                                    <div class="glide__track" data-glide-el="track">
                                        <div class="glide__slides">
                                            <div class="glide__slide">
                                                <div class="card mb-5">
                                                    <img src="/img/product/small/product-3.webp" class="card-img-top" alt="card image" />
                                                    <div class="card-body">
                                                        <h5 class="card-title">Card title 1</h5>
                                                        <p class="card-text">Liquorice caramels apple pie chupa.</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="glide__slide">
                                                <div class="card mb-5">
                                                    <img src="/img/product/small/product-3.webp" class="card-img-top" alt="card image" />
                                                    <div class="card-body">
                                                        <h5 class="card-title">Card title 2</h5>
                                                        <p class="card-text">Liquorice caramels apple pie chupa.</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="glide__slide">
                                                <div class="card mb-5">
                                                    <img src="/img/product/small/product-3.webp" class="card-img-top" alt="card image" />
                                                    <div class="card-body">
                                                        <h5 class="card-title">Card title 3</h5>
                                                        <p class="card-text">Liquorice caramels apple pie chupa.</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="glide__slide">
                                                <div class="card mb-5">
                                                    <img src="/img/product/small/product-3.webp" class="card-img-top" alt="card image" />
                                                    <div class="card-body">
                                                        <h5 class="card-title">Card title 4</h5>
                                                        <p class="card-text">Liquorice caramels apple pie chupa.</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="glide__slide">
                                                <div class="card mb-5">
                                                    <img src="/img/product/small/product-3.webp" class="card-img-top" alt="card image" />
                                                    <div class="card-body">
                                                        <h5 class="card-title">Card title 5</h5>
                                                        <p class="card-text">Liquorice caramels apple pie chupa.</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="glide__slide">
                                                <div class="card mb-5">
                                                    <img src="/img/product/small/product-3.webp" class="card-img-top" alt="card image" />
                                                    <div class="card-body">
                                                        <h5 class="card-title">Card title 6</h5>
                                                        <p class="card-text">Liquorice caramels apple pie chupa.</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="glide__slide">
                                                <div class="card mb-5">
                                                    <img src="/img/product/small/product-3.webp" class="card-img-top" alt="card image" />
                                                    <div class="card-body">
                                                        <h5 class="card-title">Card title 7</h5>
                                                        <p class="card-text">Liquorice caramels apple pie chupa.</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-center">
                  <span class="glide__arrows slider-nav" data-glide-el="controls">
                    <button class="btn btn-icon btn-icon-only btn-outline-primary" data-glide-dir="<">
                      <i data-acorn-icon="chevron-left"></i>
                    </button>
                  </span>
                                        <span class="glide__bullets" data-glide-el="controls[nav]"></span>
                                        <span class="glide__arrows slider-nav" data-glide-el="controls">
                    <button class="btn btn-icon btn-icon-only btn-outline-primary" data-glide-dir=">">
                      <i data-acorn-icon="chevron-right"></i>
                    </button>
                  </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                    <!-- Basic End -->

                    <!-- No Controls Start -->
                    <section class="scroll-section" id="noControls">
                        <h2 class="small-title">No Controls</h2>
                        <div class="row">
                            <div class="col-12 p-0 mb-5">
                                <div class="glide" id="glideNoControls">
                                    <div class="glide__track" data-glide-el="track">
                                        <div class="glide__slides">
                                            <div class="glide__slide">
                                                <div class="card mb-5">
                                                    <img src="/img/product/small/product-1.webp" class="card-img-top" alt="card image" />
                                                    <div class="card-body">
                                                        <h5 class="card-title">Card title 1</h5>
                                                        <p class="card-text">Liquorice caramels apple pie chupa.</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="glide__slide">
                                                <div class="card mb-5">
                                                    <img src="/img/product/small/product-1.webp" class="card-img-top" alt="card image" />
                                                    <div class="card-body">
                                                        <h5 class="card-title">Card title 2</h5>
                                                        <p class="card-text">Liquorice caramels apple pie chupa.</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="glide__slide">
                                                <div class="card mb-5">
                                                    <img src="/img/product/small/product-1.webp" class="card-img-top" alt="card image" />
                                                    <div class="card-body">
                                                        <h5 class="card-title">Card title 3</h5>
                                                        <p class="card-text">Liquorice caramels apple pie chupa.</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="glide__slide">
                                                <div class="card mb-5">
                                                    <img src="/img/product/small/product-1.webp" class="card-img-top" alt="card image" />
                                                    <div class="card-body">
                                                        <h5 class="card-title">Card title 4</h5>
                                                        <p class="card-text">Liquorice caramels apple pie chupa.</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="glide__slide">
                                                <div class="card mb-5">
                                                    <img src="/img/product/small/product-1.webp" class="card-img-top" alt="card image" />
                                                    <div class="card-body">
                                                        <h5 class="card-title">Card title 5</h5>
                                                        <p class="card-text">Liquorice caramels apple pie chupa.</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="glide__slide">
                                                <div class="card mb-5">
                                                    <img src="/img/product/small/product-1.webp" class="card-img-top" alt="card image" />
                                                    <div class="card-body">
                                                        <h5 class="card-title">Card title 6</h5>
                                                        <p class="card-text">Liquorice caramels apple pie chupa.</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="glide__slide">
                                                <div class="card mb-5">
                                                    <img src="/img/product/small/product-1.webp" class="card-img-top" alt="card image" />
                                                    <div class="card-body">
                                                        <h5 class="card-title">Card title 7</h5>
                                                        <p class="card-text">Liquorice caramels apple pie chupa.</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                    <!-- No Controls End -->

                    <!-- Center Start -->
                    <section class="scroll-section" id="center">
                        <h2 class="small-title">Center</h2>
                        <div class="row">
                            <div class="col-12 mb-5">
                                <div class="glide" id="glideCenter">
                                    <div class="glide__track" data-glide-el="track">
                                        <div class="glide__slides">
                                            <div class="glide__slide">
                                                <div class="card mb-5">
                                                    <img src="/img/product/small/product-10.webp" class="card-img-top" alt="card image" />
                                                    <div class="card-body">
                                                        <h5 class="card-title">Card title 1</h5>
                                                        <p class="card-text">Liquorice caramels apple pie chupa.</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="glide__slide">
                                                <div class="card mb-5">
                                                    <img src="/img/product/small/product-10.webp" class="card-img-top" alt="card image" />
                                                    <div class="card-body">
                                                        <h5 class="card-title">Card title 2</h5>
                                                        <p class="card-text">Liquorice caramels apple pie chupa.</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="glide__slide">
                                                <div class="card mb-5">
                                                    <img src="/img/product/small/product-10.webp" class="card-img-top" alt="card image" />
                                                    <div class="card-body">
                                                        <h5 class="card-title">Card title 3</h5>
                                                        <p class="card-text">Liquorice caramels apple pie chupa.</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="glide__slide">
                                                <div class="card mb-5">
                                                    <img src="/img/product/small/product-10.webp" class="card-img-top" alt="card image" />
                                                    <div class="card-body">
                                                        <h5 class="card-title">Card title 4</h5>
                                                        <p class="card-text">Liquorice caramels apple pie chupa.</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="glide__slide">
                                                <div class="card mb-5">
                                                    <img src="/img/product/small/product-10.webp" class="card-img-top" alt="card image" />
                                                    <div class="card-body">
                                                        <h5 class="card-title">Card title 5</h5>
                                                        <p class="card-text">Liquorice caramels apple pie chupa.</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="glide__slide">
                                                <div class="card mb-5">
                                                    <img src="/img/product/small/product-10.webp" class="card-img-top" alt="card image" />
                                                    <div class="card-body">
                                                        <h5 class="card-title">Card title 6</h5>
                                                        <p class="card-text">Liquorice caramels apple pie chupa.</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-center">
                  <span class="glide__arrows slider-nav" data-glide-el="controls">
                    <button class="btn btn-icon btn-icon-only btn-outline-primary" data-glide-dir="<">
                      <i data-acorn-icon="chevron-left"></i>
                    </button>
                  </span>
                                        <span class="glide__bullets" data-glide-el="controls[nav]"></span>
                                        <span class="glide__arrows slider-nav" data-glide-el="controls">
                    <button class="btn btn-icon btn-icon-only btn-outline-primary" data-glide-dir=">">
                      <i data-acorn-icon="chevron-right"></i>
                    </button>
                  </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                    <!-- Center End -->

                    <!-- Single Start -->
                    <section class="scroll-section" id="single">
                        <h2 class="small-title">Single</h2>
                        <div class="row">
                            <div class="col-12 p-0 mb-5">
                                <div class="glide" id="glideSingle">
                                    <div class="glide__track" data-glide-el="track">
                                        <div class="glide__slides">
                                            <div class="glide__slide">
                                                <div class="card mb-5">
                                                    <div class="row g-0 sh-10">
                                                        <div class="col-2 col-lg-1 d-flex align-items-center h-100">
                                                            <img src="/img/product/small/product-8.webp" alt="card image" class="card-img card-img-horizontal" />
                                                        </div>
                                                        <div class="col-10 col-lg-11 h-100">
                                                            <div class="row g-0 h-100 align-content-center">
                                                                <div class="col-12 col-lg-5 d-flex align-items-center">
                                                                    <p class="heading mb-0 ps-card">Sugar Plum Gummi</p>
                                                                </div>
                                                                <div class="col-lg-3 col-5 col-sm-5 d-flex align-items-center mb-1 mb-md-0">
                                                                    <p class="mb-0 text-alternate ps-card">08.08.2021</p>
                                                                </div>
                                                                <div class="col-lg-2 col-sm-4 col-3 d-flex align-items-center mb-0">
                                                                    <span class="badge bg-tertiary text-uppercase">New</span>
                                                                </div>
                                                                <div class="col-lg-2 col-sm-3 col-4 d-flex align-items-center justify-content-end">
                                                                    <div class="pe-card">
                                                                        <button class="btn btn-sm btn-icon btn-icon-only btn-outline-primary" type="button">
                                                                            <i data-acorn-icon="check"></i>
                                                                        </button>
                                                                        <button class="btn btn-sm btn-icon btn-icon-only btn-outline-secondary ms-2" type="button">
                                                                            <i data-acorn-icon="close"></i>
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="glide__slide">
                                                <div class="card mb-5">
                                                    <div class="row g-0 sh-10">
                                                        <div class="col-2 col-lg-1 d-flex align-items-center h-100">
                                                            <img src="/img/product/small/product-8.webp" alt="card image" class="card-img card-img-horizontal" />
                                                        </div>
                                                        <div class="col-10 col-lg-11 h-100">
                                                            <div class="row g-0 h-100 align-content-center">
                                                                <div class="col-12 col-lg-5 d-flex align-items-center">
                                                                    <p class="heading mb-0 ps-card">Sugar Plum Gummi</p>
                                                                </div>
                                                                <div class="col-lg-3 col-5 col-sm-5 d-flex align-items-center mb-1 mb-md-0">
                                                                    <p class="mb-0 text-alternate ps-card">08.08.2021</p>
                                                                </div>
                                                                <div class="col-lg-2 col-sm-4 col-3 d-flex align-items-center mb-0">
                                                                    <span class="badge bg-tertiary text-uppercase">New</span>
                                                                </div>
                                                                <div class="col-lg-2 col-sm-3 col-4 d-flex align-items-center justify-content-end">
                                                                    <div class="pe-card">
                                                                        <button class="btn btn-sm btn-icon btn-icon-only btn-outline-primary" type="button">
                                                                            <i data-acorn-icon="check"></i>
                                                                        </button>
                                                                        <button class="btn btn-sm btn-icon btn-icon-only btn-outline-secondary ms-2" type="button">
                                                                            <i data-acorn-icon="close"></i>
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="glide__slide">
                                                <div class="card mb-5">
                                                    <div class="row g-0 sh-10">
                                                        <div class="col-2 col-lg-1 d-flex align-items-center h-100">
                                                            <img src="/img/product/small/product-8.webp" alt="card image" class="card-img card-img-horizontal" />
                                                        </div>
                                                        <div class="col-10 col-lg-11 h-100">
                                                            <div class="row g-0 h-100 align-content-center">
                                                                <div class="col-12 col-lg-5 d-flex align-items-center">
                                                                    <p class="heading mb-0 ps-card">Sugar Plum Gummi</p>
                                                                </div>
                                                                <div class="col-lg-3 col-5 col-sm-5 d-flex align-items-center mb-1 mb-md-0">
                                                                    <p class="mb-0 text-alternate ps-card">08.08.2021</p>
                                                                </div>
                                                                <div class="col-lg-2 col-sm-4 col-3 d-flex align-items-center mb-0">
                                                                    <span class="badge bg-tertiary text-uppercase">New</span>
                                                                </div>
                                                                <div class="col-lg-2 col-sm-3 col-4 d-flex align-items-center justify-content-end">
                                                                    <div class="pe-card">
                                                                        <button class="btn btn-sm btn-icon btn-icon-only btn-outline-primary" type="button">
                                                                            <i data-acorn-icon="check"></i>
                                                                        </button>
                                                                        <button class="btn btn-sm btn-icon btn-icon-only btn-outline-secondary ms-2" type="button">
                                                                            <i data-acorn-icon="close"></i>
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-center">
                  <span class="glide__arrows slider-nav" data-glide-el="controls">
                    <button class="btn btn-icon btn-icon-only btn-outline-primary" data-glide-dir="<">
                      <i data-acorn-icon="chevron-left"></i>
                    </button>
                  </span>
                                        <span class="glide__bullets" data-glide-el="controls[nav]"></span>
                                        <span class="glide__arrows slider-nav" data-glide-el="controls">
                    <button class="btn btn-icon btn-icon-only btn-outline-primary" data-glide-dir=">">
                      <i data-acorn-icon="chevron-right"></i>
                    </button>
                  </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                    <!-- Single End -->

                    <!-- Small Gutters Start -->
                    <section class="scroll-section" id="smallGutters">
                        <h2 class="small-title">Small Gutters</h2>
                        <div class="row gx-2">
                            <div class="col-12 col-xl-6 p-0 mb-5">
                                <div class="glide" id="glideSmall">
                                    <div class="glide__track" data-glide-el="track">
                                        <div class="glide__slides">
                                            <div class="glide__slide">
                                                <div class="card mb-5 sh-25">
                                                    <div class="card-body text-center align-items-center d-flex flex-column justify-content-between">
                                                        <div class="d-flex sw-6 sh-6 bg-gradient-light mb-3 align-items-center justify-content-center rounded-xl">
                                                            <i data-acorn-icon="pepper" class="text-white"></i>
                                                        </div>
                                                        <p class="card-text mb-0 d-flex">Pending Orders</p>
                                                        <p class="h4 text-center mb-0 d-flex text-primary">25</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="glide__slide">
                                                <div class="card mb-5 sh-25">
                                                    <div class="card-body text-center align-items-center d-flex flex-column justify-content-between">
                                                        <div class="d-flex sw-6 sh-6 bg-gradient-light mb-3 align-items-center justify-content-center rounded-xl">
                                                            <i data-acorn-icon="loaf" class="text-white"></i>
                                                        </div>
                                                        <p class="card-text mb-0 d-flex">Pending Orders</p>
                                                        <p class="h4 text-center mb-0 d-flex text-primary">25</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="glide__slide">
                                                <div class="card mb-5 sh-25">
                                                    <div class="card-body text-center align-items-center d-flex flex-column justify-content-between">
                                                        <div class="d-flex sw-6 sh-6 bg-gradient-light mb-3 align-items-center justify-content-center rounded-xl">
                                                            <i data-acorn-icon="pear" class="text-white"></i>
                                                        </div>
                                                        <p class="card-text mb-0 d-flex">Pending Orders</p>
                                                        <p class="h4 text-center mb-0 d-flex text-primary">25</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="glide__slide">
                                                <div class="card mb-5 sh-25">
                                                    <div class="card-body text-center align-items-center d-flex flex-column justify-content-between">
                                                        <div class="d-flex sw-6 sh-6 bg-gradient-light mb-3 align-items-center justify-content-center rounded-xl">
                                                            <i data-acorn-icon="loaf" class="text-white"></i>
                                                        </div>
                                                        <p class="card-text mb-0 d-flex">Pending Orders</p>
                                                        <p class="h4 text-center mb-0 d-flex text-primary">25</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="glide__slide">
                                                <div class="card mb-5 sh-25">
                                                    <div class="card-body text-center align-items-center d-flex flex-column justify-content-between">
                                                        <div class="d-flex sw-6 sh-6 bg-gradient-light mb-3 align-items-center justify-content-center rounded-xl">
                                                            <i data-acorn-icon="cupcake" class="text-white"></i>
                                                        </div>
                                                        <p class="card-text mb-0 d-flex">Pending Orders</p>
                                                        <p class="h4 text-center mb-0 d-flex text-primary">25</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="glide__slide">
                                                <div class="card mb-5 sh-25">
                                                    <div class="card-body text-center align-items-center d-flex flex-column justify-content-between">
                                                        <div class="d-flex sw-6 sh-6 bg-gradient-light mb-3 align-items-center justify-content-center rounded-xl">
                                                            <i data-acorn-icon="radish" class="text-white"></i>
                                                        </div>
                                                        <p class="card-text mb-0 d-flex">Pending Orders</p>
                                                        <p class="h4 text-center mb-0 d-flex text-primary">25</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                    <!-- Small Gutters End -->

                    <!-- Auto Play Start -->
                    <section class="scroll-section" id="autoPlay">
                        <h2 class="small-title">Auto Play</h2>
                        <div class="row gx-2">
                            <div class="col-12 col-xl-6 p-0 mb-5">
                                <div class="glide" id="glideAuto">
                                    <div class="glide__track" data-glide-el="track">
                                        <div class="glide__slides">
                                            <div class="glide__slide">
                                                <div class="card mb-5 sh-25">
                                                    <div class="card-body text-center align-items-center d-flex flex-column justify-content-between">
                                                        <div class="d-flex sw-6 sh-6 bg-gradient-light mb-3 align-items-center justify-content-center rounded-xl">
                                                            <i data-acorn-icon="pepper" class="text-white"></i>
                                                        </div>
                                                        <p class="card-text mb-0 d-flex">Pending Orders</p>
                                                        <p class="h4 text-center mb-0 d-flex text-primary">25</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="glide__slide">
                                                <div class="card mb-5 sh-25">
                                                    <div class="card-body text-center align-items-center d-flex flex-column justify-content-between">
                                                        <div class="d-flex sw-6 sh-6 bg-gradient-light mb-3 align-items-center justify-content-center rounded-xl">
                                                            <i data-acorn-icon="loaf" class="text-white"></i>
                                                        </div>
                                                        <p class="card-text mb-0 d-flex">Pending Orders</p>
                                                        <p class="h4 text-center mb-0 d-flex text-primary">25</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="glide__slide">
                                                <div class="card mb-5 sh-25">
                                                    <div class="card-body text-center align-items-center d-flex flex-column justify-content-between">
                                                        <div class="d-flex sw-6 sh-6 bg-gradient-light mb-3 align-items-center justify-content-center rounded-xl">
                                                            <i data-acorn-icon="pear" class="text-white"></i>
                                                        </div>
                                                        <p class="card-text mb-0 d-flex">Pending Orders</p>
                                                        <p class="h4 text-center mb-0 d-flex text-primary">25</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="glide__slide">
                                                <div class="card mb-5 sh-25">
                                                    <div class="card-body text-center align-items-center d-flex flex-column justify-content-between">
                                                        <div class="d-flex sw-6 sh-6 bg-gradient-light mb-3 align-items-center justify-content-center rounded-xl">
                                                            <i data-acorn-icon="loaf" class="text-white"></i>
                                                        </div>
                                                        <p class="card-text mb-0 d-flex">Pending Orders</p>
                                                        <p class="h4 text-center mb-0 d-flex text-primary">25</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="glide__slide">
                                                <div class="card mb-5 sh-25">
                                                    <div class="card-body text-center align-items-center d-flex flex-column justify-content-between">
                                                        <div class="d-flex sw-6 sh-6 bg-gradient-light mb-3 align-items-center justify-content-center rounded-xl">
                                                            <i data-acorn-icon="cupcake" class="text-white"></i>
                                                        </div>
                                                        <p class="card-text mb-0 d-flex">Pending Orders</p>
                                                        <p class="h4 text-center mb-0 d-flex text-primary">25</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="glide__slide">
                                                <div class="card mb-5 sh-25">
                                                    <div class="card-body text-center align-items-center d-flex flex-column justify-content-between">
                                                        <div class="d-flex sw-6 sh-6 bg-gradient-light mb-3 align-items-center justify-content-center rounded-xl">
                                                            <i data-acorn-icon="radish" class="text-white"></i>
                                                        </div>
                                                        <p class="card-text mb-0 d-flex">Pending Orders</p>
                                                        <p class="h4 text-center mb-0 d-flex text-primary">25</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                    <!-- Auto Play End -->

                    <!-- Flow Start -->
                    <section class="scroll-section" id="flow">
                        <h2 class="small-title">Flow</h2>
                        <div class="row gx-2">
                            <div class="col-12 p-0 mb-5">
                                <div class="glide" id="glideFlow">
                                    <div class="glide__track" data-glide-el="track">
                                        <div class="glide__slides">
                                            <div class="glide__slide">
                                                <div class="card mb-5 sh-19 d-flex">
                                                    <div class="card-body d-flex">
                                                        <img
                                                                src="/img/brand/codecanyon-light-background.webp"
                                                                class="img-fluid grayscale opacity-75 d-flex align-self-center"
                                                                alt="logo image"
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="glide__slide">
                                                <div class="card mb-5 sh-19 d-flex">
                                                    <div class="card-body d-flex">
                                                        <img
                                                                src="/img/brand/graphicriver-light-background.webp"
                                                                class="img-fluid grayscale opacity-75 d-flex align-self-center"
                                                                alt="logo image"
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="glide__slide">
                                                <div class="card mb-5 sh-19 d-flex">
                                                    <div class="card-body d-flex">
                                                        <img
                                                                src="/img/brand/photodune-light-background.webp"
                                                                class="img-fluid grayscale opacity-75 d-flex align-self-center"
                                                                alt="logo image"
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="glide__slide">
                                                <div class="card mb-5 sh-19 d-flex">
                                                    <div class="card-body d-flex">
                                                        <img
                                                                src="/img/brand/themeforest-light-background.webp"
                                                                class="img-fluid grayscale opacity-75 d-flex align-self-center"
                                                                alt="logo image"
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="glide__slide">
                                                <div class="card mb-5 sh-19 d-flex">
                                                    <div class="card-body d-flex">
                                                        <img
                                                                src="/img/brand/codecanyon-light-background.webp"
                                                                class="img-fluid grayscale opacity-75 d-flex align-self-center"
                                                                alt="logo image"
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="glide__slide">
                                                <div class="card mb-5 sh-19 d-flex">
                                                    <div class="card-body d-flex">
                                                        <img
                                                                src="/img/brand/graphicriver-light-background.webp"
                                                                class="img-fluid grayscale opacity-75 d-flex align-self-center"
                                                                alt="logo image"
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                    <!-- Flow End -->

                    <!-- Gallery Boxed Start -->
                    <section class="scroll-section" id="galleryBoxed">
                        <h2 class="small-title">Gallery Boxed</h2>
                        <div class="row mb-5">
                            <div class="col-12">
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <div class="glide" id="glideGalleryBoxed">
                                            <!-- Large Images Start -->
                                            <div class="glide glide-large">
                                                <div class="glide__track" data-glide-el="track">
                                                    <ul class="glide__slides gallery-glide-custom">
                                                        <li class="glide__slide p-0">
                                                            <a href="/img/product/large/product-1.webp">
                                                                <img
                                                                        alt="detail"
                                                                        src="/img/product/large/product-1.webp"
                                                                        class="responsive border-0 rounded-md img-fluid mb-3 sh-50 w-100"
                                                                />
                                                            </a>
                                                        </li>
                                                        <li class="glide__slide p-0">
                                                            <a href="/img/product/large/product-2.webp">
                                                                <img
                                                                        alt="detail"
                                                                        src="/img/product/large/product-2.webp"
                                                                        class="responsive border-0 rounded-md img-fluid mb-3 sh-50 w-100"
                                                                />
                                                            </a>
                                                        </li>
                                                        <li class="glide__slide p-0">
                                                            <a href="/img/product/large/product-3.webp">
                                                                <img
                                                                        alt="detail"
                                                                        src="/img/product/large/product-3.webp"
                                                                        class="responsive border-0 rounded-md img-fluid mb-3 sh-50 w-100"
                                                                />
                                                            </a>
                                                        </li>
                                                        <li class="glide__slide p-0">
                                                            <a href="/img/product/large/product-4.webp">
                                                                <img
                                                                        alt="detail"
                                                                        src="/img/product/large/product-4.webp"
                                                                        class="responsive border-0 rounded-md img-fluid mb-3 sh-50 w-100"
                                                                />
                                                            </a>
                                                        </li>
                                                        <li class="glide__slide p-0">
                                                            <a href="/img/product/large/product-5.webp">
                                                                <img
                                                                        alt="detail"
                                                                        src="/img/product/large/product-5.webp"
                                                                        class="responsive border-0 rounded-md img-fluid mb-3 sh-50 w-100"
                                                                />
                                                            </a>
                                                        </li>
                                                        <li class="glide__slide p-0">
                                                            <a href="/img/product/large/product-6.webp">
                                                                <img
                                                                        alt="detail"
                                                                        src="/img/product/large/product-6.webp"
                                                                        class="responsive border-0 rounded-md img-fluid mb-3 sh-50 w-100"
                                                                />
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                            <!-- Large Images End -->

                                            <!-- Thumbs Start -->
                                            <div class="glide glide-thumb">
                                                <div class="glide__track" data-glide-el="track">
                                                    <ul class="glide__slides">
                                                        <li class="glide__slide p-0">
                                                            <img alt="thumb" src="/img/product/small/product-1.webp" class="responsive rounded-md img-fluid" />
                                                        </li>
                                                        <li class="glide__slide p-0">
                                                            <img alt="thumb" src="/img/product/small/product-2.webp" class="responsive rounded-md img-fluid" />
                                                        </li>
                                                        <li class="glide__slide p-0">
                                                            <img alt="thumb" src="/img/product/small/product-3.webp" class="responsive rounded-md img-fluid" />
                                                        </li>
                                                        <li class="glide__slide p-0">
                                                            <img alt="thumb" src="/img/product/small/product-4.webp" class="responsive rounded-md img-fluid" />
                                                        </li>
                                                        <li class="glide__slide p-0">
                                                            <img alt="thumb" src="/img/product/small/product-5.webp" class="responsive rounded-md img-fluid" />
                                                        </li>
                                                        <li class="glide__slide p-0">
                                                            <img alt="thumb" src="/img/product/small/product-6.webp" class="responsive rounded-md img-fluid" />
                                                        </li>
                                                    </ul>
                                                </div>
                                                <div class="glide__arrows" data-glide-el="controls">
                                                    <button class="btn btn-icon btn-icon-only btn-foreground hover-outline left-arrow" data-glide-dir="<">
                                                        <i data-acorn-icon="chevron-left"></i>
                                                    </button>
                                                    <button class="btn btn-icon btn-icon-only btn-foreground hover-outline right-arrow" data-glide-dir=">">
                                                        <i data-acorn-icon="chevron-right"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <!-- Thumbs End -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                    <!-- Gallery Boxed End -->

                    <!-- Gallery Half Boxed Start -->
                    <section class="scroll-section" id="galleryHalfBoxed">
                        <h2 class="small-title">Gallery Half Boxed</h2>
                        <div class="row mb-5">
                            <div class="col-12">
                                <div class="card mb-4">
                                    <div class="card-body p-0">
                                        <div class="glide" id="glideGalleryHalfBoxed">
                                            <!-- Large Images Start -->
                                            <div class="glide glide-large">
                                                <div class="glide__track" data-glide-el="track">
                                                    <ul class="glide__slides gallery-glide-custom">
                                                        <li class="glide__slide p-0">
                                                            <a href="/img/product/large/product-1.webp">
                                                                <img
                                                                        alt="detail"
                                                                        src="/img/product/large/product-1.webp"
                                                                        class="responsive border-0 rounded-top-end rounded-top-start img-fluid mb-3 sh-50 w-100"
                                                                />
                                                            </a>
                                                        </li>
                                                        <li class="glide__slide p-0">
                                                            <a href="/img/product/large/product-2.webp">
                                                                <img
                                                                        alt="detail"
                                                                        src="/img/product/large/product-2.webp"
                                                                        class="responsive border-0 rounded-top-end rounded-top-start img-fluid mb-3 sh-50 w-100"
                                                                />
                                                            </a>
                                                        </li>
                                                        <li class="glide__slide p-0">
                                                            <a href="/img/product/large/product-3.webp">
                                                                <img
                                                                        alt="detail"
                                                                        src="/img/product/large/product-3.webp"
                                                                        class="responsive border-0 rounded-top-end rounded-top-start img-fluid mb-3 sh-50 w-100"
                                                                />
                                                            </a>
                                                        </li>
                                                        <li class="glide__slide p-0">
                                                            <a href="/img/product/large/product-4.webp">
                                                                <img
                                                                        alt="detail"
                                                                        src="/img/product/large/product-4.webp"
                                                                        class="responsive border-0 rounded-top-end rounded-top-start img-fluid mb-3 sh-50 w-100"
                                                                />
                                                            </a>
                                                        </li>
                                                        <li class="glide__slide p-0">
                                                            <a href="/img/product/large/product-5.webp">
                                                                <img
                                                                        alt="detail"
                                                                        src="/img/product/large/product-5.webp"
                                                                        class="responsive border-0 rounded-top-end rounded-top-start img-fluid mb-3 sh-50 w-100"
                                                                />
                                                            </a>
                                                        </li>
                                                        <li class="glide__slide p-0">
                                                            <a href="/img/product/large/product-6.webp">
                                                                <img
                                                                        alt="detail"
                                                                        src="/img/product/large/product-6.webp"
                                                                        class="responsive border-0 rounded-top-end rounded-top-start img-fluid mb-3 sh-50 w-100"
                                                                />
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                            <!-- Large Images End -->

                                            <!-- Thumbs Start -->
                                            <div class="glide glide-thumb mb-3">
                                                <div class="glide__track" data-glide-el="track">
                                                    <ul class="glide__slides">
                                                        <li class="glide__slide p-0">
                                                            <img alt="thumb" src="/img/product/small/product-1.webp" class="responsive rounded-md img-fluid" />
                                                        </li>
                                                        <li class="glide__slide p-0">
                                                            <img alt="thumb" src="/img/product/small/product-2.webp" class="responsive rounded-md img-fluid" />
                                                        </li>
                                                        <li class="glide__slide p-0">
                                                            <img alt="thumb" src="/img/product/small/product-3.webp" class="responsive rounded-md img-fluid" />
                                                        </li>
                                                        <li class="glide__slide p-0">
                                                            <img alt="thumb" src="/img/product/small/product-4.webp" class="responsive rounded-md img-fluid" />
                                                        </li>
                                                        <li class="glide__slide p-0">
                                                            <img alt="thumb" src="/img/product/small/product-5.webp" class="responsive rounded-md img-fluid" />
                                                        </li>
                                                        <li class="glide__slide p-0">
                                                            <img alt="thumb" src="/img/product/small/product-6.webp" class="responsive rounded-md img-fluid" />
                                                        </li>
                                                    </ul>
                                                </div>
                                                <div class="glide__arrows" data-glide-el="controls">
                                                    <button class="btn btn-icon btn-icon-only btn-foreground hover-outline left-arrow" data-glide-dir="<">
                                                        <i data-acorn-icon="chevron-left"></i>
                                                    </button>
                                                    <button class="btn btn-icon btn-icon-only btn-foreground hover-outline right-arrow" data-glide-dir=">">
                                                        <i data-acorn-icon="chevron-right"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <!-- Thumbs End -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                    <!-- Gallery Half Boxed End -->

                    <!-- Gallery Start -->
                    <section class="scroll-section" id="gallery">
                        <h2 class="small-title">Gallery</h2>
                        <div class="row">
                            <div class="col-12">
                                <div class="glide" id="glideGallery">
                                    <!-- Large Images Start -->
                                    <div class="glide glide-large shadow rounded mb-4">
                                        <div class="glide__track mb-0" data-glide-el="track">
                                            <ul class="glide__slides gallery-glide-custom mb-0">
                                                <li class="glide__slide p-0">
                                                    <a href="/img/product/large/product-1.webp">
                                                        <img alt="detail" src="/img/product/large/product-1.webp" class="responsive border-0 rounded img-fluid sh-50 w-100" />
                                                    </a>
                                                </li>
                                                <li class="glide__slide p-0">
                                                    <a href="/img/product/large/product-2.webp">
                                                        <img alt="detail" src="/img/product/large/product-2.webp" class="responsive border-0 rounded img-fluid sh-50 w-100" />
                                                    </a>
                                                </li>
                                                <li class="glide__slide p-0">
                                                    <a href="/img/product/large/product-3.webp">
                                                        <img alt="detail" src="/img/product/large/product-3.webp" class="responsive border-0 rounded img-fluid sh-50 w-100" />
                                                    </a>
                                                </li>
                                                <li class="glide__slide p-0">
                                                    <a href="/img/product/large/product-4.webp">
                                                        <img alt="detail" src="/img/product/large/product-4.webp" class="responsive border-0 rounded img-fluid sh-50 w-100" />
                                                    </a>
                                                </li>
                                                <li class="glide__slide p-0">
                                                    <a href="/img/product/large/product-5.webp">
                                                        <img alt="detail" src="/img/product/large/product-5.webp" class="responsive border-0 rounded img-fluid sh-50 w-100" />
                                                    </a>
                                                </li>
                                                <li class="glide__slide p-0">
                                                    <a href="/img/product/large/product-6.webp">
                                                        <img alt="detail" src="/img/product/large/product-6.webp" class="responsive border-0 rounded img-fluid sh-50 w-100" />
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    <!-- Large Images End -->
                                    <!-- Thumbs Start -->
                                    <div class="glide glide-thumb mb-3">
                                        <div class="glide__track" data-glide-el="track">
                                            <ul class="glide__slides">
                                                <li class="glide__slide p-0">
                                                    <img alt="thumb" src="/img/product/small/product-1.webp" class="responsive rounded-md img-fluid shadow" />
                                                </li>
                                                <li class="glide__slide p-0">
                                                    <img alt="thumb" src="/img/product/small/product-2.webp" class="responsive rounded-md img-fluid shadow" />
                                                </li>
                                                <li class="glide__slide p-0">
                                                    <img alt="thumb" src="/img/product/small/product-3.webp" class="responsive rounded-md img-fluid shadow" />
                                                </li>
                                                <li class="glide__slide p-0">
                                                    <img alt="thumb" src="/img/product/small/product-4.webp" class="responsive rounded-md img-fluid shadow" />
                                                </li>
                                                <li class="glide__slide p-0">
                                                    <img alt="thumb" src="/img/product/small/product-5.webp" class="responsive rounded-md img-fluid shadow" />
                                                </li>
                                                <li class="glide__slide p-0">
                                                    <img alt="thumb" src="/img/product/small/product-6.webp" class="responsive rounded-md img-fluid shadow" />
                                                </li>
                                            </ul>
                                        </div>
                                        <div class="glide__arrows" data-glide-el="controls">
                                            <button class="btn btn-icon btn-icon-only btn-foreground-alternate shadow left-arrow" data-glide-dir="<">
                                                <i data-acorn-icon="chevron-left"></i>
                                            </button>
                                            <button class="btn btn-icon btn-icon-only btn-foreground-alternate shadow right-arrow" data-glide-dir=">">
                                                <i data-acorn-icon="chevron-right"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <!-- Thumbs End -->
                                </div>
                            </div>
                        </div>
                    </section>
                    <!-- Gallery End -->
                </div>
                <!-- Content End -->
            </div>

            <!-- Scrollspy Start -->
            <div class="col-md-auto d-none d-lg-block" id="scrollSpyMenu">
                <ul class="nav flex-column">
                    <li>
                        <a class="nav-link" href="#title">
                            <i data-acorn-icon="chevron-right"></i>
                            <span>Title</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link" href="#basic">
                            <i data-acorn-icon="chevron-right"></i>
                            <span>Basic</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link" href="#noControls">
                            <i data-acorn-icon="chevron-right"></i>
                            <span>No Controls</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link" href="#center">
                            <i data-acorn-icon="chevron-right"></i>
                            <span>Center</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link" href="#single">
                            <i data-acorn-icon="chevron-right"></i>
                            <span>Single</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link" href="#smallGutters">
                            <i data-acorn-icon="chevron-right"></i>
                            <span>Small Gutters</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link" href="#autoPlay">
                            <i data-acorn-icon="chevron-right"></i>
                            <span>Auto Play</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link" href="#flow">
                            <i data-acorn-icon="chevron-right"></i>
                            <span>Flow</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link" href="#galleryBoxed">
                            <i data-acorn-icon="chevron-right"></i>
                            <span>Gallery Boxed</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link" href="#galleryHalfBoxed">
                            <i data-acorn-icon="chevron-right"></i>
                            <span>Gallery Half Boxed</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link" href="#gallery">
                            <i data-acorn-icon="chevron-right"></i>
                            <span>Gallery</span>
                        </a>
                    </li>
                </ul>
            </div>
            <!-- Scrollspy End -->
        </div>
    </div>
@endsection
