@php
    $html_tag_data = [];
    $title = 'Price listlər';
    $breadcrumbs = ['/' => 'ParfumShop', '' => 'Satışlar', '#' => 'Price listlər'];
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
                    <button type="button" class="btn btn-outline-primary btn-icon btn-icon-end w-100 w-sm-auto" data-bs-toggle="modal" data-bs-target="#priceListUpload">
                        <span>Price list yüklə</span>
                        <i data-acorn-icon="upload"></i>
                    </button>
                </div>
            </div>
        </div>

        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

        <section class="scroll-section">
            <div class="card mb-5">
                <div class="card-body">
                    <div class="table-responsive"><table class="table align-middle mb-0">
                        <thead><tr>
                            <th class="text-muted text-small text-uppercase">Anbar</th>
                            <th class="text-muted text-small text-uppercase">Cari siyahı</th>
                            <th class="text-muted text-small text-uppercase">Fayl</th>
                            <th class="text-muted text-small text-uppercase text-end">Sətir</th>
                            <th class="text-muted text-small text-uppercase text-end">Uyğunlaşıb</th>
                            <th></th>
                        </tr></thead>
                        <tbody>
                        @foreach($warehouses as $warehouse)
                            @php $list = $current[$warehouse->id] ?? null; @endphp
                            <tr>
                                <td>{{ $warehouse->name_az }}</td>
                                @if($list)
                                    @php $done = (int) ($matched[$list->id] ?? 0); @endphp
                                    <td class="text-nowrap">{{ $list->created_at->format('d.m.Y H:i') }}
                                        @if($list->created_at->lt(now()->subDays(14)))<span class="badge bg-outline-warning ms-1">köhnəlib</span>@endif
                                    </td>
                                    <td class="text-alternate">{{ $list->file_name }}</td>
                                    <td class="text-end">{{ $list->rows_count }}</td>
                                    <td class="text-end">{{ $done }} <span class="text-muted">· {{ $list->rows_count ? round($done / $list->rows_count * 100) : 0 }}%</span></td>
                                    <td class="text-end"><a class="btn btn-sm btn-primary" href="{{ route('admin.price-lists.show', $list) }}">Aç</a></td>
                                @else
                                    <td colspan="4" class="text-muted">Price list yüklənməyib</td>
                                    <td></td>
                                @endif
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                </div>
            </div>
        </section>
    </div>

    <div class="modal modal-right fade" id="priceListUpload" tabindex="-1" aria-labelledby="priceListUploadTitle" aria-hidden="true">
        <div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route('admin.price-lists.upload') }}" enctype="multipart/form-data">
            @csrf
            <div class="modal-header"><h5 class="modal-title" id="priceListUploadTitle">Price list yüklə</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button></div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="plWarehouse">Anbar</label>
                    <select id="plWarehouse" name="warehouse_id" class="form-select" required>
                        <option value="">Seçin</option>
                        @foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected(old('warehouse_id') == $warehouse->id)>{{ $warehouse->name_az }}</option>@endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="plFile">Fayl</label>
                    <input id="plFile" type="file" name="file" class="form-control" accept=".xls,.xlsx,.csv" required>
                    <div class="form-text">Excel (.xls, .xlsx) və ya CSV, 20 MB-a qədər.</div>
                </div>
                <p class="text-muted text-small mb-0">Növbəti addımda faylın sütunlarını göstərəcəksiniz (ad, qiymət). Yeni siyahı anbarın əvvəlkini əvəz edir; əvvəl təsdiqlənmiş uyğunluqlar yadda qalır.</p>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Bağla</button><button class="btn btn-primary">Davam et</button></div>
        </form></div>
    </div>
@endsection
