@extends('frontend.layout')
@section('content')
<main>
    <div class="container">
        <div class="cabinet">
            @include('frontend.partials.cabinet-sidebar',['pageTitle'=>'Sifarişlərimin tarixçəsi'])

            @if($orders->isEmpty())
                <div class="cabinet-content-empty">
                    <h2>Sifarişlərimin tarixçəsi</h2>
                    <p>Hələ sifarişiniz yoxdur.</p>
                </div>
            @else
                <div class="cabinet-orders">
                    <div class="cabinet-orders-list">
                        @foreach($orders as $order)
                            <span class="cabinet-order-date">{{ $order->created_at->format('d.m.Y, H:i') }}</span>

                            @foreach($order->items as $item)
                                @php
                                    $product = $item->product;
                                    $image = $product?->images?->first();
                                @endphp
                                <div class="cabinet-order">
                                    <div class="first">
                                        @if($image)
                                            <img src="{{ asset('frontend/uploads/products/'.$image->image) }}" alt="{{ $product?->name }}">
                                        @endif
                                        <span>
                                            <p>{{ $product?->name ?? 'Məhsul' }}</p>
                                            <span>{{ $product?->brand?->name }}</span>
                                        </span>
                                    </div>
                                    <hr width="1" size="50">
                                    <div>{{ $order->paymentMethod?->name ?? '-' }}</div>
                                    <hr width="1" size="50">
                                    <div class="ready-state">
                                        <div class="circle"></div>
                                        {{ $order->status?->name_az ?? $order->status?->name ?? '-' }}
                                    </div>
                                    <hr width="1" size="50">
                                    <div class="fourth">{{ $order->customer_note ?: 'Şərhsiz' }}</div>
                                    <hr width="1" size="50">
                                    <div>{{ number_format($item->total, 2) }} ₼</div>
                                </div>
                            @endforeach
                        @endforeach
                    </div>

                    @if($orders->hasPages())
                        <div class="main-products__pagination">{{ $orders->links() }}</div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</main>
@endsection
