{{-- Stok → alış: sətirlər (nominal, barkod aralığı, say, qiymət) və neçəsinin satıldığı --}}
@php
    $title = 'Alış #'.$purchase->id;
    $breadcrumbs = ['/admin' => 'ParfumShop', route('admin.stock.purchases') => 'Alışlar', '#' => $title];
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
            <div class="col-sm-3"><div class="text-muted text-small">Təchizatçı</div><div>{{ $purchase->supplier ?: '—' }}</div></div>
            <div class="col-sm-3"><div class="text-muted text-small">Məbləğ</div><div class="fw-bold">{{ number_format((float) $purchase->total_cost, 2) }} ₼</div></div>
            <div class="col-sm-3"><div class="text-muted text-small">Daxil edən</div><div>{{ trim((string) $purchase->creator?->full_name) ?: '—' }} · {{ $purchase->created_at->format('d.m.Y H:i') }}</div></div>
            @if($purchase->note)<div class="col-12"><div class="text-muted text-small">Qeyd</div><div>{{ $purchase->note }}</div></div>@endif
        </div>
    </div></div>

    <div class="card mb-5"><div class="card-body">
        <table class="table mb-0">
            <thead><tr>
                <th class="text-muted text-small text-uppercase">Mal</th>
                <th class="text-muted text-small text-uppercase">Barkodlar</th>
                <th class="text-muted text-small text-uppercase text-end">Say</th>
                <th class="text-muted text-small text-uppercase text-end">Satılıb</th>
                <th class="text-muted text-small text-uppercase text-end">Bir ədəd</th>
                <th class="text-muted text-small text-uppercase text-end">Cəmi</th>
            </tr></thead>
            <tbody>
            @foreach($purchase->items as $item)
                <tr>
                    <td><a href="{{ route('admin.stock.cards', ['denomination' => (int) $item->denomination]) }}">{{ $item->label() }}</a></td>
                    <td class="text-alternate">{{ $item->barcode_from }} – {{ $item->barcode_to }}</td>
                    <td class="text-end">{{ $item->quantity }}</td>
                    <td class="text-end">{{ (int) ($sold[$item->id] ?? 0) }}</td>
                    <td class="text-end">{{ number_format((float) $item->unit_cost, 2) }} ₼</td>
                    <td class="text-end">{{ number_format($item->quantity * (float) $item->unit_cost, 2) }} ₼</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div></div>
</div>
@endsection
