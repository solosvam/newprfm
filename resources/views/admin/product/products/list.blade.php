@php
    $html_tag_data = [];
    $title = 'Məhsullar';
    $breadcrumbs = ["/admin"=>"ParfumShop", "#"=>"Məhsullar"]
@endphp
@extends('admin.layout',['title'=>$title])

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
                    @include('admin._layout.breadcrumb',['breadcrumbs'=>$breadcrumbs])
                </div>
                <!-- Title End -->

                <!-- Top Buttons Start -->
                <div class="col-12 col-sm-6 d-flex align-items-start justify-content-end">
                    <!-- Tour Button Start -->
                    <a type="button" class="btn btn-outline-primary btn-icon btn-icon-end w-100 w-sm-auto" href="{{route('product.add')}}">
                        <span>Yeni Məhsul</span>
                        <i data-acorn-icon="plus"></i>
                    </a>
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
                                    <th class="text-muted text-small text-uppercase">ŞƏKİL</th>
                                    <th class="text-muted text-small text-uppercase">BREND</th>
                                    <th class="text-muted text-small text-uppercase">AD</th>
                                    <th class="text-muted text-small text-uppercase">TIP</th>
                                    <th class="text-muted text-small text-uppercase">ÖLÇÜ</th>
                                    <th class="text-muted text-small text-uppercase">QİYMƏT</th>
                                    <th class="text-muted text-small text-uppercase">KATEQORİYA</th>
                                    <th class="text-muted text-small text-uppercase">AKTIV</th>
                                    <th class="text-muted text-small text-uppercase">ƏMƏLİYYAT</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($products as $key => $product)
                                <tr>
                                    <td>{{$key+1}}</td>
                                    <td><img src="{{ asset('frontend/uploads/products/' . $product->images[0]->image) }}" class="card-img rounded-xl sh-6 sw-6" alt="thumb" /></td>
                                    <td>{{$product->brand->name}}</td>
                                    <td class="text-alternate">{{$product->name}}</td>
                                    <td class="text-alternate">{{$product->type->name_az}}</td>
                                    <td class="text-alternate">{{$product->sizes->count()}} ölçü</td>
                                    <td class="text-alternate">{{$product->sizes[0]->pivot->price}} AZN</td>
                                    <td class="text-alternate">{{$product->categories->count()}} kateqoriya</td>
                                    <td class="text-alternate">{{ $product->active ? 'Aktiv' : 'Deaktiv' }}</td>
                                    <td class="text-alternate">
                                        <a href="{{route('product.edit',$product->id)}}" class="btn btn-primary btn-sm">Edit</a>
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
    </div>
@endsection
