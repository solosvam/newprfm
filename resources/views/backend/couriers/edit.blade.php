@php
    $html_tag_data = [];
    $title = 'Kuryer edit';
    $breadcrumbs = ["/"=>"ParfumShop", ""=>"Kuryer edit"]
@endphp
@extends('backend.layout',['html_tag_data'=>$html_tag_data, 'title'=>$title])

@section('css')

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
                    <div class="card h-100-card">
                        <div class="card-body">
                            <form action="{{route('courier.update',$courier->id)}}" method="post">
                                @csrf
                                <div class="mb-3 row">
                                    <label class="col-lg-2 col-md-3 col-sm-4 col-form-label">Ad</label>
                                    <div class="col-sm-8 col-md-9 col-lg-10">
                                        <input type="text" class="form-control" name="name" value="{{$courier->name}}" />
                                    </div>
                                </div>
                                <div class="mb-3 row">
                                    <label class="col-lg-2 col-md-3 col-sm-4 col-form-label">Soyad</label>
                                    <div class="col-sm-8 col-md-9 col-lg-10">
                                        <input type="text" class="form-control" name="surname" value="{{$courier->surname}}" />
                                    </div>
                                </div>
                                <div class="mb-3 row">
                                    <label class="col-lg-2 col-md-3 col-sm-4 col-form-label">Email</label>
                                    <div class="col-sm-8 col-md-9 col-lg-10">
                                        <input type="email" class="form-control" name="email" value="{{$courier->email}}" />
                                    </div>
                                </div>
                                <div class="mb-3 row">
                                    <label class="col-lg-2 col-md-3 col-sm-4 col-form-label">Mobil</label>
                                    <div class="col-sm-8 col-md-9 col-lg-10">
                                        <input type="text" class="form-control" name="mobile" value="{{$courier->mobile}}" />
                                    </div>
                                </div>

                                <div class="mb-3 row">
                                    <label class="col-lg-2 col-md-3 col-sm-4 col-form-label">Şifrə (boş buraxıldıqda dəyişdirilmir)</label>
                                    <div class="col-sm-8 col-md-9 col-lg-10">
                                        <input type="text" class="form-control" name="password"  />
                                    </div>
                                </div>

                                <div class="mb-3 row">
                                    <label class="col-lg-2 col-md-3 col-sm-4 col-form-label">Aktiv</label>
                                    <div class="col-sm-8 col-md-9 col-lg-10">
                                        <select class="form-select" name="active">
                                            <option value="1" {{ $courier->active ? 'selected' : '' }}>Aktiv</option>
                                            <option value="0" {{ !$courier->active ? 'selected' : '' }}>Deaktiv</option>
                                        </select>
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
