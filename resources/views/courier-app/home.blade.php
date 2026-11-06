{{-- Kuryer → ana səhifə: hesablaşma, aktiv sifarişlər --}}
@extends('courier-app.layout', ['tab' => 'orders', 'refresh' => true])
@section('title', 'Sifarişlər')

@section('content')
    <div class="stats">
        @include('courier-app._money', ['balance' => $balance, 'self' => true])
        <div class="stat">
            <div class="stat__label">Aktiv</div>
            <div class="stat__value {{ $activeCount ? 'accent' : '' }}">{{ $activeCount }}</div>
        </div>
        <div class="stat">
            <div class="stat__label">Bu gün təhvil</div>
            <div class="stat__value">{{ $deliveredToday }}</div>
        </div>
    </div>

    <h2 class="section">Aktiv sifarişlər</h2>
    @forelse($orders as $order)
        @include('courier-app._order-card', ['order' => $order, 'statuses' => $statuses, 'href' => route('kuryer.order', $order)])
    @empty
        <div class="empty">Hazırda sizə təyin olunmuş sifariş yoxdur.</div>
    @endforelse
@endsection
