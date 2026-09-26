@extends('frontend.layouts.app')

@section('content')
    <main>
        <div class="wrap">
            <div class="account-layout">
                @include('frontend.partials.cabinet-sidebar', ['pageTitle' => __('orders_history')])

                @if($orders->isEmpty())
                    <div class="account-panel">
                        <h1 class="account-panel-title">{{ __('orders_history') }}</h1>
                        <p class="account-card-empty">{{ __('profile_you_have_no_orders_yet') }}</p>
                    </div>
                @else
                    <div class="order-history">
                        @foreach($orders as $order)
                            <div class="order-card">
                                <div class="order-card__head">
                                    <div>
                                        <span class="order-card__label">{{ __('orders_order_date') }}</span>
                                        <span class="order-card__value">{{ $order->created_at->format('d.m.Y') }}</span>
                                    </div>
                                    <div>
                                        <span class="order-card__label">{{ __('orders_order_summary') }}</span>
                                        <span class="order-card__value">{{ $order->items->sum('quantity') }} {{ __('orders_products') }}</span>
                                    </div>
                                    <div>
                                        <span class="order-card__label">{{ __('orders_order_no') }}</span>
                                        <span class="order-card__value">{{ $order->order_no }}</span>
                                    </div>
                                    <div>
                                        <span class="order-card__label">{{ __('orders_total') }}</span>
                                        <span class="order-card__total">{{ number_format($order->total, 2) }} ₼</span>
                                    </div>
                                    <a class="order-card__details" href="{{ route('profile.orders.show', $order) }}">{{ __('orders_details') }}</a>
                                </div>

                                <div class="order-card__body">
                                    <div class="order-card__status">
                                        <div class="order-card__status-title">{{ $order->status?->localized_name ?? __('orders_order_received') }}</div>
                                        <div class="order-card__status-sub">{{ $order->items->sum('quantity') }} {{ __('orders_products_ordered') }}</div>
                                    </div>

                                    <div class="order-card__products">
                                        @foreach($order->items->take(4) as $item)
                                            @php($image = $item->product?->images?->first())
                                            <div class="order-card__product">
                                                @if($image)
                                                    <img src="{{ asset('frontend/uploads/products/' . $image->image) }}" alt="{{ $item->product?->name }}">
                                                @endif
                                            </div>
                                        @endforeach
                                        @if($order->items->count() > 4)
                                            <span class="order-card__more">+{{ $order->items->count() - 4 }} {{ __('orders_products') }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        @if($orders->hasPages())
                            <div class="main-products__pagination">{{ $orders->links() }}</div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </main>
@endsection
