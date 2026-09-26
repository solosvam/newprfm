@extends('frontend.layouts.app')

@section('content')
    <main>
        <div class="wrap">
            <div class="order-success">
                <div class="order-success__icon">
                    <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                </div>

                <h1 class="order-success__title">{{ __('reviews_your_order_has_been_received') }}</h1>
                <p class="order-success__subtitle">
                    {{ __('reviews_order_number') }} <strong>{{ $order->order_no }}</strong>
                </p>

                <a href="{{ route('profile.orders') }}" class="btn btn-dark order-success__link">{{ __('reviews_view_my_orders') }}</a>
            </div>
        </div>
    </main>
@endsection
