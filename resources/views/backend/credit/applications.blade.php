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
                <thead><tr><th>#</th><th>Tarix</th><th>Müştəri</th><th>Telefon</th><th>Sifariş</th><th>Qiymət</th><th>Müddət</th><th>Aylıq</th><th>Ümumi</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse($applications as $application)
                        <tr>
                            <td>{{ $application->id }}</td>
                            <td>{{ $application->created_at?->format('d.m.Y H:i') }}</td>
                            <td>
                                @if($application->customer)
                                    <a href="{{ route('admin.crm.customer', $application->customer) }}">{{ $application->customer->name }} {{ $application->customer->surname }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $application->customer?->mobile }}</td>
                            <td class="text-nowrap">
                                @if($application->order)
                                    @if($application->order->customer_id)
                                        <a href="{{ route('admin.crm.order', [$application->order->customer_id, $application->order]) }}">{{ $application->order->order_no }}</a>
                                    @else
                                        {{ $application->order->order_no }}
                                    @endif
                                    @if($application->order->contract_file)
                                        @can('ferrum')
                                            <a href="{{ route('admin.ferrum.contract', $application->order) }}" target="_blank" rel="noopener" class="ms-1"
                                               data-bs-toggle="tooltip" title="Ferrum müqaviləsi" aria-label="Ferrum müqaviləsi"><i data-acorn-icon="file-text" data-acorn-size="16"></i></a>
                                        @endcan
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ number_format((float) $application->order?->subtotal, 2) }} ₼</td>
                            <td>{{ $application->period?->month }} ay</td>
                            <td>{{ number_format($application->monthly, 2) }} ₼</td>
                            <td class="text-nowrap">
                                {{ number_format($application->total, 2) }} ₼
                                @if($application->total_edited_by)
                                    {{-- operator ümumi məbləği (faizi) kampaniya üçün dəyişib --}}
                                    <small class="d-block text-muted">standart {{ number_format((float) $application->standard_total, 2) }} ₼ · {{ $editors[$application->total_edited_by] ?? 'Əməkdaş #'.$application->total_edited_by }}</small>
                                @endif
                            </td>
                            <td>{{ $application->status?->name_az }}</td>
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
