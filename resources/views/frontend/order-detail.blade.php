@extends('frontend.layout')
@section('content')
<main><div class="container"><div class="cabinet">
@include('frontend.partials.cabinet-sidebar',['pageTitle'=>'Sifariş detalları'])
<div class="cabinet-orders" style="display:block">
 <h2>Sifariş {{ $order->order_no }}</h2>
 <p>{{ $order->created_at->format('d.m.Y, H:i') }} · {{ $order->status?->name_az ?? $order->status?->name }}</p>
 <div class="cabinet-orders-list">
 @foreach($order->items as $item)
  <div class="cabinet-order">
   <div class="first">
    @php($image=$item->product?->images?->first())
    @if($image)<img src="{{ asset('frontend/uploads/products/'.$image->image) }}" alt="">@endif
    <span><p>{{ $item->product?->name ?? 'Məhsul' }}</p><span>{{ $item->product?->brand?->name }} · {{ $item->variant?->size?->name_az }}</span></span>
   </div>
   <hr width="1" size="50"><div>{{ $item->quantity }} ədəd</div>
   <hr width="1" size="50"><div>{{ number_format($item->unit_price,2) }} ₼</div>
   <hr width="1" size="50"><div>{{ number_format($item->total,2) }} ₼</div>
  </div>
 @endforeach
 </div>
 <div style="margin-top:35px;line-height:2">
  <p><strong>Ünvan:</strong> {{ $order->address?->label ?? '-' }}</p>
  @if($order->address?->building)<p><strong>Bina:</strong> {{ $order->address->building }} @if($order->address->entrance) · Giriş {{ $order->address->entrance }} @endif @if($order->address->floor) · Mərtəbə {{ $order->address->floor }} @endif @if($order->address->apartment) · Mənzil {{ $order->address->apartment }} @endif</p>@endif
  <p><strong>Ödəniş:</strong> {{ $order->paymentMethod?->name ?? '-' }}</p>
  @if($order->customer_note)<p><strong>Qeyd:</strong> {{ $order->customer_note }}</p>@endif
  <p><strong>Ara cəm:</strong> {{ number_format($order->subtotal,2) }} ₼</p>
  @if($order->discount>0)<p><strong>Endirim:</strong> -{{ number_format($order->discount,2) }} ₼</p>@endif
  @if($order->bonus_used>0)<p><strong>Bonusla ödənilib:</strong> {{ number_format($order->bonus_used,2) }} ₼</p>@endif
  <p><strong>Qazanılan bonus:</strong> +{{ number_format($order->bonus_earned,2) }} ₼</p>
  <p style="font-size:22px"><strong>Toplam:</strong> {{ number_format($order->total,2) }} ₼</p>
 </div>
</div>
</div></div></main>
@endsection