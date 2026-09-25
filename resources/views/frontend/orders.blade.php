@extends('frontend.layout')
@section('content')
<style>
.order-history{width:100%;display:flex;flex-direction:column;gap:24px}
.order-card{border:1px solid #e2e2e2;border-radius:14px;overflow:hidden;background:#fff}
.order-card__head{display:grid;grid-template-columns:1.1fr 1.1fr 1fr 1fr auto;align-items:center;gap:28px;padding:22px 28px;background:#f6f6f6}
.order-card__label{display:block;font-size:14px;font-weight:600;color:#333;margin-bottom:5px}
.order-card__value{font-size:16px;color:#333}
.order-card__total{font-size:18px;font-weight:600}
.order-card__details{display:inline-flex;align-items:center;justify-content:center;min-width:150px;height:48px;padding:0 24px;border:1px solid #111;border-radius:4px;color:#111;text-decoration:none;font-weight:600;transition:.2s}
.order-card__details:hover{background:#111;color:#fff}
.order-card__body{display:flex;align-items:center;justify-content:space-between;gap:30px;padding:28px}
.order-card__status{min-width:240px}
.order-card__status-title{font-size:17px;font-weight:600;margin-bottom:8px}
.order-card__status-sub{font-size:14px;color:#777}
.order-card__products{display:flex;align-items:center;gap:12px;flex:1}
.order-card__product{width:72px;height:82px;border:1px solid #e8e8e8;border-radius:7px;background:#fff;display:flex;align-items:center;justify-content:center;padding:6px}
.order-card__product img{width:100%;height:100%;object-fit:contain}
.order-card__more{font-size:14px;color:#777}
@media(max-width:900px){.order-card__head{grid-template-columns:1fr 1fr}.order-card__details{width:100%}.order-card__body{align-items:flex-start;flex-direction:column}.order-card__status{min-width:0}}
</style>
<main>
 <div class="container">
  <div class="cabinet">
   @include('frontend.partials.cabinet-sidebar',['pageTitle'=>__('Sifarişlərimin tarixçəsi')])
   @if($orders->isEmpty())
    <div class="cabinet-content-empty"><h2>{{ __('Sifarişlərimin tarixçəsi') }}</h2><p>{{ __('Hələ sifarişiniz yoxdur.') }}</p></div>
   @else
    <div class="order-history">
     @foreach($orders as $order)
      <div class="order-card">
       <div class="order-card__head">
        <div><span class="order-card__label">{{ __('Sifariş tarixi') }}</span><span class="order-card__value">{{ $order->created_at->format('d.m.Y') }}</span></div>
        <div><span class="order-card__label">{{ __('Sifariş xülasəsi') }}</span><span class="order-card__value">{{ $order->items->sum('quantity') }} {{ __('məhsul') }}</span></div>
        <div><span class="order-card__label">{{ __('Sifariş №') }}</span><span class="order-card__value">{{ $order->order_no }}</span></div>
        <div><span class="order-card__label">{{ __('Toplam') }}</span><span class="order-card__total">{{ number_format($order->total,2) }} ₼</span></div>
        <a class="order-card__details" href="{{ route('profile.orders.show',$order) }}">{{ __('Detallar') }}</a>
       </div>
       <div class="order-card__body">
        <div class="order-card__status">
         <div class="order-card__status-title">{{ ($order->status?->{'name_' . app()->getLocale()} ?: $order->status?->name_az) ?? $order->status?->name ?? __('Sifariş qəbul edildi') }}</div>
         <div class="order-card__status-sub">{{ $order->items->sum('quantity') }} {{ __('məhsul sifariş edilib') }}</div>
        </div>
        <div class="order-card__products">
         @foreach($order->items->take(4) as $item)
          @php($image=$item->product?->images?->first())
          <div class="order-card__product">
           @if($image)<img src="{{ asset('frontend/uploads/products/'.$image->image) }}" alt="{{ $item->product?->name }}">@endif
          </div>
         @endforeach
         @if($order->items->count()>4)<span class="order-card__more">+{{ $order->items->count()-4 }} {{ __('məhsul') }}</span>@endif
        </div>
       </div>
      </div>
     @endforeach
     @if($orders->hasPages())<div class="main-products__pagination">{{ $orders->links() }}</div>@endif
    </div>
   @endif
  </div>
 </div>
</main>
@endsection