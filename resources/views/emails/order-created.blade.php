@extends('emails.layout')
@section('body')
@php
    $copy = [
        'az' => ['title' => 'Sifarişiniz qəbul edildi', 'hello' => 'Salam', 'number' => 'Sifariş nömrəsi', 'product' => 'Məhsul', 'qty' => 'Say', 'price' => 'Məbləğ', 'total' => 'Yekun', 'address' => 'Çatdırılma ünvanı', 'button' => 'Sifarişlərim'],
        'ru' => ['title' => 'Ваш заказ принят', 'hello' => 'Здравствуйте', 'number' => 'Номер заказа', 'product' => 'Товар', 'qty' => 'Кол-во', 'price' => 'Сумма', 'total' => 'Итого', 'address' => 'Адрес доставки', 'button' => 'Мои заказы'],
        'en' => ['title' => 'Your order is confirmed', 'hello' => 'Hello', 'number' => 'Order number', 'product' => 'Product', 'qty' => 'Qty', 'price' => 'Amount', 'total' => 'Total', 'address' => 'Delivery address', 'button' => 'My orders'],
    ][$locale];
@endphp
<h1 style="font-family:Georgia,serif;font-size:26px;font-weight:normal;margin:0 0 20px">{{ $copy['title'] }}</h1>
<p>{{ $copy['number'] }}: <strong>{{ $order->order_no }}</strong></p>
<table role="presentation" width="100%" cellspacing="0" cellpadding="10" style="border-collapse:collapse;font-size:14px">
<tr style="background:#f7f5f2"><th align="left">{{ $copy['product'] }}</th><th align="center">{{ $copy['qty'] }}</th><th align="right">{{ $copy['price'] }}</th></tr>
@foreach($order->items as $item)
<tr style="border-bottom:1px solid #eee">
<td>{{ $item->product?->name ?? '—' }}@if($item->variant?->size) — {{ $item->variant->size->name ?? '' }}@endif</td>
<td align="center">{{ $item->quantity }}</td>
<td align="right">{{ number_format((float)$item->total, 2) }} AZN</td>
</tr>
@endforeach
<tr><td colspan="2" align="right"><strong>{{ $copy['total'] }}</strong></td><td align="right"><strong>{{ number_format((float)$order->total, 2) }} AZN</strong></td></tr>
</table>
@if($order->address)
<p style="line-height:1.6"><strong>{{ $copy['address'] }}:</strong><br>{{ $order->address->city }}, {{ $order->address->address }}</p>
@endif
<p style="margin:30px 0"><a href="{{ route('profile.orders') }}" style="background:#28221e;color:white;text-decoration:none;padding:14px 24px;display:inline-block">{{ $copy['button'] }}</a></p>
@endsection
