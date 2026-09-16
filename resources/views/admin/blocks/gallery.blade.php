@php
    $html_tag_data = ["scrollspy"=>"true"];
    $title = 'Gallery';
    $description = 'Mobile and desktop gallery elements that designed in various shapes for different layouts.';
    $breadcrumbs = ["/"=>"Home","/Blocks"=>"Blocks"]
@endphp
@extends('layout',['html_tag_data'=>$html_tag_data, 'title'=>$title, 'description'=>$description])

@section('css')
    <link rel="stylesheet" href="/css/vendor/glide.core.min.css"/>
    <link rel="stylesheet" href="/css/vendor/baguetteBox.min.css"/>
@endsection

@section('js_vendor')
    <script src="/js/cs/scrollspy.js"></script>
    <script src="/js/vendor/baguetteBox.min.js"></script>
@endsection

@section('js_page')
    <script src="/js/pages/blocks.gallery.js"></script>
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

                    <!-- Small Gutters Start -->
                    <section class="scroll-section" id="smallGutters">
                        <h2 class="small-title">Small Gutters</h2>
                        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 gallery g-2 mb-5">
                            <div class="col">
                                <a href="/img/product/large/product-1.webp" class="card hover-img-scale-up">
                                    <img class="card-img sh-25 scale" src="/img/product/small/product-1.webp" alt="card image" />
                                </a>
                            </div>
                            <div class="col">
                                <a href="/img/product/large/product-1.webp" class="card hover-img-scale-up">
                                    <img class="card-img sh-25 scale" src="/img/product/small/product-1.webp" alt="card image" />
                                </a>
                            </div>
                            <div class="col">
                                <a href="/img/product/large/product-1.webp" class="card hover-img-scale-up">
                                    <img class="card-img sh-25 scale" src="/img/product/small/product-1.webp" alt="card image" />
                                </a>
                            </div>
                            <div class="col">
                                <a href="/img/product/large/product-1.webp" class="card hover-img-scale-up">
                                    <img class="card-img sh-25 scale" src="/img/product/small/product-1.webp" alt="card image" />
                                </a>
                            </div>
                            <div class="col">
                                <a href="/img/product/large/product-1.webp" class="card hover-img-scale-up">
                                    <img class="card-img sh-25 scale" src="/img/product/small/product-1.webp" alt="card image" />
                                </a>
                            </div>
                            <div class="col">
                                <a href="/img/product/large/product-1.webp" class="card hover-img-scale-up">
                                    <img class="card-img sh-25 scale" src="/img/product/small/product-1.webp" alt="card image" />
                                </a>
                            </div>
                            <div class="col">
                                <a href="/img/product/large/product-1.webp" class="card hover-img-scale-up">
                                    <img class="card-img sh-25 scale" src="/img/product/small/product-1.webp" alt="card image" />
                                </a>
                            </div>
                            <div class="col">
                                <a href="/img/product/large/product-1.webp" class="card hover-img-scale-up">
                                    <img class="card-img sh-25 scale" src="/img/product/small/product-1.webp" alt="card image" />
                                </a>
                            </div>
                        </div>
                    </section>
                    <!-- Small Gutters End -->

                    <!-- Standard Gutters Start -->
                    <section class="scroll-section" id="standardGutters">
                        <h2 class="small-title">Standard Gutters</h2>
                        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 gallery g-4 mb-5">
                            <div class="col">
                                <a href="/img/product/large/product-2.webp" class="card hover-img-scale-up">
                                    <img class="card-img sh-25 scale" src="/img/product/small/product-2.webp" alt="card image" />
                                </a>
                            </div>
                            <div class="col">
                                <a href="/img/product/large/product-2.webp" class="card hover-img-scale-up">
                                    <img class="card-img sh-25 scale" src="/img/product/small/product-2.webp" alt="card image" />
                                </a>
                            </div>
                            <div class="col">
                                <a href="/img/product/large/product-2.webp" class="card hover-img-scale-up">
                                    <img class="card-img sh-25 scale" src="/img/product/small/product-2.webp" alt="card image" />
                                </a>
                            </div>
                            <div class="col">
                                <a href="/img/product/large/product-2.webp" class="card hover-img-scale-up">
                                    <img class="card-img sh-25 scale" src="/img/product/small/product-2.webp" alt="card image" />
                                </a>
                            </div>
                            <div class="col">
                                <a href="/img/product/large/product-2.webp" class="card hover-img-scale-up">
                                    <img class="card-img sh-25 scale" src="/img/product/small/product-2.webp" alt="card image" />
                                </a>
                            </div>
                            <div class="col">
                                <a href="/img/product/large/product-2.webp" class="card hover-img-scale-up">
                                    <img class="card-img sh-25 scale" src="/img/product/small/product-2.webp" alt="card image" />
                                </a>
                            </div>
                            <div class="col">
                                <a href="/img/product/large/product-2.webp" class="card hover-img-scale-up">
                                    <img class="card-img sh-25 scale" src="/img/product/small/product-2.webp" alt="card image" />
                                </a>
                            </div>
                            <div class="col">
                                <a href="/img/product/large/product-2.webp" class="card hover-img-scale-up">
                                    <img class="card-img sh-25 scale" src="/img/product/small/product-2.webp" alt="card image" />
                                </a>
                            </div>
                        </div>
                    </section>
                    <!-- Standard Gutters End -->

                    <!-- Small Columns Start -->
                    <section class="scroll-section" id="smallColumns">
                        <h2 class="small-title">Small Columns</h2>
                        <div class="row">
                            <div class="col-6 col-xl-6 mb-5">
                                <div class="row row-cols-1 row-cols-xl-3 gallery g-2">
                                    <div class="col">
                                        <a href="/img/product/large/product-3.webp" class="card hover-img-scale-down">
                                            <img class="card-img sh-15 sh-sm-25 scale" src="/img/product/small/product-3.webp" alt="card image" />
                                        </a>
                                    </div>
                                    <div class="col">
                                        <a href="/img/product/large/product-3.webp" class="card hover-img-scale-down">
                                            <img class="card-img sh-15 sh-sm-25 scale" src="/img/product/small/product-3.webp" alt="card image" />
                                        </a>
                                    </div>
                                    <div class="col">
                                        <a href="/img/product/large/product-3.webp" class="card hover-img-scale-down">
                                            <img class="card-img sh-15 sh-sm-25 scale" src="/img/product/small/product-3.webp" alt="card image" />
                                        </a>
                                    </div>
                                    <div class="col">
                                        <a href="/img/product/large/product-3.webp" class="card hover-img-scale-down">
                                            <img class="card-img sh-15 sh-sm-25 scale" src="/img/product/small/product-3.webp" alt="card image" />
                                        </a>
                                    </div>
                                    <div class="col">
                                        <a href="/img/product/large/product-3.webp" class="card hover-img-scale-down">
                                            <img class="card-img sh-15 sh-sm-25 scale" src="/img/product/small/product-3.webp" alt="card image" />
                                        </a>
                                    </div>
                                    <div class="col">
                                        <a href="/img/product/large/product-3.webp" class="card hover-img-scale-down">
                                            <img class="card-img sh-15 sh-sm-25 scale" src="/img/product/small/product-3.webp" alt="card image" />
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="col-6 col-xl-6 mb-5">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="row row-cols-1 row-cols-xl-2 gallery g-2">
                                            <div class="col">
                                                <a href="/img/product/large/product-4.webp" class="card hover-img-scale-down">
                                                    <img class="card-img sh-15 sh-sm-14 scale" src="/img/product/small/product-4.webp" alt="card image" />
                                                </a>
                                            </div>
                                            <div class="col">
                                                <a href="/img/product/large/product-4.webp" class="card hover-img-scale-down">
                                                    <img class="card-img sh-15 sh-sm-14 scale" src="/img/product/small/product-4.webp" alt="card image" />
                                                </a>
                                            </div>
                                            <div class="col">
                                                <a href="/img/product/large/product-4.webp" class="card hover-img-scale-down">
                                                    <img class="card-img sh-15 sh-sm-14 scale" src="/img/product/small/product-4.webp" alt="card image" />
                                                </a>
                                            </div>
                                            <div class="col">
                                                <a href="/img/product/large/product-4.webp" class="card hover-img-scale-down">
                                                    <img class="card-img sh-15 sh-sm-14 scale" src="/img/product/small/product-4.webp" alt="card image" />
                                                </a>
                                            </div>
                                            <div class="col">
                                                <a href="/img/product/large/product-4.webp" class="card hover-img-scale-down">
                                                    <img class="card-img sh-15 sh-sm-14 scale" src="/img/product/small/product-4.webp" alt="card image" />
                                                </a>
                                            </div>
                                            <div class="col">
                                                <a href="/img/product/large/product-4.webp" class="card hover-img-scale-down">
                                                    <img class="card-img sh-15 sh-sm-14 scale" src="/img/product/small/product-4.webp" alt="card image" />
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                    <!-- Small Columns End -->

                    <!-- Groups Start -->
                    <!-- Note: Images should be provided via background-image so we have kept them inline. -->
                    <section class="scroll-section" id="groups">
                        <h2 class="small-title">Groups</h2>
                        <div class="row">
                            <div class="col-12 col-sm-6 col-lg-6 col-xxl-6 mb-5">
                                <div class="card">
                                    <div class="card-body sh-35">
                                        <div class="row g-2 h-100 gallery">
                                            <div class="col h-100">
                                                <a
                                                        href="/img/product/large/product-1.webp"
                                                        class="w-100 h-100 rounded-md bg-cover-center d-block"
                                                        style="background-image: url('/img/product/small/product-1.webp')"
                                                ></a>
                                            </div>
                                            <div class="col d-flex flex-column justify-content-stretch h-100">
                                                <div class="d-flex mb-2 flex-grow-1">
                                                    <a
                                                            href="/img/product/large/product-1.webp"
                                                            class="w-100 h-100 rounded-md bg-cover-center d-block"
                                                            style="background-image: url('/img/product/small/product-1.webp')"
                                                    ></a>
                                                </div>
                                                <div class="d-flex flex-grow-1">
                                                    <a
                                                            href="/img/product/large/product-1.webp"
                                                            class="w-100 h-100 rounded-md bg-cover-center d-block"
                                                            style="background-image: url('/img/product/small/product-1.webp')"
                                                    ></a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-lg-6 col-xxl-6 mb-5">
                                <div class="card">
                                    <div class="card-body sh-35">
                                        <div class="row g-2 h-100 gallery">
                                            <div class="col h-100">
                                                <a
                                                        href="/img/product/large/product-2.webp"
                                                        class="w-100 h-100 rounded-md bg-cover-center d-block"
                                                        style="background-image: url('/img/product/small/product-2.webp')"
                                                ></a>
                                            </div>
                                            <div class="col h-100">
                                                <a
                                                        href="/img/product/large/product-2.webp"
                                                        class="w-100 h-100 rounded-md bg-cover-center d-block"
                                                        style="background-image: url('/img/product/small/product-2.webp')"
                                                ></a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-lg-6 col-xxl-6 mb-5">
                                <div class="card">
                                    <div class="card-body sh-35">
                                        <div class="row g-2 h-100 gallery">
                                            <div class="col d-flex flex-column justify-content-stretch h-100">
                                                <div class="d-flex mb-2 flex-grow-1">
                                                    <a
                                                            href="/img/product/large/product-3.webp"
                                                            class="w-100 h-100 rounded-md bg-cover-center d-block"
                                                            style="background-image: url('/img/product/small/product-3.webp')"
                                                    ></a>
                                                </div>
                                                <div class="d-flex flex-grow-1">
                                                    <a
                                                            href="/img/product/large/product-3.webp"
                                                            class="w-100 h-100 rounded-md bg-cover-center d-block"
                                                            style="background-image: url('/img/product/small/product-3.webp')"
                                                    ></a>
                                                </div>
                                            </div>
                                            <div class="col d-flex flex-column justify-content-stretch h-100">
                                                <div class="d-flex mb-2 flex-grow-1">
                                                    <a
                                                            href="/img/product/large/product-3.webp"
                                                            class="w-100 h-100 rounded-md bg-cover-center d-block"
                                                            style="background-image: url('/img/product/small/product-3.webp')"
                                                    ></a>
                                                </div>
                                                <div class="d-flex flex-grow-1">
                                                    <a
                                                            href="/img/product/large/product-3.webp"
                                                            class="w-100 h-100 rounded-md bg-cover-center d-block"
                                                            style="background-image: url('/img/product/small/product-3.webp')"
                                                    ></a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-lg-6 col-xxl-6 mb-5">
                                <div class="card">
                                    <div class="card-body sh-35">
                                        <div class="row g-2 h-100">
                                            <div class="col d-flex flex-column justify-content-stretch h-100 gallery">
                                                <div class="d-flex mb-2 flex-grow-1">
                                                    <a
                                                            href="/img/product/large/product-4.webp"
                                                            class="w-100 h-100 rounded-md bg-cover-center d-block"
                                                            style="background-image: url('/img/product/small/product-4.webp')"
                                                    ></a>
                                                </div>
                                                <div class="d-flex flex-grow-1">
                                                    <a
                                                            href="/img/product/large/product-4.webp"
                                                            class="w-100 h-100 rounded-md bg-cover-center d-block"
                                                            style="background-image: url('/img/product/small/product-4.webp')"
                                                    ></a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-lg-6 col-xxl-6 mb-5">
                                <div class="card">
                                    <div class="card-body sh-35 d-flex flex-column gallery">
                                        <div class="d-flex mb-2 flex-grow-1 h-100">
                                            <a
                                                    href="/img/product/large/product-5.webp"
                                                    class="w-100 h-100 rounded-md bg-cover-center d-block"
                                                    style="background-image: url('/img/product/small/product-5.webp')"
                                            ></a>
                                        </div>
                                        <div class="row g-2 h-100">
                                            <div class="col d-flex flex-column justify-content-stretch h-100">
                                                <div class="d-flex flex-grow-1">
                                                    <a
                                                            href="/img/product/large/product-5.webp"
                                                            class="w-100 h-100 rounded-md bg-cover-center d-block"
                                                            style="background-image: url('/img/product/small/product-5.webp')"
                                                    ></a>
                                                </div>
                                            </div>
                                            <div class="col d-flex flex-column justify-content-stretch h-100">
                                                <div class="d-flex flex-grow-1">
                                                    <a
                                                            href="/img/product/large/product-5.webp"
                                                            class="w-100 h-100 rounded-md bg-cover-center d-block"
                                                            style="background-image: url('/img/product/small/product-5.webp')"
                                                    ></a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-lg-6 col-xxl-6 mb-5">
                                <div class="card">
                                    <div class="card-body sh-35">
                                        <div class="row g-1 h-100 gallery">
                                            <div class="col h-100">
                                                <a
                                                        href="/img/product/large/product-6.webp"
                                                        class="w-100 h-100 rounded-md-start bg-cover-center d-block"
                                                        style="background-image: url('/img/product/small/product-6.webp')"
                                                ></a>
                                            </div>
                                            <div class="col d-flex flex-column justify-content-stretch h-100">
                                                <div class="d-flex mb-1 flex-grow-1">
                                                    <a
                                                            href="/img/product/large/product-6.webp"
                                                            class="w-100 h-100 rounded-md-top-end bg-cover-center d-block"
                                                            style="background-image: url('/img/product/small/product-6.webp')"
                                                    ></a>
                                                </div>
                                                <div class="d-flex flex-grow-1">
                                                    <a
                                                            href="/img/product/large/product-6.webp"
                                                            class="w-100 h-100 rounded-md-bottom-end bg-cover-center d-block"
                                                            style="background-image: url('/img/product/small/product-6.webp')"
                                                    ></a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-lg-6 col-xxl-6 mb-5">
                                <div class="card">
                                    <div class="card-body sh-35">
                                        <div class="row g-1 h-100 gallery">
                                            <div class="col h-100">
                                                <a
                                                        href="/img/product/large/product-1.webp"
                                                        class="w-100 h-100 rounded-md-start bg-cover-center d-block"
                                                        style="background-image: url('/img/product/small/product-1.webp')"
                                                ></a>
                                            </div>
                                            <div class="col h h-100">
                                                <a
                                                        href="/img/product/large/product-1.webp"
                                                        class="w-100 h-100 rounded-md-end bg-cover-center d-block"
                                                        style="background-image: url('/img/product/small/product-1.webp')"
                                                ></a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-lg-6 col-xxl-6 mb-5">
                                <div class="card">
                                    <div class="card-body sh-35">
                                        <div class="row g-1 h-100 gallery">
                                            <div class="col d-flex flex-column justify-content-stretch h-100">
                                                <div class="d-flex mb-1 flex-grow-1">
                                                    <a
                                                            href="/img/product/large/product-2.webp"
                                                            class="w-100 h-100 rounded-md-top-start bg-cover-center d-block"
                                                            style="background-image: url('/img/product/small/product-2.webp')"
                                                    ></a>
                                                </div>
                                                <div class="d-flex flex-grow-1">
                                                    <a
                                                            href="/img/product/large/product-2.webp"
                                                            class="w-100 h-100 rounded-md-bottom-start bg-cover-center d-block"
                                                            style="background-image: url('/img/product/small/product-2.webp')"
                                                    ></a>
                                                </div>
                                            </div>
                                            <div class="col d-flex flex-column justify-content-stretch h-100">
                                                <div class="d-flex mb-1 flex-grow-1">
                                                    <a
                                                            href="/img/product/large/product-2.webp"
                                                            class="w-100 h-100 rounded-md-top-end bg-cover-center d-block"
                                                            style="background-image: url('/img/product/small/product-2.webp')"
                                                    ></a>
                                                </div>
                                                <div class="d-flex flex-grow-1">
                                                    <a
                                                            href="/img/product/large/product-2.webp"
                                                            class="w-100 h-100 rounded-md-bottom-end bg-cover-center d-block"
                                                            style="background-image: url('/img/product/small/product-2.webp')"
                                                    ></a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-lg-6 col-xxl-6 mb-5">
                                <div class="card">
                                    <div class="card-body sh-35">
                                        <div class="row g-1 h-100">
                                            <div class="col d-flex flex-column justify-content-stretch h-100 gallery">
                                                <div class="d-flex mb-1 flex-grow-1">
                                                    <a
                                                            href="/img/product/large/product-3.webp"
                                                            class="w-100 h-100 rounded-md-top bg-cover-center d-block"
                                                            style="background-image: url('/img/product/small/product-3.webp')"
                                                    ></a>
                                                </div>
                                                <div class="d-flex flex-grow-1">
                                                    <a
                                                            href="/img/product/large/product-3.webp"
                                                            class="w-100 h-100 rounded-md-bottom bg-cover-center d-block"
                                                            style="background-image: url('/img/product/small/product-3.webp')"
                                                    ></a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-lg-6 col-xxl-6 mb-5">
                                <div class="card">
                                    <div class="card-body sh-35 d-flex flex-column gallery">
                                        <div class="d-flex mb-1 flex-grow-1 h-100">
                                            <a
                                                    href="/img/product/large/product-4.webp"
                                                    class="w-100 h-100 rounded-md-top bg-cover-center d-block"
                                                    style="background-image: url('/img/product/small/product-4.webp')"
                                            ></a>
                                        </div>
                                        <div class="row g-1 h-100">
                                            <div class="col d-flex flex-column justify-content-stretch h-100">
                                                <div class="d-flex flex-grow-1">
                                                    <a
                                                            href="/img/product/large/product-4.webp"
                                                            class="w-100 h-100 rounded-md-bottom-start bg-cover-center d-block"
                                                            style="background-image: url('/img/product/small/product-4.webp')"
                                                    ></a>
                                                </div>
                                            </div>
                                            <div class="col d-flex flex-column justify-content-stretch h-100">
                                                <div class="d-flex flex-grow-1">
                                                    <a
                                                            href="/img/product/large/product-4.webp"
                                                            class="w-100 h-100 rounded-md-bottom-end bg-cover-center d-block"
                                                            style="background-image: url('/img/product/small/product-4.webp')"
                                                    ></a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                    <!-- Groups End -->
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
                        <a class="nav-link" href="#smallGutters">
                            <i data-acorn-icon="chevron-right"></i>
                            <span>Small Gutters</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link" href="#standardGutters">
                            <i data-acorn-icon="chevron-right"></i>
                            <span>Standard Gutters</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link" href="#smallColumns">
                            <i data-acorn-icon="chevron-right"></i>
                            <span>Small Columns</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link" href="#groups">
                            <i data-acorn-icon="chevron-right"></i>
                            <span>Groups</span>
                        </a>
                    </li>
                </ul>
            </div>
            <!-- Scrollspy End -->
        </div>
    </div>
@endsection
