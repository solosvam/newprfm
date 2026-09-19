@php
    $html_tag_data = ["scrollspy"=>"true"];
    $title = 'Users';
    $description = 'User cards for various type of content and layout.';
    $breadcrumbs = ["/"=>"Home","/Blocks"=>"Blocks"]
@endphp
@extends('layout',['html_tag_data'=>$html_tag_data, 'title'=>$title, 'description'=>$description])

@section('css')
@endsection

@section('js_vendor')
    <script src="/js/cs/scrollspy.js"></script>
    <script src="/js/vendor/jquery.barrating.min.js"></script>
@endsection

@section('js_page')
    <script src="/js/pages/blocks.users.js"></script>
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

                    <!-- Side Image Start -->
                    <section class="scroll-section" id="sideImage">
                        <h2 class="small-title">Side Image</h2>

                        <div class="row">
                            <div class="col-auto w-100 sw-md-50">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <div class="row g-0 sh-6">
                                            <div class="col-auto">
                                                <img src="/img/profile/profile-6.webp" class="card-img rounded-xl sh-6 sw-6" alt="thumb" />
                                            </div>
                                            <div class="col">
                                                <div class="card-body d-flex flex-row pt-0 pb-0 ps-3 pe-0 h-100 align-items-center justify-content-between">
                                                    <div class="d-flex flex-column">
                                                        <div>Joisse Kaycee</div>
                                                        <div class="text-small text-muted">UX Designer</div>
                                                    </div>
                                                    <div class="d-flex">
                                                        <button class="btn btn-outline-secondary btn-sm ms-1" type="button">Edit</button>
                                                        <button class="btn btn-sm btn-icon btn-icon-only btn-outline-secondary ms-1" type="button">
                                                            <i data-acorn-icon="more-vertical"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-auto w-100 sw-md-50">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <div class="row g-0 align-items-start align-content-start align-content-md-center align-items-md-center sh-5">
                                            <div class="col-auto d-flex align-items-center">
                                                <div class="sw-12">
                                                    <div class="sw-4 mb-1 d-inline-block">
                                                        <img src="/img/profile/profile-1.webp" class="img-fluid rounded-xl border border-2 border-foreground" alt="thumb"
                                                             data-delay='{"show":"500", "hide":"0"}' data-bs-toggle="tooltip" data-bs-placement="top" title="Kathryn Mengel" />
                                                    </div>
                                                    <div class="sw-4 mb-1 d-inline-block ms-n3">
                                                        <img src="/img/profile/profile-3.webp" class="img-fluid rounded-xl border border-2 border-foreground" alt="thumb"
                                                             data-delay='{"show":"500", "hide":"0"}' data-bs-toggle="tooltip" data-bs-placement="top" title="Kirby Peters" />
                                                    </div>
                                                    <div class="sw-4 sh-4 ms-n3 bg-separator-light d-inline-flex justify-content-center align-items-center rounded-xl">
                                                        <div class="fw-bold text-small text-primary cursor-default" data-delay='{"show":"500", "hide":"0"}' data-bs-toggle="tooltip"
                                                             data-bs-placement="top" title="Kenny Carlson, Noor Wilder, Henri Pate">
                                                            3+
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col">
                                                <div>Developer Team</div>
                                                <div class="text-muted text-small">5 Members</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-auto w-100 sw-md-50">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <div class="row g-0 sh-5">
                                            <div class="col-auto">
                                                <img src="/img/profile/profile-1.webp" class="card-img rounded-xl sh-5 sw-5" alt="thumb" />
                                            </div>
                                            <div class="col">
                                                <div class="card-body d-flex flex-row pt-0 pb-0 ps-3 pe-0 h-100 align-items-center justify-content-between">
                                                    <div class="d-flex flex-row">
                                                        <span class="me-1">1.</span>
                                                        <span>Cherish Kerr</span>
                                                    </div>
                                                    <div class="d-flex align-items-center">
                                                        <span class="text-muted me-2 d-inline-block">7.8K</span>
                                                        <i data-acorn-icon="arrow-top" class="text-primary" data-acorn-size="17"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-auto w-100 sw-md-50">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-6">
                                                <div class="d-flex align-items-center">
                                                    <div class="sw-5 d-inline-block position-relative me-3">
                                                        <img src="/img/profile/profile-1.webp" class="img-fluid rounded-xl" alt="thumb" />
                                                    </div>
                                                    <div class="d-inline-block">
                                                        <a href="#">Olli Hawkins</a>
                                                        <div class="text-muted text-small">Development Lead</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-6 text-muted">
                                                <div class="row g-0 justify-content-end">
                                                    <div class="col-auto pe-3">
                                                        <i data-acorn-icon="eye" class="text-primary me-1" data-acorn-size="15"></i>
                                                        <span class="align-middle">4.8K</span>
                                                    </div>
                                                    <div class="col-auto">
                                                        <i data-acorn-icon="message" class="text-primary me-1" data-acorn-size="15"></i>
                                                        <span class="align-middle">12</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-auto w-100 sw-md-50">
                                <div class="card mb-3">
                                    <a href="#" class="row g-0 sh-10">
                                        <div class="col-auto h-100">
                                            <img src="/img/profile/profile-6.webp" alt="alternate text" class="card-img card-img-horizontal sw-11" />
                                        </div>
                                        <div class="col">
                                            <div class="card-body d-flex flex-column pt-0 pb-0 h-100 justify-content-center">
                                                <div class="d-flex flex-column">
                                                    <div>Londyn Michael</div>
                                                    <div class="text-small text-muted text-truncate">Software Engineer</div>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-auto w-100 sw-md-50">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <div class="row g-0">
                                            <div class="col-auto">
                                                <div class="sw-5 me-3">
                                                    <img src="/img/profile/profile-1.webp" class="img-fluid rounded-xl" alt="thumb" />
                                                </div>
                                            </div>
                                            <div class="col">
                                                <a href="#">Olli Hawkins</a>
                                                <div class="text-muted text-small mb-2">Development Lead</div>
                                                <div class="text-medium text-alternate clamp-line" data-line="4">
                                                    Cupcake chocolate cake jelly beans lemon drops danish bear claw carrot cake soufflé. Marshmallow jujubes tiramisu apple pie carrot
                                                    cake danish candy. Croissant croissant chocolate bar. Jelly macaroon apple pie croissant pastry cheesecake. Marshmallow oat cake
                                                    lemon drops chocolate bonbon powder lemon drops chocolate. Danish tootsie roll dessert soufflé.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-auto w-100 sw-md-50">
                                <div class="card mb-5">
                                    <div class="card-body">
                                        <div class="row g-0">
                                            <div class="col-auto">
                                                <div class="sw-6 me-4">
                                                    <a href="#">
                                                        <img src="/img/profile/profile-6.webp" class="img-fluid rounded-xl" alt="thumb" />
                                                    </a>
                                                </div>
                                            </div>
                                            <div class="col d-flex flex-column justify-content-center">
                                                <a href="#">Londyn Michael</a>
                                                <div class="text-muted mb-1">Software Engineer</div>
                                                <div class="mb-3">
                                                    <div class="br-wrapper br-theme-cs-icon d-inline-block">
                                                        <select class="rating" name="rating" autocomplete="off" data-readonly="true" data-initial-rating="5">
                                                            <option value="1">1</option>
                                                            <option value="2">2</option>
                                                            <option value="3">3</option>
                                                            <option value="4">4</option>
                                                            <option value="5">5</option>
                                                        </select>
                                                    </div>
                                                    <div class="text-muted d-inline-block text-small align-text-top">(25)</div>
                                                </div>
                                                <div class="row g-0 align-items-center mb-2">
                                                    <div class="col-auto">
                                                        <div class="sw-3 d-flex justify-content-center align-items-center">
                                                            <i data-acorn-icon="support" class="text-primary"></i>
                                                        </div>
                                                    </div>
                                                    <div class="col ps-3">
                                                        <div class="d-flex align-items-center lh-1-25">Available Online</div>
                                                    </div>
                                                </div>
                                                <div class="row g-0 align-items-center mb-2">
                                                    <div class="col-auto">
                                                        <div class="sw-3 d-flex justify-content-center align-items-center">
                                                            <i data-acorn-icon="pin" class="text-primary"></i>
                                                        </div>
                                                    </div>
                                                    <div class="col ps-3">
                                                        <div class="d-flex align-items-center lh-1-25">Canada</div>
                                                    </div>
                                                </div>
                                                <div class="row g-0 align-items-center mb-2">
                                                    <div class="col-auto">
                                                        <div class="sw-3 d-flex justify-content-center align-items-center">
                                                            <i data-acorn-icon="content" class="text-primary"></i>
                                                        </div>
                                                    </div>
                                                    <div class="col ps-3">
                                                        <div class="d-flex align-items-center lh-1-25">La Trobe University</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </section>
                    <!-- Side Image End -->

                    <!-- Top Image Start -->
                    <section class="scroll-section" id="topImage">
                        <h2 class="small-title">Top Image</h2>

                        <div class="row">
                            <div class="col-auto w-100 sw-md-50">
                                <div class="card mb-3">
                                    <a href="#">
                                        <img src="/img/profile/profile-large-1.webp" class="card-img-top sh-30" alt="card image" />
                                    </a>
                                    <div class="card-body">
                                        <a href="#">Londyn Michael</a>
                                        <div class="text-muted mb-1">Software Engineer</div>
                                        <div class="mb-3">
                                            <div class="br-wrapper br-theme-cs-icon d-inline-block">
                                                <select class="rating" name="rating" autocomplete="off" data-readonly="true" data-initial-rating="5">
                                                    <option value="1">1</option>
                                                    <option value="2">2</option>
                                                    <option value="3">3</option>
                                                    <option value="4">4</option>
                                                    <option value="5">5</option>
                                                </select>
                                            </div>
                                            <div class="text-muted d-inline-block text-small align-text-top">(25)</div>
                                        </div>
                                        <div class="row g-0 align-items-center mb-1">
                                            <div class="col-auto">
                                                <div class="sw-3 sh-4 d-flex justify-content-center align-items-center">
                                                    <i data-acorn-icon="palette" class="text-primary"></i>
                                                </div>
                                            </div>
                                            <div class="col ps-3">
                                                <div class="row g-0">
                                                    <div class="col">
                                                        <div class="sh-4 d-flex align-items-center lh-1-25">Courses</div>
                                                    </div>
                                                    <div class="col-auto">
                                                        <div class="sh-4 d-flex align-items-center text-alternate">25</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-0 align-items-center mb-1">
                                            <div class="col-auto">
                                                <div class="sw-3 sh-4 d-flex justify-content-center align-items-center">
                                                    <i data-acorn-icon="clock" class="text-primary"></i>
                                                </div>
                                            </div>
                                            <div class="col ps-3">
                                                <div class="row g-0">
                                                    <div class="col">
                                                        <div class="sh-4 d-flex align-items-center lh-1-25">Content</div>
                                                    </div>
                                                    <div class="col-auto">
                                                        <div class="sh-4 d-flex align-items-center text-alternate">210h</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-0 align-items-center mb-4">
                                            <div class="col-auto">
                                                <div class="sw-3 sh-4 d-flex justify-content-center align-items-center">
                                                    <i data-acorn-icon="book" class="text-primary"></i>
                                                </div>
                                            </div>
                                            <div class="col ps-3">
                                                <div class="row g-0">
                                                    <div class="col">
                                                        <div class="sh-4 d-flex align-items-center lh-1-25">Trainee</div>
                                                    </div>
                                                    <div class="col-auto">
                                                        <div class="sh-4 d-flex align-items-center text-alternate">12.148</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex flex-row justify-content-between w-100">
                                            <a href="#" class="btn btn-outline-primary w-100 me-1 btn-sm">Courses</a>
                                            <button class="btn btn-icon btn-icon-only btn-outline-primary btn-sm" type="button">
                                                <i data-acorn-icon="more-horizontal"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-auto w-100 sw-md-50">
                                <div class="card mb-3">
                                    <div class="card-body text-center">
                                        <div class="sw-13 position-relative mb-3 mx-auto">
                                            <img src="/img/profile/profile-1.webp" class="img-fluid rounded-xl" alt="thumb" />
                                        </div>
                                        <a href="#" class="body-link">Blaine Cottrell</a>
                                        <div class="text-muted text-medium mb-2">Chemistry</div>
                                        <div class="mb-2">
                                            <div class="br-wrapper br-theme-cs-icon d-inline-block">
                                                <select class="rating" name="rating" autocomplete="off" data-readonly="true" data-initial-rating="5">
                                                    <option value="1">1</option>
                                                    <option value="2">2</option>
                                                    <option value="3">3</option>
                                                    <option value="4">4</option>
                                                    <option value="5">5</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="mb-3 text-muted sh-7">Pie fruitcake jelly beans. Candy tootsie chocolate croissant jujubes icing macaroon croissant.</div>
                                        <div class="row g-0 align-items-center mb-1">
                                            <div class="col-auto">
                                                <div class="sw-3 sh-4 d-flex justify-content-center align-items-center">
                                                    <i data-acorn-icon="palette" class="text-primary"></i>
                                                </div>
                                            </div>
                                            <div class="col ps-3">
                                                <div class="row g-0">
                                                    <div class="col">
                                                        <div class="sh-4 d-flex align-items-center lh-1-25">Courses</div>
                                                    </div>
                                                    <div class="col-auto">
                                                        <div class="sh-4 d-flex align-items-center text-alternate">25</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-0 align-items-center mb-1">
                                            <div class="col-auto">
                                                <div class="sw-3 sh-4 d-flex justify-content-center align-items-center">
                                                    <i data-acorn-icon="clock" class="text-primary"></i>
                                                </div>
                                            </div>
                                            <div class="col ps-3">
                                                <div class="row g-0">
                                                    <div class="col">
                                                        <div class="sh-4 d-flex align-items-center lh-1-25">Content</div>
                                                    </div>
                                                    <div class="col-auto">
                                                        <div class="sh-4 d-flex align-items-center text-alternate">210h</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-0 align-items-center mb-4">
                                            <div class="col-auto">
                                                <div class="sw-3 sh-4 d-flex justify-content-center align-items-center">
                                                    <i data-acorn-icon="book" class="text-primary"></i>
                                                </div>
                                            </div>
                                            <div class="col ps-3">
                                                <div class="row g-0">
                                                    <div class="col">
                                                        <div class="sh-4 d-flex align-items-center lh-1-25">Trainee</div>
                                                    </div>
                                                    <div class="col-auto">
                                                        <div class="sh-4 d-flex align-items-center text-alternate">12.148</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex flex-row justify-content-between w-100">
                                            <a href="#" class="btn btn-outline-primary w-100 me-1 btn-sm">Courses</a>
                                            <button class="btn btn-icon btn-icon-only btn-outline-primary btn-sm" type="button">
                                                <i data-acorn-icon="more-horizontal"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-auto w-100 sw-md-50">
                                <div class="card mb-3">
                                    <div class="card-body mb-n5">
                                        <div class="d-flex align-items-center flex-column mb-5">
                                            <div class="mb-5 d-flex align-items-center flex-column">
                                                <div class="sw-13 position-relative mb-3">
                                                    <img src="/img/profile/profile-2.webp" class="img-fluid rounded-xl" alt="thumb" />
                                                </div>
                                                <div class="h5 mb-0">Blaine Cottrell</div>
                                                <div class="text-muted">Executive UI/UX Designer</div>
                                                <div class="text-muted">
                                                    <i data-acorn-icon="pin" class="me-1"></i>
                                                    <span class="align-middle">Montreal, Canada</span>
                                                </div>
                                            </div>
                                            <div class="d-flex flex-row justify-content-between w-100 w-sm-50 w-xl-100">
                                                <button type="button" class="btn btn-primary w-100 me-2">Hire</button>
                                                <button type="button" class="btn btn-outline-primary w-100 me-2">Follow</button>
                                                <button class="btn btn-icon btn-icon-only btn-outline-primary" type="button">
                                                    <i data-acorn-icon="more-horizontal"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="mb-5">
                                            <div class="row g-0 align-items-center mb-2">
                                                <div class="col-auto">
                                                    <div class="border border-primary sw-5 sh-5 rounded-xl d-flex justify-content-center align-items-center">
                                                        <i data-acorn-icon="screen" class="text-primary"></i>
                                                    </div>
                                                </div>
                                                <div class="col ps-3">
                                                    <div class="row g-0">
                                                        <div class="col">
                                                            <div class="sh-5 d-flex align-items-center lh-1-25">Project Views</div>
                                                        </div>
                                                        <div class="col-auto">
                                                            <div class="sh-5 d-flex align-items-center">23.573</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row g-0 align-items-center mb-2">
                                                <div class="col-auto">
                                                    <div class="border border-primary sw-5 sh-5 rounded-xl d-flex justify-content-center align-items-center">
                                                        <i data-acorn-icon="paint-roller" class="text-primary"></i>
                                                    </div>
                                                </div>
                                                <div class="col ps-3">
                                                    <div class="row g-0">
                                                        <div class="col">
                                                            <div class="sh-5 d-flex align-items-center lh-1-25">Project Saves</div>
                                                        </div>
                                                        <div class="col-auto">
                                                            <div class="sh-5 d-flex align-items-center">1.124</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row g-0 align-items-center mb-2">
                                                <div class="col-auto">
                                                    <div class="border border-primary sw-5 sh-5 rounded-xl d-flex justify-content-center align-items-center">
                                                        <i data-acorn-icon="like" class="text-primary"></i>
                                                    </div>
                                                </div>
                                                <div class="col ps-3">
                                                    <div class="row g-0">
                                                        <div class="col">
                                                            <div class="sh-5 d-flex align-items-center lh-1-25">Likes</div>
                                                        </div>
                                                        <div class="col-auto">
                                                            <div class="sh-5 d-flex align-items-center">12.573</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row g-0 align-items-center mb-2">
                                                <div class="col-auto">
                                                    <div class="border border-primary sw-5 sh-5 rounded-xl d-flex justify-content-center align-items-center">
                                                        <i data-acorn-icon="user" class="text-primary"></i>
                                                    </div>
                                                </div>
                                                <div class="col ps-3">
                                                    <div class="row g-0">
                                                        <div class="col">
                                                            <div class="sh-5 d-flex align-items-center lh-1-25">Followers</div>
                                                        </div>
                                                        <div class="col-auto">
                                                            <div class="sh-5 d-flex align-items-center">1.245</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                    <!-- Top Image End -->


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
                        <a class="nav-link" href="#sideImage">
                            <i data-acorn-icon="chevron-right"></i>
                            <span>Side Image</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link" href="#topImage">
                            <i data-acorn-icon="chevron-right"></i>
                            <span>Top Image</span>
                        </a>
                    </li>
                </ul>
            </div>
            <!-- Scrollspy End -->
        </div>
    </div>
@endsection
