{{-- İstehsal → xammal alışı: sətirlər və ödəniş --}}
@use('App\Models\Production\MaterialPurchase')
@use('App\Services\Production\OwnProduction')
@php
    $title = 'Xammal alışı #'.$purchase->id;
    $breadcrumbs = ['/admin' => 'ParfumShop', route('admin.production.purchases') => 'Xammal alışları', '#' => $title];
@endphp
@extends('backend.layout', ['title' => $title])

@section('content')
<div class="container">
    <div class="page-title-container">
        <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }}</h1>
        @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
    </div>
    @include('backend.procurement.feedback')

    <div class="card mb-3"><div class="card-body">
        <div class="row g-3">
            <div class="col-sm-3"><div class="text-muted text-small">Tarix</div><div>{{ $purchase->purchased_at->format('d.m.Y') }}</div></div>
            <div class="col-sm-3"><div class="text-muted text-small">Tədarükçü</div>
                <div>@if($purchase->supplier)<a href="{{ route('admin.production.supplier', $purchase->supplier) }}">{{ $purchase->supplier->name }}</a>@else — @endif</div></div>
            <div class="col-sm-3"><div class="text-muted text-small">Məbləğ</div><div class="fw-bold">{{ number_format((float) $purchase->total, 2) }} ₼</div></div>
            <div class="col-sm-3"><div class="text-muted text-small">Ödəniş</div>
                <div>{{ MaterialPurchase::PAYMENTS[$purchase->payment] ?? $purchase->payment }}@if($payment) — {{ $payment->from?->name }}@endif</div></div>
            <div class="col-sm-6"><div class="text-muted text-small">Daxil edən</div><div>{{ trim((string) $purchase->creator?->full_name) ?: '—' }} · {{ $purchase->created_at->format('d.m.Y H:i') }}</div></div>
            @if($purchase->note)<div class="col-sm-6"><div class="text-muted text-small">Qeyd</div><div>{{ $purchase->note }}</div></div>@endif
        </div>
    </div></div>

    <div class="card mb-5"><div class="card-body">
        <table class="table mb-0">
            <thead><tr>
                <th class="text-muted text-small text-uppercase">Xammal</th>
                <th class="text-muted text-small text-uppercase text-end">Miqdar</th>
                <th class="text-muted text-small text-uppercase text-end">Vahid</th>
                <th class="text-muted text-small text-uppercase text-end">Məbləğ</th>
            </tr></thead>
            <tbody>
            @foreach($purchase->lines as $line)
                <tr>
                    <td>@if($line->material)<a href="{{ route('admin.production.material', $line->material) }}">{{ $line->material->name }}</a>@else — @endif</td>
                    <td class="text-end">{{ OwnProduction::qty((float) $line->quantity) }} {{ $line->material?->unitLabel() }}</td>
                    <td class="text-end">{{ number_format((float) $line->unit_cost, 4) }} ₼</td>
                    <td class="text-end">{{ number_format((float) $line->quantity * (float) $line->unit_cost, 2) }} ₼</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div></div>
</div>
@endsection
