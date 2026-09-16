@php
    $html_tag_data = [];
    $title = 'Sual edit';
    $breadcrumbs = ["/"=>"ParfumShop", ""=>"Sual edit"]
@endphp
@extends('admin.layout',['html_tag_data'=>$html_tag_data, 'title'=>$title])

@section('css')

@endsection

@section('content')
    <div class="container">
        <div class="page-title-container">
            <div class="row">
                <!-- Title Start -->
                <div class="col-12 col-sm-6">
                    <h1 class="mb-0 pb-0 display-4" id="title">{{$title}}</h1>
                    @include('admin._layout.breadcrumb',['breadcrumbs'=>$breadcrumbs])
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
                    <div class="card h-100-card">
                        <div class="card-body">
                            <form action="{{route('faq.update',$faq->id)}}" method="post">
                                @csrf
                                <div class="mb-3 row">
                                    <label class="col-md-1 col-form-label">Sual AZ</label>
                                    <div class="col-md-5">
                                        <input type="text" class="form-control" name="title_az" value="{{$faq->title_az}}" />
                                    </div>
                                    <div class="col-md-6">
                                        <textarea class="form-control" name="content_az">{{$faq->content_az}}</textarea>
                                    </div>
                                </div>
                                <div class="mb-3 row">
                                    <label class="col-md-1 col-form-label">Sual EN</label>
                                    <div class="col-md-5">
                                        <input type="text" class="form-control" name="title_en" value="{{$faq->title_en}}" />
                                    </div>
                                    <div class="col-md-6">
                                        <textarea class="form-control" name="content_en">{{$faq->content_en}}</textarea>
                                    </div>
                                </div>
                                <div class="mb-3 row">
                                    <label class="col-md-1 col-form-label">Sual RU</label>
                                    <div class="col-md-5">
                                        <input type="text" class="form-control" name="title_ru" value="{{$faq->title_ru}}" />
                                    </div>
                                    <div class="col-md-6">
                                        <textarea class="form-control" name="content_ru">{{$faq->content_ru}}</textarea>
                                    </div>
                                </div>


                                <div class="mb-3 row mt-5">
                                    <div class="col-sm-8 col-md-9 col-lg-10 ms-auto">
                                        <button type="submit" class="btn btn-primary">Yenilə</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection
