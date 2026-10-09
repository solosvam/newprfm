@php
    $html_tag_data = [];
    $title = 'Ölçü siyahısı';
    $breadcrumbs = ["/"=>"ParfumShop", ""=>"Ölçü siyahısı"]
@endphp
@extends('backend.layout',['html_tag_data'=>$html_tag_data, 'title'=>$title])

@section('css')
    <link rel="stylesheet" href="{{ asset('backend/css/vendor/datatables.min.css') }}"/>
@endsection

@section('js_page')
    <script src="{{ asset('backend/js/vendor/datatables.min.js') }}"></script>
    <script src="{{ asset('backend/js/cs/datatable.extend.js') }}"></script>
    <script src="{{ asset_v('backend/js/plugins/datatable.static.js') }}"></script>
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
                        <span>Yeni Ölçü</span>
                        <i data-acorn-icon="plus"></i>
                    </button>
                    <!-- Tour Button End -->
                </div>
                <!-- Top Buttons End -->
            </div>
        </div>
        <div class="data-table-rows slim">
            @include('backend._layout.datatable-toolbar', ['table' => '#datatableSizes'])

            <div class="data-table-responsive-wrapper">
                <table id="datatableSizes" class="data-table nowrap w-100 data-table-static"
                       data-noun="ölçü" data-noun-from="ölçüdən">
                    <thead>
                    <tr>
                        <th class="text-muted text-small text-uppercase">#</th>
                        <th class="text-muted text-small text-uppercase">Adı AZ</th>
                        <th class="text-muted text-small text-uppercase">Adı EN</th>
                        <th class="text-muted text-small text-uppercase">Adı RU</th>
                        <th class="text-muted text-small text-uppercase no-sort">Əməliyyat</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($sizes as $size)
                        <tr>
                            <td></td>
                            <td>{{ $size->name_az }}</td>
                            <td>{{ $size->name_en }}</td>
                            <td>{{ $size->name_ru }}</td>
                            <td>
                                <a href="{{ route('admin.size.edit', $size->id) }}" class="btn btn-primary btn-sm">Edit</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="modal modal-right fade" id="newAdmin" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Yeni Ölçü</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="{{route('admin.size.add')}}">
                            @csrf
                            <label>Ölçü adı AZ</label>
                            <input type="text" name="name_az" class="form-control" placeholder="Ölçü adı AZ" value="{{old('name_az')}}" required>
                            <label>Ölçü adı EN</label>
                            <input type="text" name="name_en" class="form-control" placeholder="Ölçü adı EN" value="{{old('name_en')}}" required>
                            <label>Ölçü adı RU</label>
                            <input type="text" name="name_ru" class="form-control" placeholder="Ölçü adı RU" value="{{old('name_ru')}}" required>
                            <hr>
                            <button type="submit" class="btn btn-primary">Əlavə et</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
