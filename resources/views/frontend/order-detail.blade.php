@extends('frontend.layouts.app')

@section('content')
    <main>
        <div class="wrap">
            <div class="account-layout">
                @include('frontend.partials.cabinet-sidebar', ['pageTitle' => __('orders_order_details')])

                <div class="order-detail">
                    <div class="order-detail__header">
                        <h1>{{ __('orders_order') }} {{ $order->order_no }}</h1>
                        <p>{{ $order->created_at->format('d.m.Y, H:i') }} · {{ $order->status?->localized_name }}</p>
                    </div>

                    <div class="order-table-wrap">
                        <table class="order-table">
                            <thead>
                            <tr>
                                <th>{{ __('orders_product') }}</th>
                                <th>{{ __('orders_size') }}</th>
                                <th>{{ __('orders_quantity') }}</th>
                                <th>{{ __('orders_unit_price') }}</th>
                                <th>{{ __('orders_amount') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($order->items as $item)
                                @php($image = $item->product?->images?->first())
                                <tr>
                                    <td>
                                        <div class="order-product">
                                            <div class="order-product__image">
                                                @if($image)
                                                    <img src="{{ asset('frontend/uploads/products/' . $image->image) }}" alt="{{ $item->product?->name }}">
                                                @endif
                                            </div>
                                            <div>
                                                <div class="order-product__name">{{ $item->product?->name ?? __('orders_product') }}</div>
                                                <div class="order-product__meta">{{ $item->product?->brand?->name }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ ($item->variant?->size?->{'name_' . app()->getLocale()} ?: $item->variant?->size?->name_az) ?? '-' }}</td>
                                    <td>{{ $item->quantity }} {{ __('orders_pcs') }}</td>
                                    <td>{{ number_format($item->unit_price, 2) }} ₼</td>
                                    <td><strong>{{ number_format($item->total, 2) }} ₼</strong></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="order-info-grid">
                        <div class="order-info-box">
                            <h3>{{ __('orders_delivery_details') }}</h3>
                            <div class="order-info-box__body">
                                <div><strong>{{ __('orders_address') }}</strong> {{ $order->address?->label ?? '-' }}</div>
                                @if($order->address?->building)<div><strong>{{ __('orders_building') }}</strong> {{ $order->address->building }}</div>@endif
                                @if($order->address?->entrance)<div><strong>{{ __('orders_entrance') }}</strong> {{ $order->address->entrance }}</div>@endif
                                @if($order->address?->floor)<div><strong>{{ __('orders_floor') }}</strong> {{ $order->address->floor }}</div>@endif
                                @if($order->address?->apartment)<div><strong>{{ __('orders_apartment') }}</strong> {{ $order->address->apartment }}</div>@endif
                                @if($order->customer_note)<div><strong>{{ __('orders_note') }}</strong> {{ $order->customer_note }}</div>@endif
                            </div>
                        </div>

                        <div class="order-info-box">
                            <h3>{{ __('orders_payment_details') }}</h3>
                            <div class="order-info-box__body">
                                <div class="order-summary-row"><span>{{ __('checkout_payment_method') }}</span><strong>{{ $order->paymentMethod?->name ?? '-' }}</strong></div>
                                <div class="order-summary-row"><span>{{ __('orders_subtotal') }}</span><span>{{ number_format($order->subtotal, 2) }} ₼</span></div>
                                @if($order->discount > 0)
                                    <div class="order-summary-row"><span>{{ __('orders_discount') }}</span><span>-{{ number_format($order->discount, 2) }} ₼</span></div>
                                @endif
                                @if($order->bonus_used > 0)
                                    <div class="order-summary-row"><span>{{ __('orders_paid_with_bonuses') }}</span><span>-{{ number_format($order->bonus_used, 2) }} ₼</span></div>
                                @endif
                                <div class="order-summary-row order-summary-row--bonus"><span>{{ __('orders_bonus_earned') }}</span><span>+{{ number_format($order->bonus_earned, 2) }} ₼</span></div>
                                <div class="order-summary-row order-summary-row--total"><span>{{ __('orders_total') }}</span><span>{{ number_format($order->total, 2) }} ₼</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
