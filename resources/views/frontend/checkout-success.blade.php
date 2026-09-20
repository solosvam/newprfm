@extends('frontend.layout')
@section('content')
<main><div class="container"><div class="checkout-success"><h1>Sifarişiniz qəbul edildi</h1><p>Sifariş nömrəsi: <strong>{{ $order->order_no }}</strong></p><a href="{{ route('profile.orders') }}">Sifarişlərimə bax</a></div></div></main>
@endsection