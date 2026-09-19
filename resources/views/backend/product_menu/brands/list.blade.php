@php
    $html_tag_data = [];
    $title = 'Brendlər';
    $breadcrumbs = ["/"=>"ParfumShop", ""=>"Brendlər"]
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
                    <button type="button" class="btn btn-outline-primary btn-icon btn-icon-end w-100 w-sm-auto" data-bs-toggle="modal" data-bs-target="#newAdmin">
                        <span>Yeni Brend</span>
                        <i data-acorn-icon="plus"></i>
                    </button>
                    <!-- Tour Button End -->
                </div>
                <!-- Top Buttons End -->
            </div>
        </div>
        <div class="row">
            <div class="col">
                <section class="scroll-section" id="userButtons">
                    <div class="card">
                        <div class="card-body mb-n2" id="existingHtmlList">
                            <div class="search-input-container border border-separator rounded-md bg-foreground mb-4">
                                <input class="form-control search" type="text" autocomplete="off" placeholder="Axtar" />
                                <span class="search-magnifier-icon">
                                    <i data-acorn-icon="search"></i>
                                </span>
                            </div>

                            <div class="list">
                                @foreach($brands as $brand)
                                <div class="row g-0 sh-6 mb-2">
                                    <div class="col-auto">
                                        <img src="{{ asset('frontend/uploads/brands/' . $brand->image) }}" class="card-img rounded-xl sh-6 sw-6" alt="thumb" />
                                    </div>
                                    <div class="col">
                                        <div class="card-body d-flex flex-row pt-0 pb-0 ps-3 pe-0 h-100 align-items-center justify-content-between">
                                            <div class="d-flex flex-column">
                                                <div class="name">{{$brand->name}}</div>
                                                <div class="text-small position">{{ $brand->active ? 'Aktiv' : 'Deaktiv' }}</div>
                                            </div>
                                            <div class="d-flex">
                                                <a href="{{route('admin.brand.edit',$brand->id)}}" class="btn btn-outline-primary btn-sm ms-1">Edit</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            {{$brands->links('backend.pagination')}}
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <div class="modal modal-right fade" id="newAdmin" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Yeni Brend</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="{{route('admin.brand.add')}}" enctype="multipart/form-data">
                            @csrf
                            <label>Brend adı</label>
                            <input type="text" name="name" class="form-control" placeholder="Brend adı" value="{{old('name')}}" required>
                            <label>Şəkil</label>
                            <input type="file" name="image" class="form-control" required>
                            <hr>
                            <button type="submit" class="btn btn-primary">Əlavə et</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
