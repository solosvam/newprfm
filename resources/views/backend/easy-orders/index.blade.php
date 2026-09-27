@php $title = 'Asan sifariş'; $breadcrumbs = ['/admin' => 'ParfumShop', '#' => $title]; @endphp
@extends('backend.layout', ['title' => $title])
@section('content')
<div class="container">
    <div class="page-title-container"><h1 class="mb-3 display-4">{{ $title }}</h1></div>
    <div class="card"><div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>#</th><th>Sifariş</th><th>Tarix</th><th>Əlaqə nömrəsi</th><th>Məhsullar</th><th>Məbləğ</th><th></th></tr></thead>
                <tbody>
                @forelse($orders as $order)
                    <tr>
                        <td>{{ $order->id }}</td>
                        <td>{{ $order->order_no }}</td>
                        <td>{{ $order->created_at?->format('d.m.Y H:i') }}</td>
                        <td>{{ $order->guest_mobile }}</td>
                        <td>@foreach($order->items as $item){{ $item->product?->name ?? 'Məhsul' }} ×{{ $item->quantity }}@if(!$loop->last), @endif @endforeach</td>
                        <td>{{ number_format((float)$order->total, 2) }} ₼</td>
                        <td><a class="btn btn-primary btn-sm" href="{{ route('admin.easy-orders.show', $order) }}">Əlaqə saxla / Tamamla</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">Gözləyən asan sifariş yoxdur.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $orders->links() }}
    </div></div>
</div>
@endsection
