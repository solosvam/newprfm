{{-- İstehsal → Xammal: qalıq (alış − sərf), orta maya, dəyəri --}}
@use('App\Models\Production\RawMaterial')
@use('App\Services\Production\OwnProduction')
@php
    $title = 'Xammal';
    $breadcrumbs = ['/admin' => 'ParfumShop', '' => 'İstehsal', '#' => $title];
@endphp
@extends('backend.layout', ['title' => $title])

@section('content')
<div class="container">
    <div class="page-title-container">
        <div class="row">
            <div class="col-12 col-sm-6">
                <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }}</h1>
                @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
            </div>
            <div class="col-12 col-sm-6 d-flex align-items-start justify-content-end gap-2">
                <a href="{{ route('admin.production.purchases') }}" class="btn btn-outline-secondary">Alışlar</a>
                <button type="button" class="btn btn-outline-primary btn-icon btn-icon-end w-100 w-sm-auto" data-bs-toggle="modal" data-bs-target="#materialModal">
                    <span>Yeni xammal</span><i data-acorn-icon="plus"></i>
                </button>
            </div>
        </div>
    </div>
    @include('backend.procurement.feedback')

    <div class="card mb-5"><div class="card-body">
        @if($materials->isEmpty())
            <div class="text-muted text-center py-5">Hələ xammal yoxdur. "Yeni xammal" ilə əsans, spirt, flakon, qapaq, qutu, etiket və s. əlavə edin, sonra alışını yazın.</div>
        @else
            <div class="table-responsive"><table class="table mb-0">
                <thead><tr>
                    <th class="text-muted text-small text-uppercase">Xammal</th>
                    <th class="text-muted text-small text-uppercase text-end">Qalıq</th>
                    <th class="text-muted text-small text-uppercase text-end">Orta maya (vahid)</th>
                    <th class="text-muted text-small text-uppercase text-end">Dəyəri</th>
                </tr></thead>
                <tbody>
                @foreach($materials as $m)
                    @php $stock = (float) ($stocks[$m->id] ?? 0); @endphp
                    <tr class="cursor-pointer" onclick="location.href='{{ route('admin.production.material', $m) }}'">
                        <td><a href="{{ route('admin.production.material', $m) }}">{{ $m->name }}</a>
                            @unless($m->active)<span class="badge bg-outline-secondary ms-1">Deaktiv</span>@endunless
                            @if($m->note)<div class="text-alternate text-small">{{ $m->note }}</div>@endif</td>
                        <td class="text-end {{ $stock <= 0 ? 'text-danger' : '' }}">{{ OwnProduction::qty($stock) }} {{ $m->unitLabel() }}</td>
                        <td class="text-end">{{ number_format((float) $m->avg_cost, 4) }} ₼</td>
                        <td class="text-end">{{ number_format(max(0, $stock) * (float) $m->avg_cost, 2) }} ₼</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </div></div>
</div>

<div class="modal modal-right fade" id="materialModal" tabindex="-1" aria-labelledby="materialTitle" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route('admin.production.materials.store') }}" data-once>
        @csrf
        <div class="modal-header"><h5 class="modal-title" id="materialTitle">Yeni xammal</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button></div>
        <div class="modal-body">
            @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <div class="mb-3"><label class="form-label" for="materialName">Ad</label>
                <input type="text" id="materialName" name="name" class="form-control" maxlength="150" value="{{ old('name') }}" placeholder="Məs. Flakon 50 ml" required></div>
            <div class="mb-3"><label class="form-label" for="materialUnit">Vahid</label>
                <select id="materialUnit" name="unit" class="form-select" required>
                    @foreach(RawMaterial::UNITS as $code => $label)<option value="{{ $code }}" @selected(old('unit') === $code)>{{ $label }}</option>@endforeach
                </select>
                <div class="form-text">Alış, resept və sərf bu vahidlə yazılır; ilk hərəkətdən sonra dəyişdirilmir.</div></div>
            <div class="mb-3"><label class="form-label" for="materialNote">Qeyd</label>
                <input type="text" id="materialNote" name="note" class="form-control" maxlength="500" value="{{ old('note') }}"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Bağla</button><button class="btn btn-primary">Əlavə et</button></div>
    </form></div>
</div>
@endsection

@section('js_page')
@if($errors->any())
<script>new bootstrap.Modal(document.getElementById('materialModal')).show();</script>
@endif
@endsection
