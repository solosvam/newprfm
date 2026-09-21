@extends('frontend.layout')
@section('content')
<main><div class="container"><div class="cabinet">
@include('frontend.partials.cabinet-sidebar',['pageTitle'=>'Sifarişlərimin tarixçəsi'])
@if($orders->isEmpty())
<div class="cabinet-content-empty"><h2>Sifarişlərimin tarixçəsi</h2><p>Hələ sifarişiniz yoxdur.</p></div>
@else
<div class="cabinet-orders"><div class="cabinet-orders-list">
@foreach($orders as $order)
<a href="{{ route('profile.orders.show',$order) }}" class="cabinet-order" style="text-decoration:none;color:inherit">
 <div class="first"><span><p>{{ $order->order_no }}</p><span>{{ $order->created_at->format('d.m.Y, H:i') }} · {{ $order->items->sum('quantity') }} məhsul</span></span></div>
 <hr width="1" size="50">
 <div>{{ $order->paymentMethod?->name ?? '-' }}</div>
 <hr width="1" size="50">
 <div class="ready-state"><div class="circle"></div>{{ $order->status?->name_az ?? $order->status?->name ?? '-' }}</div>
 <hr width="1" size="50">
 <div class="fourth">+{{ number_format($order->bonus_earned,2) }} ₼ bonus</div>
 <hr width="1" size="50">
 <div>{{ number_format($order->total,2) }} ₼</div>
</a>
@endforeach
</div>@if($orders->hasPages())<div class="main-products__pagination">{{ $orders->links() }}</div>@endif</div>
@endif
</div></div></main>
@endsection