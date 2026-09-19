@php
    $html_tag_data = [];
    $title = 'Rol icazələri';
    $breadcrumbs = ["/"=>"ParfumShop", ""=>"Rol icazələri"]
@endphp
@extends('backend.layout',['html_tag_data'=>$html_tag_data, 'title'=>$title])

@section('js_page')
    <script src="{{asset('backend/js/plugins/lists.js')}}"></script>
    <script src="{{asset('backend/js/cs/scrollspy.js')}}"></script>
    <script src="{{asset('backend/js/vendor/list.js')}}"></script>
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

                    <!-- Tour Button End -->
                </div>
                <!-- Top Buttons End -->
            </div>
        </div>
        <div class="row">
            <div class="col">
                <section class="scroll-section" id="userButtons">
                    <h2 class="small-title">Rol icazələri</h2>
                    <div class="row g-2" id="sortAndFilter">
                        <div class="col-12">
                            <div class="row gx-2">
                                <div class="col-12 col-sm mb-1 mb-sm-0">
                                    <div class="search-input-container shadow rounded-md bg-foreground mb-2">
                                        <input class="form-control search" type="text" autocomplete="off" placeholder="Axtarış" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row g-0 h-100 align-content-center mb-2 custom-sort d-none d-sm-flex">
                                        <div class="col-4 col-sm-3 d-flex align-items-center">
                                            <div class="text-small">AKTİV</div>
                                        </div>
                                        <div class="col-4 col-sm-3 d-flex align-items-center">
                                            <div class="text-small">ROL ADI</div>
                                        </div>
                                        <div class="col-4 col-sm-6 d-flex align-items-center">
                                            <div class="text-small">DETALLAR</div>
                                        </div>
                                    </div>

                                    <div class="list scroll-out">
                                        <div class="scroll-by-count" data-count="20" data-childSelector=".scroll-child">
                                            @foreach($permissions as $permission)
                                                <div class="h-auto sh-sm-5 mb-3 mb-sm-0 scroll-child">
                                                    <div class="row g-0 h-100 align-content-center">
                                                        <div class="col-12 col-sm-3 d-flex align-items-center ">
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input role-permission-switch" type="checkbox" data-id="{{$permission->id}}" data-role-id="{{$role->id}}" id="switch_{{$permission->id}}" @if($role->hasPermissionTo($permission->name)) checked @endif/>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-sm-3 d-flex align-items-center text-muted category">{{$permission->name}}</div>
                                                        <div class="col-12 col-sm-6 d-flex align-items-center text-muted ">{{$permission->description}}</div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection
