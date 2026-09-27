@php
    $html_tag_data = [];
    $title = 'Hissəli ödəniş müraciətləri';
    $breadcrumbs = ["/"=>"ParfumShop", ""=>$title];
@endphp
@extends('backend.layout',['html_tag_data'=>$html_tag_data, 'title'=>$title])
@section('content')
<div class="container">
    <div class="page-title-container">
        <h1 class="mb-0 pb-0 display-4">{{ $title }}</h1>
        @include('backend._layout.breadcrumb',['breadcrumbs'=>$breadcrumbs])
    </div>
    <div class="card">
        <div class="card-body table-responsive">
            <table class="table">
                <thead><tr><th>#</th><th>Tarix</th><th>Müştəri</th><th>Telefon</th><th>Məhsul</th><th>Sifariş</th><th>Qiymət</th><th>Müddət</th><th>Aylıq</th><th>Ümumi</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse($applications as $application)
                        <tr>
                            <td>{{ $application->id }}</td>
                            <td>{{ $application->created_at?->format('d.m.Y H:i') }}</td>
                            <td>{{ $application->customer?->name }} {{ $application->customer?->surname }}</td>
                            <td>{{ $application->customer?->mobile }}</td>
                            <td>@foreach($application->order?->items ?? [] as $item){{ $item->product?->name }} ({{ $item->variant?->size?->name_az }})@endforeach</td>
                            <td>{{ $application->order?->order_no }}</td><td>{{ number_format((float) $application->order?->subtotal, 2) }} ₼</td>
                            <td>{{ $application->period?->month }} ay</td>
                            <td>{{ number_format($application->monthly, 2) }} ₼</td>
                            <td>{{ number_format($application->total, 2) }} ₼</td>
                            <td>{{ $application->status?->name_az }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="text-center">Hələ müraciət yoxdur.</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $applications->links() }}
        </div>
    </div>
</div>
@endsection
