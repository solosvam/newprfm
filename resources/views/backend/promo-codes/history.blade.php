@php $title = 'Promo kod tarixçəsi'; @endphp
@extends('backend.layout', ['title' => $title])
@section('content')
<div class="container">
    <div class="page-title-container d-flex align-items-center justify-content-between mb-4">
        <div><h1 class="mb-0 pb-0 display-4">{{ $promo->code }} — istifadə tarixçəsi</h1><p class="text-muted mt-2">İstifadə sayı: {{ $promo->used_count }} · Sifariş sayı: {{ $orders->total() }}</p></div>
        <a class="btn btn-outline-secondary" href="{{ route('admin.promo-codes.index') }}">Geri</a>
    </div>
    <div class="card"><div class="card-body"><div class="table-responsive">
        <table class="table align-middle"><thead><tr><th>Sifariş</th><th>Müştəri</th><th>Tarix</th><th>Endirim</th><th>Toplam</th><th>Ödəniş</th></tr></thead><tbody>
        @forelse($orders as $order)
            <tr>
                <td><strong>{{ $order->order_no }}</strong></td>
                <td>{{ $order->customer?->name }} {{ $order->customer?->surname }}</td>
                <td>{{ $order->created_at?->format('d.m.Y H:i') }}</td>
                <td>{{ number_format((float)$order->discount,2) }} ₼</td>
                <td>{{ number_format((float)$order->total,2) }} ₼</td>
                <td>{{ $order->paymentMethod?->localized_name }}<br><span class="small text-muted">{{ $order->payment_status }}</span></td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted py-4">Bu promo kodla sifariş yoxdur.</td></tr>
        @endforelse
        </tbody></table>
    </div>{{ $orders->links() }}</div></div>
</div>
@endsection
