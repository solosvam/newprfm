{{-- İstehsal → Tədarükçülər (xammal): borc = borca alışlar − ödənişlər --}}
@php
    $title = 'Tədarükçülər';
    $breadcrumbs = ['/admin' => 'ParfumShop', '' => 'İstehsal', '#' => $title];
    $totalDebt = $debts->filter(fn ($d) => $d > 0)->sum();
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
            <div class="col-12 col-sm-6 d-flex align-items-start justify-content-end">
                <button type="button" class="btn btn-outline-primary btn-icon btn-icon-end w-100 w-sm-auto" data-bs-toggle="modal" data-bs-target="#supplierModal">
                    <span>Yeni tədarükçü</span><i data-acorn-icon="plus"></i>
                </button>
            </div>
        </div>
    </div>
    @include('backend.procurement.feedback')

    <div class="card mb-5"><div class="card-body">
        @if($suppliers->isEmpty())
            <div class="text-muted text-center py-5">Hələ tədarükçü yoxdur. Xammalı aldığınız şirkət və ya şəxsləri əlavə edin.</div>
        @else
            <div class="table-responsive"><table class="table mb-0">
                <thead><tr>
                    <th class="text-muted text-small text-uppercase">Tədarükçü</th>
                    <th class="text-muted text-small text-uppercase text-end">Alış</th>
                    <th class="text-muted text-small text-uppercase">Son alış</th>
                    <th class="text-muted text-small text-uppercase text-end">Borcumuz</th>
                </tr></thead>
                <tbody>
                @foreach($suppliers as $s)
                    @php $debt = $debts[$s->id] ?? 0; $b = $bought[$s->id] ?? null; @endphp
                    <tr class="cursor-pointer" onclick="location.href='{{ route('admin.production.supplier', $s) }}'">
                        <td><a href="{{ route('admin.production.supplier', $s) }}">{{ $s->name }}</a>
                            @unless($s->active)<span class="badge bg-outline-secondary ms-1">Deaktiv</span>@endunless
                            @if($s->description)<div class="text-alternate text-small">{{ $s->description }}</div>@endif</td>
                        <td class="text-end">{{ (int) ($b?->c ?? 0) }}</td>
                        <td class="text-alternate">{{ $b?->last ? \Illuminate\Support\Carbon::parse($b->last)->format('d.m.Y') : '—' }}</td>
                        <td class="text-end {{ $debt > 0 ? 'text-danger fw-bold' : 'text-alternate' }}">{{ number_format($debt / 100, 2) }} ₼</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr><td colspan="3" class="text-end text-muted">Cəmi borc</td><td class="text-end fw-bold">{{ number_format($totalDebt / 100, 2) }} ₼</td></tr></tfoot>
            </table></div>
        @endif
    </div></div>
</div>

<div class="modal modal-right fade" id="supplierModal" tabindex="-1" aria-labelledby="supplierTitle" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route('admin.production.suppliers.store') }}" data-once>
        @csrf
        <div class="modal-header"><h5 class="modal-title" id="supplierTitle">Yeni tədarükçü</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button></div>
        <div class="modal-body">
            @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <div class="mb-3"><label class="form-label" for="sName">Ad</label>
                <input type="text" id="sName" name="name" class="form-control" maxlength="150" value="{{ old('name') }}" required></div>
            <div class="mb-3"><label class="form-label" for="sDesc">Əlaqə / qeyd</label>
                <input type="text" id="sDesc" name="description" class="form-control" maxlength="255" value="{{ old('description') }}" placeholder="Telefon, ünvan..."></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Bağla</button><button class="btn btn-primary">Əlavə et</button></div>
    </form></div>
</div>
@endsection

@section('js_page')
@if($errors->any())
<script>new bootstrap.Modal(document.getElementById('supplierModal')).show();</script>
@endif
@endsection
