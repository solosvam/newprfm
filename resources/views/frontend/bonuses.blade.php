@extends('frontend.layout')
@section('content')
<style>
.bonus-history{width:100%}.bonus-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px}.bonus-balance{background:#f4f4f4;border-radius:8px;padding:14px 20px}.bonus-balance strong{font-size:22px}.bonus-table{width:100%;border-collapse:collapse}.bonus-table th{background:#f3f3f3;color:#555;text-align:left;padding:16px 18px;border-bottom:1px solid #ddd}.bonus-table td{padding:18px;border-bottom:1px solid #e6e6e6}.bonus-table a{color:#111;font-weight:600;text-decoration:underline}.bonus-amount{font-weight:700}.bonus-amount.earn{color:#16883c}.bonus-amount.spend{color:#b52b2b}
</style>
<main><div class="container"><div class="cabinet">
@include('frontend.partials.cabinet-sidebar',['pageTitle'=>__('Bonus tarixçəsi')])
<div class="bonus-history">
 <div class="bonus-head"><h2>{{ __('Bonus tarixçəsi') }}</h2><div class="bonus-balance">{{ __('Bonus balansı:') }} <strong>{{ number_format(auth()->user()->bonus_balance ?? 0,2) }} ₼</strong></div></div>
 @if($transactions->isEmpty())<p>{{ __('Hələ bonus əməliyyatınız yoxdur.') }}</p>
 @else
 <table class="bonus-table">
  <thead><tr><th>{{ __('Sifariş №') }}</th><th>{{ __('Tarix') }}</th><th>{{ __('Əməliyyat') }}</th><th>{{ __('Bonus məbləği') }}</th></tr></thead>
  <tbody>
  @foreach($transactions as $transaction)
   <tr>
    <td>@if($transaction->order)<a href="{{ route('profile.orders.show',$transaction->order) }}">{{ $transaction->order->order_no }}</a>@else — @endif</td>
    <td>{{ $transaction->created_at->format('d.m.Y, H:i') }}</td>
    <td>{{ $transaction->type === 'earn' ? __('Qazanılan bonus') : ($transaction->type === 'spend' ? __('İstifadə olunan bonus') : ($transaction->note ?: __('Bonus əməliyyatı'))) }}</td>
    <td class="bonus-amount {{ $transaction->amount >= 0 ? 'earn' : 'spend' }}">{{ $transaction->amount >= 0 ? '+' : '' }}{{ number_format($transaction->amount,2) }} ₼</td>
   </tr>
  @endforeach
  </tbody>
 </table>
 @if($transactions->hasPages())<div class="main-products__pagination">{{ $transactions->links() }}</div>@endif
 @endif
</div>
</div></div></main>
@endsection