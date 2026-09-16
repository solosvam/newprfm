@php
    $html_tag_data = ["scrollspy"=>"true"];
    $title = 'Lightbox';
    $description= 'Simple and easy to use lightbox script written in pure JavaScript.';
    $breadcrumbs = ["/"=>"Home","/Interface"=>"Interface","/Interface/Plugins"=>"Plugins"]
@endphp
@extends('layout',['html_tag_data'=>$html_tag_data, 'title'=>$title, 'description'=>$description])

@section('css')
    <link rel="stylesheet" href="/css/vendor/baguetteBox.min.css"/>
@endsection

@section('js_vendor')
    <script src="/js/cs/scrollspy.js"></script>
    <script src="/js/vendor/baguetteBox.min.js"></script>
@endsection

@section('js_page')
    <script src="/js/plugins/lightbox.js"></script>
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

                    <!-- Gallery Start -->
                    <section class="scroll-section" id="gallery">
                        <h2 class="small-title">Gallery</h2>
                        <div class="card mb-5">
                            <div class="card-body">
                                <div class="row gallery g-2">
                                    <div class="col-6 col-sm-4 col-lg-3 col-xl-2">
                                        <a href="/img/product/large/product-1.webp">
                                            <img alt="lightbox" src="/img/product/small/product-1.webp" class="card-img" />
                                        </a>
                                    </div>
                                    <div class="col-6 col-sm-4 col-lg-3 col-xl-2">
                                        <a href="/img/product/large/product-2.webp">
                                            <img alt="lightbox" src="/img/product/small/product-2.webp" class="card-img" />
                                        </a>
                                    </div>
                                    <div class="col-6 col-sm-4 col-lg-3 col-xl-2">
                                        <a href="/img/product/large/product-3.webp">
                                            <img alt="lightbox" src="/img/product/small/product-3.webp" class="card-img" />
                                        </a>
                                    </div>
                                    <div class="col-6 col-sm-4 col-lg-3 col-xl-2">
                                        <a href="/img/product/large/product-4.webp">
                                            <img alt="lightbox" src="/img/product/small/product-4.webp" class="card-img" />
                                        </a>
                                    </div>
                                    <div class="col-6 col-sm-4 col-lg-3 col-xl-2">
                                        <a href="/img/product/large/product-5.webp">
                                            <img alt="lightbox" src="/img/product/small/product-5.webp" class="card-img" />
                                        </a>
                                    </div>
                                    <div class="col-6 col-sm-4 col-lg-3 col-xl-2">
                                        <a href="/img/product/large/product-6.webp">
                                            <img alt="lightbox" src="/img/product/small/product-6.webp" class="card-img" />
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                    <!-- Gallery End -->

                    <!-- Fade In Start -->
                    <section class="scroll-section" id="fadeIn">
                        <h2 class="small-title">Fade In</h2>
                        <div class="card mb-5">
                            <div class="card-body">
                                <div class="row gallery-fadeIn g-2">
                                    <div class="col-6 col-sm-4 col-lg-3 col-xl-2">
                                        <a href="/img/product/large/product-1.webp">
                                            <img alt="lightbox" src="/img/product/small/product-1.webp" class="card-img" />
                                        </a>
                                    </div>
                                    <div class="col-6 col-sm-4 col-lg-3 col-xl-2">
                                        <a href="/img/product/large/product-2.webp">
                                            <img alt="lightbox" src="/img/product/small/product-2.webp" class="card-img" />
                                        </a>
                                    </div>
                                    <div class="col-6 col-sm-4 col-lg-3 col-xl-2">
                                        <a href="/img/product/large/product-3.webp">
                                            <img alt="lightbox" src="/img/product/small/product-3.webp" class="card-img" />
                                        </a>
                                    </div>
                                    <div class="col-6 col-sm-4 col-lg-3 col-xl-2">
                                        <a href="/img/product/large/product-4.webp">
                                            <img alt="lightbox" src="/img/product/small/product-4.webp" class="card-img" />
                                        </a>
                                    </div>
                                    <div class="col-6 col-sm-4 col-lg-3 col-xl-2">
                                        <a href="/img/product/large/product-5.webp">
                                            <img alt="lightbox" src="/img/product/small/product-5.webp" class="card-img" />
                                        </a>
                                    </div>
                                    <div class="col-6 col-sm-4 col-lg-3 col-xl-2">
                                        <a href="/img/product/large/product-6.webp">
                                            <img alt="lightbox" src="/img/product/small/product-6.webp" class="card-img" />
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                    <!-- Fade In End -->

                    <!-- Captions Start -->
                    <section class="scroll-section" id="captions">
                        <h2 class="small-title">Captions</h2>
                        <div class="card mb-5">
                            <div class="card-body">
                                <div class="row gallery g-2">
                                    <div class="col-6 col-sm-4 col-lg-3 col-xl-2">
                                        <a href="/img/product/large/product-1.webp" data-caption="Pain de Campagne">
                                            <img alt="lightbox" src="/img/product/small/product-1.webp" class="card-img" />
                                        </a>
                                    </div>
                                    <div class="col-6 col-sm-4 col-lg-3 col-xl-2">
                                        <a href="/img/product/large/product-2.webp" data-caption="Bauernbrot">
                                            <img alt="lightbox" src="/img/product/small/product-2.webp" class="card-img" />
                                        </a>
                                    </div>
                                    <div class="col-6 col-sm-4 col-lg-3 col-xl-2">
                                        <a href="/img/product/large/product-3.webp" data-caption="Pullman Loaf">
                                            <img alt="lightbox" src="/img/product/small/product-3.webp" class="card-img" />
                                        </a>
                                    </div>

                                    <div class="col-6 col-sm-4 col-lg-3 col-xl-2">
                                        <a href="/img/product/large/product-4.webp" data-caption="Baguette">
                                            <img alt="lightbox" src="/img/product/small/product-4.webp" class="card-img" />
                                        </a>
                                    </div>
                                    <div class="col-6 col-sm-4 col-lg-3 col-xl-2">
                                        <a href="/img/product/large/product-5.webp" data-caption="Barmbrack">
                                            <img alt="lightbox" src="/img/product/small/product-5.webp" class="card-img" />
                                        </a>
                                    </div>
                                    <div class="col-6 col-sm-4 col-lg-3 col-xl-2">
                                        <a href="/img/product/large/product-6.webp" data-caption="Basler Brot">
                                            <img alt="lightbox" src="/img/product/small/product-6.webp" class="card-img" />
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                    <!-- Captions End -->

                    <!-- Single Image Start -->
                    <section class="scroll-section" id="singleImage">
                        <h2 class="small-title">Single Image</h2>
                        <div class="row">
                            <div class="col-6 col-sm-4 col-lg-3 col-xl-2">
                                <div class="card">
                                    <div class="card-body">
                                        <a href="/img/product/large/product-1.webp" class="lightbox">
                                            <img alt="lightbox" src="/img/product/small/product-1.webp" class="card-img" />
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                    <!-- Single Image End -->
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
                        <a class="nav-link" href="#gallery">
                            <i data-acorn-icon="chevron-right"></i>
                            <span>Gallery</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link" href="#fadeIn">
                            <i data-acorn-icon="chevron-right"></i>
                            <span>Fade In</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link" href="#captions">
                            <i data-acorn-icon="chevron-right"></i>
                            <span>Captions</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link" href="#singleImage">
                            <i data-acorn-icon="chevron-right"></i>
                            <span>Single Image</span>
                        </a>
                    </li>
                </ul>
            </div>
            <!-- Scrollspy End -->
        </div>
    </div>
@endsection
