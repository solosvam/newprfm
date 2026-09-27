@extends('frontend.layouts.app')

@section('content')
    <main>
        <div class="order-success">
            <div class="order-success__icon">
                <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
            </div>

            @if ($order->paymentMethod?->code === 'card_online' && $order->payment_status === 'paid')
                <h1 class="order-success__title">{{ match(app()->getLocale()) {
                    'ru' => 'Оплата прошла успешно',
                    'en' => 'Payment successful',
                    default => 'Ödəniş uğurla tamamlandı',
                } }}</h1>
                <p class="order-success__subtitle">{{ match(app()->getLocale()) {
                    'ru' => 'Ваш заказ принят.',
                    'en' => 'Your order has been received.',
                    default => 'Sifarişiniz qəbul edildi.',
                } }}</p>
            @elseif ($order->creditApplication()->exists())
                <h1 class="order-success__title">{{ __('credit_success_title') }}</h1>
                <p class="order-success__subtitle">{{ __('credit_success_contact') }}</p>
            @else
                <h1 class="order-success__title">{{ __('reviews_your_order_has_been_received') }}</h1>
            @endif
            <p class="order-success__subtitle">
                {{ __('reviews_order_number') }} <strong>{{ $order->order_no }}</strong>
            </p>

            @if(!($guestOneClick ?? false))
                <a href="{{ route('orders') }}" class="btn btn-dark order-success__link">{{ __('reviews_view_my_orders') }}</a>
            @else
                <p class="order-success__subtitle">{{ __('product_one_click_contact') }}</p>
                <a href="{{ route('home') }}" class="btn btn-dark order-success__link">{{ __('product_home') }}</a>
            @endif
        </div>
    </main>
@endsection
