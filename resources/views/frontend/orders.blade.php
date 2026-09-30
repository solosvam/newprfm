@extends('frontend.layouts.app')

@section('page-css')
<link rel="stylesheet" href="{{ asset_v('frontend/css/pages/account.css') }}">
@endsection

@section('content')
    <main>
        <div class="account-layout">
            @include('frontend.partials.cabinet-sidebar', ['pageTitle' => __('orders_history')])

            @if($orders->isEmpty())
                <div class="account-panel">
                    <h1 class="account-panel-title">{{ __('orders_history') }}</h1>
                    <p class="account-card-empty">{{ __('profile_you_have_no_orders_yet') }}</p>
                </div>
            @else
                <div class="order-history">
                    <div class="order-history__header">
                        <h1 class="account-panel-title">{{ __('orders_history') }}</h1>
                        <span class="order-history__count">{{ $orders->total() }}</span>
                    </div>

                    @foreach($orders as $order)
                        @php
                            $customerStatus = $order->status?->forCustomer(); // daxili mərhələlər → "Hazırlanır"
                            $statusKey = $customerStatus?->code ?? 'received';
                            $visibleItems = $order->items->take(3);
                            $hiddenCount = $order->items->count() - $visibleItems->count();
                        @endphp

                        <article class="order-card {{ $order->isAwaitingPayment() ? 'order-card--awaiting' : '' }}">
                            <div class="order-card__main">
                                <div class="order-card__top">
                                    <span class="order-card__no">№ {{ $order->order_no }}</span>
                                    <span class="order-status order-status--{{ $statusKey }}">
                                        {{ $customerStatus?->localized_name ?? __('orders_order_received') }}
                                    </span>
                                    @if($order->isAwaitingPayment())
                                        <span class="order-status order-status--awaiting-payment">{{ __('orders_payment_awaiting') }}</span>
                                    @endif
                                </div>
                                <div class="order-card__meta">
                                    <time datetime="{{ $order->created_at->toIso8601String() }}">
                                        {{ $order->created_at->format('d.m.Y') }}
                                    </time>
                                    <span>{{ $order->created_at->format('H:i') }}</span>
                                    <span>{{ $order->items->sum('quantity') }} {{ __('orders_products') }}</span>
                                    @if($order->paymentMethod)
                                        <span class="order-card__payment">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/>
                                            </svg>
                                            {{ $order->paymentMethod->localized_name }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="order-card__products">
                                @foreach($visibleItems as $item)
                                    @php($image = $item->product?->images?->first())
                                    <div class="order-card__product" title="{{ $item->product?->name }}">
                                        @if($image)
                                            <img src="{{ asset('frontend/uploads/products/' . $image->image) }}"
                                                 alt="{{ $item->product?->name }}" loading="lazy">
                                        @else
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                                <rect x="7" y="8" width="10" height="13" rx="2"/><path d="M10 8V5h4v3"/>
                                            </svg>
                                        @endif
                                        @if($item->quantity > 1)
                                            <span class="order-card__qty">×{{ $item->quantity }}</span>
                                        @endif
                                    </div>
                                @endforeach
                                @if($hiddenCount > 0)
                                    <div class="order-card__product order-card__product--more">+{{ $hiddenCount }}</div>
                                @endif
                            </div>

                            <div class="order-card__side">
                                <span class="order-card__total">{{ number_format($order->total, 2) }} ₼</span>
                                @if($order->isAwaitingPayment())
                                    {{-- Kartın üstündəki link bütün kartı örtür; form ondan yuxarıda qalır --}}
                                    <form method="POST" action="{{ route('payment.birbank.start', $order) }}" class="order-card__pay">
                                        @csrf
                                        <button type="submit" class="btn btn-dark">{{ __('orders_payment_pay') }}</button>
                                    </form>
                                @endif
                                <a class="order-card__details" href="{{ route('order.details', $order) }}"
                                   aria-label="{{ __('orders_details') }} — № {{ $order->order_no }}">
                                    {{ __('orders_details') }}
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path d="M5 12h14M13 6l6 6-6 6"/>
                                    </svg>
                                </a>
                            </div>
                        </article>
                    @endforeach

                    @if($orders->hasPages())
                        {{ $orders->links('frontend.includes.pagination') }}
                    @endif
                </div>
            @endif
        </div>
    </main>
@endsection
