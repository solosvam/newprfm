@php
    $html_tag_data = [];
    $title = 'Tez tez verilən suallar';
    $breadcrumbs = ["/"=>"ParfumShop", ""=>"Tez tez verilən suallar"]
@endphp
@extends('backend.layout',['html_tag_data'=>$html_tag_data, 'title'=>$title])

@section('css')
    <link rel="stylesheet" href="{{asset('backend/css/vendor/datatables.min.css')}}"/>
@endsection

@section('js_page')
    <script src="{{asset('backend/js/vendor/datatables.min.js')}}"></script>
    <script src="{{asset('backend/js/cs/scrollspy.js')}}"></script>
    <script src="{{asset('backend/js/cs/datatable.extend.js')}}"></script>
    <script src="{{asset('backend/js/plugins/datatable.boxedvariations.js')}}"></script>
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
                        <span>Yeni Sual</span>
                        <i data-acorn-icon="plus"></i>
                    </button>
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
                            <!-- Hover Controls Start -->
                            <div class="row">
                                <div class="col-12 col-sm-5 col-lg-3 col-xxl-2 mb-1">
                                    <div class="d-inline-block float-md-start me-1 mb-1 search-input-container w-100 border border-separator bg-foreground search-sm">
                                        <input class="form-control form-control-sm datatable-search" placeholder="Axtar" data-datatable="#datatableHover" />
                                        <span class="search-magnifier-icon">
                                          <i data-acorn-icon="search"></i>
                                        </span>
                                        <span class="search-delete-icon d-none">
                                          <i data-acorn-icon="close"></i>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-7 col-lg-9 col-xxl-10 text-end mb-1">
                                    <div class="d-inline-block">
                                        <div class="d-inline-block datatable-export" data-datatable="#datatableHover">
                                            <button
                                                class="btn btn-icon btn-icon-only btn-outline-muted btn-sm dropdown" data-bs-toggle="dropdown" type="button" data-bs-offset="0,3">
                                                <i data-acorn-icon="download"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-sm dropdown-menu-end">
                                                <button class="dropdown-item export-excel" type="button">Excel</button>
                                                <button class="dropdown-item export-cvs" type="button">Cvs</button>
                                            </div>
                                        </div>
                                        <div class="dropdown-as-select d-inline-block datatable-length" data-datatable="#datatableHover">
                                            <button
                                                class="btn btn-outline-muted btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" data-bs-offset="0,3">
                                                10 Nəticə
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-sm dropdown-menu-end">
                                                <a class="dropdown-item" href="#">5 Nəticə</a>
                                                <a class="dropdown-item active" href="#">10 Nəticə</a>
                                                <a class="dropdown-item" href="#">20 Nəticə</a>
                                                <a class="dropdown-item" href="#">50 Nəticə</a>
                                                <a class="dropdown-item" href="#">100 Nəticə</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Hover Controls End -->

                            <!-- Hover Table Start -->
                            <table class="data-table data-table-pagination data-table-standard responsive nowrap hover" id="datatableHover" data-order='[[ 0, "desc" ]]'>
                                <thead>
                                <tr>
                                    <th class="text-muted text-small text-uppercase">#</th>
                                    <th class="text-muted text-small text-uppercase">SUAL</th>
                                    <th class="text-muted text-small text-uppercase">CAVAB</th>
                                    <th class="text-muted text-small text-uppercase">ƏMƏLİYYAT</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($faqs as $key => $faq)
                                    <tr>
                                        <td>{{$key+1}}</td>
                                        <td>{{$faq->title_az}}</td>
                                        <td>{{$faq->content_az}}</td>
                                        <td class="text-alternate">
                                            <a href="{{route('admin.faq.edit',$faq->id)}}" class="btn btn-primary btn-sm">Edit</a>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <div class="modal modal-right fade" id="newAdmin" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Yeni sual</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="{{route('admin.faq.add')}}">
                            @csrf
                            <label>Sual AZ</label>
                            <input type="text" name="title_az" class="form-control" placeholder="Sual AZ" value="{{old('title_az')}}" required>
                            <label>Sual EN</label>
                            <input type="text" name="title_en" class="form-control" placeholder="Sual EN" value="{{old('title_en')}}" required>
                            <label>Sual RU</label>
                            <input type="text" name="title_ru" class="form-control" placeholder="Sual RU" value="{{old('title_ru')}}" required>
                            <label>Cavab AZ</label>
                            <textarea class="form-control" name="content_az">{{old('content_az')}}</textarea>
                            <label>Cavab EN</label>
                            <textarea class="form-control" name="content_en">{{old('content_en')}}</textarea>
                            <label>Cavab RU</label>
                            <textarea class="form-control" name="content_ru">{{old('content_ru')}}</textarea>
                            <hr>
                            <button type="submit" class="btn btn-primary">Əlavə et</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
