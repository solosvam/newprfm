@extends('frontend.layout')
@section('content')
<main><div class="container"><div class="checkout-success"><h1>{{ __('reviews_your_order_has_been_received') }}</h1><p>{{ __('reviews_order_number') }} <strong>{{ $order->order_no }}</strong></p><a href="{{ route('profile.orders') }}">{{ __('reviews_view_my_orders') }}</a></div></div></main>
@endsection