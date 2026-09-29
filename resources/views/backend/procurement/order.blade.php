@extends('backend.layout', ['title' => 'Sifarişin təminatı', 'html_tag_data' => []])
@section('content')
<div class="container">
    <div class="d-flex justify-content-between gap-3 mb-4">
        <div><h1 class="display-4">{{ $order->order_no }} — Anbar sorğuları</h1><span>{{ $order->status?->name_az }}</span></div>
        <a href="{{ route('admin.procurement.warehouses') }}" class="btn btn-outline-primary align-self-start">Anbarlar</a>
    </div>
    @include('backend.procurement.feedback')
    @include('backend.procurement.order-content')
</div>
@endsection
