@extends('frontend.layout')
@section('content')
<main><div class="container"><div class="checkout-success"><h1>{{ __('Sifarişiniz qəbul edildi') }}</h1><p>{{ __('Sifariş nömrəsi:') }} <strong>{{ $order->order_no }}</strong></p><a href="{{ route('profile.orders') }}">{{ __('Sifarişlərimə bax') }}</a></div></div></main>
@endsection