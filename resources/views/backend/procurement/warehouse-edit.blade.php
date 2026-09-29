@php
    $html_tag_data = [];
    $title = 'Anbarı redaktə et';
    $breadcrumbs = ['/' => 'ParfumShop', route('admin.procurement.warehouses') => 'Anbarlar', '' => $title];
@endphp
@extends('backend.layout', ['html_tag_data' => $html_tag_data, 'title' => $title])

@section('content')
    <div class="container">
        <div class="page-title-container">
            <div class="row">
                <div class="col-12 col-sm-6">
                    <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }}</h1>
                    @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
                </div>
                <div class="col-12 col-sm-6 d-flex align-items-start justify-content-end">
                    <a href="{{ route('admin.procurement.warehouses') }}" class="btn btn-outline-primary w-100 w-sm-auto">Anbarlar</a>
                </div>
            </div>
        </div>
        @include('backend.procurement.feedback')
        <div class="row">
            <div class="col">
                <section class="scroll-section" id="warehouseFields">
                    <h2 class="small-title">Anbar məlumatları</h2>
                    <div class="card h-100-card">
                        <div class="card-body">
                            <form action="{{ route('admin.procurement.warehouses.update', $warehouse) }}" method="POST">
                                @csrf
                                @method('PUT')
                                @include('backend.procurement.warehouse-fields', ['horizontal' => true])
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
