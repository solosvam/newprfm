@extends('frontend.layout')
@section('content')
<style>
.order-detail{width:100%}
.order-detail__header{margin-bottom:28px}
.order-detail__header h2{font-size:28px;margin:0 0 5px}
.order-detail__header p{color:#666;margin:0}
.order-table{width:100%;border-collapse:collapse;background:#fff}
.order-table th{padding:15px 18px;background:#f3f3f3;text-align:left;font-size:14px;color:#555;font-weight:600;border-bottom:1px solid #ddd}
.order-table td{padding:18px;border-bottom:1px solid #e5e5e5;vertical-align:middle}
.order-table th:not(:first-child),.order-table td:not(:first-child){text-align:center}
.order-product{display:flex;align-items:center;gap:14px}
.order-product__image{width:65px;height:75px;display:flex;align-items:center;justify-content:center}
.order-product__image img{width:100%;height:100%;object-fit:contain}
.order-product__name{font-weight:600;margin-bottom:4px}
.order-product__meta{font-size:13px;color:#999}
.order-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:30px}
.order-info-box{border:1px solid #e2e2e2;border-radius:8px;overflow:hidden}
.order-info-box h3{font-size:17px;margin:0;padding:15px 20px;background:#f5f5f5;border-bottom:1px solid #e2e2e2}
.order-info-box__body{padding:18px 20px;line-height:1.8}
.order-summary-row{display:flex;justify-content:space-between;gap:20px;padding:7px 0}
.order-summary-row.total{font-size:20px;font-weight:700;border-top:1px solid #ddd;margin-top:8px;padding-top:15px}
.order-summary-row.bonus{font-weight:600}
@media(max-width:800px){.order-info-grid{grid-template-columns:1fr}.order-table{font-size:13px}.order-table th,.order-table td{padding:10px}.order-product__image{width:45px;height:55px}}
</style>
<main><div class="container"><div class="cabinet">
@include('frontend.partials.cabinet-sidebar',['pageTitle'=>__('orders_order_details')])
<div class="order-detail">
 <div class="order-detail__header">
  <h2>{{ __('orders_order') }} {{ $order->order_no }}</h2>
  <p>{{ $order->created_at->format('d.m.Y, H:i') }} · {{ ($order->status?->{'name_' . app()->getLocale()} ?: $order->status?->name_az) ?? $order->status?->name }}</p>
 </div>

 <table class="order-table">
  <thead><tr><th>{{ __('orders_product') }}</th><th>{{ __('orders_size') }}</th><th>{{ __('orders_quantity') }}</th><th>{{ __('orders_unit_price') }}</th><th>{{ __('orders_amount') }}</th></tr></thead>
  <tbody>
  @foreach($order->items as $item)
   @php($image=$item->product?->images?->first())
   <tr>
    <td><div class="order-product">
     <div class="order-product__image">@if($image)<img src="{{ asset('frontend/uploads/products/'.$image->image) }}" alt="{{ $item->product?->name }}">@endif</div>
     <div><div class="order-product__name">{{ $item->product?->name ?? __('orders_product') }}</div><div class="order-product__meta">{{ $item->product?->brand?->name }}</div></div>
    </div></td>
    <td>{{ ($item->variant?->size?->{'name_' . app()->getLocale()} ?: $item->variant?->size?->name_az) ?? '-' }}</td>
    <td>{{ $item->quantity }} {{ __('orders_pcs') }}</td>
    <td>{{ number_format($item->unit_price,2) }} ₼</td>
    <td><strong>{{ number_format($item->total,2) }} ₼</strong></td>
   </tr>
  @endforeach
  </tbody>
 </table>

 <div class="order-info-grid">
  <div class="order-info-box">
   <h3>{{ __('orders_delivery_details') }}</h3>
   <div class="order-info-box__body">
    <div><strong>{{ __('orders_address') }}</strong> {{ $order->address?->label ?? '-' }}</div>
    @if($order->address?->building)<div><strong>{{ __('orders_building') }}</strong> {{ $order->address->building }}</div>@endif
    @if($order->address?->entrance)<div><strong>{{ __('orders_entrance') }}</strong> {{ $order->address->entrance }}</div>@endif
    @if($order->address?->floor)<div><strong>{{ __('orders_floor') }}</strong> {{ $order->address->floor }}</div>@endif
    @if($order->address?->apartment)<div><strong>{{ __('orders_apartment') }}</strong> {{ $order->address->apartment }}</div>@endif
    @if($order->customer_note)<div><strong>{{ __('orders_note') }}</strong> {{ $order->customer_note }}</div>@endif
   </div>
  </div>

  <div class="order-info-box">
   <h3>{{ __('orders_payment_details') }}</h3>
   <div class="order-info-box__body">
    <div class="order-summary-row"><span>{{ __('checkout_payment_method') }}</span><strong>{{ $order->paymentMethod?->name ?? '-' }}</strong></div>
    <div class="order-summary-row"><span>{{ __('orders_subtotal') }}</span><span>{{ number_format($order->subtotal,2) }} ₼</span></div>
    @if($order->discount>0)<div class="order-summary-row"><span>{{ __('orders_discount') }}</span><span>-{{ number_format($order->discount,2) }} ₼</span></div>@endif
    @if($order->bonus_used>0)<div class="order-summary-row"><span>{{ __('orders_paid_with_bonuses') }}</span><span>-{{ number_format($order->bonus_used,2) }} ₼</span></div>@endif
    <div class="order-summary-row bonus"><span>{{ __('orders_bonus_earned') }}</span><span>+{{ number_format($order->bonus_earned,2) }} ₼</span></div>
    <div class="order-summary-row total"><span>{{ __('orders_total') }}</span><span>{{ number_format($order->total,2) }} ₼</span></div>
   </div>
  </div>
 </div>
</div>
</div></div></main>
@endsection