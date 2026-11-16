{{-- İstehsal → Xammal alışları: siyahı və "Yeni alış" (sətirlər: xammal, miqdar, məbləğ; ödəniş — balansdan / borca / başlanğıc qalıq) --}}
@use('App\Models\Production\MaterialPurchase')
@use('App\Services\Production\OwnProduction')
@php
    $title = 'Xammal alışları';
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
            <div class="col-12 col-sm-6 d-flex align-items-start justify-content-end">
                <button type="button" class="btn btn-outline-primary btn-icon btn-icon-end w-100 w-sm-auto" data-bs-toggle="modal" data-bs-target="#purchaseModal" @disabled($materials->isEmpty())>
                    <span>Yeni alış</span><i data-acorn-icon="plus"></i>
                </button>
            </div>
        </div>
    </div>
    @include('backend.procurement.feedback')
    @if($materials->isEmpty())
        <div class="alert alert-info">Alış yazmaq üçün əvvəlcə <a href="{{ route('admin.production.materials') }}">Xammal</a> bölməsində xammalı əlavə edin.</div>
    @endif

    <div class="card mb-5"><div class="card-body">
        @if($purchases->isEmpty())
            <div class="text-muted text-center py-5">Hələ xammal alışı yoxdur.</div>
        @else
            <div class="table-responsive"><table class="table mb-0">
                <thead><tr>
                    <th class="text-muted text-small text-uppercase">Tarix</th>
                    <th class="text-muted text-small text-uppercase">Tədarükçü</th>
                    <th class="text-muted text-small text-uppercase">Xammal</th>
                    <th class="text-muted text-small text-uppercase text-end">Məbləğ</th>
                    <th class="text-muted text-small text-uppercase">Ödəniş</th>
                </tr></thead>
                <tbody>
                @foreach($purchases as $p)
                    <tr class="cursor-pointer" onclick="location.href='{{ route('admin.production.purchase', $p) }}'">
                        <td><a href="{{ route('admin.production.purchase', $p) }}">{{ $p->purchased_at->format('d.m.Y') }}</a></td>
                        <td>{{ $p->supplier?->name ?? '—' }}</td>
                        <td class="text-alternate">{{ $p->lines->map(fn ($l) => ($l->material?->name ?? '—').' '.OwnProduction::qty((float) $l->quantity).' '.$l->material?->unitLabel())->implode(', ') }}</td>
                        <td class="text-end">{{ number_format((float) $p->total, 2) }} ₼</td>
                        <td>@if($p->payment === 'debt')<span class="badge bg-outline-danger">Borca</span>@elseif($p->payment === 'paid')<span class="badge bg-outline-success">Ödənilib</span>@else<span class="badge bg-outline-secondary">Başlanğıc qalıq</span>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="mt-3">{{ $purchases->links() }}</div>
        @endif
    </div></div>
</div>

@include('backend.production._purchase-form', ['purchase' => null])
@endsection

@section('js_page')
    @include('backend.production._purchase-form-js')
@endsection
