@php
    $html_tag_data = [];
    $title = 'Kateqoriya siyahısı';
    $breadcrumbs = ["/"=>"ParfumShop", ""=>"Kateqoriya siyahısı"]
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
                        <span>Yeni Kateqoriya</span>
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
                                            <div class="text-small">ADI AZ</div>
                                        </div>
                                        <div class="col-4 col-sm-3 d-flex align-items-center">
                                            <div class="text-small">ADI EN</div>
                                        </div>
                                        <div class="col-4 col-sm-3 d-flex align-items-center">
                                            <div class="text-small">ADI RU</div>
                                        </div>
                                        <div class="col-4 col-sm-1 d-flex align-items-center">
                                            <div class="text-small">AKTIV</div>
                                        </div>
                                        <div class="col-4 col-sm-2 d-flex align-items-center justify-content-end">
                                            <div class="text-small">EDIT</div>
                                        </div>
                                    </div>

                                    <div class="list scroll-out">
                                        <div class="scroll-by-count" data-count="20" data-childSelector=".scroll-child">
                                            @foreach($categories as $category)
                                                <div class="h-auto sh-sm-5 mb-3 mb-sm-0 scroll-child">
                                                    <div class="row g-0 h-100 align-content-center">
                                                        <div class="col-12 col-sm-3 d-flex align-items-center category">{{$category->name_az}}</div>
                                                        <div class="col-12 col-sm-3 d-flex align-items-center category">{{$category->name_en}}</div>
                                                        <div class="col-12 col-sm-3 d-flex align-items-center category">{{$category->name_ru}}</div>
                                                        <div class="col-12 col-sm-1 d-flex align-items-center category">{{ $category->active ? 'Aktiv' : 'Deaktiv' }}</div>
                                                        <div class="col-12 col-sm-2 d-flex align-items-center justify-content-sm-end text-muted sale">
                                                            <a href="{{route('admin.category.edit',$category->id)}}" class="btn btn-outline-secondary btn-sm ms-1" type="button">Edit</a>
                                                        </div>
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

        <div class="modal modal-right fade" id="newAdmin" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Yeni Kateqoriya</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="{{route('admin.category.add')}}">
                            @csrf
                            <label>ADI AZ</label>
                            <input type="text" name="name_az" class="form-control" placeholder="ADI AZ" value="{{old('name_az')}}" required>
                            <label>ADI EN</label>
                            <input type="text" name="name_en" class="form-control" placeholder="ADI EN" value="{{old('name_en')}}" required>
                            <label>ADI RU</label>
                            <input type="text" name="name_ru" class="form-control" placeholder="ADI RU" value="{{old('name_ru')}}" required>
                            <label>Slug (URL)</label>
                            <input type="text" name="slug" class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug') }}" placeholder="Boş saxlasanız ingilis adından yaradılacaq">
                            @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <hr>
                            <button type="submit" class="btn btn-primary">Əlavə et</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
