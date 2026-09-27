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
                <thead><tr><th>#</th><th>Tarix</th><th>Müştəri</th><th>Telefon</th><th>Məhsul</th><th>Qiymət</th><th>Müddət</th><th>Aylıq</th><th>Ümumi</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse($applications as $application)
                        <tr>
                            <td>{{ $application->id }}</td>
                            <td>{{ $application->created_at?->format('d.m.Y H:i') }}</td>
                            <td>{{ $application->customer_name }} {{ $application->customer_surname }}</td>
                            <td>{{ $application->customer_mobile }}</td>
                            <td>{{ $application->product_name }} (variant #{{ $application->product_variant_id }})</td>
                            <td>{{ number_format($application->product_price, 2) }} ₼</td>
                            <td>{{ \App\Models\CreditPeriod::find($application->credit_period_id)?->month }} ay</td>
                            <td>{{ number_format($application->monthly, 2) }} ₼</td>
                            <td>{{ number_format($application->total, 2) }} ₼</td>
                            <td>{{ $application->status }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center">Hələ müraciət yoxdur.</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $applications->links() }}
        </div>
    </div>
</div>
@endsection
